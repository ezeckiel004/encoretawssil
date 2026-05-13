<?php
// app/Http/Controllers/Admin/NavetteController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Navette;
use App\Models\Livreur;
use App\Models\Livraison;
use App\Models\Colis;
use App\Services\OptimisationTrajetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Events\NavetteTerminee;

class NavetteController extends Controller
{
    protected $optimisationService;

    public function __construct(OptimisationTrajetService $optimisationService)
    {
        $this->optimisationService = $optimisationService;
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'hub',
                'createur',
                'acteurs',
                'gains'
            ])->withCount('livraisons');

            // Filtres
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('wilaya_depart') && $request->wilaya_depart) {
                $query->where('wilaya_depart_id', $request->wilaya_depart);
            }

            if ($request->has('wilaya_arrivee') && $request->wilaya_arrivee) {
                $query->where('wilaya_arrivee_id', $request->wilaya_arrivee);
            }

            if ($request->has('date_depart') && $request->date_depart) {
                $query->whereDate('date_depart', $request->date_depart);
            }

            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            if ($request->has('hub_id') && $request->hub_id) {
                $query->where('hub_id', $request->hub_id);
            }

            // Recherche
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('hub', function ($q) use ($search) {
                            $q->where('nom', 'like', "%{$search}%");
                        });
                });
            }

            // Tri
            $orderBy = $request->get('order_by', 'date_depart');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $navettes = $query->paginate($request->get('per_page', 20));

            // Ajouter les totaux calculés pour chaque navette
            $navettes->getCollection()->transform(function ($navette) {
                $totalGains = 0;
                foreach ($navette->gains as $gain) {
                    $totalGains += floatval($gain->montant_commission);
                }
                $navette->total_gains = $totalGains;
                $navette->nb_acteurs = $navette->acteurs->count();
                return $navette;
            });

            return response()->json([
                'success' => true,
                'data' => $navettes
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des navettes'
            ], 500);
        }
    }

    /**
     * Voir une navette spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'hub',
                'createur',
                'acteurs',
                'acteurs.gestionnaire.user',
                'acteurs.hub',
                'livraisons' => function($query) {
                    $query->with([
                        'demandeLivraison' => function($q) {
                            $q->with([
                                'client.user',
                                'colis',
                                'destinataire.user'
                            ]);
                        },
                        'client.user'
                    ]);
                }
            ])->find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            $navette->nb_livraisons = $navette->nb_livraisons;
            $navette->poids_total = $navette->poids_total;
            $navette->valeur_totale = $navette->valeur_totale;
            $navette->taux_remplissage = $navette->taux_remplissage;

            return response()->json([
                'success' => true,
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@show: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la navette: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle navette
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart_id' => 'required|string|size:2',
            'wilaya_arrivee_id' => 'required|string|size:2',
            'wilayas_transit' => 'nullable|array',
            'wilayas_transit.*' => 'string|size:2',
            'heure_depart' => 'required|date_format:H:i',
            'date_depart' => 'required|date',
            'hub_id' => 'nullable|exists:hubs,id',
            'vehicule_immatriculation' => 'nullable|string|max:20',
            'capacite_max' => 'required|integer|min:1|max:500',
            'prix_base' => 'required|numeric|min:0',
            'prix_par_livraison' => 'required|numeric|min:0',
            'livraison_ids' => 'nullable|array',
            'livraison_ids.*' => 'exists:livraisons,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $distance = $this->optimisationService->getDistance(
                $request->wilaya_depart_id,
                $request->wilaya_arrivee_id
            );

            $dureeEstimee = $this->optimisationService->estimerDuree($distance);

            $dateDepart = Carbon::parse($request->date_depart . ' ' . $request->heure_depart);
            $dateArrivee = $dateDepart->copy()->addHours($dureeEstimee);

            $navette = Navette::create([
                'wilaya_depart_id' => $request->wilaya_depart_id,
                'wilaya_arrivee_id' => $request->wilaya_arrivee_id,
                'wilayas_transit' => $request->wilayas_transit ?? [],
                'heure_depart' => $request->heure_depart,
                'heure_arrivee' => $dateArrivee->format('H:i'),
                'hub_id' => $request->hub_id,
                'vehicule_immatriculation' => $request->vehicule_immatriculation,
                'capacite_max' => $request->capacite_max,
                'prix_base' => $request->prix_base,
                'prix_par_livraison' => $request->prix_par_livraison,
                'distance_km' => $distance,
                'carburant_estime' => $this->optimisationService->estimerCarburant($distance),
                'peages_estimes' => $this->optimisationService->estimerPeages([$request->wilaya_depart_id, $request->wilaya_arrivee_id]),
                'status' => 'planifiee',
                'date_depart' => $dateDepart,
                'date_arrivee_prevue' => $dateArrivee,
                'created_by' => auth()->id(),
                'notes' => $request->notes
            ]);

            // Ajouter les wilayas de transit
            if ($request->has('wilayas_transit') && is_array($request->wilayas_transit)) {
                foreach ($request->wilayas_transit as $index => $wilayaCode) {
                    DB::table('navette_wilaya_transit')->insert([
                        'navette_id' => $navette->id,
                        'wilaya_code' => $wilayaCode,
                        'ordre' => $index,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Ajouter les livraisons
            if ($request->has('livraison_ids') && is_array($request->livraison_ids)) {
                $ordre = 1;
                foreach ($request->livraison_ids as $livraisonId) {
                    $livraison = Livraison::find($livraisonId);

                    if ($livraison) {
                        $navette->livraisons()->attach($livraisonId, [
                            'ordre_chargement' => $ordre++,
                            'date_prise_en_charge' => now()
                        ]);

                        $livraison->update([
                            'navette_id' => $navette->id,
                            'status' => 'prise_en_charge_ramassage'
                        ]);
                    }
                }
            }

            // Calculer la répartition des parts
            $navette->calculerRepartitionParts();

            DB::commit();

            // Recharger la navette avec les relations nécessaires
            $navette->load(['wilayaDepart', 'wilayasTransit', 'wilayaArrivee', 'hub']);

            // Formater les acteurs pour la réponse
            $acteursFormatted = [];
            foreach ($navette->acteurs as $acteur) {
                if ($acteur->type === 'gestionnaire') {
                    $gestionnaire = $acteur->gestionnaire;
                    if ($gestionnaire && $gestionnaire->user) {
                        $acteursFormatted[] = [
                            'type' => 'gestionnaire',
                            'id' => $acteur->acteur_id,
                            'nom' => $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom,
                            'email' => $gestionnaire->user->email,
                            'wilaya' => $acteur->wilaya_code,
                            'part' => (float) $acteur->part_pourcentage
                        ];
                    }
                } elseif ($acteur->type === 'hub') {
                    $hub = $acteur->hub;
                    if ($hub) {
                        $acteursFormatted[] = [
                            'type' => 'hub',
                            'id' => $acteur->acteur_id,
                            'nom' => $hub->nom,
                            'email' => $hub->email,
                            'part' => (float) $acteur->part_pourcentage
                        ];
                    }
                }
            }

            $responseData = $navette->toArray();
            $responseData['repartition'] = $acteursFormatted;

            return response()->json([
                'success' => true,
                'message' => 'Navette créée avec succès',
                'data' => $responseData
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une navette - CORRIGÉ POUR FONCTIONNER COMME LE MANAGER
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Vérifier si la navette est terminée ou annulée (non modifiable)
            if (in_array($navette->status, ['terminee', 'annulee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette navette est ' . $navette->status . ' et ne peut plus être modifiée'
                ], 400);
            }

            // Déterminer si la navette est en cours
            $isEnCours = ($navette->status === 'en_cours');

            // Règles de validation selon le statut (COMME LE MANAGER)
            $rules = [
                'notes' => 'nullable|string',
                'livraison_ids' => 'nullable|array',
                'livraison_ids.*' => 'exists:livraisons,id'
            ];

            if (!$isEnCours) {
                // Pour les navettes planifiées : tous les champs sont modifiables
                $rules = array_merge($rules, [
                    'wilaya_depart_id' => 'sometimes|string|size:2',
                    'wilaya_arrivee_id' => 'sometimes|string|size:2',
                    'wilayas_transit' => 'nullable|array',
                    'wilayas_transit.*' => 'string|size:2',
                    'heure_depart' => 'sometimes|date_format:H:i',
                    'date_depart' => 'sometimes|date',
                    'hub_id' => 'nullable|exists:hubs,id',
                    'vehicule_immatriculation' => 'nullable|string|max:20',
                    'capacite_max' => 'sometimes|integer|min:1|max:500',
                    'prix_base' => 'sometimes|numeric|min:0',
                    'prix_par_livraison' => 'sometimes|numeric|min:0',
                    'status' => 'sometimes|in:planifiee,en_cours,terminee,annulee'
                ]);
            } else {
                // Pour les navettes en cours : seuls certains champs sont modifiables (COMME LE MANAGER)
                $rules = array_merge($rules, [
                    'wilayas_transit' => 'nullable|array',
                    'wilayas_transit.*' => 'string|size:2',
                    'hub_id' => 'nullable|exists:hubs,id',
                    'vehicule_immatriculation' => 'nullable|string|max:20',
                ]);
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [];

            if (!$isEnCours) {
                // Planifiée : tous les champs
                $updateData = $request->only([
                    'wilaya_depart_id',
                    'wilaya_arrivee_id',
                    'hub_id',
                    'vehicule_immatriculation',
                    'capacite_max',
                    'prix_base',
                    'prix_par_livraison',
                    'status',
                    'notes'
                ]);

                // Gestion spécifique de la date et heure
                if ($request->has('date_depart') || $request->has('heure_depart')) {
                    $dateDepartStr = $request->date_depart ?? $navette->date_depart->format('Y-m-d');
                    $heureDepartStr = $request->heure_depart ?? $navette->heure_depart;

                    $dateDepart = Carbon::parse($dateDepartStr . ' ' . $heureDepartStr);
                    $updateData['date_depart'] = $dateDepart;
                    $updateData['heure_depart'] = $heureDepartStr;

                    // Recalculer la distance et la durée
                    $depart = $request->wilaya_depart_id ?? $navette->wilaya_depart_id;
                    $arrivee = $request->wilaya_arrivee_id ?? $navette->wilaya_arrivee_id;
                    $distance = $this->optimisationService->getDistance($depart, $arrivee);
                    $dureeEstimee = $this->optimisationService->estimerDuree($distance);
                    $dateArrivee = $dateDepart->copy()->addHours($dureeEstimee);

                    $updateData['heure_arrivee'] = $dateArrivee->format('H:i');
                    $updateData['date_arrivee_prevue'] = $dateArrivee;
                    $updateData['distance_km'] = $distance;
                    $updateData['carburant_estime'] = $this->optimisationService->estimerCarburant($distance);
                    $updateData['peages_estimes'] = $this->optimisationService->estimerPeages([$depart, $arrivee]);
                }
            } else {
                // En cours : seulement les champs autorisés (COMME LE MANAGER)
                $updateData = $request->only([
                    'wilayas_transit',
                    'hub_id',
                    'vehicule_immatriculation',
                    'notes'
                ]);
            }

            // Appliquer les mises à jour
            if (!empty($updateData)) {
                $navette->update($updateData);
            }

            // Gérer les wilayas de transit (autorisé pour planifiée ET en_cours) - COMME LE MANAGER
            if ($request->has('wilayas_transit')) {
                // Supprimer les anciennes wilayas de transit
                DB::table('navette_wilaya_transit')->where('navette_id', $navette->id)->delete();

                // Ajouter les nouvelles
                if (is_array($request->wilayas_transit)) {
                    foreach ($request->wilayas_transit as $index => $wilayaCode) {
                        DB::table('navette_wilaya_transit')->insert([
                            'navette_id' => $navette->id,
                            'wilaya_code' => $wilayaCode,
                            'ordre' => $index,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }

            // Gérer les livraisons (autorisé pour planifiée ET en_cours)
            if ($request->has('livraison_ids')) {
                $newLivraisonIds = $request->livraison_ids;
                $currentLivraisonIds = $navette->livraisons->pluck('id')->toArray();

                $toAdd = array_diff($newLivraisonIds, $currentLivraisonIds);
                $toRemove = array_diff($currentLivraisonIds, $newLivraisonIds);

                $currentCount = count($currentLivraisonIds);
                $newCount = count($newLivraisonIds);

                if ($newCount > $navette->capacite_max) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "La capacité maximale est de {$navette->capacite_max} livraisons"
                    ], 400);
                }

                // Ajouter les nouvelles livraisons
                foreach ($toAdd as $livraisonId) {
                    $ordre = $currentCount + 1;
                    $navette->livraisons()->attach($livraisonId, [
                        'ordre_chargement' => $ordre,
                        'date_prise_en_charge' => now()
                    ]);

                    Livraison::where('id', $livraisonId)->update([
                        'navette_id' => $navette->id,
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }

                // Retirer les livraisons supprimées
                foreach ($toRemove as $livraisonId) {
                    $navette->livraisons()->detach($livraisonId);

                    Livraison::where('id', $livraisonId)->update([
                        'navette_id' => null,
                        'status' => 'en_attente'
                    ]);
                }

                // Réordonner les livraisons restantes
                $remainingLivraisons = $navette->livraisons()->orderBy('ordre_chargement')->get();
                $ordre = 1;
                foreach ($remainingLivraisons as $livraison) {
                    $navette->livraisons()->updateExistingPivot($livraison->id, [
                        'ordre_chargement' => $ordre++
                    ]);
                }
            }

            // Recalculer la répartition des parts si nécessaire
            $navette->calculerRepartitionParts();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette mise à jour avec succès',
                'data' => $navette->fresh([
                    'wilayaDepart',
                    'wilayasTransit',
                    'wilayaArrivee',
                    'hub',
                    'acteurs',
                    'livraisons.demandeLivraison.client.user',
                    'livraisons.demandeLivraison.colis',
                    'livraisons.client.user'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@update: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une navette
     */
    public function destroy($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if (!in_array($navette->status, ['planifiee', 'annulee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées ou annulées peuvent être supprimées'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                foreach ($navette->livraisons as $livraison) {
                    $livraison->update([
                        'navette_id' => null,
                        'status' => 'en_attente'
                    ]);
                }

                $navette->livraisons()->detach();
                DB::table('navette_wilaya_transit')->where('navette_id', $navette->id)->delete();
                $navette->acteurs()->delete();
                $navette->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Démarrer une navette
     */
    public function demarrer($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if ($navette->status !== 'planifiee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées peuvent être démarrées'
                ], 400);
            }

            if ($navette->acteurs()->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun acteur défini pour cette navette. Vérifiez la configuration.'
                ], 400);
            }

            $navette->update([
                'status' => 'en_cours',
                'date_depart' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Navette démarrée',
                'data' => $navette->load(['acteurs'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@demarrer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage'
            ], 500);
        }
    }

    /**
     * Terminer une navette
     */
    public function terminer($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if ($navette->status !== 'en_cours') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes en cours peuvent être terminées'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                $navette->update([
                    'status' => 'terminee',
                    'date_arrivee_reelle' => now()
                ]);

                foreach ($navette->livraisons as $livraison) {
                    $navette->livraisons()->updateExistingPivot($livraison->id, [
                        'date_livraison' => now()
                    ]);

                    $livraison->update([
                        'status' => 'en_transit'
                    ]);
                }

                event(new NavetteTerminee($navette));
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette terminée et gains calculés',
                'data' => $navette->load(['acteurs', 'gains'])
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@terminer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la terminaison: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler une navette
     */
    public function annuler($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette navette ne peut pas être annulée'
                ], 400);
            }

            DB::transaction(function () use ($navette) {
                foreach ($navette->livraisons as $livraison) {
                    $livraison->update([
                        'navette_id' => null,
                        'status' => 'en_attente'
                    ]);
                }

                $navette->livraisons()->detach();
                $navette->update(['status' => 'annulee']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette annulée'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@annuler: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }

    /**
     * Ajouter des livraisons à une navette
     */
    public function ajouterLivraisons(Request $request, $id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'ajouter des livraisons à une navette ' . $navette->status
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'livraison_ids' => 'required|array',
                'livraison_ids.*' => 'required|exists:livraisons,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $nbActuel = $navette->livraisons()->count();
            $nbNouveaux = count($request->livraison_ids);

            if ($nbActuel + $nbNouveaux > $navette->capacite_max) {
                return response()->json([
                    'success' => false,
                    'message' => 'Capacité dépassée. Maximum: ' . $navette->capacite_max
                ], 400);
            }

            DB::transaction(function () use ($navette, $request, $nbActuel) {
                $ordre = $nbActuel + 1;

                foreach ($request->livraison_ids as $livraisonId) {
                    $livraison = Livraison::find($livraisonId);

                    if (!$livraison) {
                        throw new \Exception("Livraison non trouvée: " . $livraisonId);
                    }

                    $navette->livraisons()->syncWithoutDetaching([$livraisonId => [
                        'ordre_chargement' => $ordre++,
                        'date_prise_en_charge' => now()
                    ]]);

                    $livraison->update([
                        'navette_id' => $navette->id,
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Livraisons ajoutées à la navette',
                'data' => $navette->load('livraisons')
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@ajouterLivraisons: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout des livraisons'
            ], 500);
        }
    }

    /**
     * Retirer des livraisons d'une navette
     */
    public function retirerLivraisons(Request $request, $id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            if (!in_array($navette->status, ['planifiee', 'en_cours'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de retirer des livraisons'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'livraison_ids' => 'required|array',
                'livraison_ids.*' => 'exists:livraisons,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::transaction(function () use ($navette, $request) {
                foreach ($request->livraison_ids as $livraisonId) {
                    $navette->livraisons()->detach($livraisonId);

                    $livraison = Livraison::find($livraisonId);
                    if ($livraison) {
                        $livraison->update([
                            'navette_id' => null,
                            'status' => 'en_attente'
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Livraisons retirées de la navette'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@retirerLivraisons: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du retrait des livraisons'
            ], 500);
        }
    }

    /**
     * Obtenir les suggestions de navettes optimisées
     */
    public function suggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart' => 'required|string|size:2',
            'date_limite' => 'nullable|date',
            'priorite' => 'nullable|in:date,urgence,valeur'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $livraisonsDisponibles = Livraison::with(['colis', 'demandeLivraison'])
                ->whereNull('navette_id')
                ->whereIn('status', ['en_attente', 'pret_a_charger'])
                ->orderBy('created_at', 'desc')
                ->get();

            if ($livraisonsDisponibles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'Aucune livraison disponible'
                ]);
            }

            $livraisonsParDestination = $livraisonsDisponibles
                ->groupBy(function($livraison) {
                    return $livraison->demandeLivraison?->wilaya_destination ?? substr($livraison->id, 0, 2);
                })
                ->filter(function($group) {
                    return $group->count() >= 2;
                });

            $suggestions = [];

            foreach ($livraisonsParDestination as $wilayaCode => $livraisonGroup) {
                $suggestions[] = [
                    'type' => 'destination_unique',
                    'wilaya' => $wilayaCode,
                    'wilaya_nom' => $this->getWilayaName($wilayaCode),
                    'nb_livraisons' => $livraisonGroup->count(),
                    'poids_total' => $livraisonGroup->sum(function($l) {
                        return $l->colis?->poids ?? 0;
                    }),
                    'valeur_totale' => $livraisonGroup->sum(function($l) {
                        return $l->colis?->colis_prix ?? 0;
                    }),
                    'date_plus_ancienne' => $livraisonGroup->min('created_at')?->toDateString(),
                    'urgence' => $livraisonGroup->count() > 10 ? 'haute' : 'moyenne',
                    'confiance' => min(90, 70 + $livraisonGroup->count()),
                    'livraisons_exemples' => $livraisonGroup->take(5)->map(function ($livraison) {
                        return [
                            'id' => $livraison->id,
                            'reference' => $livraison->demandeLivraison?->reference ?? 'LIV-' . substr($livraison->id, 0, 8),
                            'client' => $livraison->client?->nom ?? 'Client inconnu',
                            'prix' => $livraison->colis?->colis_prix ?? 0,
                            'poids' => $livraison->colis?->poids ?? 0
                        ];
                    })->toArray()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@suggestions: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Aucune suggestion disponible pour le moment'
            ]);
        }
    }

    /**
     * Créer une navette optimisée
     */
    public function creerOptimisee(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'wilaya_depart' => 'required|string|size:2',
            'wilaya_arrivee' => 'nullable|string|size:2',
            'wilayas_transit' => 'nullable|array',
            'wilayas_transit.*' => 'string|size:2',
            'date_limite' => 'nullable|date',
            'priorite' => 'nullable|in:date,urgence,valeur',
            'capacite' => 'nullable|integer|min:1|max:500',
            'prix_base' => 'nullable|numeric|min:0',
            'prix_par_livraison' => 'nullable|numeric|min:0',
            'heure_depart' => 'nullable|date_format:H:i'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dateDepart = $request->heure_depart
                ? Carbon::parse($request->date_limite ?? now()->toDateString() . ' ' . $request->heure_depart)
                : Carbon::now();

            $wilayaArrivee = $request->wilaya_arrivee ?? '31';

            $navette = Navette::create([
                'wilaya_depart_id' => $request->wilaya_depart,
                'wilaya_arrivee_id' => $wilayaArrivee,
                'wilayas_transit' => $request->wilayas_transit ?? [],
                'heure_depart' => $dateDepart->format('H:i'),
                'heure_arrivee' => $dateDepart->copy()->addHours(8)->format('H:i'),
                'capacite_max' => $request->capacite ?? 100,
                'prix_base' => $request->prix_base ?? 300,
                'prix_par_livraison' => $request->prix_par_livraison ?? 10,
                'status' => 'planifiee',
                'date_depart' => $dateDepart,
                'date_arrivee_prevue' => $dateDepart->copy()->addHours(8),
                'created_by' => auth()->id(),
                'notes' => 'Navette créée automatiquement via optimisation'
            ]);

            if ($request->has('wilayas_transit') && is_array($request->wilayas_transit)) {
                foreach ($request->wilayas_transit as $index => $wilayaCode) {
                    DB::table('navette_wilaya_transit')->insert([
                        'navette_id' => $navette->id,
                        'wilaya_code' => $wilayaCode,
                        'ordre' => $index,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            $navette->calculerRepartitionParts();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette optimisée créée avec succès',
                'data' => $navette->load(['acteurs'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur NavetteController@creerOptimisee: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des navettes
     */
    public function statistiques(Request $request): JsonResponse
    {
        try {
            $debut = $request->get('debut') ? Carbon::parse($request->debut) : Carbon::now()->startOfMonth();
            $fin = $request->get('fin') ? Carbon::parse($request->fin) : Carbon::now()->endOfMonth();

            $stats = [
                'global' => [
                    'total_navettes' => Navette::whereBetween('date_depart', [$debut, $fin])->count(),
                    'total_livraisons' => DB::table('navette_livraison')
                        ->whereBetween('created_at', [$debut, $fin])
                        ->count(),
                    'distance_totale' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->sum('distance_km'),
                ],
                'par_status' => [
                    'planifiees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'planifiee')
                        ->count(),
                    'en_cours' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'en_cours')
                        ->count(),
                    'terminees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'terminee')
                        ->count(),
                    'annulees' => Navette::whereBetween('date_depart', [$debut, $fin])
                        ->where('status', 'annulee')
                        ->count()
                ],
                'par_hub' => Navette::whereBetween('date_depart', [$debut, $fin])
                    ->whereNotNull('hub_id')
                    ->select('hub_id', DB::raw('count(*) as total'))
                    ->groupBy('hub_id')
                    ->with('hub')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@statistiques: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Exporter la liste des navettes (PDF)
     */
    public function exportPDF(Request $request)
    {
        try {
            $query = Navette::with([
                'wilayaDepart',
                'wilayasTransit',
                'wilayaArrivee',
                'hub'
            ])->withCount('livraisons');

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            if ($request->has('wilaya_depart') && $request->wilaya_depart) {
                $query->where('wilaya_depart_id', $request->wilaya_depart);
            }
            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            $navettes = $query->orderBy('date_depart', 'desc')->get();

            $pdf = Pdf::loadView('pdf.navettes', [
                'navettes' => $navettes,
                'filters' => $request->all(),
                'date_generation' => Carbon::now()->format('d/m/Y H:i')
            ]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->download('navettes-' . Carbon::now()->format('Ymd-His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@exportPDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF'
            ], 500);
        }
    }

    /**
     * Obtenir les livraisons d'une navette
     */
    public function getLivraisons($id): JsonResponse
    {
        try {
            if (!Str::isUuid($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de navette invalide'
                ], 400);
            }

            $navette = Navette::with([
                'livraisons.client',
                'livraisons.colis',
                'livraisons.demandeLivraison'
            ])->find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'navette' => [
                        'id' => $navette->id,
                        'reference' => $navette->reference,
                        'status' => $navette->status,
                        'capacite_max' => $navette->capacite_max,
                        'nb_livraisons' => $navette->livraisons->count()
                    ],
                    'livraisons' => $navette->livraisons->map(function($livraison) {
                        return [
                            'id' => $livraison->id,
                            'reference' => $livraison->demandeLivraison?->reference ?? 'N/A',
                            'client' => $livraison->client?->nom ?? 'Client inconnu',
                            'colis' => [
                                'label' => $livraison->colis?->colis_label ?? 'N/A',
                                'poids' => $livraison->colis?->poids ?? 0,
                                'prix' => $livraison->colis?->colis_prix ?? 0
                            ],
                            'destination' => $livraison->demandeLivraison?->addresse_delivery ?? 'N/A',
                            'wilaya_destination' => $livraison->demandeLivraison?->wilaya_destination ?? 'N/A',
                            'status' => $livraison->status,
                            'ordre_chargement' => $livraison->pivot->ordre_chargement ?? null,
                            'date_prise_en_charge' => $livraison->pivot->date_prise_en_charge,
                            'date_livraison' => $livraison->pivot->date_livraison,
                            'qr_code_scan' => $livraison->pivot->qr_code_scan,
                            'incident_notes' => $livraison->pivot->incident_notes
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur NavetteController@getLivraisons: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons'
            ], 500);
        }
    }

    /**
     * Obtenir le nom d'une wilaya à partir de son code
     */
    private function getWilayaName($code): string
    {
        $wilayas = [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi', '05' => 'Batna', '06' => 'Béjaïa',
            '07' => 'Biskra', '08' => 'Béchar', '09' => 'Blida',
            '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou',
            '16' => 'Alger', '17' => 'Djelfa', '18' => 'Jijel',
            '19' => 'Sétif', '20' => 'Saïda', '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem',
            '28' => 'M\'Sila', '29' => 'Mascara', '30' => 'Ouargla',
            '31' => 'Oran', '32' => 'El Bayadh', '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès', '36' => 'El Tarf',
            '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza',
            '43' => 'Mila', '44' => 'Aïn Defla', '45' => 'Naâma',
            '46' => 'Aïn Témouchent', '47' => 'Ghardaïa', '48' => 'Relizane',
            '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar', '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès', '53' => 'In Salah', '54' => 'In Guezzam',
            '55' => 'Touggourt', '56' => 'Djanet', '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? 'Wilaya inconnue';
    }
}
