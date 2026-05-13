<?php
// app/Http/Controllers/Admin/GestionnaireController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gestionnaire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GestionnaireController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Récupérer tous les gestionnaires
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Gestionnaire::with('user');

            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtre par wilaya
            if ($request->has('wilaya_id')) {
                $query->where('wilaya_id', $request->wilaya_id);
            }

            // Recherche
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $gestionnaires = $query->get();

            return response()->json([
                'success' => true,
                'data' => $gestionnaires
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur index gestionnaires: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gestionnaires'
            ], 500);
        }
    }

    /**
     * Récupérer un gestionnaire par ID
     */
    public function show($id): JsonResponse
    {
        try {
            $gestionnaire = Gestionnaire::with('user')->find($id);

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gestionnaire non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $gestionnaire
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur show gestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du gestionnaire'
            ], 500);
        }
    }

    /**
     * Créer un nouveau gestionnaire
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'wilaya_id' => 'required|string|max:10',
            'status' => 'sometimes|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Vérifier si l'utilisateur est déjà gestionnaire
            $existing = Gestionnaire::where('user_id', $request->user_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet utilisateur est déjà gestionnaire'
                ], 400);
            }

            $gestionnaire = Gestionnaire::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user_id,
                'wilaya_id' => $request->wilaya_id,
                'status' => $request->status ?? 'active'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gestionnaire créé avec succès',
                'data' => $gestionnaire->load('user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création gestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création'
            ], 500);
        }
    }

    /**
     * Mettre à jour un gestionnaire
     */
    public function update(Request $request, $id): JsonResponse
    {
        $gestionnaire = Gestionnaire::find($id);

        if (!$gestionnaire) {
            return response()->json([
                'success' => false,
                'message' => 'Gestionnaire non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'wilaya_id' => 'sometimes|string|max:10',
            'status' => 'sometimes|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gestionnaire->update($request->only(['wilaya_id', 'status']));

            return response()->json([
                'success' => true,
                'message' => 'Gestionnaire mis à jour',
                'data' => $gestionnaire->load('user')
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur update gestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer un gestionnaire
     */
    public function destroy($id): JsonResponse
    {
        try {
            $gestionnaire = Gestionnaire::find($id);

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gestionnaire non trouvé'
                ], 404);
            }

            $gestionnaire->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gestionnaire supprimé'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur suppression gestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Activer/désactiver un gestionnaire
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        try {
            $gestionnaire = Gestionnaire::find($id);

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gestionnaire non trouvé'
                ], 404);
            }

            $newStatus = $gestionnaire->status === 'active' ? 'inactive' : 'active';
            $gestionnaire->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'data' => ['status' => $newStatus]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur toggle gestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }
}
