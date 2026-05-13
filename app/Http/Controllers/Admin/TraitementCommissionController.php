<?php
// app/Http/Controllers/Admin/TraitementCommissionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GestionnaireGain;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\StatutCommissionMail;
use Carbon\Carbon;

class TraitementCommissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Récupérer toutes les demandes de commission
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = GestionnaireGain::with(['gestionnaire.user', 'livraison'])
                ->where('status', 'demande_envoyee')
                ->orderBy('date_demande', 'desc');

            // Filtre par gestionnaire
            if ($request->has('gestionnaire_id')) {
                $query->where('gestionnaire_id', $request->gestionnaire_id);
            }

            // Filtre par période
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->whereBetween('date_demande', [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ]);
            }

            $demandes = $query->get();

            // Statistiques
            $stats = [
                'total_demandes' => $demandes->count(),
                'montant_total' => $demandes->sum('montant_commission'),
                'par_gestionnaire' => $demandes->groupBy('gestionnaire_id')
                    ->map(function ($items, $gestionnaireId) {
                        return [
                            'gestionnaire_id' => $gestionnaireId,
                            'nom' => $items->first()->gestionnaire->user->prenom . ' ' . $items->first()->gestionnaire->user->nom,
                            'wilaya' => $items->first()->gestionnaire->wilaya_id,
                            'nb_demandes' => $items->count(),
                            'montant_total' => $items->sum('montant_commission')
                        ];
                    })->values()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'demandes' => $demandes,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index demandes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des demandes'
            ], 500);
        }
    }

    /**
     * Marquer une commission comme payée
     */
    public function marquerPaye(Request $request, $gainId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $gain = GestionnaireGain::with('gestionnaire.user')->find($gainId);

            if (!$gain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gain non trouvé'
                ], 404);
            }

            if ($gain->status !== 'demande_envoyee') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce gain n\'est pas en attente de paiement'
                ], 400);
            }

            $note = $request->input('note', null);

            $gain->update([
                'status' => 'paye',
                'date_paiement' => now(),
                'note_admin' => $note
            ]);

            // Envoyer un email au gestionnaire
            if ($gain->gestionnaire && $gain->gestionnaire->user && $gain->gestionnaire->user->email) {
                Mail::to($gain->gestionnaire->user->email)->send(
                    new StatutCommissionMail($gain, 'paye', $note)
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commission marquée comme payée',
                'data' => $gain
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur marquerPaye: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage'
            ], 500);
        }
    }

    /**
     * Marquer plusieurs commissions comme payées
     */
    public function marquerPayeMultiple(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'gain_ids' => 'required|array',
                'gain_ids.*' => 'required|string',
                'note' => 'nullable|string'
            ]);

            $gains = GestionnaireGain::with('gestionnaire.user')
                ->whereIn('id', $request->gain_ids)
                ->where('status', 'demande_envoyee')
                ->get();

            if ($gains->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun gain valide trouvé'
                ], 400);
            }

            $note = $request->input('note', null);

            foreach ($gains as $gain) {
                $gain->update([
                    'status' => 'paye',
                    'date_paiement' => now(),
                    'note_admin' => $note
                ]);

                // Envoyer un email à chaque gestionnaire
                if ($gain->gestionnaire && $gain->gestionnaire->user && $gain->gestionnaire->user->email) {
                    Mail::to($gain->gestionnaire->user->email)->send(
                        new StatutCommissionMail($gain, 'paye', $note)
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($gains) . ' commission(s) marquée(s) comme payée(s)',
                'data' => [
                    'nb_traites' => count($gains),
                    'montant_total' => $gains->sum('montant_commission')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur marquerPayeMultiple: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage'
            ], 500);
        }
    }

    /**
     * Marquer une commission comme annulée
     */
    public function marquerAnnule(Request $request, $gainId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $gain = GestionnaireGain::with('gestionnaire.user')->find($gainId);

            if (!$gain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gain non trouvé'
                ], 404);
            }

            if (!in_array($gain->status, ['en_attente', 'demande_envoyee'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce gain ne peut pas être annulé'
                ], 400);
            }

            $note = $request->input('note', 'Annulé par l\'admin');

            $gain->update([
                'status' => 'annule',
                'note_admin' => $note
            ]);

            // Envoyer un email au gestionnaire
            if ($gain->gestionnaire && $gain->gestionnaire->user && $gain->gestionnaire->user->email) {
                Mail::to($gain->gestionnaire->user->email)->send(
                    new StatutCommissionMail($gain, 'annule', $note)
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commission annulée',
                'data' => $gain
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur marquerAnnule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques des paiements
     */
    public function statistiques(Request $request): JsonResponse
    {
        try {
            $stats = [
                'en_attente' => GestionnaireGain::where('status', 'en_attente')->count(),
                'demandes' => GestionnaireGain::where('status', 'demande_envoyee')->count(),
                'paye' => GestionnaireGain::where('status', 'paye')->count(),
                'annule' => GestionnaireGain::where('status', 'annule')->count(),
                'montant_en_attente' => GestionnaireGain::where('status', 'en_attente')->sum('montant_commission'),
                'montant_demandes' => GestionnaireGain::where('status', 'demande_envoyee')->sum('montant_commission'),
                'montant_paye' => GestionnaireGain::where('status', 'paye')->sum('montant_commission'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques paiements: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }
}
