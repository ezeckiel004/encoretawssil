<?php
// app/Http/Controllers/Admin/HubController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class HubController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Lister tous les hubs
     */
    public function index(): JsonResponse
    {
        try {
            $hubs = Hub::all();

            return response()->json([
                'success' => true,
                'data' => $hubs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des hubs'
            ], 500);
        }
    }

    /**
     * Voir un hub spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $hub = Hub::find($id);

            if (!$hub) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hub introuvable'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $hub
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du hub'
            ], 500);
        }
    }

    /**
     * Créer un nouveau hub
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'email' => 'required|email|unique:hubs,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $hub = Hub::create([
                'nom' => $request->nom,
                'email' => $request->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hub créé avec succès',
                'data' => $hub
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du hub'
            ], 500);
        }
    }

    /**
     * Mettre à jour un hub
     */
    public function update(Request $request, $id): JsonResponse
    {
        $hub = Hub::find($id);

        if (!$hub) {
            return response()->json([
                'success' => false,
                'message' => 'Hub introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:hubs,email,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $hub->update($request->only(['nom', 'email']));

            return response()->json([
                'success' => true,
                'message' => 'Hub mis à jour avec succès',
                'data' => $hub
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du hub'
            ], 500);
        }
    }

    /**
     * Supprimer un hub
     */
    public function destroy($id): JsonResponse
    {
        try {
            $hub = Hub::find($id);

            if (!$hub) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hub introuvable'
                ], 404);
            }

            $hub->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hub supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du hub'
            ], 500);
        }
    }
}
