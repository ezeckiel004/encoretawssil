<?php

namespace App\Http\Controllers;

use App\Models\Bordereau;
use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class BordereauController extends Controller
{
    /**
     * Afficher tous les bordereaux.
     */
    public function index(): JsonResponse
    {
        $bordereaux = Bordereau::with('client')->get();

        return response()->json([
            'success' => true,
            'data' => $bordereaux,
        ], 200);
    }

    /**
     * Créer un nouveau bordereau.
     */
    public function store(Request $request): JsonResponse
    {
       
        $validatedData = $request->validate([
           // 'numero' => 'required|string|max:255|unique:bordereaux,numero',
            'photo_reception' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validation de la photo
            'signed_by' => 'nullable|string|max:255',
            'commentaire' => 'nullable|string|max:500',
            'note' => 'nullable|integer|min:1|max:5',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

       

        try {
            $utilsController = new UtilsController();

            $photoPath = $utilsController->uploadPhoto($request, 'photo');
            $validatedData['numero'] = $this->generateUniqueNumero(); 

            if ($photoPath) {
                $validatedData['photo_reception'] = $photoPath; 
                $validatedData['photo_reception_url'] = asset('storage/' . $photoPath); // URL de la photo
            }

            $bordereau = Bordereau::create($validatedData);

            /*
            $notificationController = new NotificationController();

            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: $bordereau->client->user_id,
                type: NotificationType::BORDEREAU_CREATED,
                title: "Nouveau bordereau créé",
                body: "Votre bordereau numéro " . $bordereau->numero . " a été créé avec succès."
            );
            */
            
            Livraison::update(
                ['bordereau_id' => $bordereau->id],
                [
                    'status' => 'livre',
                    'date_livraison' => $bordereau->updated_at
                    ]
            );
            return response()->json([
                'success' => true,
                'message' => 'Bordereau créé avec succès',
                'data' => $bordereau,
            ], 201);


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du bordereau',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher un bordereau spécifique.
     */
    public function show($id): JsonResponse
    {
        $bordereau = Bordereau::with('client')->find($id);

        if (!$bordereau) {
            return response()->json([
                'success' => false,
                'message' => 'Bordereau introuvable',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $bordereau,
        ], 200);
    }

    /**
     * Mettre à jour un bordereau.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $bordereau = Bordereau::find($id);

        if (!$bordereau) {
            return response()->json([
                'success' => false,
                'message' => 'Bordereau introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'numero' => 'sometimes|string|max:255|unique:bordereaux,numero,' . $id,
            'photo_reception' => 'nullable|string|max:255',
            'signed_by' => 'nullable|string|max:255',
            'commentaire' => 'nullable|string|max:500',
            'note' => 'nullable|integer|min:1|max:5',
            'client_id' => 'sometimes|integer|exists:clients,id',
        ]);

        $bordereau->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Bordereau mis à jour avec succès',
            'data' => $bordereau,
        ], 200);
    }

    /**
     * Supprimer un bordereau.
     */
    public function destroy($id): JsonResponse
    {
        $bordereau = Bordereau::find($id);

        if (!$bordereau) {
            return response()->json([
                'success' => false,
                'message' => 'Bordereau introuvable',
            ], 404);
        }

        $bordereau->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bordereau supprimé avec succès',
        ], 200);
    }

    public function generateUniqueNumero(): string
    {
        do {
            // Générer un code PIN aléatoire à 5 chiffres
            $pin = 'BORD'+ str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Bordereau::where('numero', $pin)->exists()); // Vérifier l'unicité

        return $pin;
    }
}