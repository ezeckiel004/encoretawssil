<?php

namespace App\Http\Controllers;

use App\Models\Commentaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommentaireController extends Controller
{
    /**
     * Afficher tous les commentaires.
     */
    public function index(): JsonResponse
    {
        $commentaires = Commentaire::with(['user', 'livraison'])->get();


        return response()->json([
            'success' => true,
            'data' => $commentaires,
        ], 200);
    }

    /**
     * Créer un nouveau commentaire.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'livreur_id' => 'required|string|exists:livreur,id',
            'livraison_id' => 'required|integer|exists:livraisons,id',
            'message' => 'nullable|string|max:255',
            
        ]);

        try {
            $validatedData['livreur'] = $request->user()->nom . $request->user()->prenom; // Assuming the user is authenticated and has a name attribute
            $commentaire = Commentaire::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Commentaire créé avec succès',
                'data' => $commentaire,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du commentaire',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher un commentaire spécifique.
     */
    public function show($id): JsonResponse
    {
        $commentaire = Commentaire::find($id);

        if (!$commentaire) {
            return response()->json([
                'success' => false,
                'message' => 'Commentaire introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $commentaire,
        ], 200);
    }

    /**
     * Mettre à jour un commentaire.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $commentaire = Commentaire::find($id);

        if (!$commentaire) {
            return response()->json([
                'success' => false,
                'message' => 'Commentaire introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'message' => 'required|text',
        ]);

        $commentaire->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire mis à jour avec succès',
            'data' => $commentaire,
        ], 200);
    }

    /**
     * Supprimer un commentaire.
     */
    public function destroy($id): JsonResponse
    {
        $commentaire = Commentaire::find($id);

        if (!$commentaire) {
            return response()->json([
                'success' => false,
                'message' => 'Commentaire introuvable',
            ], 404);
        }

        $commentaire->delete();

        return response()->json([
            'success' => true,
            'message' => 'Commentaire supprimé avec succès',
        ], 200);
    }
}