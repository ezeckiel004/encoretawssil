<?php
// app/Http/Controllers/Manager/NavetteController.php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Navette;
use App\Models\Livraison;
use App\Models\Gestionnaire;
use App\Services\OptimisationTrajetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Events\NavetteTerminee;

class NavetteController extends Controller
{
    protected $optimisationService;

    public function __construct(OptimisationTrajetService $optimisationService)
    {
        $this->optimisationService = $optimisationService;
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire');
    }

    /**
     * Récupérer le profil du gestionnaire connecté
     */
    public function profile(Request $request): JsonResponse
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

            $gestionnaire->load('wilaya', 'user');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $gestionnaire->id,
                    'user_id' => $gestionnaire->user_id,
                    'wilaya_id' => $gestionnaire->wilaya_id,
                    'wilaya' => $gestionnaire->wilaya,
                    'user' => $gestionnaire->user,
                    'gestionnaire' => $gestionnaire
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister les navettes de la wilaya du gestionnaire
     */
    public function index(Request $request): JsonResponse
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

            $wilayaId = $gestionnaire->wilaya_id;

            $query = Navette::with([
                'wilayaDepart',
                'wilayaArrivee',
                'hub',
                'createur',
                'acteurs',
                'gains'
            ])->withCount('livraisons')
                ->where(function ($q) use ($wilayaId) {
                    $q->where('wilaya_depart_id', $wilayaId)
                        ->orWhere('wilaya_arrivee_id', $wilayaId)
                        ->orWhereJsonContains('wilayas_transit', $wilayaId);
                });

            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_debut') && $request->has('date_fin') && $request->date_debut && $request->date_fin) {
                $query->whereBetween('date_depart', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            if ($request->has('search') && $request->search) {
                $query->where('reference', 'like', "%{$request->search}%");
            }

            $orderBy = $request->get('order_by', 'date_depart');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);

            $navettes = $query->paginate($request->get('per_page', 20));

            $navettes->getCollection()->transform(function ($navette) {
                $navette->total_gains = $navette->gains->sum('montant_commission');
                $navette->nb_acteurs = $navette->acteurs->count();
                return $navette;
            });

            return response()->json([
                'success' => true,
                'data' => $navettes
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des navettes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Voir une navette spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

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
                'livraisons' => function ($query) {
                    $query->with([
                        'demandeLivraison' => function ($q) {
                            $q->with([
                                'client.user',
                                'colis',
                                'destinataire.user'
                            ]);
                        },
                        'client.user'
                    ]);
                },
                'gains'
            ])->find($id);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            $concerneWilaya = (
                $navette->wilaya_depart_id == $wilayaId ||
                $navette->wilaya_arrivee_id == $wilayaId
            );

            if (!$concerneWilaya && is_array($navette->wilayas_transit)) {
                $concerneWilaya = in_array($wilayaId, $navette->wilayas_transit);
            }

