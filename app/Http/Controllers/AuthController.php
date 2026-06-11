<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\NotificationToken;
use App\Enums\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\PasswordResetToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ResetPasswordMail;
use App\Models\Gestionnaire;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom'       => 'required|string|max:255',
            'prenom'    => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:8',
            'telephone' => 'required|string|max:20|unique:users',
            'role'      => 'string|in:client,livreur,admin,gestionnaire',
            'latitude'  => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $utilsController = new UtilsController();
        $photoPath = $utilsController->uploadPhoto($request, 'photo');

        try {
            DB::beginTransaction();

            $user = User::create([
                'nom'       => $request->nom,
                'prenom'    => $request->prenom,
                'email'     => $request->email,
                'password'  => Hash::make($request->password),
                'telephone' => $request->telephone,
                'role'      => $request->role ?? 'client',
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
                'photo'     => $photoPath,
                'photo_url' => $photoPath ? asset('storage/' . $photoPath) : null,
                'actif'     => true,
            ]);

            if ($user->role == 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'status'  => 'active',
                ]);
            } elseif ($user->role == 'livreur') {
                Livreur::create([
                    'user_id' => $user->id,
                    'demande_adhesions_id' => null,
                    'type' => 'distributeur',
                    'wilaya_id' => $request->wilaya_id ?? '16',
                ]);
            } elseif ($user->role == 'gestionnaire') {
                Gestionnaire::create([
                    'user_id' => $user->id,
                    'wilaya_id' => $request->wilaya_id ?? '16',
                    'status' => 'active',
                ]);
            }

            $deviceName = $request->header('User-Agent', 'unknown_device');
            $token = $user->createToken($deviceName)->plainTextToken;

            DB::commit();

            $user->load('client', 'livreur', 'gestionnaire');

            return response()->json([
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur - Permet les connexions multiples
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'nullable|email|required_without:telephone',
            'telephone' => 'nullable|string|required_without:email',
            'password'  => 'required|string',
            'device_name' => 'nullable|string|max:255', // Optionnel: nom personnalisé pour l'appareil
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::where(function ($query) use ($request) {
                if ($request->filled('email')) {
                    $query->where('email', $request->email);
                }
                if ($request->filled('telephone')) {
                    $query->orWhere('telephone', $request->telephone);
                }
            })->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects',
                ], 401);
            }

            // Vérification du compte suspendu
            if (! $user->actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte a été suspendu. Veuillez contacter votre administrateur.',
                    'suspended' => true,
                ], 403);
            }

            // NE PAS supprimer les tokens existants pour permettre les connexions multiples
            // $user->tokens()->delete(); // ← Cette ligne est commentée/supprimée

            // Créer un nouveau token pour ce nouvel appareil
            $deviceName = $request->device_name ?? $request->header('User-Agent', 'unknown_device');
            $token = $user->createToken($deviceName)->plainTextToken;

            $user->load('client', 'livreur', 'gestionnaire');

            return response()->json([
                'user'       => $user,
                'token'      => $token,
                'token_type' => 'Bearer',
                'message'    => 'Connexion réussie'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier si l'utilisateur connecté est suspendu
     */
    public function checkSuspended(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié',
                    'suspended' => false,
                ], 401);
            }

            return response()->json([
                'success' => true,
                'suspended' => !$user->actif,
                'actif' => $user->actif,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur checkSuspended: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
            ], 500);
        }
    }

    /**
     * Récupérer les informations de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            // Vérifier si l'utilisateur est suspendu
            if (! $user->actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte suspendu',
                    'suspended' => true,
                ], 403);
            }

            if ($user->role === 'gestionnaire') {
                $user->load('gestionnaire');
            } elseif ($user->role === 'client') {
                $user->load('client');
            } elseif ($user->role === 'livreur') {
                $user->load('livreur');
            }

            return response()->json([
                'success' => true,
                'user' => $user
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur me(): ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'utilisateur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion d'un utilisateur (uniquement l'appareil actuel)
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Supprimer UNIQUEMENT le token actuel, pas tous
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion de tous les appareils réussie',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lister tous les appareils connectés
     */
    public function listDevices(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentTokenId = $user->currentAccessToken()->id;

            $tokens = $user->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => $tokens->map(function ($token) use ($currentTokenId) {
                    return [
                        'id' => $token->id,
                        'device_name' => $token->name ?? 'Appareil inconnu',
                        'is_current' => $token->id === $currentTokenId,
                        'last_used_at' => $token->last_used_at,
                        'connected_since' => $token->created_at,
                    ];
                }),
                'total_devices' => $tokens->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur listDevices: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des appareils',
            ], 500);
        }
    }

    /**
     * Déconnecter un appareil spécifique (par son token ID)
     */
    public function revokeDevice(Request $request, $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            $token = $user->tokens()->where('id', $tokenId)->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appareil non trouvé',
                ], 404);
            }

            // Ne pas permettre de supprimer l'appareil actuel
            if ($token->id === $user->currentAccessToken()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas déconnecter l\'appareil actuel. Utilisez la déconnexion classique.',
                ], 400);
            }

            $token->delete();

            return response()->json([
                'success' => true,
                'message' => 'Appareil déconnecté avec succès',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur revokeDevice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion de l\'appareil',
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil utilisateur
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier si l'utilisateur est suspendu
        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de modifier le profil',
            ], 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'nom' => 'sometimes|required|string|max:255',
            'prenom' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'telephone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'telephone')->ignore($user->id),
            ],
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user->update(array_filter($input, fn($value) => !is_null($value) && $value !== ''));

            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour sa photo de profil utilisateur
     */
    public function updatePhotoProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier si l'utilisateur est suspendu
        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de modifier la photo',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if ($user->photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            }

            $utilsController = new UtilsController();
            $photoPath = $utilsController->uploadPhoto($request, 'photo');

            $user->update([
                'photo' => $photoPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo de Profil mise à jour avec succès',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la Photo de profil',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifier si l'utilisateur est suspendu
        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de changer le mot de passe',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect',
                ], 401);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Révoquer tous les tokens pour forcer la reconnexion sur tous les appareils
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe changé avec succès. Veuillez vous reconnecter sur tous vos appareils.',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Réinitialisation du mot de passe (envoi email)
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $token = Str::random(60);

            PasswordResetToken::where('email', $request->email)->delete();

            PasswordResetToken::create([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now(),
            ]);

            try {
                Mail::to($request->email)->send(new ResetPasswordMail($request->email, $token));
                Log::info('Password reset email sent to: ' . $request->email);
            } catch (\Exception $mailError) {
                Log::error('Erreur envoi email:', ['error' => $mailError->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Email de réinitialisation envoyé. Veuillez vérifier votre boîte de réception.',
                'expires_in' => 1800,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur forgotPassword:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du token',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier si le token de réinitialisation est valide
     */
    public function verifyResetToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string|min:60|max:60',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou token invalide',
            ], 422);
        }

        try {
            $resetToken = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->token)
                ->first();

            if (!$resetToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token ou email invalide',
                ], 404);
            }

            if (!$resetToken->isValid()) {
                $resetToken->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien de réinitialisation a expiré (durée: 30 minutes)',
                ], 410);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'email' => $request->email,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur verifyResetToken:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du token',
            ], 500);
        }
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'                 => 'required|email|exists:users,email',
            'token'                 => 'required|string|min:60|max:60',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $resetToken = PasswordResetToken::where('email', $request->email)
                ->where('token', $request->token)
                ->first();

            if (!$resetToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token ou email invalide',
                ], 404);
            }

            if (!$resetToken->isValid()) {
                $resetToken->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Ce lien de réinitialisation a expiré',
                ], 410);
            }

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                ], 404);
            }

            $user->update([
                'password' => Hash::make($request->password),
            ]);

            // Révoquer tous les tokens
            $user->tokens()->delete();
            $resetToken->delete();

            Log::info('Password reset successfully for: ' . $request->email);

            return response()->json([
                'success' => true,
                'message' => 'Votre mot de passe a été réinitialisé avec succès. Veuillez vous connecter.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur resetPassword:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier si un token d'authentification est valide
     */
    public function verifyToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide',
                ], 401);
            }

            if (! $user->actif) {
                return response()->json([
                    'success' => false,
                    'message' => 'Compte suspendu',
                    'suspended' => true,
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du token',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour le rôle de l'utilisateur
     */
    public function updateRole(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de modifier le rôle',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,user,client,livreur,gestionnaire',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user->update([
                'role' => $request->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès',
                'data'    => $user,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du rôle',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer son propre compte
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de supprimer le compte',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect',
                ], 401);
            }

            $userId = $user->id;

            DB::beginTransaction();

            Client::where('user_id', $userId)->delete();
            Livreur::where('user_id', $userId)->delete();
            Gestionnaire::where('user_id', $userId)->delete();
            $user->tokens()->delete();

            if ($user->photo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
            }

            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour la position GPS de l'utilisateur
     */
    public function updatePosition(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->actif) {
            return response()->json([
                'success' => false,
                'message' => 'Compte suspendu, impossible de mettre à jour la position',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $user->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
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
}
