<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Gestionnaire;
use App\Models\GestionnaireGain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UsersExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Vérifier si l'utilisateur est admin
     */
    private function checkAdmin(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }
        return null;
    }

    /**
     * Vérifier si l'utilisateur est admin OU s'il s'agit de son propre profil
     */
    private function checkAuthorization(Request $request, $userId = null)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return null;
        }

        if ($userId && $user->id == $userId) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Vous devez être administrateur ou propriétaire du compte.',
        ], 403);
    }

    /**
     * Récupérer tous les utilisateurs du système (Admin seulement)
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $users = User::with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer un utilisateur spécifique par ID
     */
    public function show(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAuthorization($request, $id);
        if ($authCheck) return $authCheck;

        try {
            $user = User::with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour les positions latitude et longitude de l'utilisateur.
     */
    public function updatePosition(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        try {
            $user = $request->user();

            $user->update([
                'latitude' => $validatedData['latitude'],
                'longitude' => $validatedData['longitude'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Position mise à jour avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la position',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un utilisateur (soft delete)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        if ($user->id == $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 400);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès',
        ], 200);
    }

    /**
     * SUSPENDRE OU RÉACTIVER UN UTILISATEUR (Admin seulement)
     * Utilise le champ 'actif' : false = suspendu, true = actif
     */
    public function suspendre(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        // Ne pas permettre de suspendre son propre compte
        if ($user->id == $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas suspendre votre propre compte.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // actif = true → compte actif
            // actif = false → compte suspendu
            $isCurrentlyActive = $user->actif;
            $newStatus = !$isCurrentlyActive;

            $user->update([
                'actif' => $newStatus,
            ]);

            // Si c'est un livreur, mettre à jour son statut dans la table livreurs
            if ($user->role === 'livreur' && $user->livreur) {
                $user->livreur->update([
                    'desactiver' => !$newStatus,
                ]);
            }

            DB::commit();

            $message = !$newStatus
                ? 'Utilisateur suspendu avec succès.'
                : 'Utilisateur réactivé avec succès.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'user' => $user,
                    'is_suspended' => !$newStatus,
                    'is_active' => $newStatus,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur suspension utilisateur: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suspension/réactivation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * VÉRIFIER SI UN UTILISATEUR EST SUSPENDU
     */
    public function checkSuspended(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'is_suspended' => !$user->actif,
                'is_active' => $user->actif,
            ],
        ], 200);
    }

    /**
     * RÉCUPÉRER TOUS LES UTILISATEURS SUSPENDUS
     */
    public function getSuspendedUsers(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $users = User::where('actif', false)
                ->with(['client', 'livreur', 'gestionnaire'])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => $users->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs suspendus',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activer ou désactiver un utilisateur (Admin seulement)
     * Note: Cette méthode est similaire à suspendre mais gardée pour compatibilité
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        try {
            $user->update([
                'actif' => !$user->actif,
            ]);

            return response()->json([
                'success' => true,
                'message' => $user->actif ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer un nouvel utilisateur (Admin seulement)
     */
    public function store(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $validatedData = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,livreur,admin,gestionnaire',
            'wilaya_id' => 'nullable|string|max:10',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'nom' => $validatedData['nom'],
                'prenom' => $validatedData['prenom'],
                'email' => $validatedData['email'],
                'telephone' => $validatedData['telephone'],
                'password' => Hash::make($validatedData['password']),
                'role' => $validatedData['role'],
                'latitude' => $validatedData['latitude'] ?? null,
                'longitude' => $validatedData['longitude'] ?? null,
                'actif' => true,
            ]);

            if ($user->role == 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            } elseif ($user->role == 'livreur') {
                Livreur::create([
                    'user_id' => $user->id,
                    'type' => 'distributeur',
                    'desactiver' => false,
                ]);
            } elseif ($user->role == 'gestionnaire') {
                Gestionnaire::create([
                    'user_id' => $user->id,
                    'wilaya_id' => $validatedData['wilaya_id'] ?? '16',
                    'status' => 'active',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user->load(['client', 'livreur', 'gestionnaire']),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour les informations d'un utilisateur
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $authCheck = $this->checkAuthorization($request, $id);
        if ($authCheck) return $authCheck;

        $validationRules = [
            'nom' => 'nullable|string|max:255',
            'prenom' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'telephone' => 'nullable|string|unique:users,telephone,' . $id,
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];

        if ($user->role === 'admin') {
            $validationRules['role'] = 'nullable|in:client,livreur,admin,gestionnaire';
            $validationRules['actif'] = 'nullable|boolean';
            $validationRules['wilaya_id'] = 'nullable|string|max:10';
        }

        try {
            $validatedData = $request->validate($validationRules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $cleanData = array_filter($validatedData, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $oldRole = $targetUser->role;
            $newRole = $cleanData['role'] ?? $oldRole;

            $targetUser->update($cleanData);

            if ($newRole == 'gestionnaire') {
                if ($oldRole != 'gestionnaire') {
                    Client::where('user_id', $targetUser->id)->delete();
                    Livreur::where('user_id', $targetUser->id)->delete();

                    Gestionnaire::updateOrCreate(
                        ['user_id' => $targetUser->id],
                        [
                            'wilaya_id' => $validatedData['wilaya_id'] ?? '16',
                            'status' => 'active',
                        ]
                    );
                } else {
                    Gestionnaire::updateOrCreate(
                        ['user_id' => $targetUser->id],
                        ['wilaya_id' => $validatedData['wilaya_id'] ?? '16']
                    );
                }
            } elseif ($newRole == 'client' && $oldRole != 'client') {
                Livreur::where('user_id', $targetUser->id)->delete();
                Gestionnaire::where('user_id', $targetUser->id)->delete();

                Client::updateOrCreate(
                    ['user_id' => $targetUser->id],
                    ['status' => 'active']
                );
            } elseif ($newRole == 'livreur' && $oldRole != 'livreur') {
                Client::where('user_id', $targetUser->id)->delete();
                Gestionnaire::where('user_id', $targetUser->id)->delete();

                Livreur::updateOrCreate(
                    ['user_id' => $targetUser->id],
                    [
                        'type' => 'distributeur',
                        'desactiver' => false,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $targetUser->load(['client', 'livreur', 'gestionnaire']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des utilisateurs (Admin seulement)
     */
    public function stats(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $totalUsers = User::count();
            $totalClients = User::where('role', 'client')->count();
            $totalLivreurs = User::where('role', 'livreur')->count();
            $totalAdmins = User::where('role', 'admin')->count();
            $totalGestionnaires = User::where('role', 'gestionnaire')->count();
            $activeUsers = User::where('actif', true)->count();
            $inactiveUsers = User::where('actif', false)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_clients' => $totalClients,
                    'total_livreurs' => $totalLivreurs,
                    'total_admins' => $totalAdmins,
                    'total_gestionnaires' => $totalGestionnaires,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'suspended_users' => $inactiveUsers,
                    'percentages' => [
                        'clients' => $totalUsers > 0 ? round(($totalClients / $totalUsers) * 100, 2) : 0,
                        'livreurs' => $totalUsers > 0 ? round(($totalLivreurs / $totalUsers) * 100, 2) : 0,
                        'admins' => $totalUsers > 0 ? round(($totalAdmins / $totalUsers) * 100, 2) : 0,
                        'gestionnaires' => $totalUsers > 0 ? round(($totalGestionnaires / $totalUsers) * 100, 2) : 0,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Chercher des utilisateurs par terme (Admin seulement)
     */
    public function search(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $query = $request->query('q', '');
        $role = $request->query('role', null);
        $status = $request->query('status', null);

        try {
            $users = User::where(function ($q) use ($query) {
                $q->where('nom', 'like', "%$query%")
                    ->orWhere('prenom', 'like', "%$query%")
                    ->orWhere('email', 'like', "%$query%")
                    ->orWhere('telephone', 'like', "%$query%");
            })
            ->when($role, function ($q) use ($role) {
                return $q->where('role', $role);
            })
            ->when($status === 'suspended', function ($q) {
                return $q->where('actif', false);
            })
            ->when($status === 'active', function ($q) {
                return $q->where('actif', true);
            })
            ->when($status === 'inactive', function ($q) {
                return $q->where('actif', false);
            })
            ->with(['client', 'livreur.demandeAdhesion', 'gestionnaire'])
            ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => $users->count(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'un client
     */
    public function getClientStats(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $request->user()->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        try {
            $user = User::find($id);

            if (!$user || $user->role !== 'client') {
                return response()->json([
                    'success' => false,
                    'message' => 'Client introuvable',
                ], 404);
            }

            $stats = [
                'total_livraisons' => 0,
                'livraisons_en_cours' => 0,
                'livraisons_terminees' => 0,
                'montant_total' => 0,
                'derniere_livraison' => null,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques d'un livreur
     */
    public function getLivreurStats(Request $request, $id): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $request->user()->id != $id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé.',
            ], 403);
        }

        try {
            $user = User::find($id);

            if (!$user || $user->role !== 'livreur') {
                return response()->json([
                    'success' => false,
                    'message' => 'Livreur introuvable',
                ], 404);
            }

            $stats = [
                'total_livraisons' => 0,
                'livraisons_en_attente' => 0,
                'livraisons_en_cours' => 0,
                'livraisons_terminees' => 0,
                'note_moyenne' => 0,
                'revenu_total' => 0,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le statut d'un utilisateur
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'actif' => 'required|boolean',
        ]);

        try {
            $user->update([
                'actif' => $validatedData['actif'],
            ]);

            return response()->json([
                'success' => true,
                'message' => $validatedData['actif'] ? 'Utilisateur activé avec succès' : 'Utilisateur désactivé avec succès',
                'data' => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
 * Supprimer définitivement un utilisateur de la base de données (Admin seulement)
 */
public function deleteUser(Request $request, $id): JsonResponse
{
    $authCheck = $this->checkAdmin($request);
    if ($authCheck) return $authCheck;

    try {
        DB::beginTransaction();

        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        if ($user->id == $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 400);
        }

        Log::info("=== SUPPRESSION DÉFINITIVE UTILISATEUR ===");
        Log::info("ID: " . $user->id);
        Log::info("Email: " . $user->email);
        Log::info("Rôle: " . $user->role);

        $userId = $user->id;

        // 1. Supprimer les demandes d'adhésion
        DB::table('demande_adhesions')->where('user_id', $userId)->delete();
        Log::info("Demandes d'adhésion supprimées");

        // 2. Supprimer les tokens
        DB::table('personal_access_tokens')->where('tokenable_id', $userId)->delete();
        Log::info("Tokens supprimés");

        // 3. Supprimer le client
        DB::table('clients')->where('user_id', $userId)->delete();
        Log::info("Client supprimé");

        // 4. Supprimer le livreur et ses assignations
        $livreur = DB::table('livreurs')->where('user_id', $userId)->first();
        if ($livreur) {
            DB::table('livreur_assignations')->where('livreur_id', $livreur->id)->delete();
            DB::table('livreurs')->where('user_id', $userId)->delete();
            Log::info("Livreur supprimé, ID: " . $livreur->id);
        }

        // 5. Supprimer le gestionnaire, ses gains et assignations
        $gestionnaire = DB::table('gestionnaires')->where('user_id', $userId)->first();
        if ($gestionnaire) {
            DB::table('gestionnaire_gains')->where('gestionnaire_id', $gestionnaire->id)->delete();
            DB::table('livreur_assignations')->where('gestionnaire_id', $gestionnaire->id)->delete();
            DB::table('gestionnaires')->where('user_id', $userId)->delete();
            Log::info("Gestionnaire supprimé, ID: " . $gestionnaire->id);
        }

        // 6. Supprimer la photo (optionnel, en fonction de votre structure)
        if ($user->photo) {
            try {
                $photoPath = str_replace('/storage/', '', $user->photo);
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($photoPath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
                    Log::info("Photo supprimée: " . $photoPath);
                }
            } catch (\Exception $e) {
                Log::warning("Erreur suppression photo: " . $e->getMessage());
            }
        }

        // 7. Supprimer l'utilisateur
        $user->forceDelete();
        Log::info("Utilisateur supprimé définitivement");

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé définitivement avec succès.',
        ], 200);

    } catch (\Illuminate\Database\QueryException $e) {
        DB::rollBack();
        Log::error("Erreur SQL deleteUser: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Erreur de base de données: ' . $e->getMessage(),
        ], 500);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Erreur deleteUser: " . $e->getMessage());
        Log::error("Fichier: " . $e->getFile() . ":" . $e->getLine());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la suppression: ' . $e->getMessage(),
        ], 500);
    }
}

    /**
     * Exporter les utilisateurs en Excel, CSV ou PDF
     */
    public function exportExcel(Request $request)
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $search = $request->query('search', '');
            $role = $request->query('role', '');
            $format = $request->query('format', 'xlsx');

            \Log::info('Export demandé avec format:', [
                'format' => $format,
                'search' => $search,
                'role' => $role
            ]);

            if ($format === 'pdf') {
                return $this->exportPDF($request);
            }

            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $format;
            $export = new UsersExport($search, $role);

            if ($format === 'csv') {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }

            return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            \Log::error('Erreur exportExcel: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les paramètres pour un export asynchrone
     */
    public function exportUsers(Request $request): JsonResponse
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'role' => 'nullable|in:client,livreur,admin,gestionnaire',
                'status' => 'nullable|in:active,inactive',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'columns' => 'nullable|array',
                'columns.*' => 'in:id,nom,prenom,email,telephone,role,actif,created_at,updated_at',
                'format' => 'nullable|in:xlsx,csv,pdf',
            ]);

            $params = [
                'search' => $validated['search'] ?? '',
                'role' => $validated['role'] ?? '',
                'status' => $validated['status'] ?? '',
                'start_date' => $validated['start_date'] ?? '',
                'end_date' => $validated['end_date'] ?? '',
                'columns' => $validated['columns'] ?? [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'telephone',
                    'role',
                    'actif',
                    'created_at'
                ],
                'format' => $validated['format'] ?? 'xlsx',
            ];

            $exportToken = 'export_' . md5(serialize($params) . time());
            cache()->put($exportToken, $params, 3600);

            return response()->json([
                'success' => true,
                'message' => 'Export programmé avec succès',
                'data' => [
                    'export_token' => $exportToken,
                    'download_url' => url("/api/admin/users/export/download/{$exportToken}"),
                ]
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erreur exportUsers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la préparation de l\'export',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Télécharger un export précédemment généré
     */
    public function downloadExport($token)
    {
        try {
            $params = cache()->get($token);

            if (!$params) {
                return response()->json([
                    'success' => false,
                    'message' => 'Export non trouvé ou expiré',
                ], 404);
            }

            \Log::info('Téléchargement export avec token:', ['token' => $token]);

            cache()->forget($token);

            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $params['format'];
            $export = new UsersExport(
                $params['search'],
                $params['role'],
                $params['status'],
                $params['start_date'],
                $params['end_date'],
                $params['columns']
            );

            if ($params['format'] === 'csv') {
                return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV);
            }

            return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX);
        } catch (\Exception $e) {
            \Log::error('Erreur downloadExport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du téléchargement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les utilisateurs en PDF
     */
    public function exportPDF(Request $request)
    {
        $authCheck = $this->checkAdmin($request);
        if ($authCheck) return $authCheck;

        try {
            $search = $request->query('search', '');
            $role = $request->query('role', '');

            $columns = $request->query('columns', []);
            if (empty($columns)) {
                $columns = ['id', 'nom', 'prenom', 'email', 'telephone', 'role', 'actif', 'created_at'];
            } elseif (is_string($columns)) {
                $columns = explode(',', $columns);
            }

            $query = User::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('telephone', 'like', "%{$search}%");
                    });
                })
                ->when($role, function ($q) use ($role) {
                    $q->where('role', $role);
                })
                ->orderBy('created_at', 'desc');

            $users = $query->get();

            $stats = [
                'total_users' => $users->count(),
                'active_users' => $users->where('actif', true)->count(),
                'inactive_users' => $users->where('actif', false)->count(),
                'suspended_users' => $users->where('actif', false)->count(),
                'total_clients' => $users->where('role', 'client')->count(),
                'total_livreurs' => $users->where('role', 'livreur')->count(),
                'total_admins' => $users->where('role', 'admin')->count(),
                'total_gestionnaires' => $users->where('role', 'gestionnaire')->count(),
            ];

            $data = [
                'users' => $users,
                'stats' => $stats,
                'filters' => ['search' => $search, 'role' => $role],
                'columns' => $columns,
                'date' => Carbon::now()->format('d/m/Y H:i'),
            ];

            $pdf = Pdf::loadView('pdf.users', $data);
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

            $filename = 'users-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.pdf';
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('Erreur exportPDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}