<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use App\Models\Commentaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\DemandeLivraison;
use App\Enums\NotificationType;
use App\Models\Livreur;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\NewLivreurAssignmentMail;
use App\Models\CommissionConfig;
use App\Models\Gestionnaire;
use App\Models\GestionnaireGain;

class LivraisonController extends Controller
{
    /**
     * Afficher toutes les livraisons.
     */
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

        // Récupérer les données des livraisons
        // Initialiser les compteurs



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

        // Récupérer les données des livraisons
        // Initialiser les compteurs



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
     * Créer une nouvelle livraison.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'demande_livraison_id' => 'required|integer|exists:demandes_livraison,id',
            'livreur_id' => 'required|string|exists:users,id',
            'status' => 'required|string|in:en_attente,en_cours,livree,annulee',
            'date_livraison' => 'required|date',
        ]);

        try {
            $livraison = Livraison::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Livraison créée avec succès',
                'data' => $livraison,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une livraison spécifique.
     */
    public function show($id): JsonResponse
{
    $livraison = Livraison::find($id);

    if (!$livraison) {
        return response()->json([
            'success' => false,
            'message' => 'Livraison introuvable',
        ], 404);
    }

    return response()->json(
        [
            'id' => $livraison->id,
            'client_id' => $livraison->client_id,
            'created_at' => $livraison->created_at,
            'demande_livraisons_id'=> $livraison->demande_livraisons_id,
            'livreur_distributeur_id' => $livraison->livreur_distributeur_id,
            'livreur_ramasseur_id' => $livraison->livreur_ramasseur_id,
            'bordereau_id'=> $livraison->bordereau_id,
            'code_pin'=> $livraison->code_pin,
            'date_ramassage' => $livraison->date_ramassage,
            'date_livraison' => $livraison->date_livraison,
            'status' => $livraison->status,
            'payment_status' => $livraison->payment_status,  // ✅ AJOUTER CETTE LIGNE
            'livreur_distributeur'=> $livraison->livreurDistributeur?->user,
            'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
            'demande_livraison' => $livraison->demandeLivraison->load([
                'client',
                'destinataire',
                'colis',
            ]),
            'destinataire'=> $livraison->demandeLivraison->destinataire?->user,
            'client'=> $livraison->client?->user,
            'commentaires' => $livraison->commentaires,
            'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
        ],
        200
    );
}


    public function getByClient($id): JsonResponse
    {
        try {
            $livraisons = Livraison::where('client_id', $id)
                ->with([
                    'demandeLivraison.colis',
                    'demandeLivraison.destinataire.user',
                    'client.user',
                    'livreurDistributeur.user',
                    'livreurRamasseur.user',
                    'bordereau',
                    'commentaires'
                ])
                ->get();

            $datas = [];

            foreach ($livraisons as $livraison) {
                $demande = $livraison->demandeLivraison;

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

                    'demande_livraison' => [
                        'id' => $demande->id,
                        'client_id' => $demande->client_id,
                        'destinataire_id' => $demande->destinataire_id,
                        'colis_id' => $demande->colis_id,

                        'addresse_depot' => $demande->addresse_depot,
                        'addresse_delivery' => $demande->addresse_delivery,
                        'info_additionnel' => $demande->info_additionnel,
                        'prix' => $demande->prix,

                        'lat_depot' => $demande->lat_depot,
                        'lng_depot' => $demande->lng_depot,
                        'lat_delivery' => $demande->lat_delivery,
                        'lng_delivery' => $demande->lng_delivery,

                        // === CHAMPS QUI MANQUAIENT ===
                        'wilaya_depot' => $demande->wilaya_depot,
                        'commune_depot' => $demande->commune_depot,
                        'wilaya' => $demande->wilaya,
                        'commune' => $demande->commune,

                        'created_at' => $demande->created_at,
                        'updated_at' => $demande->updated_at,

                        'colis' => $demande->colis,
                    ],

                    'destinataire' => $livraison->demandeLivraison?->destinataire,
                    'client' => $livraison->client,
                    'commentaires' => $livraison->commentaires,
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
                ];
            }

            return response()->json($datas);   // ← sans le wrapper success/data

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons : ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByLivreur($id): JsonResponse
    {
        try {
            $livraisons = Livraison::where([
                'livreur_distributeur_id' => $id,
                'livreur_ramasseur_id' => $id,
            ])->orWhere([
                'livreur_distributeur_id' => $id,
            ])->orWhere([
                'livreur_ramasseur_id' => $id,
            ])->get();

            // Vérifier si la livraison existe

            /*
            if (!$livraisons) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison trouvée pour ce livreur.',
                ], 404);
            }
            */


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
            return response()->json(
                // 'success' => true,
                //'data' => [
                $datas,
                /*'demande_livraison' => $livraison->demandeLivraison,
                    'commentaires' => Commentaire::firstWhere('livraison_id', $livraison->id),
                    'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
                    ,
                    */
                200
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Mettre à jour une livraison.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $validatedData = $request->validate([
            'demande_livraison_id' => 'sometimes|integer|exists:demandes_livraison,id',
            'livreur_id' => 'sometimes|integer|exists:users,id',
            'status' => 'sometimes|string|in:en_attente,en_cours,livree,annulee',
            'date_livraison' => 'sometimes|date',
        ]);

        $livraison->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Livraison mise à jour avec succès',
            'data' => $livraison,
        ], 200);
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
                // Type 2 : Livreurs distributeurs
                if ($livraison->status == 'en_transit') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis est deja en transit, vous ne pouvez plus  attribuer un autre distributeur',
                    ], 400);
                } else {
                    $livraison->update([
                        'livreur_distributeur_id' => $validatedData['livreur_id'],
                        'status' => 'prise_en_charge_livraison'
                    ]);
                }
            } elseif ($type == 1) {
                // Type 1 : Livreurs ramasseurs
                if ($livraison->status == 'ramasse') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Le colis a deja ete ramasse , vous ne pouvez plus  attribuer un autre ramasseur',
                    ], 400);
                } else {

                    $livraison->update([
                        'livreur_ramasseur_id' => $validatedData['livreur_id'],
                        'status' => 'prise_en_charge_ramassage'
                    ]);
                }
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

            // Envoyer un email au livreur
            try {
                $livreur = Livreur::with('user')->find($validatedData['livreur_id']);

                if ($livreur && $livreur->user && $livreur->user->email) {
                    Mail::to($livreur->user->email)->send(new NewLivreurAssignmentMail($livraison, $livreur->user, $type));
                    Log::info('Email d\'assignation envoyé au livreur: ' . $livreur->user->email . ' (Type: ' . ($type === 1 ? 'Ramasseur' : 'Distributeur') . ')');
                } else {
                    Log::warning('Livreur non trouvé ou email du livreur non défini - ID: ' . $validatedData['livreur_id']);
                }
            } catch (\Exception $e) {
                // L'envoi d'email a échoué, mais on continue l'action
                Log::error('Erreur lors de l\'envoi du mail au livreur: ' . $e->getMessage());
            }

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
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        $livraison->delete();

        return response()->json([
            'success' => true,
            'message' => 'Livraison supprimée avec succès',
        ], 200);
    }

    public function destroyByClient($id): JsonResponse
    {
        $livraison = Livraison::find($id);

        if (!$livraison) {
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        } else {
            if ($livraison->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'La livraison ne peut être supprimée que si elle est en attente',
                ], 400);
            }
        }

        $livraison->delete();

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
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            DB::beginTransaction();

            $livraison = Livraison::find($id);

            if (!$livraison) {
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

            // Si la livraison est annulée, supprimer les gains associés
            if ($nouveauStatus === 'annule' && $ancienStatus === 'livre') {
                GestionnaireGain::where('livraison_id', $livraison->id)->delete();
                Log::info("Gains supprimés pour la livraison annulée {$id}");
            }

            DB::commit();

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
            Log::error("Erreur lors de la mise à jour du statut: " . $e->getMessage());

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
            \Log::info('Recherche du colis: ' . $colis_label);

            // 1. Trouver le colis
            $colis = \App\Models\Colis::where('colis_label', $colis_label)->first();

            if (!$colis) {
                \Log::warning('Colis non trouvé: ' . $colis_label);
                return response()->json([
                    'success' => false,
                    'message' => 'Colis introuvable avec ce code de suivi'
                ], 404);
            }

            \Log::info('Colis trouvé, ID: ' . $colis->id);

            // 2. Trouver la demande de livraison qui a ce colis_id
            $demandeLivraison = \App\Models\DemandeLivraison::where('colis_id', $colis->id)->first();

            if (!$demandeLivraison) {
                \Log::warning('Aucune demande de livraison pour le colis ID: ' . $colis->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune demande de livraison trouvée pour ce colis'
                ], 404);
            }

            \Log::info('Demande de livraison trouvée, ID: ' . $demandeLivraison->id);

            // 3. Trouver la livraison associée à cette demande
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
                \Log::warning('Aucune livraison pour la demande ID: ' . $demandeLivraison->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison en cours pour ce colis'
                ], 404);
            }

            \Log::info('Livraison trouvée, ID: ' . $livraison->id . ', Status: ' . $livraison->status);

            // 4. Retourner les données formatées
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
            \Log::error('Erreur trackByColisLabel: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

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
        try {
            $livraison = Livraison::find($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $data = $this->preparePrintData($livraison);

            $html = View::make('pdf.bordereau', $data)->render();

            return response()->json([
                'success' => true,
                'html' => $html,
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Erreur génération HTML: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du HTML',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Générer et télécharger le PDF du bordereau
     */
    public function generateBordereauPDF($id)
    {
        try {
            $livraison = Livraison::find($id);

            if (!$livraison) {
                return response()->json([
                    'success' => false,
                    'message' => 'Livraison introuvable',
                ], 404);
            }

            $data = $this->preparePrintData($livraison);

            $pdf = Pdf::loadView('pdf.bordereau', $data);

            $pdf->setPaper([0, 0, 380, 700], 'portrait');
            $pdf->setOption('enable_html5_parser', true);
            $pdf->setOption('enable_remote', true);
            $pdf->setOption('defaultFont', 'DejaVu Sans');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isPhpEnabled', false);

            $fileName = 'bordereau_livraison_' . $livraison->id . '_' . date('Ymd_His') . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            \Log::error('Erreur génération PDF: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Préparer les données pour l'impression
     */
    private function preparePrintData($livraison): array
    {
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
        $destinataire = $demande->destinataire->user ?? null;
        $livreurRamasseur = $livraison->livreurRamasseur->user ?? null;
        $livreurDistributeur = $livraison->livreurDistributeur->user ?? null;

        $qrCodeData = json_encode([
            'id' => $livraison->id,
            'code_pin' => $livraison->code_pin,
            'colis_label' => $colis->colis_label ?? 'N/A',
            'status' => $livraison->status,
            'client' => ($client->prenom ?? '') . ' ' . ($client->nom ?? ''),
            'destinataire' => ($destinataire->prenom ?? '') . ' ' . ($destinataire->nom ?? ''),
            'date' => now()->toISOString(),
        ]);

        $qrCode = $this->generateQRCode($qrCodeData);

        $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
        $barcode = $this->generateBarcode($barcodeValue);

        $createdAt = $livraison->created_at
            ? Carbon::parse($livraison->created_at)->locale('fr_FR')->isoFormat('DD/MM/YYYY HH:mm')
            : 'Non définie';

        $dateLivraison = $livraison->date_livraison
            ? Carbon::parse($livraison->date_livraison)->locale('fr_FR')->isoFormat('DD/MM/YYYY HH:mm')
            : 'Non définie';

        $printDate = now()->locale('fr_FR')->isoFormat('DD/MM/YYYY');

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
            'createdAt' => $createdAt,
            'dateLivraison' => $dateLivraison,
            'printDate' => $printDate,
            'statusLabel' => $statusLabel,
        ];
    }

    /**
     * Générer un QR Code en base64
     */
    private function generateQRCode(string $data): string
    {
        try {
            if (class_exists('BaconQrCode\Writer')) {
                $renderer = new ImageRenderer(
                    new RendererStyle(90),
                    new ImagickImageBackEnd()
                );

                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($data);

                return 'data:image/png;base64,' . base64_encode($qrCode);
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur génération QR Code local: ' . $e->getMessage());
        }

        $encodedData = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={$encodedData}&format=png&margin=1";
    }

    /**
     * Générer un code-barres en base64
     */
    private function generateBarcode(string $value): string
    {
        try {
            if (class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
                $generator = new BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50);

                return 'data:image/png;base64,' . base64_encode($barcode);
            }
        } catch (\Exception $e) {
            \Log::warning('Erreur génération code-barres local: ' . $e->getMessage());
        }

        return $this->generateSimpleBarcode($value);
    }

    /**
     * Générer un code-barres simplifié (fallback)
     */
    private function generateSimpleBarcode(string $value): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return 'data:image/svg+xml;base64,' . base64_encode('
            <svg width="250" height="50" xmlns="http://www.w3.org/2000/svg">
                <rect width="250" height="50" fill="white"/>
                <text x="125" y="30" text-anchor="middle" font-family="Arial" font-size="12">' . htmlspecialchars($value) . '</text>
            </svg>
        ');
        }

        $width = 250;
        $height = 50;

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        imagefill($image, 0, 0, $white);

        $charWidth = 3;
        $x = 10;

        for ($i = 0; $i < strlen($value); $i++) {
            $char = ord($value[$i]);
            $barHeight = ($char % 40) + 10;

            imagefilledrectangle($image, $x, 10, $x + $charWidth, 10 + $barHeight, $black);
            $x += $charWidth + 1;
        }

        imagestring($image, 2, $width / 2 - (strlen($value) * 3), $height - 15, $value, $black);

        ob_start();
        imagepng($image);
        $barcode = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($barcode);
    }

    // ==================== MÉTHODES DE GESTION DES COMMISSIONS ====================

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
                    'status' => 'en_attente'
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
                    'status' => 'en_attente'
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

        // Nettoyer la valeur
        $wilayaId = trim($wilayaId);

        // Si c'est un nom de wilaya, le convertir en code
        if (isset($wilayaMapping[$wilayaId])) {
            $wilayaId = $wilayaMapping[$wilayaId];
        }
        // Sinon, si c'est un nombre, le formater sur 2 chiffres
        elseif (is_numeric($wilayaId)) {
            $wilayaId = str_pad($wilayaId, 2, '0', STR_PAD_LEFT);
        }

        Log::info("Recherche gestionnaire pour wilaya: " . $wilayaId);

        return Gestionnaire::where('wilaya_id', $wilayaId)
            ->where('status', 'active')
            ->with('user')
            ->first();
    }
}