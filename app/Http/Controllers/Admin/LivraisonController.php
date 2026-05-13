<?php

namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Models\Livraison;
use App\Models\Commentaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DemandeLivraison;
use App\Enums\NotificationType;
use App\Models\User;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LivraisonsExport;
use App\Models\Client;
use App\Models\Colis;
use App\Models\CommissionConfig;
use App\Models\Gestionnaire;
use App\Models\GestionnaireGain;
use App\Models\Livreur;

class LivraisonController extends Controller
{
    /**
     * Afficher toutes les livraisons.
     */
    // app/Http/Controllers/LivraisonController.php
    public function index(): JsonResponse
    {
        $livraisons = Livraison::get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status,  // ✅ AJOUTER CETTE LIGNE
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    public function statistiquesClient($id): JsonResponse
    {
        $livraisons = Livraison::where('client_id', $id)->get();
        $total = $livraisons->isEmpty() ? 0 : $livraisons->count();
        $statuss = [
            'livraisons_terminees' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_en_cours' => 0,
            'total_livraisons' => $total
        ];

        // Compter les livraisons selon leur status
        foreach ($livraisons as $livraison) {
            switch ($livraison->status) {
                case 'livre':
                    $statuss['livraisons_terminees']++;
                    break;
                case 'en_attente':
                    $statuss['livraisons_en_attente']++;
                    break;
                case 'prise_en_charge_ramassage':
                case 'prise_en_charge_livraison':
                case 'ramasse':
                case 'en_transit':
                    $statuss['livraisons_en_cours']++;
                    break;
            }
        }

        return response()->json(
            $statuss,
            200
        );
    }

    //Statistiques livreurs
    public function statistiquesLivreur($id): JsonResponse
    {
        $livraisons = Livraison::where('livreur_distributeur_id', $id)
            ->orWhere('livreur_ramasseur_id', $id)
            ->get();

        $total = $livraisons->isEmpty() ? 0 : $livraisons->count();
        $status = [
            'livraisons_terminees' => 0,
            'livraisons_en_attente' => 0,
            'livraisons_en_cours' => 0,
            'total_livraisons' => $total
        ];

        // Compter les livraisons selon leur status
        foreach ($livraisons as $livraison) {
            switch ($livraison->status) {
                case 'livre':
                    $status['livraisons_terminees']++;
                    break;
                case 'en_attente':
                    $status['livraisons_en_attente']++;
                    break;
                case 'prise_en_charge_ramassage':
                case 'prise_en_charge_livraison':
                case 'ramasse':
                case 'en_transit':
                    $status['livraisons_en_cours']++;
                    break;
            }
        }

        return response()->json(
            $status,
            200
        );
    }

    public function livraisonsEnCours(): JsonResponse
    {
        $livraisons = Livraison::whereNotIn('status', ['en_attente', 'livre'])->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }
        return response()->json($datas, 200);
    }

