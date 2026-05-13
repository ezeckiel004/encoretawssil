<?php
// app/Http/Controllers/Manager/GainsNavetteController.php

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
use App\Mail\DemandeCommissionNavetteMail;
use Carbon\Carbon;

class GainsNavetteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('gestionnaire');
    }

    /**
     * Récupérer les gains NAVETTES du gestionnaire connecté
     * (Gains où navette_id n'est pas null)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                Log::error('GainsNavetteController: Utilisateur non authentifié');
                return response()->json([
                    'success' => false,
                    'message' => 'Non authentifié'
                ], 401);
            }

            $gestionnaire = $user->gestionnaire;

            if (!$gestionnaire) {
                Log::error('GainsNavetteController: Profil gestionnaire introuvable pour user: ' . $user->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Profil gestionnaire introuvable'
                ], 403);
            }

            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            Log::info('GainsNavetteController: Recherche des gains', [
                'gestionnaire_id' => $gestionnaire->id,
                'periode' => $periode,
                'date_debut' => $dateDebut ? $dateDebut->toDateTimeString() : 'null',
                'date_fin' => $dateFin ? $dateFin->toDateTimeString() : 'null'
            ]);

            // Récupérer UNIQUEMENT les gains liés à des navettes (navette_id non null)
            $query = GestionnaireGain::with(['livraison.demandeLivraison', 'navette'])
                ->where('gestionnaire_id', $gestionnaire->id)
                ->whereNotNull('navette_id');  // ← FILTRE IMPORTANT : seulement les gains navettes

            // Appliquer le filtre de dates si disponible
            if ($dateDebut && $dateFin) {
                $query->whereBetween('created_at', [$dateDebut, $dateFin]);
            }

            $gains = $query->orderBy('created_at', 'desc')->get();

            Log::info('GainsNavetteController: Gains trouvés', [
                'nombre' => $gains->count(),
                'total_montant' => $gains->sum('montant_commission')
            ]);

            // Ajouter les références pour l'affichage
            $gains->transform(function ($gain) {
                $gain->navette_reference = $gain->navette?->reference ?? ($gain->navette_id ? substr($gain->navette_id, 0, 8) : null);
                $gain->livraison_reference = $gain->livraison_id ? substr($gain->livraison_id, 0, 8) : null;
                return $gain;
            });

            // Statistiques spécifiques aux gains navettes
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
                        'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : null,
                        'fin' => $dateFin ? $dateFin->format('Y-m-d') : null,
                        'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur index gains navettes: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains navettes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les gains navettes en attente
     */
    public function gainsEnAttente(Request $request): JsonResponse
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

            $gains = GestionnaireGain::with(['livraison.demandeLivraison', 'navette'])
                ->where('gestionnaire_id', $gestionnaire->id)
                ->whereNotNull('navette_id')
                ->where('status', 'en_attente')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $gains
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur gainsNavetteEnAttente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains navettes'
            ], 500);
        }
    }

    /**
     * Demander le paiement d'un gain navette
     */
    public function demanderPaiement(Request $request, $gainId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $request->user();
            $gestionnaire = $user->gestionnaire;

            $gain = GestionnaireGain::where('id', $gainId)
                ->where('gestionnaire_id', $gestionnaire->id)
                ->whereNotNull('navette_id')
                ->first();

            if (!$gain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gain navette non trouvé'
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
                    try {
                        Mail::to($admin->email)->send(new DemandeCommissionNavetteMail($gain, $gestionnaire));
                    } catch (\Exception $e) {
                        Log::error('Erreur envoi email demande paiement navette: ' . $e->getMessage());
                    }
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
            Log::error('Erreur demanderPaiementNavette: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de paiement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Demander le paiement de plusieurs gains navette
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
                ->whereNotNull('navette_id')
                ->where('status', 'en_attente')
                ->get();

            if ($gains->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun gain navette valide à demander'
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
                    try {
                        Mail::to($admin->email)->send(new DemandeCommissionNavetteMail($gains, $gestionnaire, true));
                    } catch (\Exception $e) {
                        Log::error('Erreur envoi email demande multiple navette: ' . $e->getMessage());
                    }
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
            Log::error('Erreur demanderPaiementMultipleNavette: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors des demandes de paiement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques des gains navettes
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
                'total_gains' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->sum('montant_commission'),
                'en_attente' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->where('status', 'en_attente')
                    ->sum('montant_commission'),
                'demande_envoyee' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->where('status', 'demande_envoyee')
                    ->sum('montant_commission'),
                'paye' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->where('status', 'paye')
                    ->sum('montant_commission'),
                'nb_total' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->count(),
                'nb_en_attente' => GestionnaireGain::where('gestionnaire_id', $gestionnaire->id)
                    ->whereNotNull('navette_id')
                    ->where('status', 'en_attente')
                    ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur statistiques gains navettes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques'
            ], 500);
        }
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Récupère les dates de début et fin selon la période
     */
    private function getPeriodDates($periode, $request): array
    {
        try {
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
                    if (!$request->date_debut || !$request->date_fin) {
                        // Si dates manquantes, prendre le mois en cours
                        $date = Carbon::today();
                        return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
                    }
                    return [
                        Carbon::parse($request->date_debut)->startOfDay(),
                        Carbon::parse($request->date_fin)->endOfDay()
                    ];

                default:
                    $date = Carbon::today();
                    return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            }
        } catch (\Exception $e) {
            Log::error('Erreur getPeriodDates: ' . $e->getMessage());
            $date = Carbon::today();
            return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
        }
    }

    /**
     * Génère le libellé de la période pour l'affichage
     */
    private function getPeriodLibelle($periode, $debut, $fin): string
    {
        if (!$debut || !$fin) {
            return 'Période non définie';
        }

        try {
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
        } catch (\Exception $e) {
            return 'Période';
        }
    }
}
