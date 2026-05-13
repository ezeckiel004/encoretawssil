<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class UserController extends Controller
{
    public function parse_api_response($data, $message, $error, $code = 200)
    {
        return response()->json([
            'message' => $message,
            'error'   => $error,
            'data'    => $data,
        ], $code);
    }
    
    public function index(Request $request): JsonResponse
{
    return $this->getAllUsers($request);
}

    /**
     * Récupérer tous les utilisateurs du système.
     */
    public function getAllUsers(Request $request): JsonResponse
    {
         if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }
        try {
            // Récupérer tous les utilisateurs avec leurs relations
            $users = User::with(['client', 'livreur.demandeAdhesion'])->get();

            return response()->json($users, 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
 * Vérifier si l'utilisateur est admin OU s'il s'agit de son propre profil
 */
private function checkAuthorization(Request $request, $userId = null)
{
    $user = $request->user();

    // Si l'utilisateur est admin, tout est autorisé
    if ($user->role === 'admin') {
        return null;
    }

    // Si l'utilisateur essaie d'accéder à son propre profil
    if ($userId && $user->id == $userId) {
        return null;
    }

    // Sinon, accès refusé
    return response()->json([
        'success' => false,
        'message' => 'Accès non autorisé. Vous devez être administrateur ou propriétaire du compte.',
    ], 403);
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
            $user = $request->user(); // Récupérer l'utilisateur authentifié

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

    public function destroy(Request $request, $id): JsonResponse
{
    // Vérifier si admin
    if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }

    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur introuvable',
        ], 404);
    }

    try {
        DB::beginTransaction();

        $userId = $user->id;
        Log::info("🗑️ Suppression de l'utilisateur: $userId");

        // Supprimer les demandes d'adhésion
        DB::table('demande_adhesions')->where('user_id', $userId)->delete();
        Log::info("✅ Demandes d'adhésion supprimées");

        // Supprimer le Client s'il existe
        Client::where('user_id', $userId)->delete();
        Log::info("✅ Client supprimé");

        // Supprimer le Livreur s'il existe
        Livreur::where('user_id', $userId)->delete();
        Log::info("✅ Livreur supprimé");

        // Supprimer les tokens d'authentification
        $user->tokens()->delete();
        Log::info("✅ Tokens supprimés");

        // Supprimer la photo s'elle existe
        if ($user->photo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            Log::info("✅ Photo supprimée");
        }

        // Supprimer l'utilisateur - Forcer la suppression complète même avec SoftDeletes
        $user->forceDelete();
        Log::info("✅ Utilisateur supprimé définitivement");

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé définitivement avec succès',
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("❌ Erreur suppression: " . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la suppression de l\'utilisateur',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Activer ou désactiver un utilisateur
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable',
            ], 404);
        }

        try {
            // Inverser le statut actif
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
    
    public function show(Request $request, $id): JsonResponse
    {
        // Vérifier l'autorisation
        $authCheck = $this->checkAuthorization($request, $id);
        if ($authCheck) {
            return $authCheck;
        }

        try {
            $user = User::with(['client', 'livreur.demandeAdhesion'])->find($id);

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
     * Créer un nouvel utilisateur (Admin)
     */
    public function store(Request $request): JsonResponse
    {
        
          if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }
        $validatedData = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telephone' => 'required|string|unique:users,telephone',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,livreur,admin',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            // Créer l'utilisateur
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

            // Créer le record correspondant selon le rôle
            if ($user->role == 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            } elseif ($user->role == 'livreur') {
                Livreur::create([
                    'user_id' => $user->id,
                    'type' => 'distributeur',
                    'status' => 'active',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Utilisateur créé avec succès',
                'data' => $user->load(['client', 'livreur']),
            ], 201);
        } catch (\Exception $e) {
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

    // Vérifier l'autorisation
    $authCheck = $this->checkAuthorization($request, $id);
    if ($authCheck) {
        return $authCheck;
    }

    // Définir les règles de validation selon le rôle
    $validationRules = [
        'nom' => 'nullable|string|max:255',
        'prenom' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:users,email,' . $id,
        'telephone' => 'nullable|string|unique:users,telephone,' . $id,
        'latitude' => 'nullable|numeric|between:-90,90',
        'longitude' => 'nullable|numeric|between:-180,180',
    ];

    // Seul l'admin peut modifier le rôle et le statut
    if ($user->role === 'admin') {
        $validationRules['role'] = 'nullable|in:client,livreur,admin';
        $validationRules['actif'] = 'nullable|boolean';
    }

    $validatedData = $request->validate($validationRules);

    try {
        // Nettoyer les données (supprimer les valeurs nulles)
        $cleanData = array_filter($validatedData, function ($value) {
            return !is_null($value);
        });

        $targetUser->update($cleanData);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $targetUser->load(['client', 'livreur']),
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * Obtenir les statistiques des utilisateurs
     */
    public function stats(): JsonResponse
    {
        \Log::info('Appel de users/stats par user: ' . $request->user()?->id);
    if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }
        try {
            $totalUsers = User::count();
            $totalClients = User::where('role', 'client')->count();
            $totalLivreurs = User::where('role', 'livreur')->count();
            $totalAdmins = User::where('role', 'admin')->count();
            $activeUsers = User::where('actif', 1)->count();
            $inactiveUsers = User::where('actif', 0)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => $totalUsers,
                    'total_clients' => $totalClients,
                    'total_livreurs' => $totalLivreurs,
                    'total_admins' => $totalAdmins,
                    'active_users' => $activeUsers,
                    'inactive_users' => $inactiveUsers,
                    'percentages' => [
                        'clients' => $totalUsers > 0 ? round(($totalClients / $totalUsers) * 100, 2) : 0,
                        'livreurs' => $totalUsers > 0 ? round(($totalLivreurs / $totalUsers) * 100, 2) : 0,
                        'admins' => $totalUsers > 0 ? round(($totalAdmins / $totalUsers) * 100, 2) : 0,
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
     * Chercher des utilisateurs par terme
     */
    public function search(Request $request): JsonResponse
    {
         if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }
        $query = $request->query('q', '');
        $role = $request->query('role', null);

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
            ->with(['client', 'livreur.demandeAdhesion'])
            ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
                'count' => count($users),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function getClientStats(Request $request, $id): JsonResponse
{
    // Vérifier si admin ou propriétaire
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

        // Simuler des statistiques (remplacez par votre logique réelle)
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

public function getLivreurStats(Request $request, $id): JsonResponse
{
    // Vérifier si admin ou propriétaire
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

        // Simuler des statistiques (remplacez par votre logique réelle)
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

public function updateStatus(Request $request, $id): JsonResponse
{
    // Vérifier si admin
    if ($request->user()->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé. Admin requis.',
        ], 403);
    }

    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur introuvable',
        ], 404);
    }

    $validatedData = $request->validate([
        'desactiver' => 'required|boolean',
    ]);

    try {
        // Mettre à jour le champ desactiver (inverser la logique actif/desactiver)
        $user->update([
            'desactiver' => $validatedData['desactiver'],
            // Si vous voulez aussi mettre à jour 'actif' pour compatibilité
            'actif' => !$validatedData['desactiver'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $validatedData['desactiver'] ? 'Utilisateur désactivé avec succès' : 'Utilisateur activé avec succès',
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
}