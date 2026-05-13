<?php

namespace App\Http\Controllers;

use App\Models\DemandeAdhesion;
use App\Models\Livreur;
use App\Models\User;
use App\Models\Client;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Enums\NotificationType;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
class DemandeAdhesionController extends Controller
{
    /**
     * Afficher toutes les demandes d'adhésion.
     */
    public function index(): JsonResponse
    {
        $demandes = DemandeAdhesion::with(relations: 'user')->get();
       

        return response()->json($demandes, 200);
    }

    public function getByStatus($status): JsonResponse
    {



        $demandes = DemandeAdhesion::with(relations: 'user')->where('status', $status)->get();

        /*
        return response()->json([
            'success' => true,
            'count' => $demandes->count(),
            'data' => $demandes,
        ], 200);
        */
        return response()->json( $demandes,
         200);
    }

    /**
     * Créer une nouvelle demande d'adhésion.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'message' => 'nullable|string|max:255',
            'drivers_license' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
            'vehicule' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
            'vehicule_type' => 'required|string|max:255',
            'id_card_type' => 'required|string|max:255',
            'id_card_number' => 'required|string|max:255|unique:demande_adhesions,id_card_number',
            'id_card_image' => 'nullable|file|mimes:jpeg,png,jpg,gif,pdf|max:2048',
            'id_card_expiry_date' => 'nullable|date',
            'date' => 'nullable|date',
            'info' => 'nullable|string',
            'wilaya' => 'nullable|string|max:2', // ✅ Code wilaya (01 à 58)
        ]);

        if ($validated->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validated->errors(),
            ], 422);
        }

        $validatedData = $validated->validated();

        try {
            $utilsController = new UtilsController();
            DB::beginTransaction();

            // Upload des fichiers
            $driversLicensePath = $utilsController->uploadFile($request, 'drivers_license');
            $idCardImagePath = $utilsController->uploadFile($request, 'id_card_image');
            $vehiculePath = $utilsController->uploadFile($request, 'vehicule');
            
            $validatedData['drivers_license'] = $driversLicensePath;
            $validatedData['id_card_image'] = $idCardImagePath;
            $validatedData['vehicule'] = $vehiculePath;
            $validatedData['drivers_license_url'] = $driversLicensePath ? asset('storage/' . $driversLicensePath) : null;
            $validatedData['vehicule_url'] = $vehiculePath ? asset('storage/' . $vehiculePath) : null;
            $validatedData['id_card_image_url'] = $idCardImagePath ? asset('storage/' . $idCardImagePath) : null;
            $validatedData['user_id'] = auth()->id();
            $validatedData['status'] = 'approved'; // ✅ Automatiquement approuvée

            // Créer la demande d'adhésion
            $demande = DemandeAdhesion::create([
                'user_id' =>  $validatedData['user_id'],
                'message' => $validatedData['message'] ?? null,
                'drivers_license' => $validatedData['drivers_license'] ?? null,
                'drivers_license_url' => $validatedData['drivers_license_url'] ?? null,
                'vehicule' => $validatedData['vehicule'] ?? null,
                'vehicule_url' => $validatedData['vehicule_url'] ?? null,
                'vehicule_type' => $validatedData['vehicule_type'] ?? null,
                'id_card_type' => $validatedData['id_card_type'] ?? null,
                'id_card_number' => $validatedData['id_card_number'],
                'id_card_image' => $validatedData['id_card_image'] ?? null,
                'id_card_image_url' => $validatedData['id_card_image_url'] ?? null,
                'id_card_expiry_date' => $validatedData['id_card_expiry_date'] ?? null,
                'date' => $validatedData['date'] ?? null,
                'info' => $validatedData['info'] ?? null,
                'status' => 'approved', // ✅ Status approuvé
            ]);

            // ✅ Mettre à jour le livreur existant avec l'ID de la demande et la wilaya
            $livreur = Livreur::where('user_id', auth()->id())->first();
            if ($livreur) {
                $livreur->update([
                    'demande_adhesions_id' => $demande->id,
                    'wilaya_id' => $validatedData['wilaya'] ?? $livreur->wilaya_id, // ✅ Ajouter la wilaya
                ]);
            }

            DB::commit();

            // Retourner la demande avec le livreur créé
            return response()->json(
                $demande->load('user'),
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la demande d\'adhésion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une demande d'adhésion spécifique.
     */
    public function show($id): JsonResponse
    {
        $demande = DemandeAdhesion::with(relations: 'user')->find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande d\'adhésion introuvable',
            ], 404);
        }

        return response()->json( $demande,
         200);
    }

    /**
     * Mettre à jour une demande d'adhésion.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $demande = DemandeAdhesion::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande d\'adhésion introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'message' => 'nullable|string|max:255',
            'drivers_license' => 'sometimes|string|max:255',
            'vehicule' => 'sometimes|string|max:255',
            'vehicule_type' => 'sometimes|string|max:255',
            'id_card_type' => 'sometimes|string|max:255',
            'id_card_number' => 'sometimes|string|max:255',
            'id_card_image' => 'sometimes|string|max:255',
            'id_card_expiry_date' => 'sometimes|date',
            'date' => 'sometimes|date',
            'info' => 'nullable|string',
            'status' => 'sometimes|string|in:pending,approved,rejected',
        ]);

        $demande->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'adhésion mise à jour avec succès',
            'data' => $demande,
        ], 200);
    }

    /**
     * Supprimer une demande d'adhésion.
     */
    public function destroy($id): JsonResponse
    {
        $demande = DemandeAdhesion::find($id);

        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande d\'adhésion introuvable',
            ], 404);
        }


        if ($demande->drivers_license) {
            Storage::disk('public')->delete($demande->drivers_license);
        }
        if ($demande->vehicule) {
            Storage::disk('public')->delete($demande->vehicule);
        }
        if ($demande->id_card_image) {
            Storage::disk('public')->delete($demande->id_card_image);
        }
       
        $demande->delete();

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'adhésion supprimée avec succès',
        ], 200);
    }

    /**
     * Mettre à jour le statut d'une demande d'adhésion.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validatedData = $request->validate([
            'status' => 'required|string|in:pending,approved,rejected',
            'user_id' => 'required|string|exists:users,id',
        ]);

        $demande = DemandeAdhesion::find($id);


        if (!$demande) {
            return response()->json([
                'success' => false,
                'message' => 'Demande d\'adhésion introuvable',
            ], 404);
        }

        if( Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à attribuer un status à cette demande',
            ], 403);

        }
        
        $demande->update([
            'status' => $validatedData['status'],
        ]);

        
       

       // $notificationController = new NotificationController();

        
        if($validatedData['status']=='approved'){
            User::where('id', $validatedData['user_id'])->update(['role' => 'livreur']);

            Livreur::create([
                'user_id' => $validatedData['user_id'], // Assuming the user is authenticated
                'demande_adhesions_id' => $demande->id,
            ]);


           /*
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: [$demande->user->id,],
                type: NotificationType::DemandeAdhesionA,
                title: "Votre demande d'adhesion a ete approuve",
                body: "Felicitation, vous etes maintenant livreur chez nous. Vous pouvez commencer a livrer des colis."
            );
            */

        } else if($validatedData['status']=='rejected'){
            // Envoi de la notification à l'utilisateur
            /*
            $notificationController->sendNotificationToUser(
                userId: [$demande->user->id],
                type: NotificationType::DemandeAdhesionR,
                title: "Votre demande d'adhesion a ete rejete",
                body: "Nous sommes desoles, votre demande d'adhesion a ete rejete. Vous pouvez reessayer plus tard."
            );
            */
        }

        /*
         // Envoi de la notification à l'administrateur
         $notificationController->sendNotificationToUser(
            userId: [User::where('role', 'admin')->first()->id, $demande->user->id],
            type: NotificationType::DemandeAdhesion,
            title: "Le statut de la demande d'adhesion a ete mis a jour",
            body: "Le statut de la demande d'adhesion de " . $demande->user->name . " a ete mis a jour."
        );
        */
       
        

        /*
        return response()->json([
            'success' => true,
            'message' => 'Statut de la demande d\'adhésion mis à jour avec succès',
            'data' => $demande,
        ], 200);
        */
        return response()->json( $demande,
         200);

    }
}
