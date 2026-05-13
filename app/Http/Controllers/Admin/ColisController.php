<?php
// app/Http/Controllers/Admin/ColisController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ColisController extends Controller
{
    /**
     * Liste des colis avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Colis::with('demandeLivraisons');

            // Filtre pour les colis non assignés à une navette
            if ($request->has('non_assignes') && $request->non_assignes) {
                $query->whereDoesntHave('navettes', function($q) {
                    $q->whereIn('status', ['planifiee', 'en_cours']);
                });
            }

            // Filtre par statut (si vous avez un champ statut dans colis)
            if ($request->has('statut') && $request->statut) {
                $query->where('statut', $request->statut);
            }

            // Recherche par label ou référence
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('colis_label', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            }

            // Pagination
            $colis = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $colis
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@index: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colis'
            ], 500);
        }
    }

    /**
     * Liste des colis disponibles (non assignés)
     */
    public function disponibles(): JsonResponse
    {
        try {
            $colis = Colis::whereDoesntHave('navettes', function($q) {
                $q->whereIn('status', ['planifiee', 'en_cours']);
            })->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $colis
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@disponibles: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colis disponibles'
            ], 500);
        }
    }

    /**
     * Voir un colis spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $colis = Colis::with(['demandeLivraisons', 'navettes'])->find($id);

            if (!$colis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Colis non trouvé'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $colis
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@show: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du colis'
            ], 500);
        }
    }

    /**
     * Créer un nouveau colis
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'colis_type' => 'required|string|max:50',
            'colis_label' => 'required|string|max:255',
            'colis_photo' => 'nullable|string',
            'colis_description' => 'nullable|string',
            'poids' => 'required|numeric|min:0',
            'hauteur' => 'nullable|numeric|min:0',
            'largeur' => 'nullable|numeric|min:0',
            'colis_prix' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $colis = Colis::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Colis créé avec succès',
                'data' => $colis
            ], 201);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@store: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du colis'
            ], 500);
        }
    }

    /**
     * Mettre à jour un colis
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $colis = Colis::find($id);

            if (!$colis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Colis non trouvé'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'colis_type' => 'sometimes|string|max:50',
                'colis_label' => 'sometimes|string|max:255',
                'colis_photo' => 'nullable|string',
                'colis_description' => 'nullable|string',
                'poids' => 'sometimes|numeric|min:0',
                'hauteur' => 'nullable|numeric|min:0',
                'largeur' => 'nullable|numeric|min:0',
                'colis_prix' => 'sometimes|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $colis->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Colis mis à jour avec succès',
                'data' => $colis
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@update: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du colis'
            ], 500);
        }
    }

    /**
     * Supprimer un colis
     */
    public function destroy($id): JsonResponse
    {
        try {
            $colis = Colis::find($id);

            if (!$colis) {
                return response()->json([
                    'success' => false,
                    'message' => 'Colis non trouvé'
                ], 404);
            }

            // Vérifier si le colis est utilisé dans des navettes
            if ($colis->navettes()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un colis assigné à une navette'
                ], 400);
            }

            $colis->delete();

            return response()->json([
                'success' => true,
                'message' => 'Colis supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur ColisController@destroy: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du colis'
            ], 500);
        }
    }
}
