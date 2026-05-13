<?php
// app/Services/OptimisationTrajetService.php

namespace App\Services;

use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Navette;
use App\Models\Wilaya;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OptimisationTrajetService
{
    /**
     * Matrice des distances entre wilayas (en km)
     * À enrichir avec les vraies distances
     */
    protected $distances = [];

    /**
     * Constructeur - Charge les distances
     */
    public function __construct()
    {
        $this->chargerDistances();
    }

    /**
     * Charger la matrice des distances
     */
    protected function chargerDistances()
    {
        // Exemple de distances (à remplacer par vos vraies données)
        $this->distances = [
            // Alger (16) vers...
            '16' => [
                '01' => 500, // Adrar
                '02' => 200, // Chlef
                '03' => 400, // Laghouat
                '04' => 450, // Oum El Bouaghi
                '05' => 430, // Batna
                '06' => 250, // Béjaïa
                '07' => 400, // Biskra
                '08' => 800, // Béchar
                '09' => 50,  // Blida
                '10' => 100, // Bouira
                '11' => 1500, // Tamanrasset
                '12' => 600, // Tébessa
                '13' => 550, // Tlemcen
                '14' => 300, // Tiaret
                '15' => 100, // Tizi Ouzou
                '16' => 0,   // Alger
                // ... à compléter pour toutes les wilayas
            ],
            // Oran (31) vers...
            '31' => [
                '16' => 450, // Alger
                // ... à compléter
            ],
        ];
    }

    /**
     * Obtenir la distance entre deux wilayas
     */
    public function getDistance(string $depart, string $arrivee): float
    {
        if (isset($this->distances[$depart][$arrivee])) {
            return $this->distances[$depart][$arrivee];
        }

        // Distance par défaut si non trouvée
        return 300;
    }

    /**
     * Regrouper les colis par destination avec priorisation
     */
    public function regrouperColisParDestination(string $wilayaDepart, array $options = []): Collection
    {
        $dateLimite = $options['date_limite'] ?? null;
        $wilayasCibles = $options['wilayas'] ?? [];
        $priorite = $options['priorite'] ?? 'date';

        // Récupérer les colis en attente
        $query = Colis::whereHas('demandeLivraison', function ($q) use ($wilayaDepart, $wilayasCibles) {
            $q->where('wilaya_depot', $wilayaDepart)
                ->whereHas('livraison', function ($q) {
                    $q->where('status', 'en_attente');
                });

            if (!empty($wilayasCibles)) {
                $q->whereIn('wilaya', $wilayasCibles);
            }
        })->with(['demandeLivraison' => function ($q) {
            $q->with('livraison');
        }]);

        if ($dateLimite) {
            $query->whereHas('demandeLivraison', function ($q) use ($dateLimite) {
                $q->whereDate('created_at', '<=', $dateLimite);
            });
        }

        $colis = $query->get();

        // Regrouper par wilaya de destination
        $groupes = $colis->groupBy(function ($colis) {
            return $colis->demandeLivraison->wilaya;
        });

        // Trier chaque groupe selon la priorité
        foreach ($groupes as $wilaya => $groupe) {
            switch ($priorite) {
                case 'date':
                    $groupes[$wilaya] = $groupe->sortBy(function ($c) {
                        return $c->demandeLivraison->created_at;
                    });
                    break;

                case 'urgence':
                    $groupes[$wilaya] = $groupe->sortByDesc(function ($c) {
                        // Calculer un score d'urgence
                        $age = $c->created_at->diffInHours(now());
                        $prix = $c->colis_prix ?? 0;
                        return ($age * 0.7) + ($prix * 0.3);
                    });
                    break;

                case 'valeur':
                    $groupes[$wilaya] = $groupe->sortByDesc('colis_prix');
                    break;
            }
        }

        return $groupes;
    }

    /**
     * Calculer le meilleur trajet (algorithme du voyageur de commerce)
     */
    public function calculerMeilleurTrajet(array $destinations): array
    {
        $nbDestinations = count($destinations);

        if ($nbDestinations <= 1) {
            return [
                'itineraire' => $destinations,
                'distance' => 0,
                'itineraire_complet' => $destinations
            ];
        }

        if ($nbDestinations == 2) {
            $distance = $this->getDistance($destinations[0], $destinations[1]);
            return [
                'itineraire' => $destinations,
                'distance' => $distance,
                'itineraire_complet' => $destinations
            ];
        }

        // Algorithme du plus proche voisin pour n > 2
        $itineraire = [$destinations[0]];
        $nonVisite = array_slice($destinations, 1);
        $distanceTotale = 0;

        while (!empty($nonVisite)) {
            $dernier = end($itineraire);
            $plusProche = null;
            $plusPetiteDistance = INF;
            $indexPlusProche = null;

            foreach ($nonVisite as $index => $destination) {
                $dist = $this->getDistance($dernier, $destination);
                if ($dist < $plusPetiteDistance) {
                    $plusPetiteDistance = $dist;
                    $plusProche = $destination;
                    $indexPlusProche = $index;
                }
            }

            $itineraire[] = $plusProche;
            $distanceTotale += $plusPetiteDistance;
            unset($nonVisite[$indexPlusProche]);
        }

        return [
            'itineraire' => $itineraire,
            'distance' => $distanceTotale,
            'itineraire_complet' => $itineraire
        ];
    }

    /**
     * Créer une navette optimisée automatiquement
     */
    public function creerNavetteOptimisee(string $wilayaDepart, array $options = []): ?Navette
    {
        try {
            DB::beginTransaction();

            // 1. Récupérer les colis à regrouper
            $groupes = $this->regrouperColisParDestination($wilayaDepart, [
                'date_limite' => $options['date_limite'] ?? null,
                'priorite' => $options['priorite'] ?? 'date'
            ]);

            if ($groupes->isEmpty()) {
                DB::rollBack();
                return null;
            }

            // 2. Sélectionner les destinations avec assez de colis
            $destinations = [];
            $colisSelectionnes = collect();
            $capaciteMax = $options['capacite'] ?? 100;

            foreach ($groupes as $wilaya => $colis) {
                if ($colis->count() >= ($options['seuil_min'] ?? 5)) {
                    $destinations[] = $wilaya;

                    // Prendre jusqu'à capacité
                    $aPrendre = $colis->take($capaciteMax - $colisSelectionnes->count());
                    $colisSelectionnes = $colisSelectionnes->merge($aPrendre);

                    if ($colisSelectionnes->count() >= $capaciteMax) {
                        break;
                    }
                }
            }

            if ($destinationsEmpty()) {
                DB::rollBack();
                return null;
            }

            // 3. Calculer le meilleur trajet
            $trajet = $this->calculerMeilleurTrajet(array_merge([$wilayaDepart], $destinations));

            // 4. Estimer les coûts
            $distance = $trajet['distance'];
            $dureeEstimee = $this->estimerDuree($distance);
            $carburantEstime = $this->estimerCarburant($distance);
            $peagesEstimes = $this->estimerPeages($trajet['itineraire']);

            // 5. Déterminer les prix
            $valeurTotale = $colisSelectionnes->sum('colis_prix');
            $prixBase = $options['prix_base'] ?? 300;
            $prixParColis = $options['prix_par_colis'] ?? 10;

            // 6. Créer la navette
            $navette = Navette::create([
                'wilaya_depart_id' => $wilayaDepart,
                'wilaya_arrivee_id' => end($destinations),
                'wilaya_transit_id' => count($destinations) > 2 ? $destinations[1] : null,
                'heure_depart' => $options['heure_depart'] ?? now(),
                'heure_arrivee' => now()->addHours($dureeEstimee),
                'capacite_max' => $capaciteMax,
                'prix_base' => $prixBase,
                'prix_par_colis' => $prixParColis,
                'distance_km' => $distance,
                'carburant_estime' => $carburantEstime,
                'peages_estimes' => $peagesEstimes,
                'status' => 'planifiee',
                'date_depart' => $options['date_depart'] ?? now(),
                'date_arrivee_prevue' => now()->addHours($dureeEstimee),
                'created_by' => auth()->id() ?? $options['created_by'],
                'notes' => "Navette créée automatiquement avec " . $colisSelectionnes->count() . " colis"
            ]);

            // 7. Attacher les colis et mettre à jour les livraisons
            $position = 1;
            foreach ($colisSelectionnes as $colis) {
                $navette->colis()->attach($colis->id, [
                    'position_chargement' => $position++,
                    'date_chargement' => now()
                ]);

                // Mettre à jour la livraison associée
                if ($colis->demandeLivraison && $colis->demandeLivraison->livraison) {
                    $colis->demandeLivraison->livraison->update([
                        'navette_id' => $navette->id,
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }
            }

            DB::commit();

            return $navette->load(['wilayaDepart', 'wilayaArrivee', 'colis']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création navette optimisée: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Générer des suggestions de navettes
     */
    public function genererSuggestions(string $wilayaDepart, array $options = []): array
    {
        // 1. Analyser les colis en attente
        $groupes = $this->regrouperColisParDestination($wilayaDepart, [
            'date_limite' => $options['date_limite'] ?? null
        ]);

        $suggestions = [];

        // 2. Suggestions pour chaque destination significative
        foreach ($groupes as $wilaya => $colis) {
            if ($colis->count() >= 10) {
                $suggestions[] = [
                    'type' => 'destination_unique',
                    'wilaya' => $wilaya,
                    'nb_colis' => $colis->count(),
                    'poids_total' => $colis->sum('poids'),
                    'valeur_totale' => $colis->sum('colis_prix'),
                    'date_plus_ancienne' => $colis->min('created_at'),
                    'urgence' => $colis->count() > 20 ? 'haute' : 'moyenne',
                    'action' => 'creer_navette',
                    'confiance' => $colis->count() > 15 ? 90 : 70
                ];
            }
        }

        // 3. Suggestion de tournée multi-destinations
        $destinationsPotentielles = $groupes->keys()->toArray();
        if (count($destinationsPotentielles) >= 2) {
            $trajet = $this->calculerMeilleurTrajet(array_merge([$wilayaDepart], $destinationsPotentielles));

            $totalColis = $groupes->flatten()->count();
            if ($totalColis >= 15) {
                $suggestions[] = [
                    'type' => 'tournee_multi_destinations',
                    'itineraire' => $trajet['itineraire'],
                    'distance' => $trajet['distance'],
                    'duree' => $this->estimerDuree($trajet['distance']),
                    'nb_colis_total' => $totalColis,
                    'destinations' => $destinationsPotentielles,
                    'repartition' => $groupes->map(function ($g) {
                        return $g->count();
                    })->toArray(),
                    'action' => 'optimiser_tournee',
                    'confiance' => 80
                ];
            }
        }

        // 4 Suggestion basée sur l'historique
        $historique = $this->analyserHistorique($wilayaDepart);
        if (!empty($historique)) {
            $suggestions = array_merge($suggestions, $historique);
        }

        return $suggestions;
    }

    /**
     * Analyser l'historique pour suggérer des navettes récurrentes
     */
    protected function analyserHistorique(string $wilayaDepart): array
    {
        $suggestions = [];

        // Récupérer les navettes passées
        $navettesPassees = Navette::where('wilaya_depart_id', $wilayaDepart)
            ->where('status', 'terminee')
            ->where('created_at', '>=', now()->subMonths(3))
            ->get();

        if ($navettesPassees->isEmpty()) {
            return [];
        }

        // Analyser les tendances
        $destinationsFrequentes = $navettesPassees->groupBy('wilaya_arrivee_id')
            ->map(function ($g) {
                return [
                    'count' => $g->count(),
                    'avg_colis' => $g->avg(function ($n) {
                        return $n->nb_colis;
                    })
                ];
            })
            ->sortByDesc('count')
            ->take(3);

        foreach ($destinationsFrequentes as $wilaya => $stats) {
            if ($stats['count'] >= 5) {
                $suggestions[] = [
                    'type' => 'recurrente',
                    'wilaya' => $wilaya,
                    'frequence' => $stats['count'] . ' fois en 3 mois',
                    'nb_colis_moyen' => round($stats['avg_colis']),
                    'action' => 'planifier_recurrente',
                    'confiance' => 85
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Estimer la durée du trajet
     */
    public function estimerDuree(float $distanceKm): float
    {
        // Vitesse moyenne : 70 km/h
        return $distanceKm / 70;
    }

    /**
     * Estimer le carburant nécessaire
     */
    public function estimerCarburant(float $distanceKm): float
    {
        // Consommation : 10L/100km, prix: 1.8€/L
        return ($distanceKm / 100) * 10 * 1.8;
    }

    /**
     * Estimer les péages
     */
    public function estimerPeages(array $itineraire): float
    {
        // Estimation simplifiée : 0.05€ par km
        $distance = $this->calculerDistanceTrajet($itineraire);
        return $distance * 0.05;
    }

    /**
     * Calculer la distance totale d'un trajet
     */
    public function calculerDistanceTrajet(array $itineraire): float
    {
        $distance = 0;
        for ($i = 0; $i < count($itineraire) - 1; $i++) {
            $distance += $this->getDistance($itineraire[$i], $itineraire[$i + 1]);
        }
        return $distance;
    }
}
