<?php

namespace App\Http\Controllers;

use App\Models\ResponseAvis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\NotificationType;
use App\Models\User;

class ResponseAvisController extends Controller
{
    /**
     * Afficher toutes les réponses aux avis.
     */
    public function index(): JsonResponse
    {
        $reponses = ResponseAvis::all();

        return response()->json([
            'success' => true,
            'data' => $reponses,
        ], 200);
    }

    /**
     * Créer une nouvelle réponse à un avis.
     */
    public function store(Request $request): JsonResponse
    {
       
        $validatedData = $request->validate([
            'avis_id' => 'required|integer|exists:avis,id',
            'admin_id' => 'required|integer|exists:users,id',
            'message' => 'required|text',
        ]);

        try {
            $reponse = ResponseAvis::create($validatedData);

            /*
            $notificationController = new NotificationController();
           
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: $reponse->avis->user_id,
                type: NotificationType::AVIS_RESPONSE,
                title: "L'admin a repondu à votre message",
                body: $reponse->message
            );
            */

            return response()->json([
                'success' => true,
                'message' => 'Réponse à l\'avis créée avec succès',
                'data' => $reponse,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la réponse à l\'avis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une réponse spécifique.
     */
    public function show($id): JsonResponse
    {
        $reponse = ResponseAvis::find($id);

        if (!$reponse) {
            return response()->json([
                'success' => false,
                'message' => 'Réponse à l\'avis introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $reponse,
        ], 200);
    }

    /**
     * Mettre à jour une réponse à un avis.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $reponse = ResponseAvis::find($id);

        if (!$reponse) {
            return response()->json([
                'success' => false,
                'message' => 'Réponse à l\'avis introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'message' => 'required|string|max:255',
        ]);

        $reponse->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Réponse à l\'avis mise à jour avec succès',
            'data' => $reponse,
        ], 200);
    }

    /**
     * Supprimer une réponse à un avis.
     */
    public function destroy($id): JsonResponse
    {
        $reponse = ResponseAvis::find($id);

        if (!$reponse) {
            return response()->json([
                'success' => false,
                'message' => 'Réponse à l\'avis introuvable',
            ], 404);
        }

        $reponse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Réponse à l\'avis supprimée avec succès',
        ], 200);
    }
}