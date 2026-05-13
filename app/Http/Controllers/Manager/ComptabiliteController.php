<?php
// app/Http/Controllers/Manager/ComptabiliteController.php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Navette;
use App\Models\DemandeLivraison;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BilanGestionnaireExport;
use App\Models\GestionnaireGain;
use App\Services\CommissionService;

class ComptabiliteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        // Note: Le middleware 'gestionnaire' doit être défini dans Kernel.php
    }

    /**
     * Récupérer le bilan financier pour le gestionnaire connecté
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Vérifier si l'utilisateur est connecté
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Récupérer le gestionnaire associé à l'utilisateur
            // Relation à définir dans le modèle User
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                Log::warning('Profil gestionnaire introuvable', ['user_id' => $user->id]);

                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $wilayaId = $gestionnaire->wilaya_id;

            if (!$wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wilaya non définie pour ce gestionnaire'
                ], 400);
            }

            $periode = $request->get('periode', 'mois');

            // Récupérer les dates selon la période
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $stats = [
                'gestionnaire' => [
                    'id' => $gestionnaire->id,
                    'user_id' => $user->id,
                    'nom' => $user->nom ?? '',
                    'prenom' => $user->prenom ?? '',
                    'wilaya_id' => $wilayaId,
                    'wilaya_nom' => $this->getWilayaName($wilayaId)
                ],
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilayaId, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilayaId, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilayaId, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilayaId, $dateDebut, $dateFin),
                'evolution' => $this->getEvolutionMensuelle($wilayaId, $dateDebut, $dateFin),
                'statuts_livraisons' => $this->getRepartitionStatuts($wilayaId, $dateDebut, $dateFin),
                'performances' => $this->getPerformances($wilayaId, $dateDebut, $dateFin)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur bilan gestionnaire: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bilan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporter le bilan (Excel/PDF)
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $wilayaId = $gestionnaire->wilaya_id;

            if (!$wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wilaya non définie pour ce gestionnaire'
                ], 400);
            }

            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'mois');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $data = [
                'gestionnaire' => [
                    'nom' => $user->nom ?? '',
                    'prenom' => $user->prenom ?? '',
                    'wilaya_id' => $wilayaId,
                    'wilaya_nom' => $this->getWilayaName($wilayaId)
                ],
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilayaId, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilayaId, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilayaId, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilayaId, $dateDebut, $dateFin),
                'statuts_livraisons' => $this->getRepartitionStatuts($wilayaId, $dateDebut, $dateFin),
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            $filename = 'bilan-' . $data['gestionnaire']['wilaya_nom'] . '-' . Carbon::now()->format('Ymd-His');

            if ($format === 'pdf') {
                // Vérifier que la vue existe
                if (!view()->exists('pdf.bilan-gestionnaire')) {
                    // Créer une vue simple si elle n'existe pas
                    $html = $this->generateSimpleHtmlReport($data);
                    $pdf = Pdf::loadHTML($html);
                } else {
                    $pdf = Pdf::loadView('pdf.bilan-gestionnaire', ['data' => $data]);
                }

                $pdf->setPaper('A4', 'portrait');
                return $pdf->download($filename . '.pdf');
            } else {
                // Pour Excel, on va créer un export simple
                return Excel::download(
                    new BilanGestionnaireExport($data),
                    $filename . '.xlsx'
                );
            }
        } catch (\Exception $e) {
            Log::error('Erreur export bilan gestionnaire: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer un rapport HTML simple si la vue n'existe pas
     */
    private function generateSimpleHtmlReport($data)
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Bilan ' . $data['gestionnaire']['wilaya_nom'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #333; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
                th { background-color: #f4f4f4; }
            </style>
        </head>
        <body>
            <h1>Bilan financier - ' . $data['gestionnaire']['wilaya_nom'] . '</h1>
            <p>Période: ' . $data['periode']['libelle'] . '</p>
            <p>Date de génération: ' . $data['date_generation'] . '</p>

            <h2>Chiffre d\'affaires</h2>
            <table>
                <tr>
                    <th>Indicateur</th>
                    <th>Valeur</th>
                </tr>
                <tr>
                    <td>Chiffre d\'affaires total</td>
                    <td>' . number_format($data['finances']['chiffre_affaires_total'], 0, ',', ' ') . ' DZD</td>
                </tr>
                <tr>
                    <td>Valeur des colis</td>
                    <td>' . number_format($data['finances']['valeur_colis'], 0, ',', ' ') . ' DZD</td>
                </tr>
                <tr>
                    <td>Revenus livraisons</td>
                    <td>' . number_format($data['finances']['revenus_livraisons'], 0, ',', ' ') . ' DZD</td>
                </tr>
                <tr>
                    <td>Revenus navettes</td>
                    <td>' . number_format($data['finances']['revenus_navettes'], 0, ',', ' ') . ' DZD</td>
                </tr>
            </table>

            <h2>Statistiques</h2>
            <table>
                <tr>
                    <th>Total colis</th>
                    <td>' . $data['colis']['total'] . '</td>
                </tr>
                <tr>
                    <th>Total livraisons</th>
                    <td>' . $data['livraisons']['total'] . '</td>
                </tr>
                <tr>
                    <th>Livraisons terminées</th>
                    <td>' . $data['livraisons']['terminees'] . '</td>
                </tr>
                <tr>
                    <th>Livraisons en cours</th>
                    <td>' . $data['livraisons']['en_cours'] . '</td>
                </tr>
            </table>
        </body>
        </html>';

        return $html;
    }

    // ==================== MÉTHODES STATISTIQUES ====================

    /**
     * Statistiques des colis pour la wilaya du gestionnaire
     */
    private function getStatsColis($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            $query = Colis::query()
                ->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                    $q->where('wilaya_depot', $wilayaId);
                });

            if ($dateDebut && $dateFin) {
                $query->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $colis = $query->get();

            return [
                'total' => $colis->count(),
                'valeur_totale' => $colis->sum('colis_prix') ?? 0,
                'valeur_moyenne' => $colis->count() > 0 ? round($colis->avg('colis_prix') ?? 0, 2) : 0,
                'poids_total' => $colis->sum('poids') ?? 0,
                'poids_moyen' => $colis->count() > 0 ? round($colis->avg('poids') ?? 0, 2) : 0,
                'avec_prix' => $colis->whereNotNull('colis_prix')->count(),
                'sans_prix' => $colis->whereNull('colis_prix')->count()
            ];
        } catch (\Exception $e) {
            Log::error('Erreur getStatsColis: ' . $e->getMessage());
            return [
                'total' => 0,
                'valeur_totale' => 0,
                'valeur_moyenne' => 0,
                'poids_total' => 0,
                'poids_moyen' => 0,
                'avec_prix' => 0,
                'sans_prix' => 0
            ];
        }
    }

    /**
     * Statistiques des livraisons pour la wilaya du gestionnaire
     */
    private function getStatsLivraisons($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            $query = Livraison::with('demandeLivraison')
                ->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                    $q->where(function ($sub) use ($wilayaId) {
                        $sub->where('wilaya', $wilayaId)
                            ->orWhere('wilaya_depot', $wilayaId);
                    });
                });

            if ($dateDebut && $dateFin) {
                $query->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $livraisons = $query->get();

            // Calculer le prix total
            $prixTotal = 0;
            foreach ($livraisons as $livraison) {
                if ($livraison->demandeLivraison) {
                    $prixTotal += $livraison->demandeLivraison->prix ?? 0;
                }
            }

            // Calculer le délai moyen de livraison
            $dureeTotale = 0;
            $livraisonsTerminees = $livraisons->where('status', 'livre');
            foreach ($livraisonsTerminees as $livraison) {
                if ($livraison->date_livraison_effective && $livraison->created_at) {
                    try {
                        $duree = Carbon::parse($livraison->created_at)->diffInHours(Carbon::parse($livraison->date_livraison_effective));
                        $dureeTotale += $duree;
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de parsing
                    }
                }
            }
            $dureeMoyenne = $livraisonsTerminees->count() > 0 ? round($dureeTotale / $livraisonsTerminees->count(), 2) : 0;

            $tauxSucces = $livraisons->count() > 0
                ? round(($livraisons->where('status', 'livre')->count() / $livraisons->count()) * 100, 2)
                : 0;

            return [
                'total' => $livraisons->count(),
                'terminees' => $livraisons->where('status', 'livre')->count(),
                'en_cours' => $livraisons->whereNotIn('status', ['livre', 'annule'])->count(),
                'en_attente' => $livraisons->where('status', 'en_attente')->count(),
                'annulees' => $livraisons->where('status', 'annule')->count(),
                'prix_total' => $prixTotal,
                'prix_moyen' => $livraisons->count() > 0 ? round($prixTotal / $livraisons->count(), 2) : 0,
                'duree_moyenne_livraison' => $dureeMoyenne,
                'taux_succes' => $tauxSucces
            ];
        } catch (\Exception $e) {
            Log::error('Erreur getStatsLivraisons: ' . $e->getMessage());
            return [
                'total' => 0,
                'terminees' => 0,
                'en_cours' => 0,
                'en_attente' => 0,
                'annulees' => 0,
                'prix_total' => 0,
                'prix_moyen' => 0,
                'duree_moyenne_livraison' => 0,
                'taux_succes' => 0
            ];
        }
    }

    /**
     * Répartition des livraisons par statut
     */
    private function getRepartitionStatuts($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            $query = Livraison::whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                $q->where(function ($sub) use ($wilayaId) {
                    $sub->where('wilaya', $wilayaId)
                        ->orWhere('wilaya_depot', $wilayaId);
                });
            });

            if ($dateDebut && $dateFin) {
                $query->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $livraisons = $query->get();

            $statuts = [
                'en_attente' => 0,
                'prise_en_charge_ramassage' => 0,
                'ramasse' => 0,
                'en_transit' => 0,
                'prise_en_charge_livraison' => 0,
                'livre' => 0,
                'annule' => 0
            ];

            foreach ($livraisons as $livraison) {
                if (isset($statuts[$livraison->status])) {
                    $statuts[$livraison->status]++;
                }
            }

            return $statuts;
        } catch (\Exception $e) {
            Log::error('Erreur getRepartitionStatuts: ' . $e->getMessage());
            return [
                'en_attente' => 0,
                'prise_en_charge_ramassage' => 0,
                'ramasse' => 0,
                'en_transit' => 0,
                'prise_en_charge_livraison' => 0,
                'livre' => 0,
                'annule' => 0
            ];
        }
    }



    private function getStatsNavettes($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        $query = Navette::query()
            ->where(function ($q) use ($wilayaId) {
                $q->where('wilaya_depart_id', $wilayaId)
                    ->orWhere('wilaya_arrivee_id', $wilayaId)
                    ->orWhereJsonContains('wilayas_transit', $wilayaId); // Correction ici
            });

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        $navettes = $query->get();

        $revenusNavettes = 0;
        $colisTransportes = 0;

        foreach ($navettes as $navette) {
            $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
            $colisTransportes += $navette->nb_colis;
        }

        return [
            'total' => $navettes->count(),
            'planifiees' => $navettes->where('status', 'planifiee')->count(),
            'en_cours' => $navettes->where('status', 'en_cours')->count(),
            'terminees' => $navettes->where('status', 'terminee')->count(),
            'annulees' => $navettes->where('status', 'annulee')->count(),
            'revenus' => $revenusNavettes,
            'colis_transportes' => $colisTransportes,
            'distance_totale' => $navettes->sum('distance_km') ?? 0,
            'distance_moyenne' => $navettes->count() > 0 ? round($navettes->avg('distance_km') ?? 0, 2) : 0,
            'taux_remplissage_moyen' => $navettes->count() > 0 ? round($navettes->avg('taux_remplissage') ?? 0, 2) : 0
        ];
    }

    /**
     * Bilan financier pour la wilaya du gestionnaire
     */
    private function getBilanFinancier($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            // Valeur des colis
            $colisQuery = Colis::query()
                ->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                    $q->where('wilaya_depot', $wilayaId);
                });

            if ($dateDebut && $dateFin) {
                $colisQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
            }
            $valeurColis = $colisQuery->sum('colis_prix') ?? 0;

            // Revenus livraisons
            $livraisonsQuery = Livraison::where('status', 'livre')
                ->with('demandeLivraison')
                ->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                    $q->where(function ($sub) use ($wilayaId) {
                        $sub->where('wilaya', $wilayaId)
                            ->orWhere('wilaya_depot', $wilayaId);
                    });
                });

            if ($dateDebut && $dateFin) {
                $livraisonsQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $livraisons = $livraisonsQuery->get();
            $revenusLivraisons = 0;
            foreach ($livraisons as $livraison) {
                if ($livraison->demandeLivraison) {
                    $revenusLivraisons += $livraison->demandeLivraison->prix ?? 0;
                }
            }

            // Revenus navettes
            $navettesQuery = Navette::where('status', 'terminee')
                ->where(function ($q) use ($wilayaId) {
                    $q->where('wilaya_depart_id', $wilayaId)
                        ->orWhere('wilaya_arrivee_id', $wilayaId);
                });

            if ($dateDebut && $dateFin) {
                $navettesQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $revenusNavettes = 0;
            foreach ($navettesQuery->get() as $navette) {
                $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
            }

            $total = $valeurColis + $revenusLivraisons + $revenusNavettes;

            return [
                'valeur_colis' => $valeurColis,
                'revenus_livraisons' => $revenusLivraisons,
                'revenus_navettes' => $revenusNavettes,
                'chiffre_affaires_total' => $total,
                'repartition' => [
                    'colis' => [
                        'montant' => $valeurColis,
                        'pourcentage' => $total > 0 ? round(($valeurColis / $total) * 100, 2) : 0
                    ],
                    'livraisons' => [
                        'montant' => $revenusLivraisons,
                        'pourcentage' => $total > 0 ? round(($revenusLivraisons / $total) * 100, 2) : 0
                    ],
                    'navettes' => [
                        'montant' => $revenusNavettes,
                        'pourcentage' => $total > 0 ? round(($revenusNavettes / $total) * 100, 2) : 0
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Erreur getBilanFinancier: ' . $e->getMessage());
            return [
                'valeur_colis' => 0,
                'revenus_livraisons' => 0,
                'revenus_navettes' => 0,
                'chiffre_affaires_total' => 0,
                'repartition' => [
                    'colis' => ['montant' => 0, 'pourcentage' => 0],
                    'livraisons' => ['montant' => 0, 'pourcentage' => 0],
                    'navettes' => ['montant' => 0, 'pourcentage' => 0]
                ]
            ];
        }
    }

    /**
     * Évolution mensuelle pour graphique
     */
    private function getEvolutionMensuelle($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            if (!$dateDebut || !$dateFin) {
                $dateFin = Carbon::now();
                $dateDebut = Carbon::now()->subMonths(11)->startOfMonth();
            }

            $mois = [];
            $current = $dateDebut->copy();

            while ($current <= $dateFin) {
                $moisKey = $current->format('Y-m');
                $debutMois = $current->copy()->startOfMonth();
                $finMois = $current->copy()->endOfMonth();

                $bilanMois = $this->getBilanFinancier($wilayaId, $debutMois, $finMois);
                $statsLivraisons = $this->getStatsLivraisons($wilayaId, $debutMois, $finMois);
                $statsColis = $this->getStatsColis($wilayaId, $debutMois, $finMois);

                $mois[] = [
                    'mois' => $current->locale('fr')->isoFormat('MMM YYYY'),
                    'date' => $moisKey,
                    'chiffre_affaires' => $bilanMois['chiffre_affaires_total'],
                    'livraisons' => $statsLivraisons['total'],
                    'colis' => $statsColis['total']
                ];

                $current->addMonth();
            }

            return $mois;
        } catch (\Exception $e) {
            Log::error('Erreur getEvolutionMensuelle: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Indicateurs de performance
     */
    private function getPerformances($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        try {
            $livraisons = $this->getStatsLivraisons($wilayaId, $dateDebut, $dateFin);

            $livraisonsPrec = $this->getStatsLivraisons(
                $wilayaId,
                $dateDebut ? Carbon::parse($dateDebut)->copy()->subDays(30) : null,
                $dateFin ? Carbon::parse($dateFin)->copy()->subDays(30) : null
            );

            $evolutionActivite = $livraisonsPrec['total'] > 0
                ? round((($livraisons['total'] - $livraisonsPrec['total']) / $livraisonsPrec['total']) * 100, 2)
                : 0;

            return [
                'evolution_activite' => $evolutionActivite,
                'taux_satisfaction' => 95,
                'livraisons_par_jour' => $this->getLivraisonsParJour($wilayaId, $dateDebut, $dateFin)
            ];
        } catch (\Exception $e) {
            Log::error('Erreur getPerformances: ' . $e->getMessage());
            return [
                'evolution_activite' => 0,
                'taux_satisfaction' => 0,
                'livraisons_par_jour' => 0
            ];
        }
    }

    /**
     * Moyenne des livraisons par jour
     */
    private function getLivraisonsParJour($wilayaId, $dateDebut = null, $dateFin = null): float
    {
        try {
            if (!$dateDebut || !$dateFin) {
                return 0;
            }

            $nbJours = Carbon::parse($dateDebut)->diffInDays(Carbon::parse($dateFin)) + 1;
            $nbLivraisons = $this->getStatsLivraisons($wilayaId, $dateDebut, $dateFin)['total'];

            return $nbJours > 0 ? round($nbLivraisons / $nbJours, 2) : 0;
        } catch (\Exception $e) {
            Log::error('Erreur getLivraisonsParJour: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Statistiques mensuelles pour une année donnée
     */
    public function statistiquesMensuelles(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || !$user->gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $wilayaId = $user->gestionnaire->wilaya_id;
            $annee = $request->get('annee', Carbon::now()->year);

            $stats = $this->getStatistiquesParMois($wilayaId, $annee);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur stats mensuelles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Statistiques par mois pour une année donnée
     */
    private function getStatistiquesParMois($wilayaId, $annee): array
    {
        try {
            $stats = [];

            for ($mois = 1; $mois <= 12; $mois++) {
                $debutMois = Carbon::create($annee, $mois, 1)->startOfMonth();
                $finMois = Carbon::create($annee, $mois, 1)->endOfMonth();

                $stats[] = [
                    'mois' => $debutMois->locale('fr')->isoFormat('MMMM'),
                    'mois_num' => $mois,
                    'livraisons' => $this->getStatsLivraisons($wilayaId, $debutMois, $finMois)['total'],
                    'chiffre_affaires' => $this->getBilanFinancier($wilayaId, $debutMois, $finMois)['chiffre_affaires_total'],
                    'colis' => $this->getStatsColis($wilayaId, $debutMois, $finMois)['total']
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            Log::error('Erreur getStatistiquesParMois: ' . $e->getMessage());
            return [];
        }
    }

    // ==================== UTILITAIRES ====================

    private function getPeriodDates($periode, $request): array
    {
        try {
            switch ($periode) {
                case 'jour':
                    $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                    return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
                case 'semaine':
                    $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                    return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
                case 'mois':
                    $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                    return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
                case 'annee':
                    $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                    return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
                case 'personnalise':
                    if (!$request->date_debut || !$request->date_fin) {
                        // Si dates manquantes, prendre le mois en cours
                        return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
                    }
                    return [
                        Carbon::parse($request->date_debut)->startOfDay(),
                        Carbon::parse($request->date_fin)->endOfDay()
                    ];
                default:
                    return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
            }
        } catch (\Exception $e) {
            Log::error('Erreur getPeriodDates: ' . $e->getMessage());
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        }
    }

    private function getPeriodLibelle($periode, $debut, $fin): string
    {
        if (!$debut || !$fin) {
            return 'Période non définie';
        }

        try {
            if ($periode === 'personnalise') {
                return 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
            }

            $labels = [
                'jour' => 'Journée du ' . $debut->format('d/m/Y'),
                'semaine' => 'Semaine du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
                'mois' => 'Mois de ' . $debut->locale('fr')->isoFormat('MMMM YYYY'),
                'annee' => 'Année ' . $debut->format('Y')
            ];

            return $labels[$periode] ?? 'Période';
        } catch (\Exception $e) {
            return 'Période';
        }
    }

    private function getWilayaName($code): string
    {
        if ($code === null) {
            return 'Wilaya inconnue';
        }

        $wilayas = [
            '01' => 'Adrar',
            '02' => 'Chlef',
            '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi',
            '05' => 'Batna',
            '06' => 'Béjaïa',
            '07' => 'Biskra',
            '08' => 'Béchar',
            '09' => 'Blida',
            '10' => 'Bouira',
            '11' => 'Tamanrasset',
            '12' => 'Tébessa',
            '13' => 'Tlemcen',
            '14' => 'Tiaret',
            '15' => 'Tizi Ouzou',
            '16' => 'Alger',
            '17' => 'Djelfa',
            '18' => 'Jijel',
            '19' => 'Sétif',
            '20' => 'Saïda',
            '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès',
            '23' => 'Annaba',
            '24' => 'Guelma',
            '25' => 'Constantine',
            '26' => 'Médéa',
            '27' => 'Mostaganem',
            '28' => 'M\'Sila',
            '29' => 'Mascara',
            '30' => 'Ouargla',
            '31' => 'Oran',
            '32' => 'El Bayadh',
            '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj',
            '35' => 'Boumerdès',
            '36' => 'El Tarf',
            '37' => 'Tindouf',
            '38' => 'Tissemsilt',
            '39' => 'El Oued',
            '40' => 'Khenchela',
            '41' => 'Souk Ahras',
            '42' => 'Tipaza',
            '43' => 'Mila',
            '44' => 'Aïn Defla',
            '45' => 'Naâma',
            '46' => 'Aïn Témouchent',
            '47' => 'Ghardaïa',
            '48' => 'Relizane',
            '49' => 'Timimoun',
            '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès',
            '53' => 'In Salah',
            '54' => 'In Guezzam',
            '55' => 'Touggourt',
            '56' => 'Djanet',
            '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? 'Wilaya ' . $code;
    }

    public function mesGains(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);

            $commissionService = new CommissionService();
            $gains = $commissionService->getGainsGestionnaire(
                $gestionnaire->id,
                $dates[0],
                $dates[1]
            );

            // Ajouter des statistiques détaillées
            $gains['details_par_mois'] = $this->getGainsParMois($gestionnaire->id, $dates[0], $dates[1]);
            $gains['total_attendu'] = GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                ->where('status', 'calcule')
                ->sum('montant_commission');
            $gains['total_verse'] = GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                ->where('status', 'verse')
                ->sum('montant_commission');

            return response()->json([
                'success' => true,
                'data' => $gains
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération gains: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains'
            ], 500);
        }
    }

    private function getGainsParMois($gestionnaireId, $debut, $fin)
    {
        $mois = [];
        $current = $debut->copy();

        while ($current <= $fin) {
            $debutMois = $current->copy()->startOfMonth();
            $finMois = $current->copy()->endOfMonth();

            $gainsMois = GestionnaireGain::where('gestionnaire_id', $gestionnaireId)
                ->whereBetween('created_at', [$debutMois, $finMois])
                ->sum('montant_commission');

            $mois[] = [
                'mois' => $current->locale('fr')->isoFormat('MMMM YYYY'),
                'gains' => $gainsMois
            ];

            $current->addMonth();
        }

        return $mois;
    }
}
