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
     * Afficher toutes les livraisons avec calcul du total.
     */
    /**
     * Afficher toutes les livraisons avec calcul du total et type de livraison.
     */
    public function index(): JsonResponse
    {
        $livraisons = Livraison::with([
            'demandeLivraison.colis',
            'demandeLivraison.client.user',
            'demandeLivraison.destinataire.user',
            'livreurDistributeur.user',
            'livreurRamasseur.user',
            'commentaires',
            'bordereau'
        ])->get();

        $datas = [];
        foreach ($livraisons as $livraison) {
            $demande = $livraison->demandeLivraison;
            $colis = $demande?->colis;

            // Récupérer les prix
            $prixColis = (float) ($colis?->colis_prix ?? 0);
            $prixLivraison = (float) ($demande?->prix ?? 0);
            $isLivraisonGratuite = $demande?->livraison_gratuite ?? false;
            $isDepotClient = $demande?->depose_au_depot ?? false;

            // Déterminer le type de livraison
            if ($isLivraisonGratuite) {
                $typeLivraison = "gratuite";
            } elseif ($isDepotClient) {
                $typeLivraison = "depose";
            } else {
                $typeLivraison = "normale";
            }

            // Calculer le total selon le cas
            if ($isLivraisonGratuite) {
                $total = $prixColis - $prixLivraison;
            } else {
                $total = $prixColis + $prixLivraison;
            }

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
                'return_status' => $livraison->return_status,
                'payment_status' => $livraison->payment_status,
                'type_livraison' => $demande?->type_livraison ?? null,
                'livreur_distributeur' => $livraison->livreurDistributeur?->user,
                'livreur_ramasseur' => $livraison->livreurRamasseur?->user,
                'demande_livraison' => $demande?->load([
                    'client',
                    'destinataire',
                    'colis',
                ]),
                'destinataire' => $demande?->destinataire?->user,
                'client' => $livraison->client?->user,
                'commentaires' => $livraison->commentaires,
                'bordereau' => $livraison->bordereau_id ? $livraison->bordereau : null,
                // ✅ NOUVEAUX CHAMPS
                'prix_colis' => $prixColis,
                'prix_livraison' => $prixLivraison,
                'livraison_gratuite' => $isLivraisonGratuite,
                'depose_au_depot' => $isDepotClient,
                'type_livraison_mode' => $typeLivraison, // "normale", "depose", "gratuite"
                'total' => $total,
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

        return response()->json($statuss, 200);
    }

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

        return response()->json($status, 200);
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
     * Afficher une livraison spécifique avec calcul du total.
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

        $demande = $livraison->demandeLivraison;
        $colis = $demande?->colis;

        // Récupérer les prix (le prix livraison reste inchangé)
        $prixColis = (float) ($colis?->colis_prix ?? 0);
        $prixLivraison = (float) ($demande?->prix ?? 0);
        $isLivraisonGratuite = $demande?->livraison_gratuite ?? false;

        // Calculer le total selon le cas
        if ($isLivraisonGratuite) {
            // Livraison gratuite: total = prix colis - prix livraison (remise sur le colis)
            $total = $prixColis - $prixLivraison;
        } else {
            // Cas normal: total = prix colis + prix livraison
            $total = $prixColis + $prixLivraison;
        }

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
                'payment_status' => $livraison->payment_status,
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
                // Informations de prix (prix livraison inchangé)
                'prix_colis' => $prixColis,
                'prix_livraison' => $prixLivraison,  // ← Garde sa valeur réelle
                'livraison_gratuite' => $isLivraisonGratuite,
                'total' => $total,
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

            return response()->json($datas, 200);
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

            return response()->json($datas, 200);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des livraisons du livreur ID {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des livraisons du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignLivreur(Request $request, $id): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'livreur_id' => 'required|string|exists:livreurs,id',
            'type' => 'required|integer|in:1,2',
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

            if ($type == 2) {
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

            DB::commit();

            return response()->json($livraison, 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'attribution du livreur',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

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

    public function updateStatus(Request $request, $id): JsonResponse
    {
        Log::info("Début mise à jour du statut pour livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
            'return_status' => 'nullable|string|in:chez_livreurs,retour_en_traitement,retour_prets'
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

            $updateData = [
                'status' => $nouveauStatus,
            ];

            if ($nouveauStatus === 'annule' && isset($validatedData['return_status'])) {
                $updateData['return_status'] = $validatedData['return_status'];
            }

            $livraison->update($updateData);

            $resultatCommission = null;
            if ($nouveauStatus === 'livre' && $ancienStatus !== 'livre') {
                $resultatCommission = $this->calculerCommissionsLivraison($livraison);

                if ($resultatCommission['success']) {
                    Log::info("Commissions calculées avec succès pour la livraison {$id}", $resultatCommission['data']);
                } else {
                    Log::warning("Échec du calcul des commissions pour la livraison {$id}: " . $resultatCommission['message']);
                }
            }

            if ($nouveauStatus === 'annule' && $ancienStatus === 'livre') {
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

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche de la livraison',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
        if (strpos($imageData, 'data:image') === 0) {
            return $imageData;
        }

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

    public function debugBordereau($id)
    {
        try {
            $livraison = $this->findLivraison($id);

            if (!$livraison) {
                return response()->json(['error' => 'Livraison non trouvée'], 404);
            }

            $data = $this->preparePrintData($livraison);

            $debugInfo = [
                'livraison_id' => $livraison->id,
                'qrCode_exists' => isset($data['qrCode']),
                'qrCode_type' => isset($data['qrCode']) ? substr($data['qrCode'], 0, 50) . '...' : 'N/A',
                'barcode_exists' => isset($data['barcode']),
                'barcode_type' => isset($data['barcode']) ? substr($data['barcode'], 0, 50) . '...' : 'N/A',
            ];

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

            $pdf = Pdf::loadView('pdf.bordereau', $data);

            $pdf->setPaper([0, 0, 283.464, 425.197], 'portrait');

            $pdf->setOptions([
                'defaultFont'             => 'DejaVuSans',
                'isHtml5ParserEnabled'    => true,
                'isRemoteEnabled'         => true,
                'dpi'                     => 96,
                'margin-top'              => 0,
                'margin-right'            => 0,
                'margin-bottom'           => 0,
                'margin-left'             => 0,
                'isFontSubsettingEnabled' => true,
                'defaultPaperSize'        => [0, 0, 283.464, 425.197],
                'tempDir'                 => storage_path('app/temp'),
            ]);

            $pdf->getDomPDF()->set_option('default_charset', 'UTF-8');
            $pdf->getDomPDF()->set_option('font_height_ratio', '1.0');

            $fileName = 'bordereau_livraison_' . $livraison->id . '_' . now()->format('Ymd-His') . '.pdf';

            Log::info("PDF bordereau généré avec succès pour livraison #" . $livraison->id);

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF bordereau - ID ' . $id . ' : ' . $e->getMessage());

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
            $data = urlencode("ID:{$livraison->id}|PIN:{$livraison->code_pin}");
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$data}&format=png&margin=0";

            return $qrUrl;
        } catch (\Exception $e) {
            Log::warning("Erreur génération QR Code: " . $e->getMessage());

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

    private function extractWilayaInfo($wilayaValue, $livraisonId = null): array
    {
        $wilayaNumber = '';
        $wilayaName = '';

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
            'El Meniaa' => '58'
        ];

        $wilayaValue = trim($wilayaValue);

        if (!empty($wilayaValue)) {
            if (is_numeric($wilayaValue)) {
                $wilayaNumber = str_pad($wilayaValue, 2, '0', STR_PAD_LEFT);
                $wilayaName = array_search($wilayaNumber, $wilayaMap) ?: '';
            } else {
                foreach ($wilayaMap as $nom => $num) {
                    if (strcasecmp(trim($nom), trim($wilayaValue)) === 0) {
                        $wilayaName = $nom;
                        $wilayaNumber = $num;
                        break;
                    }
                }

                if (empty($wilayaNumber)) {
                    foreach ($wilayaMap as $nom => $num) {
                        if (stripos($wilayaValue, $nom) !== false || stripos($nom, $wilayaValue) !== false) {
                            $wilayaName = $nom;
                            $wilayaNumber = $num;
                            break;
                        }
                    }
                }
            }
        }

        return ['number' => $wilayaNumber, 'name' => $wilayaName];
    }

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

            $destinataire = null;
            if ($demande->destinataire && $demande->destinataire->user) {
                $destinataire = $demande->destinataire->user;
            } elseif (isset($demande->destinataire) && is_object($demande->destinataire)) {
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

            if ($client) {
                $client->prenom = $this->cleanTextForDisplay($client->prenom ?? '');
                $client->nom = $this->cleanTextForDisplay($client->nom ?? '');
                $client->telephone = $this->cleanTextForDisplay($client->telephone ?? '');
            }

            if ($destinataire) {
                $destinataire->prenom = $this->cleanTextForDisplay($destinataire->prenom ?? '');
                $destinataire->nom = $this->cleanTextForDisplay($destinataire->nom ?? '');
                $destinataire->telephone = $this->cleanTextForDisplay($destinataire->telephone ?? '');
            }

            if ($demande) {
                $demande->addresse_depot = $this->cleanTextForDisplay($demande->addresse_depot ?? '');
                $demande->addresse_delivery = $this->cleanTextForDisplay($demande->addresse_delivery ?? '');
                $demande->wilaya = $this->cleanTextForDisplay($demande->wilaya ?? '');
                $demande->commune = $this->cleanTextForDisplay($demande->commune ?? '');
            }

            $qrCode = $this->generateSimpleQRCode($livraison);
            $barcodeValue = $colis->colis_label ?? 'COLIS-' . $livraison->id;
            $barcode = $this->generateSimpleBarcode($barcodeValue);

            $wilayaInfo = $this->extractWilayaInfo($demande->wilaya ?? '', $livraison->id);

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

            return [
                'livraison' => $livraison,
                'demande' => $demande,
                'colis' => $colis,
                'client' => $client,
                'destinataire' => $destinataire,
                'livreurRamasseur' => $livraison->livreurRamasseur->user ?? null,
                'livreurDistributeur' => $livraison->livreurDistributeur->user ?? null,
                'qrCode' => $qrCode,
                'barcode' => $barcode,
                'colisLabel' => $this->cleanTextForDisplay($barcodeValue),
                'printDate' => $printDate,
                'statusLabel' => $statusLabel,
                'wilayaNumber' => $wilayaInfo['number'],
                'wilayaName' => $wilayaInfo['name'],
            ];
        } catch (\Exception $e) {
            Log::error("Erreur préparation données: " . $e->getMessage());
            throw $e;
        }
    }

    private function cleanTextForDisplay($text)
    {
        if (empty($text)) {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        $maxLength = 50;
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength) . '...';
        }

        return trim($text);
    }

    private function extractWilayaFromLivraison($livraison)
    {
        $demande = $livraison->demandeLivraison;
        $adresseLivraison = $demande->addresse_delivery ?? '';
        $wilayaNumber = '';
        $wilayaName = '';

        $wilayaMap = [
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
            'MSILA' => '28',
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
            'TIMIMOUN' => '49',
            'BORDJ BADJI MOKHTAR' => '50',
            'OULED DJELLAL' => '51',
            'BENI ABBES' => '52',
            'IN SALAH' => '53',
            'IN GUEZZAM' => '54',
            'TOUGGOURT' => '55',
            'DJANET' => '56',
            'EL M\'GHAIR' => '57',
            'EL MGHAIR' => '57',
            'EL MENIAA' => '58',
        ];

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

        $findWilayaInText = function ($text) use ($wilayaMap, $specialCases) {
            if (empty($text)) return null;

            $textUpper = strtoupper($text);

            foreach ($specialCases as $nom => $num) {
                if (strpos($textUpper, $nom) !== false) {
                    return ['num' => $num, 'nom' => $nom];
                }
            }

            foreach ($wilayaMap as $nom => $num) {
                if (strpos($textUpper, $nom) !== false) {
                    return ['num' => $num, 'nom' => $nom];
                }
            }

            return null;
        };

        $wilayaText = trim($demande->wilaya ?? '');
        if (!empty($wilayaText)) {
            $result = $findWilayaInText($wilayaText);
            if ($result) {
                $wilayaNumber = $result['num'];
                $wilayaName = $result['nom'];
            }
        }

        if (empty($wilayaNumber) && !empty($adresseLivraison)) {
            $result = $findWilayaInText($adresseLivraison);
            if ($result) {
                $wilayaNumber = $result['num'];
                $wilayaName = $result['nom'];
            }
        }

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

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            ['&comma;', '&#44;', '&amp;', '&quot;', '&lt;', '&gt;', '&nbsp;'],
            [',', ',', '&', '"', '<', '>', ' '],
            $text
        );

        return trim($text);
    }

    private function cleanAllTexts($data)
    {
        if (isset($data['demande'])) {
            $data['demande']->addresse_depot = $this->cleanText($data['demande']->addresse_depot ?? '');
            $data['demande']->addresse_delivery = $this->cleanText($data['demande']->addresse_delivery ?? '');
        }

        return $data;
    }

    private function generateSimpleBarcodeImage($value)
    {
        try {
            if (function_exists('imagecreatetruecolor')) {
                return $this->createBarcodeWithGD($value);
            }

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

        $x = 10;
        for ($i = 0; $i < strlen($value); $i++) {
            $charCode = ord($value[$i]);
            $barHeight = ($charCode % 30) + 10;

            imagefilledrectangle($image, $x, 10, $x + 3, 10 + $barHeight, $black);
            $x += 5;
        }

        imagestring($image, 2, 50, $height - 20, $value, $black);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

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
            Log::warning('Erreur génération QR Code local: ' . $e->getMessage());
        }

        $encodedData = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size=90x90&data={$encodedData}&format=png&margin=1";
    }

    private function generateBarcode(string $value): string
    {
        try {
            if (class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
                $generator = new BarcodeGeneratorPNG();
                $barcode = $generator->getBarcode($value, $generator::TYPE_CODE_128, 2, 50);

                return 'data:image/png;base64,' . base64_encode($barcode);
            }
        } catch (\Exception $e) {
            Log::warning('Erreur génération code-barres local: ' . $e->getMessage());
        }

        return $this->generateSimpleBarcode($value);
    }

    private function generateSimpleBarcode($value)
    {
        try {
            $encodedValue = urlencode($value);
            return "https://barcode.tec-it.com/barcode.ashx?data={$encodedValue}&code=Code128&dpi=96&dataseparator=";
        } catch (\Exception $e) {
            Log::warning("Erreur génération code-barres: " . $e->getMessage());
            return "https://barcode.tec-it.com/barcode.ashx?data=123456&code=Code128";
        }
    }

    private function findLivraison($id)
    {
        if (Str::isUuid($id)) {
            return Livraison::where('id', $id)->first();
        }

        return Livraison::find($id);
    }

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

    public function statistiquesGenerales(): JsonResponse
    {
        Log::info("Récupération des statistiques générales");

        try {
            $totalLivraisons = Livraison::count();
            $totalEnAttente = Livraison::where('status', 'en_attente')->count();
            $totalEnCours = Livraison::whereNotIn('status', ['en_attente', 'livre', 'annule'])->count();
            $totalTerminees = Livraison::where('status', 'livre')->count();
            $totalAnnulees = Livraison::where('status', 'annule')->count();

            $parStatut = [
                'en_attente' => $totalEnAttente,
                'prise_en_charge_ramassage' => Livraison::where('status', 'prise_en_charge_ramassage')->count(),
                'ramasse' => Livraison::where('status', 'ramasse')->count(),
                'en_transit' => Livraison::where('status', 'en_transit')->count(),
                'prise_en_charge_livraison' => Livraison::where('status', 'prise_en_charge_livraison')->count(),
                'livre' => $totalTerminees,
                'annule' => $totalAnnulees,
            ];

            $evolution = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
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

            $tauxReussite = $totalLivraisons > 0
                ? round(($totalTerminees / $totalLivraisons) * 100, 2)
                : 0;

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

    public function exportExcel(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }

        try {
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

            if ($format === 'pdf') {
                return $this->exportPDF($request);
            }

            $countQuery = Livraison::query()
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('code_pin', 'like', '%' . $search . '%')
                            ->orWhereHas('client.user', function ($q) use ($search) {
                                $q->where('nom', 'like', '%' . $search . '%')
                                    ->orWhere('prenom', 'like', '%' . $search . '%');
                            })
                            ->orWhereHas('demandeLivraison.colis', function ($q) use ($search) {
                                $q->where('colis_label', 'like', '%' . $search . '%');
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

            if ($count > 10000) {
                Log::warning('Trop de livraisons pour l\'export Excel: ' . $count);

                return response()->json([
                    'success' => false,
                    'message' => 'Trop de données à exporter (' . $count . ' livraisons). ' .
                        'Veuillez appliquer des filtres plus restrictifs.',
                ], 400);
            }

            $filename = 'livraisons-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.' . $format;

            $export = new LivraisonsExport($search, $status, $startDate, $endDate);

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

            if (strpos($e->getMessage(), 'memory') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de mémoire lors de l\'export. Veuillez appliquer des filtres plus restrictifs.',
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function exportPDF(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé. Admin requis.',
            ], 403);
        }

        try {
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

            $stats = [
                'total' => $livraisons->count(),
                'en_attente' => $livraisons->where('status', 'en_attente')->count(),
                'en_cours' => $livraisons->whereNotIn('status', ['en_attente', 'livre', 'annule'])->count(),
                'livre' => $livraisons->where('status', 'livre')->count(),
                'annule' => $livraisons->where('status', 'annule')->count(),
            ];

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

            $filename = 'livraisons-export-' . Carbon::now()->format('Y-m-d-H-i-s') . '.pdf';

            $pdf = PDF::loadView('pdf.livraisons', $data);
            $pdf->setPaper('A4', 'landscape');
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

            Log::info('PDF livraisons généré avec succès, téléchargement...');

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la génération du PDF des livraisons: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage(),
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

            $pourcentageDepart = CommissionConfig::getValue('commission_depart_default') ?? 25;
            $pourcentageArrivee = CommissionConfig::getValue('commission_arrivee_default') ?? 25;

            $montantDepart = round($prixLivraison * ($pourcentageDepart / 100), 2);
            $montantArrivee = round($prixLivraison * ($pourcentageArrivee / 100), 2);
            $montantAdmin = $prixLivraison - $montantDepart - $montantArrivee;

            $gestionnaireDepart = $this->getGestionnaireByWilaya($demande->wilaya_depot);
            $gestionnaireArrivee = $this->getGestionnaireByWilaya($demande->wilaya);

            $gainsEnregistres = [];

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

        $wilayaId = trim($wilayaId);

        if (isset($wilayaMapping[$wilayaId])) {
            $wilayaId = $wilayaMapping[$wilayaId];
        } elseif (is_numeric($wilayaId)) {
            $wilayaId = str_pad($wilayaId, 2, '0', STR_PAD_LEFT);
        }

        Log::info("Recherche gestionnaire pour wilaya: " . $wilayaId);

        return Gestionnaire::where('wilaya_id', $wilayaId)
            ->where('status', 'active')
            ->with('user')
            ->first();
    }

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

        $livraison->load([
            'client.user',
            'demandeLivraison' => function ($q) {
                $q->with(['client.user', 'destinataire.user', 'colis']);
            },
            'livreurRamasseur.user',
            'livreurDistributeur.user',
            'commentaires'
        ]);

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
                    'navette_id' => $livraison->navette_id,
                    'code_pin' => $livraison->code_pin,
                    'date_ramassage' => $livraison->date_ramassage,
                    'date_livraison' => $livraison->date_livraison,
                    'status' => $livraison->status,
                    'payment_status' => $livraison->payment_status,
                    'return_status' => $livraison->return_status,
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
                    'type_livraison' => $demande->type_livraison,
                    'prestation' => $demande->prestation,
                    'lat_depot' => $demande->lat_depot,
                    'lng_depot' => $demande->lng_depot,
                    'lat_delivery' => $demande->lat_delivery,
                    'lng_delivery' => $demande->lng_delivery,
                    'livraison_gratuite' => $demande->livraison_gratuite ?? false,
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

    public function updateReturnStatus(Request $request, $id): JsonResponse
    {
        Log::info("Admin - Début mise à jour du statut de retour pour livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'return_status' => 'required|string|in:chez_livreurs,retour_en_traitement,retour_prets'
        ]);

        if ($validator->fails()) {
            Log::warning("Validation échouée pour mise à jour statut retour: " . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $livraison = $this->findLivraison($id);

        if (!$livraison) {
            Log::warning("Livraison introuvable pour mise à jour statut retour: " . $id);
            return response()->json([
                'success' => false,
                'message' => 'Livraison introuvable',
            ], 404);
        }

        if ($livraison->status !== 'annule') {
            return response()->json([
                'success' => false,
                'message' => 'Le statut de retour ne peut être modifié que pour les livraisons annulées',
            ], 400);
        }

        $ancienStatus = $livraison->return_status;
        $nouveauStatus = $validatedData['return_status'];

        Log::info("Admin - Mise à jour du statut de retour de {$ancienStatus} à {$nouveauStatus} pour la livraison " . $id);

        $livraison->update([
            'return_status' => $nouveauStatus
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de retour mis à jour avec succès',
            'data' => [
                'id' => $livraison->id,
                'return_status' => $livraison->return_status
            ]
        ], 200);
    }

    public function update(Request $request, $id): JsonResponse
    {
        Log::info("Admin - Début mise à jour complète de la livraison ID: " . $id);

        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|string|exists:clients,id',
            'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',
            'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
            'bordereau_id' => 'nullable|string|exists:bordereaux,id',
            'navette_id' => 'nullable|string|exists:navettes,id',
            'code_pin' => 'nullable|string|size:5',
            'date_ramassage' => 'nullable|date',
            'date_livraison' => 'nullable|date',
            'status' => 'sometimes|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
            'payment_status' => 'sometimes|string|in:pending,available,in_transit,paid',
            'addresse_depot' => 'nullable|string|max:500',
            'addresse_delivery' => 'nullable|string|max:500',
            'info_additionnel' => 'nullable|string',
            'prix' => 'nullable|numeric|min:0',
            'wilaya_depot' => 'nullable|string|max:255',
            'commune_depot' => 'nullable|string|max:255',
            'wilaya' => 'nullable|string|max:255',
            'commune' => 'nullable|string|max:255',
            'type_livraison' => 'nullable|string|in:Livraison,Échange,Pick-up',
            'prestation' => 'nullable|string|in:A domicile,Stop Desk',
            'depose_au_depot' => 'nullable|boolean',
            'lat_depot' => 'nullable|numeric',
            'lng_depot' => 'nullable|numeric',
            'lat_delivery' => 'nullable|numeric',
            'lng_delivery' => 'nullable|numeric',
            'livraison_gratuite' => 'nullable|boolean',
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

            $livraisonFields = array_intersect_key($validatedData, array_flip([
                'client_id',
                'livreur_distributeur_id',
                'livreur_ramasseur_id',
                'bordereau_id',
                'navette_id',
                'code_pin',
                'date_ramassage',
                'date_livraison',
                'status',
                'payment_status'
            ]));

            if (!empty($livraisonFields)) {
                $livraison->update($livraisonFields);
                Log::info("Livraison mise à jour avec les champs: ", $livraisonFields);
            }

            if ($demande) {
                $demandeFields = array_intersect_key($validatedData, array_flip([
                    'addresse_depot',
                    'addresse_delivery',
                    'info_additionnel',
                    'prix',
                    'wilaya_depot',
                    'commune_depot',
                    'wilaya',
                    'commune',
                    'type_livraison',
                    'prestation',
                    'depose_au_depot',
                    'lat_depot',
                    'lng_depot',
                    'lat_delivery',
                    'lng_delivery',
                    'livraison_gratuite'
                ]));

                if (isset($validatedData['livraison_gratuite']) && $validatedData['livraison_gratuite'] === true) {
                    $demandeFields['prix'] = 0;
                    Log::info("Mise à jour: livraison gratuite activée, prix forcé à 0");
                }

                if (!empty($demandeFields)) {
                    $demande->update($demandeFields);
                    Log::info("Demande livraison mise à jour avec les champs: ", $demandeFields);
                }
            }

            $colis = $demande ? $demande->colis : null;
            if ($colis) {
                $colisFields = [];
                if (isset($validatedData['colis_label'])) $colisFields['colis_label'] = $validatedData['colis_label'];
                if (isset($validatedData['colis_poids'])) $colisFields['poids'] = $validatedData['colis_poids'];
                if (isset($validatedData['colis_type'])) $colisFields['colis_type'] = $validatedData['colis_type'];
                if (isset($validatedData['colis_prix'])) $colisFields['colis_prix'] = $validatedData['colis_prix'];

                if (!empty($colisFields)) {
                    $colis->update($colisFields);
                    Log::info("Colis mis à jour avec les champs: ", $colisFields);
                }
            }

            $this->logLivraisonModification($livraison, auth()->id(), $validatedData);

            DB::commit();

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

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la livraison',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function logLivraisonModification($livraison, $adminId, $changes)
    {
        try {
            Log::info("LIVRAISON MODIFIEE", [
                'livraison_id' => $livraison->id,
                'admin_id' => $adminId,
                'changes' => $changes,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning("Erreur lors du logging de modification: " . $e->getMessage());
        }
    }

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

    public function getHistory($id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => []
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info("Admin - Début création d'une nouvelle livraison");

        $validator = Validator::make($request->all(), [
            'client_id' => 'required|string|exists:clients,id',
            'livreur_ramasseur_id' => 'nullable|string|exists:livreurs,id',
            'livreur_distributeur_id' => 'nullable|string|exists:livreurs,id',
            'status' => 'required|string|in:en_attente,prise_en_charge_ramassage,ramasse,en_transit,prise_en_charge_livraison,livre,annule',
            'payment_status' => 'required|string|in:pending,available,in_transit,paid',
            'date_ramassage' => 'nullable|date',
            'date_livraison' => 'nullable|date',
            'destinataire_nom' => 'required|string|max:255',
            'destinataire_email' => 'nullable|email|max:255',
            'destinataire_telephone' => 'required|string|max:20',
            'addresse_depot' => 'nullable|string|max:500',
            'addresse_delivery' => 'required|string|max:500',
            'wilaya_depot' => 'nullable|string|max:255',
            'commune_depot' => 'nullable|string|max:255',
            'wilaya' => 'required|string|max:255',
            'commune' => 'required|string|max:255',
            'colis_label' => 'nullable|string|max:255',
            'colis_poids' => 'required|numeric|min:0.1',
            'colis_type' => 'nullable|string|max:255',
            'colis_prix' => 'nullable|numeric|min:0',
            'prix' => 'required|numeric|min:0',
            'livraison_gratuite' => 'nullable|boolean',
            'depose_au_depot' => 'boolean',
            'info_additionnel' => 'nullable|string',
            'type_livraison' => 'nullable|string|in:Livraison,Échange,Pick-up',
            'prestation' => 'nullable|string|in:A domicile,Stop Desk',
            'lat_depot' => 'nullable|numeric',
            'lng_depot' => 'nullable|numeric',
            'lat_delivery' => 'nullable|numeric',
            'lng_delivery' => 'nullable|numeric',
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

            $destinataire = $this->createOrGetDestinataire($validatedData);
            $colis = $this->createColis($validatedData);
            $demande = $this->createDemandeLivraison($validatedData, $destinataire->id, $colis->id);

            $isDepotClient = $validatedData['depose_au_depot'] ?? false;
            $initialStatus = $isDepotClient ? 'en_transit' : ($validatedData['status'] ?? 'en_attente');

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

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la livraison',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createOrGetDestinataire($data)
    {
        $user = null;

        if (!empty($data['destinataire_email'])) {
            $user = User::where('email', $data['destinataire_email'])->first();
        }

        if (!$user && !empty($data['destinataire_telephone'])) {
            $user = User::where('telephone', $data['destinataire_telephone'])->first();
        }

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

        return Client::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );
    }

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

    private function createDemandeLivraison($data, $destinataireId, $colisId)
    {
        $isDepotClient = $data['depose_au_depot'] ?? false;

        $prix = $data['prix'];
        if (isset($data['livraison_gratuite']) && $data['livraison_gratuite'] === true) {
            $prix = 0;
            Log::info("Livraison gratuite activée, prix mis à 0");
        }

        return DemandeLivraison::create([
            'client_id' => $data['client_id'],
            'destinataire_id' => $destinataireId,
            'colis_id' => $colisId,
            'depose_au_depot' => $isDepotClient,
            'addresse_depot' => $data['addresse_depot'] ?? null,
            'addresse_delivery' => $data['addresse_delivery'],
            'info_additionnel' => $data['info_additionnel'] ?? null,
            'prix' => $prix,
            'wilaya_depot' => $data['wilaya_depot'] ?? null,
            'commune_depot' => $data['commune_depot'] ?? null,
            'wilaya' => $data['wilaya'],
            'commune' => $data['commune'],
            'type_livraison' => $data['type_livraison'] ?? 'Livraison',
            'prestation' => $data['prestation'] ?? 'A domicile',
            'lat_depot' => $data['lat_depot'] ?? null,
            'lng_depot' => $data['lng_depot'] ?? null,
            'lat_delivery' => $data['lat_delivery'] ?? null,
            'lng_delivery' => $data['lng_delivery'] ?? null,
            'livraison_gratuite' => $data['livraison_gratuite'] ?? false,
        ]);
    }

    public function generatePin(): JsonResponse
    {
        return response()->json([
            'pin' => $this->generateUniquePin()
        ], 200);
    }

    private function generateUniquePin(): string
    {
        do {
            $pin = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        } while (Livraison::where('code_pin', $pin)->exists());

        return $pin;
    }

    /**
     * Générer un PDF avec plusieurs bordereaux (assemblage simple)
     */
    public function generateMultipleBordereauxPDF(Request $request)
    {
        try {
            $request->validate([
                'livraison_ids' => 'required|array|min:1',
                'livraison_ids.*' => 'required|string|exists:livraisons,id'
            ]);

            $livraisonIds = $request->input('livraison_ids');
            $livraisons = Livraison::whereIn('id', $livraisonIds)->get();

            if ($livraisons->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison trouvée'
                ], 404);
            }

            $allHtml = '';
            $total = $livraisons->count();

            foreach ($livraisons as $livraison) {
                $data = $this->preparePrintData($livraison);
                // ✅ Passer le flag pour l'impression multiple
                $data['isMultiple'] = true;

                $html = View::make('pdf.bordereau', $data)->render();
                $allHtml .= $html;
            }

            $wrapperHtml = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Bordereaux de livraison - ' . $total . ' colis</title>
            <style>
                @page {
                    size: 100mm 150mm;
                    margin: 0mm;
                }
                @media print {
                    body {
                        margin: 0;
                        padding: 0;
                    }
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
            </style>
        </head>
        <body>
            ' . $allHtml . '
        </body>
        </html>';

            $pdf = Pdf::loadHTML($wrapperHtml);
            $pdf->setPaper('a4', 'portrait');
            $pdf->setOptions([
                'defaultFont' => 'DejaVuSans',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'dpi' => 150,
            ]);

            $fileName = 'bordereaux_' . now()->format('Y-m-d_His') . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('Erreur génération PDF multiple: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer du HTML pour impression multiple (aperçu avant impression)
     */
    public function generateMultiplePrintHTML(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'livraison_ids' => 'required|array|min:1',
                'livraison_ids.*' => 'required|string|exists:livraisons,id'
            ]);

            $livraisonIds = $request->input('livraison_ids');
            $livraisons = Livraison::whereIn('id', $livraisonIds)->get();

            if ($livraisons->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune livraison trouvée'
                ], 404);
            }

            $allHtml = '';
            $total = $livraisons->count();

            foreach ($livraisons as $livraison) {
                $data = $this->preparePrintData($livraison);
                // ✅ Passer le flag pour l'impression multiple
                $data['isMultiple'] = true;

                $html = View::make('pdf.bordereau', $data)->render();
                $allHtml .= $html;
            }

            // ✅ Envelopper dans une page HTML complète
            $fullHtml = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Bordereaux de livraison - ' . $total . ' colis</title>
            <style>
                @page {
                    size: 100mm 150mm;
                    margin: 0mm;
                }
                @media print {
                    html, body {
                        margin: 0;
                        padding: 0;
                        width: 100%;
                        height: auto;
                    }
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    background: white;
                }
            </style>
        </head>
        <body>
            ' . $allHtml . '
        </body>
        </html>';

            return response()->json([
                'success' => true,
                'html' => $fullHtml,
                'count' => $total
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur génération HTML multiple: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
