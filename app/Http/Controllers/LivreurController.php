<?php

namespace App\Http\Controllers;

use App\Models\Livreur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LivreurController extends Controller
{
    /**
     * Afficher tous les livreurs.
     */
    public function index(): JsonResponse
    {
        $livreurs = Livreur::with(['user', 'demandeAdhesion'])->get();

        return response()->json([
            'success' => true,
            'data' => $livreurs,
        ], 200);
    }

   

    /**
     * Afficher un livreur spécifique.
     */
    public function show($id): JsonResponse
    {
        $livreur = Livreur::with(['user', 'demandeAdhesion'])->find($id);

        if (!$livreur) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'livreur' => $livreur->user,
                'demande_adhesions' => $livreur->demandeAdhesion,
                'desactiver'=> $livreur->desactiver,
            ],
        ], 200);
    }

    /**
     * Mettre à jour un livreur.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $livreur = Livreur::find($id);

        if (!$livreur) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'user_id' => 'sometimes|integer|exists:users,id',
            'demande_adhesions_id' => 'nullable|integer|exists:demande_adhesions,id',
            'type' => 'sometimes|string|in:distributeur,ramasseur',
            'desactiver' => 'sometimes|boolean',
        ]);

        $livreur->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Livreur mis à jour avec succès',
            'data' => $livreur,
        ], 200);
    }
    
    /**
     * Activer ou désactiver un livreur.
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $validatedData = $request->validate([
            'desactiver' => 'required|boolean', // Vérifie que la valeur est un booléen
        ]);

        $livreur = Livreur::find($id);

        if (!$livreur) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur introuvable',
            ], 404);
        }

        try {
            // Mettre à jour le statut d'activation
            $livreur->update([
                'desactiver' => $validatedData['desactiver'],
            ]);

            $message = $validatedData['desactiver'] ? 'Livreur désactivé avec succès' : 'Livreur activé avec succès';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $livreur,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un livreur.
     */
    public function destroy($id): JsonResponse
    {
        $livreur = Livreur::find($id);

        if (!$livreur) {
            return response()->json([
                'success' => false,
                'message' => 'Livreur introuvable',
            ], 404);
        }

        $livreur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Livreur supprimé avec succès',
        ], 200);
    }
    
    
}