            if (!$concerneWilaya) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas accès à cette navette'
                ], 403);
            }

            $navette->total_gains = $navette->gains->sum('montant_commission');

            return response()->json([
                'success' => true,
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la navette: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle navette pour la wilaya du gestionnaire
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $gestionnaire = $user->gestionnaire;

        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Profil gestionnaire introuvable'
            ], 403);
        }

        $wilayaId = $gestionnaire->wilaya_id;

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

        if ($request->wilaya_depart_id != $wilayaId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez créer une navette que depuis votre wilaya de gestion'
            ], 403);
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

            $navette->calculerRepartitionParts();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Navette créée avec succès',
                'data' => $navette->load(['wilayaDepart', 'wilayasTransit', 'wilayaArrivee', 'hub', 'acteurs'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur ManagerNavetteController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livraisons disponibles pour la navette
     */
    public function getLivraisonsDisponibles(Request $request): JsonResponse
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

            $wilayaId = $gestionnaire->wilaya_id;
            $wilayaNom = $this->getWilayaName($wilayaId);

            $livraisons = Livraison::with([
                'demandeLivraison' => function ($q) {
                    $q->with(['client.user', 'colis', 'destinataire.user']);
                },
                'client.user',
                'colis'
            ])
                ->whereNull('navette_id')
                ->where('status', 'en_attente')
                ->whereHas('demandeLivraison', function ($q) use ($wilayaId, $wilayaNom) {
                    $q->where(function ($sub) use ($wilayaId, $wilayaNom) {
                        $sub->where('wilaya_depot', $wilayaId)
                            ->orWhere('wilaya_depot', $wilayaNom)
                            ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%')
                            ->orWhere('wilaya', $wilayaId)
                            ->orWhere('wilaya', $wilayaNom);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedLivraisons = $livraisons->map(function ($livraison) use ($wilayaId) {
                $demande = $livraison->demandeLivraison;
                $colis = $livraison->colis;

                $prix = 0;
                if ($colis && $colis->colis_prix) {
                    $prix = $colis->colis_prix;
                } elseif ($demande && $demande->prix) {
                    $prix = $demande->prix;
                }

                $reference = 'LIV-' . substr($livraison->id, 0, 8);
                if ($demande && $demande->reference) {
                    $reference = $demande->reference;
                }

                $clientName = 'Client inconnu';
                if ($livraison->client && $livraison->client->user) {
                    $clientName = trim($livraison->client->user->prenom . ' ' . $livraison->client->user->nom);
                } elseif ($demande && $demande->client && $demande->client->user) {
                    $clientName = trim($demande->client->user->prenom . ' ' . $demande->client->user->nom);
                }

                return [
                    'id' => $livraison->id,
                    'reference' => $reference,
                    'client' => $clientName,
                    'destination' => $demande->addresse_delivery ?? 'Non spécifiée',
                    'wilaya_depart' => $demande->wilaya_depot ?? $wilayaId,
                    'wilaya_arrivee' => $demande->wilaya ?? 'Non spécifiée',
                    'colis_label' => $colis->colis_label ?? 'N/A',
                    'poids' => $colis->poids ?? 0,
                    'prix' => $prix,
                    'created_at' => $livraison->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedLivraisons
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@getLivraisonsDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livraisons disponibles pour une navette spécifique (excluant celles déjà dans la navette)
     */
    public function getLivraisonsDisponiblesForNavette(Request $request, $id): JsonResponse
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

            $wilayaId = $gestionnaire->wilaya_id;
            $wilayaNom = $this->getWilayaName($wilayaId);

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

            $livraisonsDansNavette = $navette->livraisons->pluck('id')->toArray();

            $livraisons = Livraison::with([
                'demandeLivraison' => function ($q) {
                    $q->with(['client.user', 'colis', 'destinataire.user']);
                },
                'client.user',
                'colis'
            ])
                ->whereNull('navette_id')
                ->where('status', 'en_attente')
                ->whereNotIn('id', $livraisonsDansNavette)
                ->whereHas('demandeLivraison', function ($q) use ($wilayaId, $wilayaNom) {
                    $q->where(function ($sub) use ($wilayaId, $wilayaNom) {
                        $sub->where('wilaya_depot', $wilayaId)
                            ->orWhere('wilaya_depot', $wilayaNom)
                            ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%')
                            ->orWhere('wilaya', $wilayaId)
                            ->orWhere('wilaya', $wilayaNom);
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedLivraisons = $livraisons->map(function ($livraison) use ($wilayaId) {
                $demande = $livraison->demandeLivraison;
                $colis = $livraison->colis;

                $prix = 0;
                if ($colis && $colis->colis_prix) {
                    $prix = $colis->colis_prix;
                } elseif ($demande && $demande->prix) {
                    $prix = $demande->prix;
                }

                $reference = 'LIV-' . substr($livraison->id, 0, 8);
                if ($demande && $demande->reference) {
                    $reference = $demande->reference;
                }

                $clientName = 'Client inconnu';
                if ($livraison->client && $livraison->client->user) {
                    $clientName = trim($livraison->client->user->prenom . ' ' . $livraison->client->user->nom);
                } elseif ($demande && $demande->client && $demande->client->user) {
                    $clientName = trim($demande->client->user->prenom . ' ' . $demande->client->user->nom);
                }

                return [
                    'id' => $livraison->id,
                    'reference' => $reference,
                    'client' => $clientName,
                    'destination' => $demande->addresse_delivery ?? 'Non spécifiée',
                    'wilaya_depart' => $demande->wilaya_depot ?? $wilayaId,
                    'wilaya_arrivee' => $demande->wilaya ?? 'Non spécifiée',
                    'colis_label' => $colis->colis_label ?? 'N/A',
                    'poids' => $colis->poids ?? 0,
                    'prix' => $prix,
                    'created_at' => $livraison->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedLivraisons
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@getLivraisonsDisponiblesForNavette: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Démarrer une navette
     */
    public function demarrer($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

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

            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas démarrer cette navette'
                ], 403);
            }

            if ($navette->status !== 'planifiee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Seules les navettes planifiées peuvent être démarrées'
                ], 400);
            }

            $navette->update([
                'status' => 'en_cours',
                'date_depart' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Navette démarrée',
                'data' => $navette
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@demarrer: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Terminer une navette
     */
    public function terminer($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

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

            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas terminer cette navette'
                ], 403);
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
            Log::error('Erreur ManagerNavetteController@terminer: ' . $e->getMessage());
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
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

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

            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas annuler cette navette'
                ], 403);
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
            Log::error('Erreur ManagerNavetteController@annuler: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * Mettre à jour une navette
 */
public function update(Request $request, $id): JsonResponse
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

        $wilayaId = $gestionnaire->wilaya_id;

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

        if ($navette->wilaya_depart_id != $wilayaId) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier cette navette'
            ], 403);
        }

        // Vérifier si la navette est terminée ou annulée (non modifiable)
        if (in_array($navette->status, ['terminee', 'annulee'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette navette est ' . $navette->status . ' et ne peut plus être modifiée'
            ], 400);
        }

        // Déterminer les champs modifiables selon le statut
        $isEnCours = ($navette->status === 'en_cours');

        // Validation selon le statut
        $rules = [
            'notes' => 'nullable|string',
            'livraison_ids' => 'nullable|array',
            'livraison_ids.*' => 'exists:livraisons,id'
        ];

        // Pour les navettes planifiées, tous les champs sont modifiables
        if (!$isEnCours) {
            $rules = array_merge($rules, [
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
            // Pour les navettes en cours, seuls certains champs sont modifiables
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

        // Mettre à jour les champs selon le statut
        if (!$isEnCours) {
            // Pour planifiée : tous les champs
            $updateData = $request->only([
                'wilaya_arrivee_id',
                'wilayas_transit',
                'heure_depart',
                'hub_id',
                'vehicule_immatriculation',
                'capacite_max',
                'prix_base',
                'prix_par_livraison',
                'notes',
                'status'
            ]);

            if ($request->has('date_depart') || $request->has('heure_depart')) {
                $dateDepart = Carbon::parse(
                    ($request->date_depart ?? $navette->date_depart->format('Y-m-d')) . ' ' .
                    ($request->heure_depart ?? $navette->heure_depart)
                );
                $updateData['date_depart'] = $dateDepart;

                $distance = $this->optimisationService->getDistance(
                    $navette->wilaya_depart_id,
                    $request->wilaya_arrivee_id ?? $navette->wilaya_arrivee_id
                );
                $dureeEstimee = $this->optimisationService->estimerDuree($distance);
                $dateArrivee = $dateDepart->copy()->addHours($dureeEstimee);
                $updateData['date_arrivee_prevue'] = $dateArrivee;
                $updateData['heure_arrivee'] = $dateArrivee->format('H:i');
                $updateData['distance_km'] = $distance;
            }
        } else {
            // Pour en_cours : seulement les champs autorisés
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

        // Mettre à jour les wilayas de transit (autorisé pour tous les statuts non terminés)
        if ($request->has('wilayas_transit')) {
            DB::table('navette_wilaya_transit')->where('navette_id', $navette->id)->delete();
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

        // Gérer les livraisons (autorisé pour tous les statuts non terminés)
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

            foreach ($toRemove as $livraisonId) {
                $navette->livraisons()->detach($livraisonId);

                Livraison::where('id', $livraisonId)->update([
                    'navette_id' => null,
                    'status' => 'en_attente'
                ]);
            }

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
        Log::error('Erreur ManagerNavetteController@update: ' . $e->getMessage());
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
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;
            $wilayaId = $gestionnaire->wilaya_id;

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

            if ($navette->wilaya_depart_id != $wilayaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer cette navette'
                ], 403);
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
                $navette->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Navette supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur ManagerNavetteController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le nom de la wilaya à partir du code
     */
    private function getWilayaName($code): string
    {
        $wilayas = [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat', '04' => 'Oum El Bouaghi',
            '05' => 'Batna', '06' => 'Béjaïa', '07' => 'Biskra', '08' => 'Béchar',
            '09' => 'Blida', '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou', '16' => 'Alger',
            '17' => 'Djelfa', '18' => 'Jijel', '19' => 'Sétif', '20' => 'Saïda',
            '21' => 'Skikda', '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem', '28' => 'M\'Sila',
            '29' => 'Mascara', '30' => 'Ouargla', '31' => 'Oran', '32' => 'El Bayadh',
            '33' => 'Illizi', '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès',
            '36' => 'El Tarf', '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza', '43' => 'Mila',
            '44' => 'Aïn Defla', '45' => 'Naâma', '46' => 'Aïn Témouchent', '47' => 'Ghardaïa',
            '48' => 'Relizane', '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal', '52' => 'Béni Abbès', '53' => 'In Salah',
            '54' => 'In Guezzam', '55' => 'Touggourt', '56' => 'Djanet',
            '57' => 'El M\'Ghair', '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? $code;
    }
}
