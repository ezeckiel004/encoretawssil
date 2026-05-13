<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Livreur;
use App\Models\Gestionnaire;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LivreurController extends Controller
{
    /**
     * Middleware pour vérifier la wilaya du gestionnaire
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $request->merge([
                'gestionnaire_wilaya' => $gestionnaire->wilaya_id,
                'gestionnaire' => $gestionnaire
            ]);

            return $next($request);
        });
    }

    /**
     * Table de correspondance code wilaya -> nom
     */
    private function getWilayaNameFromCode($code): ?string
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

    /**
     * Lister les livreurs de la wilaya (natifs + assignés)
     */
    public function index(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);
        $gestionnaire = $request->get('gestionnaire');

        try {
            // 1. Livreurs natifs de la wilaya
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    $livreur->assignation = null;
                    $livreur->is_active = $livreur->user->actif;
                    return $livreur;
                });

            // 2. Livreurs assignés à ce gestionnaire
            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) use ($gestionnaire) {
                            $livreur->origine = 'assigne';
                            $livreur->is_active = $livreur->user->actif;

                            $assignation = $gestionnaire->livreurAssignations()
                                ->where('livreur_id', $livreur->id)
                                ->where('status', 'active')
                                ->first();

                            $livreur->assignation = $assignation ? [
                                'id' => $assignation->id,
                                'date_debut' => $assignation->date_debut,
                                'date_fin' => $assignation->date_fin,
                                'motif' => $assignation->motif,
                                'wilaya_cible' => $assignation->wilaya_cible
                            ] : null;

                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur récupération livreurs assignés: ' . $e->getMessage());
                }
            }

            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            $totalLivreurs = $allLivreurs->count();
            $actifs = $allLivreurs->where('is_active', true)->count();
            $inactifs = $allLivreurs->where('is_active', false)->count();
            $distributeurs = $allLivreurs->where('type', 'distributeur')->count();
            $ramasseurs = $allLivreurs->where('type', 'ramasseur')->count();
            $natifs = $livreursNatifs->count();
            $assignes = $livreursAssignes->count();

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'stats' => [
                    'total' => $totalLivreurs,
                    'actifs' => $actifs,
                    'inactifs' => $inactifs,
                    'distributeurs' => $distributeurs,
                    'ramasseurs' => $ramasseurs,
                    'natifs' => $natifs,
                    'assignes' => $assignes,
                    'wilaya' => [
                        'code' => $wilayaCode,
                        'nom' => $wilayaNom
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur index livreurs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Voir un livreur spécifique
     */
    public function show(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        try {
            $livreur = null;
            $origine = null;
            $assignation = null;

            $livreur = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->find($id);

            if ($livreur) {
                $origine = 'natif';
            }

            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->find($id);

                    if ($livreur) {
                        $origine = 'assigne';

                        $assignationData = $gestionnaire->livreurAssignations()
                            ->where('livreur_id', $livreur->id)
                            ->where('status', 'active')
                            ->first();

                        if ($assignationData) {
                            $assignation = [
                                'id' => $assignationData->id,
                                'date_debut' => $assignationData->date_debut,
                                'date_fin' => $assignationData->date_fin,
                                'motif' => $assignationData->motif,
                                'wilaya_cible' => $assignationData->wilaya_cible
                            ];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            $livreur->origine = $origine;
            $livreur->assignation = $assignation;
            $livreur->is_active = $livreur->user->actif;

            $stats = [
                'livraisons_total' => $livreur->livraisonsDistribution()->count() +
                                      $livreur->livraisonsRamassage()->count(),
                'livraisons_en_cours' => $livreur->livraisonsDistribution()
                                        ->whereNotIn('status', ['livre', 'annule'])
                                        ->count() +
                                        $livreur->livraisonsRamassage()
                                        ->whereNotIn('status', ['livre', 'annule'])
                                        ->count(),
                'livraisons_terminees' => $livreur->livraisonsDistribution()
                                         ->where('status', 'livre')
                                         ->count() +
                                         $livreur->livraisonsRamassage()
                                         ->where('status', 'livre')
                                         ->count(),
                'taux_reussite' => $this->calculateTauxReussite($livreur),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'livreur' => $livreur,
                    'stats' => $stats,
                    'assignation' => $assignation
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur show livreur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du livreur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un livreur (gestionnaire uniquement)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        $validator = Validator::make($request->all(), [
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telephone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type' => 'nullable|string|in:distributeur,ramasseur',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $livreur = null;

            $livreur = Livreur::with('user')->where('wilaya_id', $wilayaCode)->find($id);

            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()->with('user')->find($id);
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné pour update: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            if (!$livreur->user->actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce livreur a été suspendu par l\'administrateur. Vous ne pouvez pas le modifier.',
                    'admin_suspended' => true
                ], 403);
            }

            $user = $livreur->user;
            $userData = [];
            $livreurData = [];

            if ($request->filled('nom')) {
                $userData['nom'] = $request->nom;
            }
            if ($request->filled('prenom')) {
                $userData['prenom'] = $request->prenom;
            }
            if ($request->filled('email')) {
                $existingUser = User::where('email', $request->email)
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cet email est déjà utilisé par un autre utilisateur.'
                    ], 422);
                }
                $userData['email'] = $request->email;
            }
            if ($request->filled('telephone')) {
                $existingUser = User::where('telephone', $request->telephone)
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($existingUser) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ce numéro de téléphone est déjà utilisé par un autre utilisateur.'
                    ], 422);
                }
                $userData['telephone'] = $request->telephone;
            }

            if ($request->hasFile('photo')) {
                $utilsController = new \App\Http\Controllers\UtilsController();
                $photoPath = $utilsController->uploadPhoto($request, 'photo');
                if ($photoPath) {
                    if ($user->photo) {
                        Storage::disk('public')->delete($user->photo);
                    }
                    $userData['photo'] = $photoPath;
                }
            }

            if ($request->has('type')) {
                $livreurData['type'] = $request->type;
            }

            if (!empty($userData)) {
                $user->update($userData);
            }

            if (!empty($livreurData)) {
                $livreur->update($livreurData);
            }

            DB::commit();

            Log::info('Gestionnaire a modifié un livreur', [
                'gestionnaire_id' => $gestionnaire->id,
                'livreur_id' => $livreur->id,
                'modifications_user' => array_keys($userData),
                'modifications_livreur' => array_keys($livreurData)
            ]);

            $livreur->refresh();
            $livreur->load(['user', 'demandeAdhesion']);

            return response()->json([
                'success' => true,
                'message' => 'Livreur modifié avec succès',
                'data' => $livreur
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur update livreur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification du livreur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer/désactiver un livreur (suspension par le gestionnaire)
     * Le gestionnaire modifie le champ 'actif' du modèle User
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        try {
            $livreur = null;

            $livreur = Livreur::with('user')->where('wilaya_id', $wilayaCode)->find($id);

            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()->with('user')->find($id);
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné pour toggle: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            // Vérification si déjà suspendu par l'admin ? Non, car on utilise le même champ
            // Le gestionnaire peut modifier n'importe quel livreur de sa wilaya

            $newStatus = !$livreur->user->actif;
            $livreur->user->update([
                'actif' => $newStatus
            ]);

            $message = $newStatus ? 'Livreur activé avec succès' : 'Livreur désactivé avec succès';

            Log::info('Gestionnaire a modifié le statut d\'un livreur', [
                'gestionnaire_id' => $gestionnaire->id,
                'gestionnaire_wilaya' => $wilayaCode,
                'livreur_id' => $livreur->id,
                'livreur_nom' => $livreur->user->nom . ' ' . $livreur->user->prenom,
                'nouveau_statut' => $newStatus ? 'actif' : 'desactive'
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $livreur->id,
                    'actif' => $newStatus
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur toggle activation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechercher des livreurs
     */
    public function search(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');
        $term = $request->query('q', '');

        try {
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->whereHas('user', function ($query) use ($term) {
                    $query->where('nom', 'like', "%{$term}%")
                          ->orWhere('prenom', 'like', "%{$term}%")
                          ->orWhere('email', 'like', "%{$term}%")
                          ->orWhere('telephone', 'like', "%{$term}%");
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    $livreur->is_active = $livreur->user->actif;
                    return $livreur;
                });

            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->whereHas('user', function ($query) use ($term) {
                            $query->where('nom', 'like', "%{$term}%")
                                  ->orWhere('prenom', 'like', "%{$term}%")
                                  ->orWhere('email', 'like', "%{$term}%")
                                  ->orWhere('telephone', 'like', "%{$term}%");
                        })
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) {
                            $livreur->origine = 'assigne';
                            $livreur->is_active = $livreur->user->actif;
                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreurs assignés: ' . $e->getMessage());
                }
            }

            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'count' => $allLivreurs->count(),
                'stats' => [
                    'natifs' => $livreursNatifs->count(),
                    'assignes' => $livreursAssignes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur recherche livreurs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs par type
     */
    public function byType(Request $request, $type): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        if (!in_array($type, ['distributeur', 'ramasseur'])) {
            return response()->json([
                'success' => false,
                'message' => 'Type invalide. Utilisez "distributeur" ou "ramasseur"'
            ], 422);
        }

        try {
            $livreursNatifs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->where('type', $type)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    $livreur->is_active = $livreur->user->actif;
                    return $livreur;
                });

            $livreursAssignes = collect();

            if ($gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreursAssignes = $gestionnaire->livreursAssignes()
                        ->with(['user', 'demandeAdhesion'])
                        ->where('type', $type)
                        ->orderBy('livreur_assignations.created_at', 'desc')
                        ->get()
                        ->map(function($livreur) {
                            $livreur->origine = 'assigne';
                            $livreur->is_active = $livreur->user->actif;
                            return $livreur;
                        });
                } catch (\Exception $e) {
                    Log::warning('Erreur récupération livreurs assignés par type: ' . $e->getMessage());
                }
            }

            $allLivreurs = $livreursNatifs->merge($livreursAssignes)->unique('id');

            return response()->json([
                'success' => true,
                'data' => $allLivreurs->values(),
                'count' => $allLivreurs->count(),
                'stats' => [
                    'natifs' => $livreursNatifs->count(),
                    'assignes' => $livreursAssignes->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur récupération livreurs par type: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs natifs uniquement
     */
    public function getNatifs(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        try {
            $livreurs = Livreur::with(['user', 'demandeAdhesion'])
                ->where('wilaya_id', $wilayaCode)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($livreur) {
                    $livreur->origine = 'natif';
                    $livreur->is_active = $livreur->user->actif;
                    return $livreur;
                });

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count(),
                'wilaya' => [
                    'code' => $wilayaCode,
                    'nom' => $wilayaNom
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur getNatifs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs natifs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les livreurs assignés uniquement
     */
    public function getAssignes(Request $request): JsonResponse
    {
        $gestionnaire = $request->get('gestionnaire');
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $wilayaNom = $this->getWilayaNameFromCode($wilayaCode);

        if (!$gestionnaire || !method_exists($gestionnaire, 'livreursAssignes')) {
            return response()->json([
                'success' => true,
                'data' => [],
                'count' => 0,
                'message' => 'Aucun livreur assigné'
            ], 200);
        }

        try {
            $livreurs = $gestionnaire->livreursAssignes()
                ->with(['user', 'demandeAdhesion'])
                ->orderBy('livreur_assignations.created_at', 'desc')
                ->get()
                ->map(function($livreur) use ($gestionnaire) {
                    $livreur->origine = 'assigne';
                    $livreur->is_active = $livreur->user->actif;

                    $assignation = $gestionnaire->livreurAssignations()
                        ->where('livreur_id', $livreur->id)
                        ->where('status', 'active')
                        ->first();

                    $livreur->assignation = $assignation ? [
                        'id' => $assignation->id,
                        'date_debut' => $assignation->date_debut,
                        'date_fin' => $assignation->date_fin,
                        'motif' => $assignation->motif
                    ] : null;

                    return $livreur;
                });

            return response()->json([
                'success' => true,
                'data' => $livreurs,
                'count' => $livreurs->count(),
                'wilaya' => [
                    'code' => $wilayaCode,
                    'nom' => $wilayaNom
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur getAssignes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livreurs assignés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer le mot de passe d'un livreur
     */
    public function changePassword(Request $request, $id): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $livreur = null;

            $livreur = Livreur::with('user')->where('wilaya_id', $wilayaCode)->find($id);

            if (!$livreur && $gestionnaire && method_exists($gestionnaire, 'livreursAssignes')) {
                try {
                    $livreur = $gestionnaire->livreursAssignes()->with('user')->find($id);
                } catch (\Exception $e) {
                    Log::warning('Erreur recherche livreur assigné pour change password: ' . $e->getMessage());
                }
            }

            if (!$livreur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable dans votre wilaya'
                ], 404);
            }

            $livreur->user->update([
                'password' => Hash::make($request->password)
            ]);

            $livreur->user->tokens()->delete();

            Log::info('Gestionnaire a changé le mot de passe d\'un livreur', [
                'gestionnaire_id' => $gestionnaire->id,
                'livreur_id' => $livreur->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès. Le livreur devra se reconnecter.'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur change password livreur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau livreur
     */
    public function store(Request $request): JsonResponse
    {
        $wilayaCode = $request->get('gestionnaire_wilaya');
        $gestionnaire = $request->get('gestionnaire');

        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'telephone' => 'required|string|max:20|unique:users,telephone',
            'password' => 'required|string|min:8|confirmed',
            'type' => 'required|string|in:distributeur,ramasseur',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $utilsController = new \App\Http\Controllers\UtilsController();
                $photoPath = $utilsController->uploadPhoto($request, 'photo');
            }

            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'telephone' => $request->telephone,
                'role' => 'livreur',
                'photo' => $photoPath,
                'actif' => true,
            ]);

            $livreur = Livreur::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'wilaya_id' => $wilayaCode,
                'demande_adhesions_id' => null,
            ]);

            DB::commit();

            Log::info('Gestionnaire a créé un nouveau livreur', [
                'gestionnaire_id' => $gestionnaire->id,
                'livreur_id' => $livreur->id
            ]);

            $livreur->load(['user', 'demandeAdhesion']);

            return response()->json([
                'success' => true,
                'message' => 'Livreur créé avec succès',
                'data' => $livreur
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création livreur: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du livreur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculer le taux de réussite d'un livreur
     */
    private function calculateTauxReussite(Livreur $livreur): float
    {
        $total = $livreur->livraisonsDistribution()->count() +
                 $livreur->livraisonsRamassage()->count();

        if ($total === 0) {
            return 0;
        }

        $terminees = $livreur->livraisonsDistribution()
                      ->where('status', 'livre')
                      ->count() +
                      $livreur->livraisonsRamassage()
                      ->where('status', 'livre')
                      ->count();

        return round(($terminees / $total) * 100, 2);
    }

    // PAS de méthode destroy() - Le gestionnaire n'a pas le droit de suppression
}