<?php
// app/Http/Controllers/Manager/GainController.php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\GestionnaireGain;
use App\Models\Gestionnaire;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DemandeCommissionMail;
use App\Mail\StatutCommissionMail;
use Carbon\Carbon;

class GainController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire');
    }

    /**
     * Récupérer les gains du gestionnaire connecté
     */
    public function index(Request $request): JsonResponse
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

            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $gains = GestionnaireGain::with('livraison.demandeLivraison')
                ->where('gestionnaire_id', $gestionnaire->id)
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->orderBy('created_at', 'desc')
                ->get();

            // Statistiques
            $stats = [
                'total_gains' => $gains->sum('montant_commission'),
                'en_attente' => $gains->where('status', 'en_attente')->sum('montant_commission'),
                'demande_envoyee' => $gains->where('status', 'demande_envoyee')->sum('montant_commission'),
                'paye' => $gains->where('status', 'paye')->sum('montant_commission'),
                'annule' => $gains->where('status', 'annule')->sum('montant_commission'),
                'nb_en_attente' => $gains->where('status', 'en_attente')->count(),
                'nb_demandes' => $gains->where('status', 'demande_envoyee')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'gains' => $gains,
                    'stats' => $stats,
                    'periode' => [
                        'debut' => $dateDebut->format('Y-m-d'),
                        'fin' => $dateFin->format('Y-m-d'),
                        'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index gains: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains'
            ], 500);
        }
    }

    /**
     * Récupérer les gains en attente (que le gestionnaire peut demander)
     */
    public function gainsEnAttente(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            $gains = GestionnaireGain::with('livraison.demandeLivraison')
                ->where('gestionnaire_id', $gestionnaire->id)
                ->where('status', 'en_attente')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $gains
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur gainsEnAttente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains'
            ], 500);
        }
    }

    /**
     * Demander le paiement d'une commission
     */
    public function demanderPaiement(Request $request, $gainId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            $gain = GestionnaireGain::where('id', $gainId)
                ->where('gestionnaire_id', $gestionnaire->id)
                ->first();

            if (!$gain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gain non trouvé'
                ], 404);
            }

            if ($gain->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce gain ne peut pas être demandé (statut: ' . $gain->status . ')'
                ], 400);
            }

            // Mettre à jour le statut
            $gain->update([
                'status' => 'demande_envoyee',
                'date_demande' => now()
            ]);

            // Envoyer un email à l'admin
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Mail::to($admin->email)->send(new DemandeCommissionMail($gain, $gestionnaire));
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande de paiement envoyée avec succès',
                'data' => $gain
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur demanderPaiement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de paiement'
            ], 500);
        }
    }

    /**
     * Demander le paiement de plusieurs commissions
     */
    public function demanderPaiementMultiple(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'gain_ids' => 'required|array',
                'gain_ids.*' => 'required|string'
            ]);

            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            $gains = GestionnaireGain::whereIn('id', $request->gain_ids)
                ->where('gestionnaire_id', $gestionnaire->id)
                ->where('status', 'en_attente')
                ->get();

            if ($gains->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun gain valide à demander'
                ], 400);
            }

            $nbMisAJour = 0;
            foreach ($gains as $gain) {
                $gain->update([
                    'status' => 'demande_envoyee',
                    'date_demande' => now()
                ]);
                $nbMisAJour++;
            }

            // Envoyer un email récapitulatif à l'admin
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                if ($admin->email) {
                    Mail::to($admin->email)->send(new DemandeCommissionMail($gains, $gestionnaire, true));
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $nbMisAJour . ' demande(s) de paiement envoyée(s) avec succès',
                'data' => [
                    'nb_demandes' => $nbMisAJour,
                    'montant_total' => $gains->sum('montant_commission')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur demanderPaiementMultiple: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des demandes de paiement'
            ], 500);
        }
    }

    /**
     * Récupérer les dates selon la période
     */
    private function getPeriodDates($periode, $request): array
    {
        switch ($periode) {
            case 'jour':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
            case 'semaine':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()];
            case 'mois':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            case 'annee':
                $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
                return [$date->copy()->startOfYear(), $date->copy()->endOfYear()];
            case 'personnalise':
                return [
                    Carbon::parse($request->date_debut)->startOfDay(),
                    Carbon::parse($request->date_fin)->endOfDay()
                ];
            default:
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        }
    }

    private function getPeriodLibelle($periode, $debut, $fin): string
    {
        if ($periode === 'personnalise') {
            return 'Du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y');
        }

        $labels = [
            'jour' => 'Journée du ' . $debut->format('d/m/Y'),
            'semaine' => 'Semaine du ' . $debut->format('d/m/Y') . ' au ' . $fin->format('d/m/Y'),
            'mois' => 'Mois de ' . $debut->locale('fr')->isoFormat('MMMM YYYY'),
            'annee' => 'Année ' . $debut->format('Y')
        ];

        return $labels[$periode] ?? 'Période';
    }
}
