<?php
// app/Http/Controllers/Admin/CommissionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionConfig;
use App\Models\GestionnaireGain;
use App\Models\Gestionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CommissionController extends Controller
{
    /**
     * Constructeur avec middleware d'authentification
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin');
    }

    /**
     * Récupérer la configuration actuelle des commissions
     */
    public function getConfig(): JsonResponse
    {
        try {
            $configs = CommissionConfig::getAllCommissionConfigs();

            return response()->json([
                'success' => true,
                'data' => [
                    'pourcentages' => $configs,
                    'details' => [
                        'depart' => [
                            'key' => 'commission_depart_default',
                            'value' => $configs['depart'],
                            'description' => 'Commission pour la wilaya de départ (%)'
                        ],
                        'arrivee' => [
                            'key' => 'commission_arrivee_default',
                            'value' => $configs['arrivee'],
                            'description' => 'Commission pour la wilaya d\'arrivée (%)'
                        ],
                        'admin' => [
                            'value' => $configs['admin'],
                            'description' => 'Part de l\'admin (calculée automatiquement)'
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération config commissions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la configuration'
            ], 500);
        }
    }

    /**
     * Mettre à jour la configuration des commissions
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'depart' => 'required|numeric|min:0|max:100',
            'arrivee' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $total = $request->depart + $request->arrivee;
        if ($total > 100) {
            return response()->json([
                'success' => false,
                'message' => 'La somme des commissions ne peut pas dépasser 100%'
            ], 400);
        }

        try {
            DB::beginTransaction();

            CommissionConfig::setValue(
                'commission_depart_default',
                $request->depart,
                'Commission pour la wilaya de départ (%)'
            );

            CommissionConfig::setValue(
                'commission_arrivee_default',
                $request->arrivee,
                'Commission pour la wilaya d\'arrivée (%)'
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Configuration mise à jour avec succès',
                'data' => [
                    'depart' => $request->depart,
                    'arrivee' => $request->arrivee,
                    'admin' => 100 - $request->depart - $request->arrivee
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur updateConfig: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Simuler un changement de configuration
     */
    public function simulerConfig(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'depart' => 'required|numeric|min:0|max:100',
            'arrivee' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $total = $request->depart + $request->arrivee;
        if ($total > 100) {
            return response()->json([
                'success' => false,
                'message' => 'La somme des commissions ne peut pas dépasser 100%'
            ], 400);
        }

        // Prix moyen des livraisons (à adapter selon votre logique métier)
        $prixMoyen = DB::table('demande_livraisons')
            ->whereNotNull('prix')
            ->avg('prix') ?? 1500;

        return response()->json([
            'success' => true,
            'data' => [
                'impact_estime' => [
                    'prix_moyen_estime' => round($prixMoyen, 2),
                    'simulation_sur_100_livraisons' => [
                        'total_ca' => round($prixMoyen * 100, 2),
                        'commissions_depart' => round(($prixMoyen * 100) * ($request->depart / 100), 2),
                        'commissions_arrivee' => round(($prixMoyen * 100) * ($request->arrivee / 100), 2),
                        'part_admin' => round(($prixMoyen * 100) * ((100 - $total) / 100), 2)
                    ]
                ]
            ]
        ]);
    }

    /**
     * Historique des modifications
     */
    public function historiqueConfig(): JsonResponse
    {
        try {
            $historique = CommissionConfig::select('key', 'value', 'updated_at')
                ->whereIn('key', ['commission_depart_default', 'commission_arrivee_default'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->groupBy('key')
                ->map(function ($items, $key) {
                    return [
                        'config' => $key === 'commission_depart_default' ? 'Départ' : 'Arrivée',
                        'valeur_actuelle' => $items->first()->value,
                        'derniere_modification' => $items->first()->updated_at->format('d/m/Y H:i'),
                        'historique' => $items->take(5)->map(function ($item) {
                            return [
                                'valeur' => $item->value . '%',
                                'date' => $item->updated_at->format('d/m/Y H:i')
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $historique
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur historiqueConfig: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }

    /**
     * Statistiques globales
     */
    public function statistiquesGlobales(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $statsGenerales = [
                'total_commissions' => GestionnaireGain::whereBetween('created_at', [$dateDebut, $dateFin])
                    ->sum('montant_commission'),
                'nombre_livraisons_commissionnees' => GestionnaireGain::whereBetween('created_at', [$dateDebut, $dateFin])
                    ->distinct('livraison_id')
                    ->count('livraison_id'),
                'nombre_gestionnaires_concernes' => GestionnaireGain::whereBetween('created_at', [$dateDebut, $dateFin])
                    ->distinct('gestionnaire_id')
                    ->count('gestionnaire_id')
            ];

            $repartitionStatut = GestionnaireGain::select('status', DB::raw('SUM(montant_commission) as total'))
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray();

            $repartitionType = GestionnaireGain::select('wilaya_type', DB::raw('SUM(montant_commission) as total'))
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->groupBy('wilaya_type')
                ->get()
                ->pluck('total', 'wilaya_type')
                ->toArray();

            $topGestionnaires = GestionnaireGain::with('gestionnaire.user')
                ->select('gestionnaire_id', DB::raw('SUM(montant_commission) as total_gains'))
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->groupBy('gestionnaire_id')
                ->orderBy('total_gains', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'gestionnaire_id' => $item->gestionnaire_id,
                        'nom' => $item->gestionnaire->user->nom . ' ' . $item->gestionnaire->user->prenom,
                        'wilaya' => $item->gestionnaire->wilaya_id,
                        'total_gains' => $item->total_gains
                    ];
                });

            $configActuelle = CommissionConfig::getAllCommissionConfigs();

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => [
                        'debut' => $dateDebut->format('Y-m-d'),
                        'fin' => $dateFin->format('Y-m-d'),
                        'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                    ],
                    'config_actuelle' => $configActuelle,
                    'stats_generales' => $statsGenerales,
                    'repartition_par_statut' => $repartitionStatut,
                    'repartition_par_type' => $repartitionType,
                    'top_gestionnaires' => $topGestionnaires
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiquesGlobales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Récupérer les gains d'un gestionnaire
     */
    public function getGainsGestionnaire(Request $request, string $gestionnaireId): JsonResponse
    {
        try {
            $gestionnaire = Gestionnaire::with('user')->find($gestionnaireId);

            if (!$gestionnaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gestionnaire introuvable'
                ], 404);
            }

            $dateDebut = $request->date_debut ? Carbon::parse($request->date_debut)->startOfDay() : Carbon::now()->startOfMonth();
            $dateFin = $request->date_fin ? Carbon::parse($request->date_fin)->endOfDay() : Carbon::now()->endOfMonth();

            $gainsQuery = GestionnaireGain::where('gestionnaire_id', $gestionnaireId)
                ->with('livraison.demandeLivraison')
                ->whereBetween('created_at', [$dateDebut, $dateFin]);

            $gains = $gainsQuery->get();

            $statsParStatut = [
                'calcule' => $gains->where('status', 'calcule')->sum('montant_commission'),
                'verse' => $gains->where('status', 'verse')->sum('montant_commission'),
                'en_attente' => $gains->where('status', 'en_attente')->sum('montant_commission'),
            ];

            $repartitionParType = [
                'depart' => $gains->where('wilaya_type', 'depart')->sum('montant_commission'),
                'arrivee' => $gains->where('wilaya_type', 'arrivee')->sum('montant_commission'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'gestionnaire' => [
                        'id' => $gestionnaire->id,
                        'nom' => $gestionnaire->user->nom . ' ' . $gestionnaire->user->prenom,
                        'wilaya' => $gestionnaire->wilaya_id
                    ],
                    'periode' => [
                        'debut' => $dateDebut->format('Y-m-d'),
                        'fin' => $dateFin->format('Y-m-d')
                    ],
                    'resume' => [
                        'total_gains' => $gains->sum('montant_commission'),
                        'nombre_livraisons' => $gains->count(),
                        'moyenne_par_livraison' => $gains->count() > 0 ? round($gains->sum('montant_commission') / $gains->count(), 2) : 0
                    ],
                    'stats_par_statut' => $statsParStatut,
                    'repartition_par_type' => $repartitionParType,
                    'details' => $gains->map(function ($gain) {
                        return [
                            'id' => $gain->id,
                            'livraison_id' => $gain->livraison_id,
                            'type' => $gain->wilaya_type,
                            'montant' => $gain->montant_commission,
                            'pourcentage' => $gain->pourcentage_applique . '%',
                            'status' => $gain->status,
                            'date' => $gain->created_at->format('d/m/Y H:i'),
                            'prix_livraison' => $gain->livraison?->demandeLivraison?->prix ?? 0
                        ];
                    })
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getGainsGestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains'
            ], 500);
        }
    }

    /**
     * Exporter les statistiques
     */
    public function exportStatistiques(Request $request)
    {
        // À implémenter selon vos besoins (Excel/PDF)
        return response()->json([
            'success' => false,
            'message' => 'Export non implémenté'
        ], 501);
    }

    // ==================== MÉTHODES UTILITAIRES ====================

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
