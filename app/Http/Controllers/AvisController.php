<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Enums\NotificationType;
use App\Models\User;

class AvisController extends Controller
{
    
    /**
     * Afficher tous les avis.
     */
    public function index(): JsonResponse
    {
        $avis = Avis::all();

        return response()->json($avis->load('user'),
         200);
    }

    /**
     * Créer un nouvel avis.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            //'livraison_id' => 'required|integer|exists:livraisons,id',
            'note' => 'required|integer|min:1|max:5',
            'message' => 'nullable|string|max:255',
        ]);

        try {
            $avis = Avis::create($validatedData);
           
            /*
            $notificationController = new NotificationController();
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: [User::where('role', 'admin')->first()->id],
                type: NotificationType::NEW_AVIS,
                title: $avis->user->name." a laissé un avis",
                body: $avis->message
            );
            */
            
            return response()->json( $avis->load('user'),
             201);

            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'avis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher un avis spécifique.
     */
    public function show($id): JsonResponse
    {
        $avis = Avis::find($id);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable',
            ], 404);
        }

        /*
        $notificationController = new NotificationController();
        // Envoi de la notification à l'utilisateur
        $notificationController->sendNotificationToUser(
            userId: $avis->user_id,
            type: NotificationType::AVIS_LU,
            title: "Votre avis a ete lu et prise en compte.",
            body: $avis->message
        );

        */
        return response()->json( 
        $avis->load('user'),
        200);
    }

    /**
     * Mettre à jour un avis.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $avis = Avis::find($id);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'note' => 'sometimes|integer|min:1|max:5',
            'commentaire' => 'nullable|string|max:255',
        ]);

        $avis->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Avis mis à jour avec succès',
            'data' => $avis,
        ], 200);
    }

    /**
     * Supprimer un avis.
     */
    public function destroy($id): JsonResponse
    {
        $avis = Avis::find($id);

        if (!$avis) {
            return response()->json([
                'success' => false,
                'message' => 'Avis introuvable',
            ], 404);
        }

        $avis->delete();

        return response()->json([
            'success' => true,
            'message' => 'Avis supprimé avec succès',
        ], 200);
    }
}
