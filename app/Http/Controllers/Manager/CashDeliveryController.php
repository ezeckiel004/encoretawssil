<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CashDelivery;
use App\Models\Gestionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\CashDeliveryNotification;
use Illuminate\Support\Facades\Validator;

class CashDeliveryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire');
    }

    /**
     * Liste des gestionnaires disponibles (sauf soi-même)
     */
    public function getGestionnairesDisponibles(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            // Récupérer tous les autres gestionnaires actifs
            $gestionnaires = Gestionnaire::with('user')
                ->where('id', '!=', $gestionnaire->id)
                ->where('status', 'active')
                ->get()
                ->map(function ($g) {
                    return [
                        'id' => $g->id,
                        'nom' => $g->user->prenom . ' ' . $g->user->nom,
                        'wilaya_id' => $g->wilaya_id,
                        'wilaya_nom' => $g->wilaya_nom,
                        'email' => $g->user->email
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $gestionnaires
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur getGestionnairesDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gestionnaires'
            ], 500);
        }
    }

    /**
     * Envoyer une demande COD
     */
    public function envoyer(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'destinataire_id' => 'required|exists:gestionnaires,id',
                'montant' => 'required|numeric|min:100|max:10000000',
                'motif' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Vérifier qu'on n'envoie pas à soi-même
            if ($request->destinataire_id === $gestionnaire->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas envoyer d\'argent à vous-même'
                ], 400);
            }

            DB::beginTransaction();

            $cashDelivery = CashDelivery::create([
                'expediteur_id' => $gestionnaire->id,
                'destinataire_id' => $request->destinataire_id,
                'montant' => $request->montant,
                'motif' => $request->motif,
                'status' => 'en_attente',
                'date_envoi' => now()
            ]);

            // Charger les relations pour l'email
            $cashDelivery->load(['expediteur.user', 'destinataire.user']);

            // Envoyer email au destinataire
            Mail::to($cashDelivery->destinataire->user->email)
                ->send(new CashDeliveryNotification($cashDelivery, 'nouvelle_demande'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande envoyée avec succès',
                'data' => $cashDelivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur envoyer COD: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accepter une demande COD
     */
    public function accepter($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $cashDelivery = CashDelivery::where('id', $id)
                ->where('destinataire_id', $gestionnaire->id)
                ->where('status', 'en_attente')
                ->first();

            if (!$cashDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande introuvable ou déjà traitée'
                ], 404);
            }

            DB::beginTransaction();

            $cashDelivery->accepter();
            $cashDelivery->load(['expediteur.user', 'destinataire.user']);

            // Envoyer email à l'expéditeur
            Mail::to($cashDelivery->expediteur->user->email)
                ->send(new CashDeliveryNotification($cashDelivery, 'demande_acceptee'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande acceptée avec succès',
                'data' => $cashDelivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur accepter COD: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation'
            ], 500);
        }
    }

    /**
     * Refuser une demande COD
     */
    public function refuser($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $cashDelivery = CashDelivery::where('id', $id)
                ->where('destinataire_id', $gestionnaire->id)
                ->where('status', 'en_attente')
                ->first();

            if (!$cashDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande introuvable ou déjà traitée'
                ], 404);
            }

            DB::beginTransaction();

            $cashDelivery->refuser();
            $cashDelivery->load(['expediteur.user', 'destinataire.user']);

            // Envoyer email à l'expéditeur
            Mail::to($cashDelivery->expediteur->user->email)
                ->send(new CashDeliveryNotification($cashDelivery, 'demande_refusee'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande refusée',
                'data' => $cashDelivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur refuser COD: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du refus'
            ], 500);
        }
    }

    /**
     * Annuler une demande COD (par l'expéditeur)
     */
    public function annuler($id): JsonResponse
    {
        try {
            $user = request()->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $cashDelivery = CashDelivery::where('id', $id)
                ->where('expediteur_id', $gestionnaire->id)
                ->where('status', 'en_attente')
                ->first();

            if (!$cashDelivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'Demande introuvable ou déjà traitée'
                ], 404);
            }

            DB::beginTransaction();

            $cashDelivery->annuler();
            $cashDelivery->load(['expediteur.user', 'destinataire.user']);

            // Envoyer email au destinataire
            Mail::to($cashDelivery->destinataire->user->email)
                ->send(new CashDeliveryNotification($cashDelivery, 'demande_annulee'));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande annulée',
                'data' => $cashDelivery
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur annuler COD: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }

    /**
     * Liste des demandes envoyées (expéditeur)
     */
    public function demandesEnvoyees(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $demandes = CashDelivery::with(['destinataire.user'])
                ->where('expediteur_id', $gestionnaire->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur demandesEnvoyees: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    /**
     * Liste des demandes reçues (destinataire)
     */
    public function demandesRecues(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $demandes = CashDelivery::with(['expediteur.user'])
                ->where('destinataire_id', $gestionnaire->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $demandes
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur demandesRecues: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération'
            ], 500);
        }
    }

    /**
     * Statistiques des transactions
     */
    public function statistiques(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $stats = [
                'envoyes' => [
                    'total' => CashDelivery::where('expediteur_id', $gestionnaire->id)->count(),
                    'en_attente' => CashDelivery::where('expediteur_id', $gestionnaire->id)->where('status', 'en_attente')->count(),
                    'acceptes' => CashDelivery::where('expediteur_id', $gestionnaire->id)->where('status', 'accepte')->count(),
                    'refuses' => CashDelivery::where('expediteur_id', $gestionnaire->id)->where('status', 'refuse')->count(),
                    'annules' => CashDelivery::where('expediteur_id', $gestionnaire->id)->where('status', 'annule')->count(),
                    'montant_total' => CashDelivery::where('expediteur_id', $gestionnaire->id)->where('status', 'accepte')->sum('montant')
                ],
                'recus' => [
                    'total' => CashDelivery::where('destinataire_id', $gestionnaire->id)->count(),
                    'en_attente' => CashDelivery::where('destinataire_id', $gestionnaire->id)->where('status', 'en_attente')->count(),
                    'acceptes' => CashDelivery::where('destinataire_id', $gestionnaire->id)->where('status', 'accepte')->count(),
                    'refuses' => CashDelivery::where('destinataire_id', $gestionnaire->id)->where('status', 'refuse')->count(),
                    'montant_total' => CashDelivery::where('destinataire_id', $gestionnaire->id)->where('status', 'accepte')->sum('montant')
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques COD: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }
}