    public function livraisonsClientEnCours($clientId): JsonResponse
    {
        $livraisons = Livraison::where('client_id', $clientId)
            ->whereNotIn('status', ['en_attente', 'livre'])
            ->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }
        return response()->json($datas, 200);
    }

    public function livraisonsLivreurEnCours($livreurId): JsonResponse
    {
        $livraisons = Livraison::where(function ($query) use ($livreurId) {
            $query->where('livreur_distributeur_id', $livreurId)
                ->orWhere('livreur_ramasseur_id', $livreurId);
        })
            ->whereNotIn('status', ['en_attente', 'livre'])
            ->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Afficher une livraison spécifique.
     */
    public function show($id): JsonResponse
    {
        Log::info("Récupération de la livraison avec ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour ID: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        Log::info("Livraison trouvée: " . $livraison->id);

        return response()->json(
            [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status, // ⬅️ AJOUTE CETTE LIGNE
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ],
            200
        );
    }

    public function getByClient($id): JsonResponse
    {
        try {
            Log::info("Récupération des livraisons pour client ID: " . $id);

            $livraisons = Livraison::where('client_id', $id)
                ->get();

            $datas = [];
            foreach ($livraisons as $livraison) {
                $datas[] = [
                    'id' => $livraison->id,
                    'client_id' => $livraison->client_id,
                    'created_at' => $livraison->created_at,
                    'demande_livraisons_id' => $livraison->demande_livraisons_id,
                    'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                    'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                    'bordereau_id' => $livraison->bordereau_id,
                    'code_pin' => $livraison->code_pin,
                    'date_ramassage' => $livraison->date_ramassage,
                    'date_livraison' => $livraison->date_livraison,
                    'status' => $livraison->status,
                    'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                    'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                    'demande_livraison' => $livraison->demandeLivraison->load([
                        'colis',
                    ]),
                    'destinataire' => $livraison->demandeLivraison->destinataire->load([
                        'user'
                    ]),
                    'client' => $livraison->client->load([
                        'user'
                    ]),
                    'commentaires' => $livraison->commentaires,
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

                ];
            }

            Log::info("Nombre de livraisons trouvées pour le client: " . count($datas));

            return response()->json(
                $datas,
                200
            );
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des livraisons du client ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du client.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getByLivreur($id): JsonResponse
    {
        try {
            Log::info("Récupération des livraisons pour livreur ID: " . $id);

            $livraisons = Livraison::where([
                'livreur_distributeur_id' => $id,
                'livreur_ramasseur_id' => $id,
            ])->orWhere([
                'livreur_distributeur_id' => $id,
            ])->orWhere([
                'livreur_ramasseur_id' => $id,
            ])->get();

            $datas = [];
            foreach ($livraisons as $livraison) {
                $datas[] = [
                    'id' => $livraison->id,
                    'client_id' => $livraison->client_id,
                    'demande_livraisons_id' => $livraison->demande_livraisons_id,
                    'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                    'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                    'bordereau_id' => $livraison->bordereau_id,
                    'code_pin' => $livraison->code_pin,
                    'date_ramassage' => $livraison->date_ramassage,
                    'date_livraison' => $livraison->date_livraison,
                    'status' => $livraison->status,
                    'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                    'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                    'demande_livraison' => $livraison->demandeLivraison->load([
                        'client',
                        'destinataire',
                        'colis',
                    ]),
                    'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                    'client' => $livraison->client?->user,
                    'commentaires' => $livraison->commentaires,
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,

                ];
            }

            Log::info("Nombre de livraisons trouvées pour le livreur: " . count($datas));

            return response()->json(
                $datas,
                200
            );
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des livraisons du livreur ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Attribuer un livreur à une livraison.
     */
    public function assignLivreur(Request $request, $id): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'livreur_id' => 'required|string|exists:livreurs,id', // Vérifie que le livreur existe
            'type' => 'required|integer|in:1,2', // 1 pour ramasseur, 2 pour distributeur
        ]);


        if ($validated->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validated->errors(),
            ], 422);
        }

        $validatedData = $validated->validated();

        $livraison = Livraison::find($id);
        $type = $validatedData['type'];

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas autorisé à attribuer un livreur à cette livraison',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Mettre à jour le livreur de la livraison
            if ($type == 2) {
                // Type 2 : Livreurs distributeurs (ne peut être assigné que si le statut est 'en_transit')
                if ($livraison->status !== 'en_transit') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le distributeur ne peut être assigné que lorsque la livraison est en transit',
                    ], 400);
                }

                $livraison->update([
                    'livreur_distributeur_id' => $validatedData['livreur_id'],
                ]);
            } elseif ($type == 1) {
                // Type 1 : Livreurs ramasseurs (ne peut être assigné que si le statut n'est pas 'ramasse')
                if ($livraison->status === 'ramasse') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis a déjà été ramassé, vous ne pouvez plus attribuer un autre ramasseur',
                    ], 400);
                }

                $livraison->update([
                    'livreur_ramasseur_id' => $validatedData['livreur_id'],
                    'status' => 'prise_en_charge_ramassage'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Type de livreur invalide',
                ], 400);
            }

            /*
            $notificationController = new NotificationController();
            // Envoi de la notification à l'utilisateur
            $notificationController->sendNotificationToUser(
                userId: $livraison->demandeLivraison->client_id,
                type: NotificationType::LIVRAISON_CONFIRMER,
                title: "Livreur attribué à la livraison",
                body: "Un livreur a été attribué à votre livraison. Veuillez vérifier les détails de la livraison."
            );
            // Envoi de la notification au livreur
            $notificationController->sendNotificationToUser(
                userId: $validatedData['livreur_id'],
                type: NotificationType::LIVRAISON_ATTRIBUER,
                title: "Vous avez été attribué à une livraison",
                body: "Vous avez été attribué à une livraison. Veuillez vérifier les détails de la livraison."
            );
            */
            DB::commit();

            /*
            return response()->json([
                'success' => true,
                'message' => 'Livreur attribué avec succès à la livraison',
                'data' => $livraison,
            ], 200);
            */
            return response()->json(
                $livraison,
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une livraison.
     */
    public function destroy($id): JsonResponse
    {
        Log::info("Début suppression de la livraison ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour suppression: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $livraison->delete();

        Log::info("Livraison supprimée avec succès: " . $id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    public function destroyByClient($id): JsonResponse
    {
        Log::info("Début suppression par client de la livraison ID: " . $id);

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour suppression par client: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        } else {
            if ($livraison->status !== 'en_attente') {
                Log::warning("Impossible de supprimer la livraison, statut incorrect: " . $livraison->status);
                return response()->json([
                    'success' => false,
                    'message' => 'La livraison ne peut être supprimée que si elle est en attente',
                ], 400);
            }
        }

        $livraison->delete();

        Log::info("Livraison supprimée par client avec succès: " . $id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    /**
     * Mettre à jour le status d'une livraison.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        Log::info("Début mise à jour du statut pour livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        ]);

        if ($validator->fails()) {
            Log::warning("Validation échouée pour mise à jour statut: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            DB::beginTransaction();

            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                Log::warning("Livraison introuvable pour mise à jour statut: " . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $ancienStatus = $livraison->status;
            $nouveauStatus = $validatedData['status'];

            Log::info("Mise à jour du statut de {$ancienStatus} à {$nouveauStatus} pour la livraison " . $id);

            // Mise à jour du statut
            $livraison->update([
                'status' => $nouveauStatus,
            ]);

            // Si la livraison est marquée comme 'livre' et qu'elle ne l'était pas avant
            // => Calculer les commissions pour les gestionnaires
            $resultatCommission = null;
            if ($nouveauStatus === 'livre' && $ancienStatus !== 'livre') {
                $resultatCommission = $this->calculerCommissionsLivraison($livraison);

                if ($resultatCommission['success']) {
                    Log::info("Commissions calculées avec succès pour la livraison {$id}", $resultatCommission['data']);
                } else {
                    Log::warning("Échec du calcul des commissions pour la livraison {$id}: " . $resultatCommission['message']);
                }
            }

            // Si la livraison est annulée, on peut éventuellement supprimer les commissions si elles avaient été calculées
            if ($nouveauStatus === 'annule' && $ancienStatus === 'livre') {
                // Supprimer les gains associés à cette livraison
                GestionnaireGain::where('livraison_id', $livraison->id)->delete();
                Log::info("Gains supprimés pour la livraison annulée {$id}");
            }

            DB::commit();

            Log::info("Statut mis à jour avec succès pour la livraison " . $id);

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => [
                    'livraison' => $livraison,
                    'commission' => $resultatCommission
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur lors de la mise à jour du statut pour la livraison {$id}: " . $e->getMessage());
            Log::error("Trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function trackByColisLabel($colis_label): JsonResponse
    {
        try {
            Log::info('Recherche du colis: ' . $colis_label);

            $colis = \App\Models\Colis::where('colis_label', $colis_label)->first();

            if (!$colis) {
                Log::warning('Colis non trouvé: ' . $colis_label);
                return response()->json([
                    'success' => false,
                    'message' => 'Colis introuvable avec ce code de suivi'
                ], 404);
            }

            Log::info('Colis trouvé, ID: ' . $colis->id);

            $demandeLivraison = \App\Models\DemandeLivraison::where('colis_id', $colis->id)->first();

            if (!$demandeLivraison) {
                Log::warning('Aucune demande de livraison pour le colis ID: ' . $colis->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune demande de livraison trouvée pour ce colis'
                ], 404);
            }

            Log::info('Demande de livraison trouvée, ID: ' . $demandeLivraison->id);

            $livraison = Livraison::where('demande_livraisons_id', $demandeLivraison->id)
                ->with([
                    'livreurDistributeur.user',
                    'livreurRamasseur.user',
                    'client.user',
                    'demandeLivraison.client.user',
                    'demandeLivraison.destinataire.user',
                    'demandeLivraison.colis',
                    'commentaires',
                    'bordereau'
                ])
                ->first();

            if (!$livraison) {
                Log::warning('Aucune livraison pour la demande ID: ' . $demandeLivraison->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison en cours pour ce colis'
                ], 404);
            }

            Log::info('Livraison trouvée, ID: ' . $livraison->id . ', Status: ' . $livraison->status);

            return response()->json([
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison,
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau,
                'colis' => $colis,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur trackByColisLabel: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer le HTML pour impression
     */
    public function generatePrintHTML($id): JsonResponse
    {
        Log::info("Début génération HTML pour impression - ID: " . $id);

        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $data = $this->preparePrintData($livraison);

            // Ajouter la taille pour le HTML
            $data['pageWidth'] = '100mm';
            $data['pageHeight'] = '150mm';

            Log::info("Données préparées pour HTML - Livraison: " . $livraison->id);

            $html = View::make('pdf.bordereau', $data)->render();

            Log::info("HTML généré avec succès - Taille: " . strlen($html) . " caractères");

            return response()->json([
                'success' => true,
                'html' => $html,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur génération HTML - ID ' . $id . ': ' . $e->getMessage());
            Log::error('Stack trace génération HTML: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du HTML',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function saveBase64ToTempFile($base64Data, $prefix = 'img')
    {
        try {
            // Extraire les données base64
            if (preg_match('/data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $type = $matches[1];
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);

                $tempFile = tempnam(sys_get_temp_dir(), $prefix . '_' . uniqid());
                file_put_contents($tempFile, $data);

                return $tempFile;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur création fichier temporaire: ' . $e->getMessage());
        }

        return null;
    }

    private function ensureDataUri($imageData)
    {
        // Si c'est déjà un data URI, le retourner
        if (strpos($imageData, 'data:image') === 0) {
            return $imageData;
        }

        // Si c'est un fichier, le convertir en data URI
        if (file_exists($imageData)) {
            try {
                $imageData = file_get_contents($imageData);
                $type = pathinfo($imageData, PATHINFO_EXTENSION);
                $base64 = base64_encode($imageData);

                return 'data:image/' . ($type === 'svg' ? 'svg+xml' : 'png') . ';base64,' . $base64;
            } catch (\Exception $e) {
                Log::warning('Erreur conversion fichier en data URI: ' . $e->getMessage());
            }
        }

        // Si c'est une URL, essayer de la télécharger
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            try {
                $imageData = file_get_contents($imageData);
                $base64 = base64_encode($imageData);

                return 'data:image/png;base64,' . $base64;
            } catch (\Exception $e) {
                Log::warning('Erreur téléchargement URL: ' . $e->getMessage());
            }
        }

        return $imageData;
    }

    private function saveBase64ToTempFileDebug($base64Data, $prefix = 'img')
    {
        try {
            // Extraire les données base64
            if (preg_match('/data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $type = $matches[1];
                $data = substr($base64Data, strpos($base64Data, ',') + 1);
                $data = base64_decode($data);

                $tempDir = storage_path('app/temp');
                if (!file_exists($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $tempFile = $tempDir . '/' . $prefix . '_' . uniqid() . '.' . $type;
                file_put_contents($tempFile, $data);

                Log::info('Fichier temporaire créé: ' . $tempFile);

                return $tempFile;
            }
        } catch (\Exception $e) {
            Log::warning('Erreur création fichier temporaire: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Méthode de debug pour voir ce qui est généré
     */
    public function debugBordereau($id)
    {
        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json(['error' => 'Livraison non trouvée'], 404);
            }

            $data = $this->preparePrintData($livraison);

            // Afficher les infos sur les images
            $debugInfo = [
                'livraison_id' => $livraison->id,
                'qrCode_exists' => isset($data['qrCode']),
                'qrCode_type' => isset($data['qrCode']) ? substr($data['qrCode'], 0, 50) . '...' : 'N/A',
                'barcode_exists' => isset($data['barcode']),
                'barcode_type' => isset($data['barcode']) ? substr($data['barcode'], 0, 50) . '...' : 'N/A',
            ];

            // Générer un aperçu HTML
            $html = view('pdf.bordereau', $data)->render();

            return response()->json([
                'debug' => $debugInfo,
                'html_preview' => $html,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }



    /**
     * Générer et télécharger le PDF du bordereau
     */
    /**
     * Générer et télécharger le PDF du bordereau (étiquette 10x15 cm)
     */
    public function generateBordereauPDF($id)
    {
        Log::info("Début génération PDF bordereau - ID: " . $id);

        try {
            $livraison = $this->findLivraison($id);
            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $data = $this->preparePrintData($livraison);

            // Chargement de la vue
            $pdf = Pdf::loadView('pdf.bordereau', $data);

            // Configuration précise pour format 100mm × 150mm
            $pdf->setPaper([0, 0, 283.464, 425.197], 'portrait'); // 100mm = 283.464pt, 150mm = 425.197pt @72dpi

            // Options importantes pour le support UTF-8 et les caractères accentués/arabe
            $pdf->setOptions([
                'defaultFont'             => 'DejaVuSans',           // Police qui supporte accents + arabe
                'isHtml5ParserEnabled'    => true,
                'isRemoteEnabled'         => true,
                'dpi'                     => 96,
                'margin-top'              => 0,
                'margin-right'            => 0,
                'margin-bottom'           => 0,
                'margin-left'             => 0,
                'isFontSubsettingEnabled' => true,
                'defaultPaperSize'        => [0, 0, 283.464, 425.197],
                'tempDir'                 => storage_path('app/temp'), // dossier temporaire
            ]);

            // Forcer explicitement l'encodage UTF-8
            $pdf->getDomPDF()->set_option('default_charset', 'UTF-8');
            $pdf->getDomPDF()->set_option('font_height_ratio', '1.0');

            // Nom du fichier
            $fileName = 'bordereau_livraison_' . $livraison->id . '_' . now()->format('Ymd-His') . '.pdf';

            Log::info("PDF bordereau généré avec succès pour livraison #" . $livraison->id);

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF bordereau - ID ' . $id . ' : ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function generateSimpleQRCode($livraison)
    {
        try {
            // Données minimales pour le QR Code
            $data = urlencode("ID:{$livraison->id}|PIN:{$livraison->code_pin}");
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$data}&format=png&margin=0";

            // Retourner directement l'URL - DomPDF peut la charger
            return $qrUrl;
        } catch (\Exception $e) {
            Log::warning("Erreur génération QR Code: " . $e->getMessage());

            // Fallback: URL de base
            return "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=livraison&format=png";
        }
    }

    private function createSimpleQRCodeSVG($livraisonId)
    {
        $shortId = substr(md5($livraisonId), 0, 8);
        $svg = '<svg width="80" height="80" xmlns="http://www.w3.org/2000/svg">
        <rect width="80" height="80" fill="#f8fafc" stroke="#000" stroke-width="1"/>
        <text x="40" y="40" text-anchor="middle" dominant-baseline="central"
              font-family="Arial" font-size="10" fill="#000">QR</text>
        <text x="40" y="55" text-anchor="middle" font-family="Arial"
              font-size="6" fill="#666">' . $shortId . '</text>
    </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }



    /**
     * Préparer les données pour l'impression
     */
    private function preparePrintData($livraison): array
    {
        try {
            $livraison->load([
                'demandeLivraison.client.user',
                'demandeLivraison.destinataire.user',
                'demandeLivraison.colis',
                'livreurRamasseur.user',
                'livreurDistributeur.user',
            ]);

            $demande = $livraison->demandeLivraison;
            $colis = $demande->colis ?? null;
            $client = $demande->client->user ?? null;

            // Récupérer le destinataire
            $destinataire = null;

            if ($demande->destinataire && $demande->destinataire->user) {
                $destinataire = $demande->destinataire->user;
            } else if (isset($demande->destinataire) && is_object($demande->destinataire)) {
                $destinataire = $demande->destinataire;
            } else {
                $destinataire = new \stdClass();
                $destinataire->prenom = $demande->destinataire_prenom ?? '';
                $destinataire->nom = $demande->destinataire_nom ?? '';
                $destinataire->telephone = $demande->telephone_destinataire
                    ?? $demande->destinataire_telephone
                    ?? $demande->destinataire_phone
                    ?? '';
            }

            $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
            $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

            // Nettoyer les caractères
            if ($client) {
                $client->prenom = $this->cleanText($client->prenom ?? '');
                $client->nom = $this->cleanText($client->nom ?? '');
                $client->telephone = $this->cleanText($client->telephone ?? '');
            }

            if ($destinataire) {
                $destinataire->prenom = $this->cleanText($destinataire->prenom ?? '');
                $destinataire->nom = $this->cleanText($destinataire->nom ?? '');
                $destinataire->telephone = $this->cleanText($destinataire->telephone ?? '');
            }

            if ($demande) {
                $demande->addresse_depot = $this->cleanText($demande->addresse_depot ?? '');
                $demande->addresse_delivery = $this->cleanText($demande->addresse_delivery ?? '');
                $demande->wilaya = $this->cleanText($demande->wilaya ?? '');
                $demande->commune = $this->cleanText($demande->commune ?? '');
            }

            // GÉNÉRATION DES IMAGES
            $qrCode = $this->generateSimpleQRCode($livraison);
            $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
            $barcode = $this->generateSimpleBarcode($barcodeValue);

            // EXTRAIRE LA WILAYA D'ARRIVÉE
            $wilayaNumber = '';
            $wilayaName = '';

            // Table de correspondance (nom wilaya -> numéro)
            $wilayaMap = [
                'Adrar' => '01',
                'Chlef' => '02',
                'Laghouat' => '03',
                'Oum El Bouaghi' => '04',
                'Batna' => '05',
                'Béjaïa' => '06',
                'Biskra' => '07',
                'Béchar' => '08',
                'Blida' => '09',
                'Bouira' => '10',
                'Tamanrasset' => '11',
                'Tébessa' => '12',
                'Tlemcen' => '13',
                'Tiaret' => '14',
                'Tizi Ouzou' => '15',
                'Alger' => '16',
                'Djelfa' => '17',
                'Jijel' => '18',
                'Sétif' => '19',
                'Saïda' => '20',
                'Skikda' => '21',
                'Sidi Bel Abbès' => '22',
                'Annaba' => '23',
                'Guelma' => '24',
                'Constantine' => '25',
                'Médéa' => '26',
                'Mostaganem' => '27',
                'M\'Sila' => '28',
                'Mascara' => '29',
                'Ouargla' => '30',
                'Oran' => '31',
                'El Bayadh' => '32',
                'Illizi' => '33',
                'Bordj Bou Arréridj' => '34',
                'Boumerdès' => '35',
                'El Tarf' => '36',
                'Tindouf' => '37',
                'Tissemsilt' => '38',
                'El Oued' => '39',
                'Khenchela' => '40',
                'Souk Ahras' => '41',
                'Tipaza' => '42',
                'Mila' => '43',
                'Aïn Defla' => '44',
                'Naâma' => '45',
                'Aïn Témouchent' => '46',
                'Ghardaïa' => '47',
                'Relizane' => '48',
                'Timimoun' => '49',
                'Bordj Badji Mokhtar' => '50',
                'Ouled Djellal' => '51',
                'Béni Abbès' => '52',
                'In Salah' => '53',
                'In Guezzam' => '54',
                'Touggourt' => '55',
                'Djanet' => '56',
                'El M\'Ghair' => '57',
                'El Meniaa' => '58',
            ];

            // Table inverse (numéro -> nom)
            $numeroMap = [];
            foreach ($wilayaMap as $nom => $num) {
                $numeroMap[$num] = $nom;
            }

            // Récupérer la valeur du champ wilaya
            $wilayaValue = trim($demande->wilaya ?? '');

            if (!empty($wilayaValue)) {
                Log::info("LIVRAISON #{$livraison->id} - wilayaValue: " . $wilayaValue);

                // CAS 1: C'est un numéro (ex: "42")
                if (is_numeric($wilayaValue)) {
                    $numeroFormate = str_pad($wilayaValue, 2, '0', STR_PAD_LEFT);
                    $wilayaNumber = $numeroFormate;
                    $wilayaName = $numeroMap[$numeroFormate] ?? '';
                    Log::info("CAS 1 - Numéro: {$wilayaNumber}, Nom: {$wilayaName}");
                }
                // CAS 2: C'est un nom (ex: "Boumerdès", "Alger")
                else {
                    // Chercher le nom exact (insensible à la casse)
                    $found = false;
                    foreach ($wilayaMap as $nom => $num) {
                        // Comparaison insensible à la casse et aux accents
                        if (strcasecmp(trim($nom), trim($wilayaValue)) === 0) {
                            $wilayaName = $nom;
                            $wilayaNumber = $num;
                            $found = true;
                            Log::info("CAS 2 - Correspondance exacte: {$nom} -> {$num}");
                            break;
                        }
                    }

                    // Si pas trouvé, chercher partiellement
                    if (!$found) {
                        foreach ($wilayaMap as $nom => $num) {
                            if (stripos($wilayaValue, $nom) !== false || stripos($nom, $wilayaValue) !== false) {
                                $wilayaName = $nom;
                                $wilayaNumber = $num;
                                Log::info("CAS 2 - Correspondance partielle: {$nom} -> {$num}");
                                break;
                            }
                        }
                    }
                }
            }

            // Date de création
            $printDate = $livraison->created_at
                ? Carbon::parse($livraison->created_at)->locale('fr_FR')->isoFormat('DD/MM/YYYY')
                : now()->locale('fr_FR')->isoFormat('DD/MM/YYYY');

            $statusLabels = [
                'en_attente' => 'En attente',
                'prise_en_charge_ramassage' => 'Prise en charge',
                'ramasse' => 'Ramasse',
                'en_transit' => 'En transit',
                'prise_en_charge_livraison' => 'En livraison',
                'livre' => 'Livré',
                'annule' => 'Annulé',
            ];

            $statusLabel = $statusLabels[$livraison->status] ?? str_replace('_', ' ', $livraison->status);

            Log::info("RÉSULTAT FINAL #{$livraison->id} - wilayaNumber: {$wilayaNumber}, wilayaName: {$wilayaName}");

            return [
                'livraison' => $livraison,
                'demande' => $demande,
                'colis' => $colis,
                'client' => $client,
                'destinataire' => $destinataire,
                'livreurRamasseur' => $livreurRamasseur,
                'livreurDistributeur' => $livreurDistributeur,
                'qrCode' => $qrCode,
                'barcode' => $barcode,
                'colisLabel' => $barcodeValue,
                'printDate' => $printDate,
                'statusLabel' => $statusLabel,
                'wilayaNumber' => $wilayaNumber,
                'wilayaName' => $wilayaName,
            ];
        } catch (\Exception $e) {
            Log::error("Erreur préparation données: " . $e->getMessage());
            throw $e;
        }
    }


    private function extractWilayaFromLivraison($livraison)
    {
        $demande = $livraison->demandeLivraison;
        $adresseLivraison = $demande->addresse_delivery ?? '';
        $wilayaNumber = '';
        $wilayaName = '';

        // Table de correspondance complète des 58 wilayas (nom -> numéro)
        $wilayaMap = [
            // Wilayas 1-48
            'ADRAR' => '01',
            'CHLEF' => '02',
            'LAGHOUAT' => '03',
            'OUM EL BOUAGHI' => '04',
            'BATNA' => '05',
            'BEJAIA' => '06',
            'BISKRA' => '07',
            'BECHAR' => '08',
            'BLIDA' => '09',
            'BOUIRA' => '10',
            'TAMANRASSET' => '11',
            'TEBESSA' => '12',
            'TLEMCEN' => '13',
            'TIARET' => '14',
            'TIZI OUZOU' => '15',
            'ALGER' => '16',
            'DJELFA' => '17',
            'JIJEL' => '18',
            'SETIF' => '19',
            'SAIDA' => '20',
            'SKIKDA' => '21',
            'SIDI BEL ABBES' => '22',
            'ANNABA' => '23',
            'GUELMA' => '24',
            'CONSTANTINE' => '25',
            'MEDEA' => '26',
            'MOSTAGANEM' => '27',
            'M\'SILA' => '28',
            'MSILA' => '28', // Variante sans apostrophe
            'MASCARA' => '29',
            'OUARGLA' => '30',
            'ORAN' => '31',
            'EL BAYADH' => '32',
            'ILLIZI' => '33',
            'BORDJ BOU ARRERIDJ' => '34',
            'BOUMERDES' => '35',
            'EL TARF' => '36',
            'TINDOUF' => '37',
            'TISSEMSILT' => '38',
            'EL OUED' => '39',
            'KHENCHELA' => '40',
            'SOUK AHRAS' => '41',
            'TIPAZA' => '42',
            'MILA' => '43',
            'AIN DEFLA' => '44',
            'NAAMA' => '45',
            'AIN TEMOUCHENT' => '46',
            'GHARDAIA' => '47',
            'RELIZANE' => '48',

            // Nouvelles wilayas 49-58
            'TIMIMOUN' => '49',
            'BORDJ BADJI MOKHTAR' => '50',
            'OULED DJELLAL' => '51',
            'BENI ABBES' => '52',
            'IN SALAH' => '53',
            'IN GUEZZAM' => '54',
            'TOUGGOURT' => '55',
            'DJANET' => '56',
            'EL M\'GHAIR' => '57',
            'EL MENIAA' => '58',
            'EL MGHAIR' => '57', // Variante sans apostrophe
        ];

        // Cas spéciaux pour les noms composés
        $specialCases = [
            'OUM EL BOUAGHI' => '04',
            'SIDI BEL ABBES' => '22',
            'BORDJ BOU ARRERIDJ' => '34',
            'BORDJ BADJI MOKHTAR' => '50',
            'OULED DJELLAL' => '51',
            'BENI ABBES' => '52',
            'IN SALAH' => '53',
            'IN GUEZZAM' => '54',
            'EL M\'GHAIR' => '57',
            'EL MGHAIR' => '57',
            'EL MENIAA' => '58',
        ];

        // Fonction pour chercher une wilaya dans un texte
        $findWilayaInText = function ($text) use ($wilayaMap, $specialCases) {
            if (empty($text)) return null;

            $textUpper = strtoupper($text);

            // Chercher les cas spéciaux d'abord
            foreach ($specialCases as $nom => $num) {
                if (strpos($textUpper, $nom) !== false) {
                    return ['num' => $num, 'nom' => $nom];
                }
            }

            // Recherche standard
            foreach ($wilayaMap as $nom => $num) {
                if (strpos($textUpper, $nom) !== false) {
                    return ['num' => $num, 'nom' => $nom];
                }
            }

            return null;
        };

        // 1. Chercher d'abord dans le champ wilaya de la demande
        $wilayaText = trim($demande->wilaya ?? '');
        if (!empty($wilayaText)) {
            $result = $findWilayaInText($wilayaText);
            if ($result) {
                $wilayaNumber = $result['num'];
                $wilayaName = $result['nom'];
            }
        }

        // 2. Si pas trouvé, chercher dans l'adresse de livraison
        if (empty($wilayaNumber) && !empty($adresseLivraison)) {
            $result = $findWilayaInText($adresseLivraison);
            if ($result) {
                $wilayaNumber = $result['num'];
                $wilayaName = $result['nom'];
            }
        }

        // 3. Fallback : ne rien mettre si non trouvé
        // (pas de fallback sur Alger)

        return [
            'number' => $wilayaNumber,
            'name' => $wilayaName,
        ];
    }

    public function debugDate($id)
    {
        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json(['error' => 'Livraison non trouvée'], 404);
            }

            return response()->json([
                'livraison_id' => $livraison->id,
                'created_at_bdd' => $livraison->created_at,
                'created_at_formate' => Carbon::parse($livraison->created_at)->format('d/m/Y H:i:s'),
                'printDate_corrigee' => Carbon::parse($livraison->created_at)->locale('fr_FR')->isoFormat('DD/MM/YYYY'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function cleanText($text)
    {
        if (empty($text)) {
            return '';
        }

        // 1. Décoder TOUTES les entités HTML (c'est la clé !)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Remplacer les caractères problématiques spécifiques
        $text = str_replace(
            ['&comma;', '&#44;', '&amp;', '&quot;', '&lt;', '&gt;', '&nbsp;'],
            [',', ',', '&', '"', '<', '>', ' '],
            $text
        );

        // 3. Pour PDF, garder les accents et caractères spéciaux INTACTS
        // Ne pas convertir en entités HTML !

        // 4. Juste trimmer et retourner
        return trim($text);
    }

    private function cleanAllTexts($data)
    {
        // Nettoyer les adresses
        if (isset($data['demande'])) {
            $data['demande']->addresse_depot = $this->cleanText($data['demande']->addresse_depot ?? '');
            $data['demande']->addresse_delivery = $this->cleanText($data['demande']->addresse_delivery ?? '');
        }

        return $data;
    }


    private function generateSimpleBarcodeImage($value)
    {
        try {
            // Si GD est disponible, créer une image simple
            if (function_exists('imagecreatetruecolor')) {
                return $this->createBarcodeWithGD($value);
            }

            // Sinon, créer un SVG
            return $this->createBarcodeSVG($value);
        } catch (\Exception $e) {
            Log::warning("Erreur génération code-barres: " . $e->getMessage());
            return $this->createBarcodeSVG($value);
        }
    }

    private function createBarcodeSVG($value)
    {
        $svg = '<svg width="200" height="50" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="50" fill="#fff" stroke="#000" stroke-width="1"/>
        <text x="100" y="25" text-anchor="middle" dominant-baseline="central"
              font-family="Arial" font-size="12" fill="#000" font-weight="bold">
              CODE: ' . htmlspecialchars($value) . '
        </text>
        <text x="100" y="40" text-anchor="middle" font-family="Arial"
              font-size="8" fill="#666">Code-barres</text>
    </svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }


    private function createBarcodeWithGD($value)
    {
        $width = 200;
        $height = 50;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        // Dessiner un motif simple de barres
        $x = 10;
        for ($i = 0; $i < strlen($value); $i++) {
            $charCode = ord($value[$i]);
            $barHeight = ($charCode % 30) + 10;

            imagefilledrectangle($image, $x, 10, $x + 3, 10 + $barHeight, $black);
            $x += 5;
        }

        // Ajouter le texte
        imagestring($image, 2, 50, $height - 20, $value, $black);

        // Capturer l'output
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Générer un QR Code en base64
     */
    private function generateQRCode(string $data): string
    {
        try {
            Log::info("Tentative de génération de QR Code local...");

            if (class_exists('BaconQrCode\Writer')) {
                Log::info("Bibliothèque BaconQrCode disponible");

                $renderer = new ImageRenderer(
                    new RendererStyle(90),
                    new ImagickImageBackEnd()
                );

                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($data);

                $base64 = 'data:image/png;base64,' . base64_encode($qrCode);
                Log::info("QR Code généré localement avec succès - Taille: " . strlen($base64) . " caractères");

                return $base64;
            } else {
                Log::warning("Bibliothèque BaconQrCode non disponible");
            }
        } catch (\Exception $e) {
            Log::warning('Erreur génération QR Code local: ' . $e->getMessage());
        }

        // Fallback: utiliser un service en ligne
        Log::info("Utilisation du service en ligne pour le QR Code");
        $encodedData = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={$encodedData}&format=png&margin=1";
    }

    /**
     * Générer un code-barres en base64
     */
    private function generateBarcode(string $value): string
    {
        try {
            Log::info("Tentative de génération de code-barres local...");

            if (class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
                Log::info("Bibliothèque Picqer Barcode disponible");

                $generator = new BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50);

                $base64 = 'data:image/png;base64,' . base64_encode($barcode);
                Log::info("Code-barres généré localement avec succès - Taille: " . strlen($base64) . " caractères");

                return $base64;
            } else {
                Log::warning("Bibliothèque Picqer Barcode non disponible");
            }
        } catch (\Exception $e) {
            Log::warning('Erreur génération code-barres local: ' . $e->getMessage());
        }

        Log::info("Utilisation de la méthode simplifiée pour le code-barres");
        return $this->generateSimpleBarcode($value);
    }

    /**
     * Générer un code-barres simplifié (fallback)
     */
    private function generateSimpleBarcode($value)
    {
        try {
            // Utiliser un service de code-barres en ligne
            $encodedValue = urlencode($value);
            return "https://barcode.tec-it.com/barcode.ashx?data={$encodedValue}&code=Code128&dpi=96&dataseparator=";
        } catch (\Exception $e) {
            Log::warning("Erreur génération code-barres: " . $e->getMessage());

            // Fallback: URL de base
            return "https://barcode.tec-it.com/barcode.ashx?data=123456&code=Code128";
        }
    }

    /**
     * Méthode helper pour trouver une livraison par ID (UUID ou numérique)
     */
    private function findLivraison($id)
    {
        if (Str::isUuid($id)) {
            return Livraison::where('id', $id)->first();
        }

        return Livraison::find($id);
    }

    ################__NOUVELLES FONCTIONS__#####################

    /**
     * Récupérer les livraisons en attente.
     */
    public function livraisonsEnAttente(): JsonResponse
    {
        Log::info("Récupération des livraisons en attente");

        $livraisons = Livraison::where('status', 'en_attente')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Récupérer les livraisons terminées.
     */
    public function livraisonsTerminees(): JsonResponse
    {
        Log::info("Récupération des livraisons terminées");

        $livraisons = Livraison::where('status', 'livre')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Récupérer les livraisons annulées.
     */
    public function livraisonsAnnulees(): JsonResponse
    {
        Log::info("Récupération des livraisons annulées");

        $livraisons = Livraison::where('status', 'annule')->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $datas[] = [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'created_at' => $livraison->created_at,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $livraison->demandeLivraison->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $livraison->demandeLivraison->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
            ];
        }

        return response()->json($datas, 200);
    }

    /**
     * Statistiques générales (admin).
     */
    public function statistiquesGenerales(): JsonResponse
    {
        Log::info("Récupération des statistiques générales");

        try {
            // Totaux
            $totalLivraisons = Livraison::count();
            $totalEnAttente = Livraison::where('status', 'en_attente')->count();
            $totalEnCours = Livraison::whereNotIn('status', ['en_attente', 'livre', 'annule'])->count();
            $totalTerminees = Livraison::where('status', 'livre')->count();
            $totalAnnulees = Livraison::where('status', 'annule')->count();

            // Par statut détaillé
            $parStatut = [
                'en_attente' => $totalEnAttente,
                'prise_en_charge_ramassage' => Livraison::where('status', 'prise_en_charge_ramassage')->count(),
                'ramasse' => Livraison::where('status', 'ramasse')->count(),
                'en_transit' => Livraison::where('status', 'en_transit')->count(),
                'prise_en_charge_livraison' => Livraison::where('status', 'prise_en_charge_livraison')->count(),
                'livre' => $totalTerminees,
                'annule' => $totalAnnulees,
            ];

            // Évolution mensuelle (derniers 6 mois)
            $evolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $month = $date->format('Y-m');
                $monthLabel = $date->locale('fr_FR')->isoFormat('MMM YYYY');

                $evolution[$monthLabel] = [
                    'total' => Livraison::whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                    'terminees' => Livraison::where('status', 'livre')
                        ->whereYear('created_at', $date->year)
                        ->whereMonth('created_at', $date->month)
                        ->count(),
                ];
            }

            // Taux de réussite
            $tauxReussite = $totalLivraisons > 0
                ? round(($totalTerminees / $totalLivraisons) * 100, 2)
                : 0;

            // Taux d'annulation
            $tauxAnnulation = $totalLivraisons > 0
                ? round(($totalAnnulees / $totalLivraisons) * 100, 2)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totaux' => [
                        'total' => $totalLivraisons,
                        'en_attente' => $totalEnAttente,
                        'en_cours' => $totalEnCours,
                        'terminees' => $totalTerminees,
                        'annulees' => $totalAnnulees,
                    ],
                    'par_statut' => $parStatut,
                    'taux' => [
                        'reussite' => $tauxReussite,
                        'annulation' => $tauxAnnulation,
                        'en_cours' => $totalLivraisons > 0
                            ? round(($totalEnCours / $totalLivraisons) * 100, 2)
                            : 0,
                    ],
                    'evolution' => $evolution,
                    'dernieres_24h' => Livraison::where('created_at', '>=', Carbon::now()->subDay())
                        ->count(),
                    'derniere_semaine' => Livraison::where('created_at', '>=', Carbon::now()->subWeek())
                        ->count(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Erreur statistiques générales: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les livraisons en Excel, CSV ou PDF
     */
    public function exportExcel(Request $request)
    {
        // Vérifier si admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }

        try {
            // Récupérer les paramètres de filtrage
            $search = $request->query('search', '');
            $status = $request->query('status', '');
            $startDate = $request->query('startDate', '');
            $endDate = $request->query('endDate', '');
            $format = $request->query('format', 'xlsx');

            Log::info('Export livraisons demandé avec paramètres:', [
                'format' => $format,
                'search' => $search,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);

            // Si format PDF, utiliser la méthode existante
            if ($format === 'pdf') {
                return $this->exportPDF($request);
            }

            // Vérifier le nombre de résultats
            $countQuery = Livraison::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('code_pin', 'like', '%' . $this->search . '%')
                            ->orWhereHas('client.user', function ($q) {
                                $q->where('nom', 'like', '%' . $this->search . '%')
                                    ->orWhere('prenom', 'like', '%' . $this->search . '%');
                            })
                            ->orWhereHas('demandeLivraison.colis', function ($q) {
                                $q->where('colis_label', 'like', '%' . $this->search . '%');
                            });
                    });
                })
                ->when($status, function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($startDate, function ($q) use ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                })
                ->when($endDate, function ($q) use ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                });

            $count = $countQuery->count();

            // Limiter à 10,000 lignes maximum
            if ($count > 10000) {
                Log::warning('Trop de livraisons pour l\'export Excel: ' . $count);

                return response()->json([
                    'success' => false,
                    'message' => 'Trop de données à exporter (' . $count . ' livraisons). ' .
                        'Veuillez appliquer des filtres plus restrictifs ou ' .
                        'contacter l\'administrateur système.',
                ], 400);
            }

            // Générer un nom de fichier unique
            $filename = 'livraisons-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $format;

            // Créer l'instance d'export avec les filtres
            $export = new LivraisonsExport($search, $status, $startDate, $endDate);

            // Exporter selon le format demandé
            switch ($format) {
                case 'csv':
                    return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::CSV, [
                        'Content-Type' => 'text/csv',
                    ]);
                default:
                    return Excel::download($export, $filename, \Maatwebsite\Excel\Excel::XLSX, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportExcel livraisons: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Message d'erreur adapté
            if (strpos($e->getMessage(), 'memory') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de mémoire lors de l\'export. ' .
                        'Veuillez appliquer des filtres plus restrictifs ' .
                        'ou contacter l\'administrateur système.',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les livraisons en PDF
     */
    public function exportPDF(Request $request)
    {
        // Vérifier si admin
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }

        try {
            // Récupérer les paramètres de filtrage
            $search = $request->query('search', '');
            $status = $request->query('status', '');
            $startDate = $request->query('startDate', '');
            $endDate = $request->query('endDate', '');

            Log::info('Export PDF livraisons avec paramètres:', [
                'search' => $search,
                'status' => $status,
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);

            // Récupérer les livraisons avec filtres
            $query = Livraison::query()
                ->with([
                    'client.user',
                    'demandeLivraison.colis',
                    'livreurRamasseur.user',
                    'livreurDistributeur.user',
                    'demandeLivraison.destinataire.user'
                ])
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('code_pin', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhereHas('client.user', function ($q) use ($search) {
                                $q->where('nom', 'like', "%{$search}%")
                                    ->orWhere('prenom', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->orWhereHas('demandeLivraison.colis', function ($q) use ($search) {
                                $q->where('colis_label', 'like', "%{$search}%");
                            })
                            ->orWhereHas('demandeLivraison.destinataire.user', function ($q) use ($search) {
                                $q->where('nom', 'like', "%{$search}%")
                                    ->orWhere('prenom', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($status, function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($startDate, function ($q) use ($startDate) {
                    $q->whereDate('created_at', '>=', $startDate);
                })
                ->when($endDate, function ($q) use ($endDate) {
                    $q->whereDate('created_at', '<=', $endDate);
                })
                ->orderBy('created_at', 'desc');

            $livraisons = $query->get();

            Log::info('Nombre de livraisons trouvées pour PDF:', ['count' => $livraisons->count()]);

            // Calculer les statistiques
            $stats = [
                'total' => $livraisons->count(),
                'en_attente' => $livraisons->where('status', 'en_attente')->count(),
                'en_cours' => $livraisons->whereNotIn('status', ['en_attente', 'livre', 'annule'])->count(),
                'livre' => $livraisons->where('status', 'livre')->count(),
                'annule' => $livraisons->where('status', 'annule')->count(),
            ];

            // Traduire les statuts
            $statusLabels = [
                'en_attente' => 'En attente',
                'prise_en_charge_ramassage' => 'Prise en charge ramassage',
                'ramasse' => 'Ramasse',
                'en_transit' => 'En transit',
                'prise_en_charge_livraison' => 'Prise en charge livraison',
                'livre' => 'Livré',
                'annule' => 'Annulé',
            ];

            $statusFilterLabel = $status ? ($statusLabels[$status] ?? $status) : 'Tous';

            // Préparer les données pour la vue
            $data = [
                'livraisons' => $livraisons,
                'stats' => $stats,
                'filters' => [
                    'search' => $search,
                    'status' => $statusFilterLabel,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ],
                'statusLabels' => $statusLabels,
            ];

            // Générer le nom de fichier
            $filename = 'livraisons-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.pdf';

            // Générer le PDF
            $pdf = PDF::loadView('pdf.livraisons', $data);

            // Options PDF
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

            Log::info('PDF livraisons généré avec succès, téléchargement...');

            // Retourner le PDF pour téléchargement
            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du PDF des livraisons: ' . $e->getMessage());
            Log::error('Trace:', $e->getTrace());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage(),
                'error' => env('APP_DEBUG') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    private function calculerCommissionsLivraison(Livraison $livraison): array
    {
        try {
            $demande = $livraison->demandeLivraison;

            if (!$demande) {
                return [
                    'success' => false,
                    'message' => 'Demande de livraison non trouvée'
                ];
            }

            $prixLivraison = (float) ($demande->prix ?? 0);

            if ($prixLivraison <= 0) {
                return [
                    'success' => false,
                    'message' => 'Le prix de la livraison est invalide ou nul'
                ];
            }

            // Récupérer les pourcentages de commission depuis la configuration
            $pourcentageDepart = CommissionConfig::getValue('commission_depart_default') ?? 25;
            $pourcentageArrivee = CommissionConfig::getValue('commission_arrivee_default') ?? 25;

            // Calculer les montants
            $montantDepart = round($prixLivraison * ($pourcentageDepart / 100), 2);
            $montantArrivee = round($prixLivraison * ($pourcentageArrivee / 100), 2);
            $montantAdmin = $prixLivraison - $montantDepart - $montantArrivee;

            // Récupérer les gestionnaires
            $gestionnaireDepart = $this->getGestionnaireByWilaya($demande->wilaya_depot);
            $gestionnaireArrivee = $this->getGestionnaireByWilaya($demande->wilaya);

            $gainsEnregistres = [];

            // Enregistrer le gain pour la wilaya de départ
            if ($gestionnaireDepart && $montantDepart > 0) {
                $gainDepart = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireDepart->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'depart',
                    'montant_commission' => $montantDepart,
                    'pourcentage_applique' => $pourcentageDepart,
                    'date_calcul' => now(),
                    'status' => 'en_attente'  // ✅ CORRIGÉ: 'calcule' → 'en_attente'
                ]);
                $gainsEnregistres['depart'] = $gainDepart;
            }

            // Enregistrer le gain pour la wilaya d'arrivée
            if ($gestionnaireArrivee && $montantArrivee > 0) {
                $gainArrivee = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireArrivee->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'arrivee',
                    'montant_commission' => $montantArrivee,
                    'pourcentage_applique' => $pourcentageArrivee,
                    'date_calcul' => now(),
                    'status' => 'en_attente'  // ✅ CORRIGÉ: 'calcule' → 'en_attente'
                ]);
                $gainsEnregistres['arrivee'] = $gainArrivee;
            }

            return [
                'success' => true,
                'data' => [
                    'prix_livraison' => $prixLivraison,
                    'pourcentage_depart' => $pourcentageDepart,
                    'montant_depart' => $montantDepart,
                    'gestionnaire_depart' => $gestionnaireDepart?->user?->nom . ' ' . $gestionnaireDepart?->user?->prenom,
                    'pourcentage_arrivee' => $pourcentageArrivee,
                    'montant_arrivee' => $montantArrivee,
                    'gestionnaire_arrivee' => $gestionnaireArrivee?->user?->nom . ' ' . $gestionnaireArrivee?->user?->prenom,
                    'montant_admin' => $montantAdmin,
                    'gains_enregistres' => $gainsEnregistres
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Erreur calcul commissions: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du calcul des commissions: ' . $e->getMessage()
            ];
        }
    }

    private function getGestionnaireByWilaya($wilayaId)
    {
        if (!$wilayaId) {
            return null;
        }

        // Mapping des noms de wilayas vers leurs codes
        $wilayaMapping = [
            'Adrar' => '01',
            'Chlef' => '02',
            'Laghouat' => '03',
            'Oum El Bouaghi' => '04',
            'Batna' => '05',
            'Béjaïa' => '06',
            'Biskra' => '07',
            'Béchar' => '08',
            'Blida' => '09',
            'Bouira' => '10',
            'Tamanrasset' => '11',
            'Tébessa' => '12',
            'Tlemcen' => '13',
            'Tiaret' => '14',
            'Tizi Ouzou' => '15',
            'Alger' => '16',
            'Djelfa' => '17',
            'Jijel' => '18',
            'Sétif' => '19',
            'Saïda' => '20',
            'Skikda' => '21',
            'Sidi Bel Abbès' => '22',
            'Annaba' => '23',
            'Guelma' => '24',
            'Constantine' => '25',
            'Médéa' => '26',
            'Mostaganem' => '27',
            "M'Sila" => '28',
            'Mascara' => '29',
            'Ouargla' => '30',
            'Oran' => '31',
            'El Bayadh' => '32',
            'Illizi' => '33',
            'Bordj Bou Arréridj' => '34',
            'Boumerdès' => '35',
            'El Tarf' => '36',
            'Tindouf' => '37',
            'Tissemsilt' => '38',
            'El Oued' => '39',
            'Khenchela' => '40',
            'Souk Ahras' => '41',
            'Tipaza' => '42',
            'Mila' => '43',
            'Aïn Defla' => '44',
            'Naâma' => '45',
            'Aïn Témouchent' => '46',
            'Ghardaïa' => '47',
            'Relizane' => '48',
            'Timimoun' => '49',
            'Bordj Badji Mokhtar' => '50',
            'Ouled Djellal' => '51',
            'Béni Abbès' => '52',
            'In Salah' => '53',
            'In Guezzam' => '54',
            'Touggourt' => '55',
            'Djanet' => '56',
            "El M'Ghair" => '57',
            'El Meniaa' => '58'
        ];

        // Nettoyer la valeur (enlever les espaces)
        $wilayaId = trim($wilayaId);

        // Si c'est un nom de wilaya (ex: "Alger"), le convertir en code
        if (isset($wilayaMapping[$wilayaId])) {
            $wilayaId = $wilayaMapping[$wilayaId];
        }
        // Sinon, si c'est un nombre, le formater sur 2 chiffres
        elseif (is_numeric($wilayaId)) {
            $wilayaId = str_pad($wilayaId, 2, '0', STR_PAD_LEFT);
        }

        // Log pour déboguer
        Log::info("Recherche gestionnaire pour wilaya: " . $wilayaId);

        return Gestionnaire::where('wilaya_id', $wilayaId)
            ->where('status', 'active')
            ->with('user')
            ->first();
    }

    /**
     * Mettre à jour le statut de paiement d'une livraison (admin)
     */
    public function updatePaymentStatus(Request $request, $id): JsonResponse
    {
        Log::info("Admin - Début mise à jour du statut de paiement pour livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|string|in:pending,available,in_transit,paid'
        ]);

        if ($validator->fails()) {
            Log::warning("Validation échouée pour mise à jour statut paiement: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour mise à jour statut paiement: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $ancienStatus = $livraison->payment_status ?? 'pending';
        $nouveauStatus = $validatedData['payment_status'];

        Log::info("Admin - Mise à jour du statut de paiement de {$ancienStatus} à {$nouveauStatus} pour la livraison " . $id);

        $livraison->update([
            'payment_status' => $nouveauStatus
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de paiement mis à jour avec succès',
            'data' => [
                'id' => $livraison->id,
                'payment_status' => $livraison->payment_status
            ]
        ], 200);
    }



/**
 * Afficher le formulaire d'édition d'une livraison (récupérer toutes les données)
 */
public function edit($id): JsonResponse
{
    Log::info("Admin - Récupération des données pour édition de la livraison ID: " . $id);

    $livraison = $this->findLivraison($id);

    if (!$livraison) {
        return response()->json([
            'success' => false,
            'message' => 'Livraison introuvable',
        ], 404);
    }

    // Charger toutes les relations nécessaires
    $livraison->load([
        'client.user',
        'demandeLivraison' => function($q) {
            $q->with(['client.user', 'destinataire.user', 'colis']);
        },
        'livreurRamasseur.user',
        'livreurDistributeur.user',
        'commentaires'
    ]);

    // Récupérer la demande de livraison associée
    $demande = $livraison->demandeLivraison;

    return response()->json([
        'success' => true,
        'data' => [
            'livraison' => [
                'id' => $livraison->id,
                'client_id' => $livraison->client_id,
                'demande_livraisons_id' => $livraison->demande_livraisons_id,
                'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
                'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
                'bordereau_id' => $livraison->bordereau_id,
                'code_pin' => $livraison->code_pin,
                'date_ramassage' => $livraison->date_ramassage,
                'date_livraison' => $livraison->date_livraison,
                'status' => $livraison->status,
                'payment_status' => $livraison->payment_status,
                'created_at' => $livraison->created_at,
                'updated_at' => $livraison->updated_at,
            ],
            'demande_livraison' => $demande ? [
                'id' => $demande->id,
                'client_id' => $demande->client_id,
                'destinataire_id' => $demande->destinataire_id,
                'colis_id' => $demande->colis_id,
                'addresse_depot' => $demande->addresse_depot,
                'addresse_delivery' => $demande->addresse_delivery,
                'info_additionnel' => $demande->info_additionnel,
                'prix' => $demande->prix,
                'wilaya_depot' => $demande->wilaya_depot,
                'commune_depot' => $demande->commune_depot,
                'wilaya' => $demande->wilaya,
                'commune' => $demande->commune,
                'depose_au_depot' => $demande->depose_au_depot,
                'lat_depot' => $demande->lat_depot,
                'lng_depot' => $demande->lng_depot,
                'lat_delivery' => $demande->lat_delivery,
                'lng_delivery' => $demande->lng_delivery,
            ] : null,
            'colis' => $demande && $demande->colis ? [
                'id' => $demande->colis->id,
                'colis_label' => $demande->colis->colis_label,
                'poids' => $demande->colis->poids,
                'colis_type' => $demande->colis->colis_type,
                'colis_prix' => $demande->colis->colis_prix,
                'colis_photo' => $demande->colis->colis_photo,
                'colis_photo_url' => $demande->colis->colis_photo_url,
            ] : null,
            'client' => $livraison->client ? [
                'id' => $livraison->client->id,
                'user_id' => $livraison->client->user_id,
                'nom' => $livraison->client->user?->nom,
                'prenom' => $livraison->client->user?->prenom,
                'email' => $livraison->client->user?->email,
                'telephone' => $livraison->client->user?->telephone,
            ] : null,
            'destinataire' => $demande && $demande->destinataire ? [
                'id' => $demande->destinataire->id,
                'user_id' => $demande->destinataire->user_id,
                'nom' => $demande->destinataire->user?->nom,
                'prenom' => $demande->destinataire->user?->prenom,
                'email' => $demande->destinataire->user?->email,
                'telephone' => $demande->destinataire->user?->telephone,
            ] : null,
            'livreur_ramasseur' => $livraison->livreurRamasseur ? [
                'id' => $livraison->livreurRamasseur->id,
                'nom' => $livraison->livreurRamasseur->user?->nom,
                'prenom' => $livraison->livreurRamasseur->user?->prenom,
                'telephone' => $livraison->livreurRamasseur->user?->telephone,
            ] : null,
            'livreur_distributeur' => $livraison->livreurDistributeur ? [
                'id' => $livraison->livreurDistributeur->id,
                'nom' => $livraison->livreurDistributeur->user?->nom,
                'prenom' => $livraison->livreurDistributeur->user?->prenom,
                'telephone' => $livraison->livreurDistributeur->user?->telephone,
            ] : null,
        ]
    ], 200);
}

/**
 * Mettre à jour une livraison complètement (admin seulement)
 */
public function update(Request $request, $id): JsonResponse
{
    Log::info("Admin - Début mise à jour complète de la livraison ID: " . $id);

    $validator = Validator::make($request->all(), [
        // Livraison fields
        'client_id' => 'sometimes|string|exists:clients,id',
        'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',
        'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
        'code_pin' => 'nullable|string|size:5',
        'date_ramassage' => 'nullable|date',
        'date_livraison' => 'nullable|date',
        'status' => 'sometimes|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        'payment_status' => 'sometimes|string|in:pending,available,in_transit,paid',

        // Demande livraison fields
        'addresse_depot' => 'nullable|string|max:255',
        'addresse_delivery' => 'nullable|string|max:255',
        'info_additionnel' => 'nullable|string',
        'prix' => 'nullable|numeric|min:0',
        'wilaya_depot' => 'nullable|string|max:255',
        'commune_depot' => 'nullable|string|max:255',
        'wilaya' => 'nullable|string|max:255',
        'commune' => 'nullable|string|max:255',

        // Colis fields
        'colis_label' => 'nullable|string|max:255',
        'colis_poids' => 'nullable|numeric|min:0',
        'colis_type' => 'nullable|string|max:255',
        'colis_prix' => 'nullable|numeric|min:0',
    ]);

    if ($validator->fails()) {
        Log::warning("Validation échouée pour mise à jour livraison: " . json_encode($validator->errors()));
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validatedData = $validator->validated();

    try {
        DB::beginTransaction();

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $demande = $livraison->demandeLivraison;

        // 1. Mettre à jour la livraison
        $livraisonFields = array_intersect_key($validatedData, array_flip([
            'client_id', 'livreur_distributeur_id', 'livreur_ramasseur_id',
            'code_pin', 'date_ramassage', 'date_livraison', 'status', 'payment_status'
        ]));

        if (!empty($livraisonFields)) {
            $livraison->update($livraisonFields);
            Log::info("Livraison mise à jour avec les champs: ", $livraisonFields);
        }

        // 2. Mettre à jour la demande de livraison si existe
        if ($demande) {
            $demandeFields = array_intersect_key($validatedData, array_flip([
                'addresse_depot', 'addresse_delivery', 'info_additionnel', 'prix',
                'wilaya_depot', 'commune_depot', 'wilaya', 'commune'
            ]));

            if (!empty($demandeFields)) {
                $demande->update($demandeFields);
                Log::info("Demande livraison mise à jour avec les champs: ", $demandeFields);
            }
        }

        // 3. Mettre à jour le colis si existe
        $colis = $demande ? $demande->colis : null;
        if ($colis) {
            $colisFields = array_intersect_key($validatedData, array_flip([
                'colis_label', 'colis_poids', 'colis_type', 'colis_prix'
            ]));

            // Renommer colis_poids -> poids
            if (isset($colisFields['colis_poids'])) {
                $colisFields['poids'] = $colisFields['colis_poids'];
                unset($colisFields['colis_poids']);
            }

            if (!empty($colisFields)) {
                $colis->update($colisFields);
                Log::info("Colis mis à jour avec les champs: ", $colisFields);
            }
        }

        // Journaliser la modification (optionnel - créer une table d'historique)
        $this->logLivraisonModification($livraison, auth()->id(), $validatedData);

        DB::commit();

        // Recharger toutes les relations
        $livraison->load([
            'client.user',
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurRamasseur.user',
            'livreurDistributeur.user'
        ]);

        Log::info("Livraison mise à jour avec succès: " . $id);

        return response()->json([
            'success' => true,
            'message' => 'Livraison mise à jour avec succès',
            'data' => $livraison
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Erreur lors de la mise à jour de la livraison {$id}: " . $e->getMessage());
        Log::error("Trace: " . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de la livraison',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Journaliser les modifications d'une livraison
 */
private function logLivraisonModification($livraison, $adminId, $changes)
{
    try {
        // Optionnel: créer une table 'livraison_historique' pour tracer les modifications
        // Pour l'instant, on log simplement dans les logs
        Log::info("LIVRAISON MODIFIEE", [
            'livraison_id' => $livraison->id,
            'admin_id' => $adminId,
            'changes' => $changes,
            'timestamp' => now()
        ]);
    } catch (\Exception $e) {
        // Ne pas bloquer si le logging échoue
        Log::warning("Erreur lors du logging de modification: " . $e->getMessage());
    }
}

/**
 * Récupérer les clients pour le select (admin)
 */
public function getClientsForSelect(): JsonResponse
{
    $clients = Client::with('user')->get()->map(function ($client) {
        return [
            'id' => $client->id,
            'label' => trim(($client->user?->prenom ?? '') . ' ' . ($client->user?->nom ?? '')),
            'telephone' => $client->user?->telephone ?? '',
            'email' => $client->user?->email ?? '',
        ];
    });

    return response()->json($clients, 200);
}

/**
 * Récupérer les livreurs pour le select (admin)
 */
public function getLivreursForSelect(): JsonResponse
{
    $livreurs = Livreur::with('user')->where('desactiver', false)->get()->map(function ($livreur) {
        return [
            'id' => $livreur->id,
            'label' => trim(($livreur->user?->prenom ?? '') . ' ' . ($livreur->user?->nom ?? '')),
            'telephone' => $livreur->user?->telephone ?? '',
            'type' => $livreur->type,
        ];
    });

    return response()->json($livreurs, 200);
}

/**
 * Récupérer l'historique des statuts d'une livraison
 */
public function getHistory($id): JsonResponse
{
    // Optionnel: implémenter avec une table d'historique
    // Pour l'instant, retourner un tableau vide
    return response()->json([
        'success' => true,
        'data' => []
    ], 200);
}


/**
 * Créer une nouvelle livraison (admin)
 */
public function store(Request $request): JsonResponse
{
    Log::info("Admin - Début création d'une nouvelle livraison");

    $validator = Validator::make($request->all(), [
        // Client
        'client_id' => 'required|string|exists:clients,id',

        // Livreurs
        'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
        'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',

        // Statuts
        'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        'payment_status' => 'required|string|in:pending,available,in_transit,paid',

        // Dates
        'date_ramassage' => 'nullable|date',
        'date_livraison' => 'nullable|date',

        // Destinataire
        'destinataire_nom' => 'required|string|max:255',
        'destinataire_email' => 'nullable|email|max:255',
        'destinataire_telephone' => 'required|string|max:20',

        // Adresses
        'addresse_depot' => 'nullable|string|max:500',
        'addresse_delivery' => 'required|string|max:500',
        'wilaya_depot' => 'nullable|string|max:255',
        'commune_depot' => 'nullable|string|max:255',
        'wilaya' => 'required|string|max:255',
        'commune' => 'required|string|max:255',

        // Colis
        'colis_label' => 'nullable|string|max:255',
        'colis_poids' => 'required|numeric|min:0.1',
        'colis_type' => 'nullable|string|max:255',
        'colis_prix' => 'nullable|numeric|min:0',

        // Prix
        'prix' => 'required|numeric|min:0',

        // Mode dépôt
        'depose_au_depot' => 'boolean',

        // Informations supplémentaires
        'info_additionnel' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        Log::warning("Validation échouée pour création livraison: " . json_encode($validator->errors()));
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $validator->errors(),
        ], 422);
    }

    $validatedData = $validator->validated();

    try {
        DB::beginTransaction();

        // 1. Créer ou récupérer le destinataire
        $destinataire = $this->createOrGetDestinataire($validatedData);

        // 2. Créer le colis
        $colis = $this->createColis($validatedData);

        // 3. Créer la demande de livraison
        $demande = $this->createDemandeLivraison($validatedData, $destinataire->id, $colis->id);

        // 4. Déterminer le statut initial en fonction du mode dépôt
        $isDepotClient = $validatedData['depose_au_depot'] ?? false;
        $initialStatus = $isDepotClient ? 'en_transit' : ($validatedData['status'] ?? 'en_attente');

        // 5. Créer la livraison
        $livraison = Livraison::create([
            'id' => (string) Str::uuid(),
            'client_id' => $validatedData['client_id'],
            'demande_livraisons_id' => $demande->id,
            'livreur_distributeur_id' => $validatedData['livreur_distributeur_id'] ?? null,
            'livreur_ramasseur_id' => $validatedData['livreur_ramasseur_id'] ?? null,
            'code_pin' => $this->generateUniquePin(),
            'date_ramassage' => $validatedData['date_ramassage'] ?? null,
            'date_livraison' => $validatedData['date_livraison'] ?? null,
            'status' => $initialStatus,
            'payment_status' => $validatedData['payment_status'] ?? 'pending',
        ]);

        DB::commit();

        Log::info("Livraison créée avec succès par admin: " . $livraison->id);

        // Charger les relations pour la réponse
        $livraison->load([
            'client.user',
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'demandeLivraison.colis',
            'livreurRamasseur.user',
            'livreurDistributeur.user'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison créée avec succès',
            'data' => $livraison
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Erreur lors de la création de la livraison: " . $e->getMessage());
        Log::error("Trace: " . $e->getTraceAsString());

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de la livraison',
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Créer ou récupérer un destinataire
 */
private function createOrGetDestinataire($data)
{
    $user = null;

    // Chercher par email
    if (!empty($data['destinataire_email'])) {
        $user = User::where('email', $data['destinataire_email'])->first();
    }

    // Chercher par téléphone
    if (!$user && !empty($data['destinataire_telephone'])) {
        $user = User::where('telephone', $data['destinataire_telephone'])->first();
    }

    // Créer un nouvel utilisateur si non trouvé
    if (!$user) {
        $nameParts = explode(' ', $data['destinataire_nom'], 2);
        $prenom = $nameParts[0] ?? '';
        $nom = $nameParts[1] ?? '';

        $user = User::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $data['destinataire_email'] ?? null,
            'telephone' => $data['destinataire_telephone'],
            'password' => bcrypt(Str::random(10)),
            'role' => 'client_destinataire',
            'actif' => true,
        ]);
    }

    // Créer ou récupérer le client destinataire
    return Client::firstOrCreate(
        ['user_id' => $user->id],
        ['status' => 'active']
    );
}

/**
 * Créer un colis
 */
private function createColis($data)
{
    $colisLabel = $data['colis_label'] ?? 'COLIS-' . strtoupper(uniqid());

    return Colis::create([
        'poids' => $data['colis_poids'],
        'colis_type' => $data['colis_type'] ?? 'Standard',
        'colis_label' => $colisLabel,
        'colis_prix' => $data['colis_prix'] ?? 0,
    ]);
}

/**
 * Créer une demande de livraison
 */
private function createDemandeLivraison($data, $destinataireId, $colisId)
{
    $isDepotClient = $data['depose_au_depot'] ?? false;

    return DemandeLivraison::create([
        'client_id' => $data['client_id'],
        'destinataire_id' => $destinataireId,
        'colis_id' => $colisId,
        'depose_au_depot' => $isDepotClient,
        'addresse_depot' => $data['addresse_depot'] ?? null,
        'addresse_delivery' => $data['addresse_delivery'],
        'info_additionnel' => $data['info_additionnel'] ?? null,
        'prix' => $data['prix'],
        'wilaya_depot' => $data['wilaya_depot'] ?? null,
        'commune_depot' => $data['commune_depot'] ?? null,
        'wilaya' => $data['wilaya'],
        'commune' => $data['commune'],
    ]);
}

/**
 * Générer un code PIN unique
 */
public function generatePin(): JsonResponse
{
    return response()->json([
        'pin' => $this->generateUniquePin()
    ], 200);
}

/**
 * Générer un code PIN unique à 5 chiffres
 */
private function generateUniquePin(): string
{
    do {
        $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    } while (Livraison::where('code_pin', $pin)->exists());

    return $pin;
}



}