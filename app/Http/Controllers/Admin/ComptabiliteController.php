<?php
// app/Http/Controllers/Admin/ComptabiliteController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Colis;
use App\Models\Livraison;
use App\Models\Navette;
use App\Models\DemandeLivraison;
use App\Models\GestionnaireGain;
use App\Models\Gestionnaire;
use App\Models\CommissionConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\BilanGlobalExport;
use App\Exports\BilanGestionnaireExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RapportGestionnairesExport;
use App\Exports\RapportGestionnairesCsvExport;



class ComptabiliteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Bilan GLOBAL - Toute la plateforme
     */
    public function bilanGlobal(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'annee');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $stats = [
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis(null, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons(null, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes(null, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier(null, $dateDebut, $dateFin),
                'top_wilayas' => $this->getTopWilayas($dateDebut, $dateFin),
                'evolution' => $this->getEvolutionGlobale($dateDebut, $dateFin)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur bilanGlobal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bilan global'
            ], 500);
        }
    }

    /**
     * Bilan pour une wilaya spécifique
     */
    public function bilanGestionnaire(Request $request, $wilayaId = null): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->role === 'admin' && $wilayaId) {
                $wilaya = $wilayaId;
            } else {
                $gestionnaire = $user->gestionnaire;
                if (!$gestionnaire) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Profil gestionnaire introuvable'
                    ], 403);
                }
                $wilaya = $gestionnaire->wilaya_id;
            }

            $periode = $request->get('periode', 'annee');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $stats = [
                'gestionnaire' => [
                    'wilaya_id' => $wilaya,
                    'wilaya_nom' => $this->getWilayaName($wilaya)
                ],
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilaya, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilaya, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilaya, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilaya, $dateDebut, $dateFin),
                'evolution' => $this->getEvolutionWilaya($wilaya, $dateDebut, $dateFin)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur bilanGestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bilan'
            ], 500);
        }
    }

    /**
     * Alias de bilanGestionnaire pour les wilayas (appelé depuis le frontend)
     */
    public function bilanWilaya(Request $request, $wilayaId): JsonResponse
    {
        return $this->bilanGestionnaire($request, $wilayaId);
    }

    /**
     * Rapport détaillé des gains
     */
    public function rapport(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $livraisons = Livraison::with([
                'demandeLivraison',
                'client.user',
                'livreurRamasseur.user',
                'livreurDistributeur.user'
            ])
                ->where('status', 'livre')
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->get();

            $statsGlobales = [
                'total_livraisons' => $livraisons->count(),
                'chiffre_affaires' => $livraisons->sum(function ($livraison) {
                    return $livraison->demandeLivraison->prix ?? 0;
                }),
                'moyenne_par_livraison' => $livraisons->count() > 0
                    ? round($livraisons->sum(function ($livraison) {
                        return $livraison->demandeLivraison->prix ?? 0;
                    }) / $livraisons->count(), 2)
                    : 0,
            ];

            $parWilaya = $livraisons->groupBy(function ($livraison) {
                return $livraison->demandeLivraison->wilaya ?? 'inconnue';
            })->map(function ($items, $wilaya) {
                return [
                    'wilaya' => $wilaya,
                    'nom' => $this->getWilayaName($wilaya),
                    'total_livraisons' => $items->count(),
                    'montant_total' => $items->sum(function ($item) {
                        return $item->demandeLivraison->prix ?? 0;
                    }),
                ];
            })->values();

            $parLivreur = $livraisons->groupBy(function ($livraison) {
                return $livraison->livreur_distributeur_id ?? $livraison->livreur_ramasseur_id ?? 'non_assigné';
            })->map(function ($items, $livreurId) {
                $premiereLivraison = $items->first();
                $livreur = null;

                if ($premiereLivraison && $livreurId !== 'non_assigné') {
                    if ($premiereLivraison->livreurDistributeur) {
                        $livreur = $premiereLivraison->livreurDistributeur->user;
                    } elseif ($premiereLivraison->livreurRamasseur) {
                        $livreur = $premiereLivraison->livreurRamasseur->user;
                    }
                }

                return [
                    'livreur_id' => $livreurId !== 'non_assigné' ? $livreurId : null,
                    'nom_livreur' => $livreur ? $livreur->nom . ' ' . $livreur->prenom : 'Non assigné',
                    'total_livraisons' => $items->count(),
                    'montant_total' => $items->sum(function ($item) {
                        return $item->demandeLivraison->prix ?? 0;
                    }),
                ];
            })->values();

            $detailLivraisons = $livraisons->map(function ($livraison) {
                $livreur = $livraison->livreurDistributeur ?? $livraison->livreurRamasseur;

                return [
                    'id' => $livraison->id,
                    'date' => $livraison->created_at->format('d/m/Y'),
                    'client' => $livraison->client?->user?->nom . ' ' . $livraison->client?->user?->prenom,
                    'montant' => $livraison->demandeLivraison->prix ?? 0,
                    'wilaya_depart' => $livraison->demandeLivraison->wilaya_depot,
                    'wilaya_arrivee' => $livraison->demandeLivraison->wilaya,
                    'livreur' => $livreur?->user?->nom . ' ' . $livreur?->user?->prenom,
                    'code_pin' => $livraison->code_pin,
                ];
            });

            $rapport = [
                'periode' => [
                    'debut' => $dateDebut->format('Y-m-d'),
                    'fin' => $dateFin->format('Y-m-d'),
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'stats_globales' => $statsGlobales,
                'par_wilaya' => $parWilaya,
                'par_livreur' => $parLivreur,
                'details' => $detailLivraisons,
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            return response()->json([
                'success' => true,
                'data' => $rapport
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur rapport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport'
            ], 500);
        }
    }

    /**
     * Rapport détaillé des gains par gestionnaire
     */
    public function rapportGestionnaires(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            // Filtres optionnels
            $gestionnaireId = $request->get('gestionnaire_id');
            $wilayaId = $request->get('wilaya_id');

            // Récupérer TOUS les gains avec leurs relations
            $query = GestionnaireGain::with([
                'gestionnaire.user',
                'livraison.demandeLivraison'
            ])->whereBetween('created_at', [$dateDebut, $dateFin]);

            // Appliquer les filtres si présents
            if ($gestionnaireId) {
                $query->where('gestionnaire_id', $gestionnaireId);
            }

            if ($wilayaId) {
                $query->whereHas('gestionnaire', function ($q) use ($wilayaId) {
                    $q->where('wilaya_id', $wilayaId);
                });
            }

            $gains = $query->get();

            // Statistiques
            $statsParWilaya = []; // Un ligne par (wilaya + gestionnaire)
            $gainsParGestionnaire = [];
            $totalCommissions = 0;
            $totalPrixLivraisons = 0;

            foreach ($gains as $gain) {
                $gestionnaire = $gain->gestionnaire;

                if (!$gestionnaire) {
                    continue;
                }

                $wilayaCode = $gestionnaire->wilaya_id;
                $gestionnaireId = $gestionnaire->id;
                $montantCommission = (float) $gain->montant_commission;

                // Prix de la livraison
                if ($gain->livraison && $gain->livraison->demandeLivraison) {
                    $totalPrixLivraisons += (float) ($gain->livraison->demandeLivraison->prix ?? 0);
                }

                // 1. STATS PAR WILAYA (une ligne par gestionnaire dans sa wilaya)
                $cleWilaya = $wilayaCode . '_' . $gestionnaireId; // Clé unique !

                if (!isset($statsParWilaya[$cleWilaya])) {
                    $statsParWilaya[$cleWilaya] = [
                        'code' => $wilayaCode,
                        'nom' => $this->getWilayaName($wilayaCode),
                        'gestionnaire_nom' => $gestionnaire->user ?
                            trim(($gestionnaire->user->prenom ?? '') . ' ' . ($gestionnaire->user->nom ?? '')) :
                            'Non assigné',
                        'gestionnaire_id' => $gestionnaireId,
                        'gestionnaire_email' => $gestionnaire->user->email ?? '',
                        'nb_livraisons' => 0,
                        'total_commissions' => 0,
                        'pourcentage' => 0
                    ];
                }

                $statsParWilaya[$cleWilaya]['nb_livraisons']++;
                $statsParWilaya[$cleWilaya]['total_commissions'] += $montantCommission;

                // 2. GAINS PAR GESTIONNAIRE (pour le détail)
                if (!isset($gainsParGestionnaire[$gestionnaireId])) {
                    $gainsParGestionnaire[$gestionnaireId] = [
                        'gestionnaire_id' => $gestionnaireId,
                        'gestionnaire_nom' => $gestionnaire->user ?
                            trim(($gestionnaire->user->prenom ?? '') . ' ' . ($gestionnaire->user->nom ?? '')) :
                            'Inconnu',
                        'gestionnaire_email' => $gestionnaire->user->email ?? '',
                        'wilaya_code' => $wilayaCode,
                        'wilaya_nom' => $this->getWilayaName($wilayaCode),
                        'total_commissions' => 0,
                        'nb_livraisons' => 0,
                        'pourcentage_applique' => (float) $gain->pourcentage_applique,
                        'statut' => $gain->status
                    ];
                }

                $gainsParGestionnaire[$gestionnaireId]['total_commissions'] += $montantCommission;
                $gainsParGestionnaire[$gestionnaireId]['nb_livraisons']++;
                $totalCommissions += $montantCommission;
            }

            // Calculer les pourcentages pour chaque ligne
            foreach ($statsParWilaya as &$ligne) {
                $ligne['pourcentage'] = $totalCommissions > 0
                    ? round(($ligne['total_commissions'] / $totalCommissions) * 100, 1)
                    : 0;
            }

            // Réindexer et trier
            $statsParWilaya = array_values($statsParWilaya);
            usort($statsParWilaya, function ($a, $b) {
                return $b['total_commissions'] <=> $a['total_commissions'];
            });

            // Top gestionnaires
            $topGestionnaires = collect($gainsParGestionnaire)
                ->sortByDesc('total_commissions')
                ->take(5)
                ->values()
                ->map(function ($item) {
                    return [
                        'id' => $item['gestionnaire_id'],
                        'nom' => $item['gestionnaire_nom'],
                        'email' => $item['gestionnaire_email'],
                        'wilaya' => $item['wilaya_code'],
                        'total_gains' => $item['total_commissions'],
                        'nb_livraisons' => $item['nb_livraisons'],
                    ];
                });

            // Totaux
            $totaux = [
                'total_commissions' => $totalCommissions,
                'nb_gestionnaires' => count($gainsParGestionnaire),
                'nb_livraisons' => $gains->count(),
                'moyenne_par_gestionnaire' => count($gainsParGestionnaire) > 0
                    ? round($totalCommissions / count($gainsParGestionnaire), 2)
                    : 0,
                'part_societe_mere' => $totalPrixLivraisons - $totalCommissions
            ];

            // Détails par gestionnaire
            $details = array_values($gainsParGestionnaire);

            return response()->json([
                'success' => true,
                'data' => [
                    'periode' => [
                        'debut' => $dateDebut->format('Y-m-d'),
                        'fin' => $dateFin->format('Y-m-d'),
                        'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                    ],
                    'totaux' => $totaux,
                    'par_wilaya' => $statsParWilaya,
                    'top_gestionnaires' => $topGestionnaires,
                    'details' => $details
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur rapportGestionnaires: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du rapport: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des impayés (gains en attente et demandes envoyées)
     */
    public function impayes(Request $request): JsonResponse
    {
        try {
            // Récupérer les gains en attente ET les demandes envoyées
            $impayes = GestionnaireGain::with(['gestionnaire.user', 'livraison'])
                ->whereIn('status', ['en_attente', 'demande_envoyee'])
                ->orderBy('created_at', 'desc')
                ->get();

            $formattedImpayes = $impayes->map(function ($gain) {
                return [
                    'id' => $gain->id,
                    'gestionnaire_id' => $gain->gestionnaire_id,
                    'livraison_id' => $gain->livraison_id,
                    'montant_commission' => $gain->montant_commission,
                    'pourcentage_applique' => $gain->pourcentage_applique,
                    'status' => $gain->status,
                    'created_at' => $gain->created_at,
                    'updated_at' => $gain->updated_at,
                    'wilaya_type' => $gain->wilaya_type,
                    'date_calcul' => $gain->date_calcul,
                    'date_demande' => $gain->date_demande,
                    'date_paiement' => $gain->date_paiement,
                    'note_admin' => $gain->note_admin,
                    'gestionnaire' => $gain->gestionnaire ? [
                        'id' => $gain->gestionnaire->id,
                        'user_id' => $gain->gestionnaire->user_id,
                        'wilaya_id' => $gain->gestionnaire->wilaya_id,
                        'status' => $gain->gestionnaire->status,
                        'user' => $gain->gestionnaire->user ? [
                            'id' => $gain->gestionnaire->user->id,
                            'nom' => $gain->gestionnaire->user->nom,
                            'prenom' => $gain->gestionnaire->user->prenom,
                            'email' => $gain->gestionnaire->user->email,
                        ] : null
                    ] : null,
                    'livraison' => $gain->livraison ? [
                        'id' => $gain->livraison->id,
                        'status' => $gain->livraison->status,
                        'code_pin' => $gain->livraison->code_pin,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedImpayes
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur impayes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des impayés'
            ], 500);
        }
    }

    /**
     * Exporter le rapport
     */
    public function exportRapport(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'mois');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $livraisons = Livraison::with([
                'demandeLivraison',
                'client.user',
                'livreurRamasseur.user',
                'livreurDistributeur.user'
            ])
                ->where('status', 'livre')
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->get();

            $data = [
                'periode' => [
                    'debut' => $dateDebut->format('Y-m-d'),
                    'fin' => $dateFin->format('Y-m-d'),
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'livraisons' => $livraisons,
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('pdf.rapport-gains', ['data' => $data]);
                $pdf->setPaper('A4', 'landscape');
                return $pdf->download('rapport-gains-' . Carbon::now()->format('Ymd-His') . '.pdf');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Export Excel en cours de développement'
                ], 501);
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportRapport: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }

    /**
     * Exporter le rapport des gestionnaires (Excel/PDF/CSV)
     */
    public function exportRapportGestionnaires(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'mois');
            $gestionnaireId = $request->get('gestionnaire_id');
            $wilayaId = $request->get('wilaya_id');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            // Récupérer les gains avec les relations
            $query = GestionnaireGain::with(['gestionnaire.user', 'livraison.demandeLivraison'])
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->orderBy('created_at', 'desc');

            if ($gestionnaireId) {
                $query->where('gestionnaire_id', $gestionnaireId);
            }

            if ($wilayaId) {
                $query->whereHas('gestionnaire', function ($q) use ($wilayaId) {
                    $q->where('wilaya_id', $wilayaId);
                });
            }

            $gains = $query->get();

            $periodeData = [
                'debut' => $dateDebut->format('d/m/Y'),
                'fin' => $dateFin->format('d/m/Y'),
                'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
            ];

            $dateGeneration = Carbon::now();

            switch ($format) {
                case 'pdf':
                    $data = [
                        'periode' => $periodeData,
                        'gains' => $gains,
                        'date_generation' => $dateGeneration->format('d/m/Y H:i:s'),
                        'total_montant' => $gains->sum('montant_commission'),
                        'total_gains' => $gains->count()
                    ];

                    $pdf = Pdf::loadView('pdf.rapport-gestionnaires', ['data' => $data]);
                    $pdf->setPaper('A4', 'landscape');
                    $pdf->setOptions([
                        'defaultFont' => 'DejaVu Sans',
                        'isHtml5ParserEnabled' => true,
                        'isRemoteEnabled' => true
                    ]);

                    $filename = 'rapport-gestionnaires-' . $dateGeneration->format('Ymd-His') . '.pdf';
                    return $pdf->download($filename);

                case 'csv':
                    $filename = 'rapport-gestionnaires-' . $dateGeneration->format('Ymd-His') . '.csv';
                    return Excel::download(
                        new RapportGestionnairesCsvExport($gains, $periodeData),
                        $filename
                    );

                case 'excel':
                default:
                    $filename = 'rapport-gestionnaires-' . $dateGeneration->format('Ymd-His') . '.xlsx';
                    return Excel::download(
                        new RapportGestionnairesExport($gains, $periodeData),
                        $filename
                    );
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportRapportGestionnaires: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les gains détaillés
     */
    public function getGainsDetails(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $gains = GestionnaireGain::with(['gestionnaire.user', 'livraison'])
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $gains
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getGainsDetails: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains'
            ], 500);
        }
    }

    /**
     * Récupérer les gains d'un gestionnaire spécifique
     */
    public function getGainsGestionnaire(Request $request, $gestionnaireId): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'mois');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $gains = GestionnaireGain::with('livraison')
                ->where('gestionnaire_id', $gestionnaireId)
                ->whereBetween('created_at', [$dateDebut, $dateFin])
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'gestionnaire_id' => $gestionnaireId,
                    'total_gains' => $gains->sum('montant_commission'),
                    'nb_livraisons' => $gains->count(),
                    'details' => $gains
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
     * Récupérer les gains d'une navette spécifique
     */
    public function getGainsNavette(Request $request, $navetteId): JsonResponse
    {
        try {
            $navette = Navette::find($navetteId);

            if (!$navette) {
                return response()->json([
                    'success' => false,
                    'message' => 'Navette introuvable'
                ], 404);
            }

            // Récupérer tous les gains de cette navette avec les relations
            $gains = GestionnaireGain::with(['gestionnaire.user', 'hub', 'livraison.demandeLivraison'])
                ->where('navette_id', $navetteId)
                ->get();

            // Récupérer la répartition des acteurs
            $acteurs = $navette->acteurs;

            // Formater les gains par acteur
            $gainsParActeur = [];
            $totalGains = 0;
            $totalPrixLivraisons = 0;

            foreach ($gains as $gain) {
                if ($gain->gestionnaire_id) {
                    $key = "gestionnaire_{$gain->gestionnaire_id}";
                    if (!isset($gainsParActeur[$key])) {
                        $gainsParActeur[$key] = [
                            'type' => 'gestionnaire',
                            'id' => $gain->gestionnaire_id,
                            'nom' => $gain->gestionnaire->user->prenom . ' ' . $gain->gestionnaire->user->nom,
                            'email' => $gain->gestionnaire->user->email,
                            'wilaya' => $gain->gestionnaire->wilaya_id,
                            'total_gains' => 0,
                            'nb_livraisons' => 0,
                            'details' => []
                        ];
                    }
                    $gainsParActeur[$key]['total_gains'] += $gain->montant_commission;
                    $gainsParActeur[$key]['nb_livraisons']++;
                    $gainsParActeur[$key]['details'][] = [
                        'livraison_id' => $gain->livraison_id,
                        'montant' => $gain->montant_commission,
                        'pourcentage' => $gain->pourcentage_applique,
                        'date' => $gain->date_calcul->format('d/m/Y H:i'),
                        'prix_livraison' => $gain->livraison->demandeLivraison->prix ?? 0
                    ];
                } elseif ($gain->hub_id) {
                    $key = "hub_{$gain->hub_id}";
                    if (!isset($gainsParActeur[$key])) {
                        $gainsParActeur[$key] = [
                            'type' => 'hub',
                            'id' => $gain->hub_id,
                            'nom' => $gain->hub->nom,
                            'email' => $gain->hub->email,
                            'total_gains' => 0,
                            'nb_livraisons' => 0,
                            'details' => []
                        ];
                    }
                    $gainsParActeur[$key]['total_gains'] += $gain->montant_commission;
                    $gainsParActeur[$key]['nb_livraisons']++;
                    $gainsParActeur[$key]['details'][] = [
                        'livraison_id' => $gain->livraison_id,
                        'montant' => $gain->montant_commission,
                        'pourcentage' => $gain->pourcentage_applique,
                        'date' => $gain->date_calcul->format('d/m/Y H:i'),
                        'prix_livraison' => $gain->livraison->demandeLivraison->prix ?? 0
                    ];
                }
                $totalGains += $gain->montant_commission;

                // Calculer le prix total des livraisons
                if ($gain->livraison && $gain->livraison->demandeLivraison) {
                    $totalPrixLivraisons += $gain->livraison->demandeLivraison->prix ?? 0;
                }
            }

            // Formater la répartition
            $repartition = [];
            foreach ($acteurs as $acteur) {
                if ($acteur->type === 'gestionnaire') {
                    $gestionnaire = $acteur->gestionnaire;
                    if ($gestionnaire && $gestionnaire->user) {
                        $repartition[] = [
                            'type' => 'gestionnaire',
                            'id' => $acteur->acteur_id,
                            'nom' => $gestionnaire->user->prenom . ' ' . $gestionnaire->user->nom,
                            'email' => $gestionnaire->user->email,
                            'wilaya' => $acteur->wilaya_code,
                            'part_pourcentage' => (float) $acteur->part_pourcentage
                        ];
                    }
                } elseif ($acteur->type === 'hub') {
                    $hub = $acteur->hub;
                    if ($hub) {
                        $repartition[] = [
                            'type' => 'hub',
                            'id' => $acteur->acteur_id,
                            'nom' => $hub->nom,
                            'email' => $hub->email,
                            'part_pourcentage' => (float) $acteur->part_pourcentage
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'navette' => [
                        'id' => $navette->id,
                        'reference' => $navette->reference,
                        'status' => $navette->status,
                        'date_depart' => $navette->date_depart,
                        'date_arrivee_reelle' => $navette->date_arrivee_reelle
                    ],
                    'repartition' => $repartition,
                    'gains_par_acteur' => array_values($gainsParActeur),
                    'total_gains' => $totalGains,
                    'total_prix_livraisons' => $totalPrixLivraisons,
                    'part_admin' => $totalPrixLivraisons - $totalGains,
                    'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur getGainsNavette: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des gains de la navette'
            ], 500);
        }
    }

    /**
     * Statistiques mensuelles
     */
    public function statistiquesMensuelles(Request $request): JsonResponse
    {
        try {
            $annee = $request->get('annee', Carbon::now()->year);
            $stats = [];

            for ($mois = 1; $mois <= 12; $mois++) {
                $debutMois = Carbon::create($annee, $mois, 1)->startOfMonth();
                $finMois = Carbon::create($annee, $mois, 1)->endOfMonth();

                $gains = GestionnaireGain::whereBetween('created_at', [$debutMois, $finMois])->sum('montant_commission');
                $livraisons = Livraison::where('status', 'livre')
                    ->whereBetween('created_at', [$debutMois, $finMois])
                    ->count();

                $stats[] = [
                    'mois' => $debutMois->locale('fr')->isoFormat('MMMM'),
                    'mois_num' => $mois,
                    'gains' => $gains,
                    'livraisons' => $livraisons
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur statistiquesMensuelles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques'
            ], 500);
        }
    }

    /**
     * Évolution mensuelle
     */
    public function evolutionMensuelle(Request $request): JsonResponse
    {
        try {
            $periode = $request->get('periode', 'annee');
            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $evolution = [];
            $current = $dateDebut->copy();

            while ($current <= $dateFin) {
                $debutMois = $current->copy()->startOfMonth();
                $finMois = $current->copy()->endOfMonth();

                $evolution[] = [
                    'mois' => $current->locale('fr')->isoFormat('MMM YYYY'),
                    'date' => $current->format('Y-m'),
                    'chiffre_affaires' => Livraison::where('status', 'livre')
                        ->whereBetween('created_at', [$debutMois, $finMois])
                        ->sum('demandeLivraison.prix'),
                    'nb_livraisons' => Livraison::where('status', 'livre')
                        ->whereBetween('created_at', [$debutMois, $finMois])
                        ->count(),
                ];

                $current->addMonth();
            }

            return response()->json([
                'success' => true,
                'data' => $evolution
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur evolutionMensuelle: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul de l\'évolution'
            ], 500);
        }
    }

    // ==================== MÉTHODES STATISTIQUES ====================

    private function getStatsColis($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Colis::query();

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                $q->where('wilaya_depot', $wilayaId);
            });
        }

        $colis = $query->get();

        return [
            'total' => $colis->count(),
            'valeur_totale' => $colis->sum('colis_prix') ?? 0,
            'valeur_moyenne' => $colis->avg('colis_prix') ?? 0,
            'poids_total' => $colis->sum('poids') ?? 0,
            'poids_moyen' => $colis->avg('poids') ?? 0,
            'avec_prix' => $colis->whereNotNull('colis_prix')->count(),
            'sans_prix' => $colis->whereNull('colis_prix')->count()
        ];
    }

    private function getStatsLivraisons($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Livraison::with('demandeLivraison');

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                $q->where(function ($sub) use ($wilayaId) {
                    $sub->where('wilaya', $wilayaId)
                        ->orWhere('wilaya_depot', $wilayaId);
                });
            });
        }

        $livraisons = $query->get();

        $prixTotal = 0;
        foreach ($livraisons as $livraison) {
            if ($livraison->demandeLivraison) {
                $prixTotal += $livraison->demandeLivraison->prix ?? 0;
            }
        }

        return [
            'total' => $livraisons->count(),
            'terminees' => $livraisons->where('status', 'livre')->count(),
            'en_cours' => $livraisons->whereNotIn('status', ['livre', 'annule'])->count(),
            'en_attente' => $livraisons->where('status', 'en_attente')->count(),
            'annulees' => $livraisons->where('status', 'annule')->count(),
            'prix_total' => $prixTotal,
            'prix_moyen' => $livraisons->count() > 0 ? $prixTotal / $livraisons->count() : 0,
            'par_statut' => [
                'en_attente' => $livraisons->where('status', 'en_attente')->count(),
                'prise_en_charge_ramassage' => $livraisons->where('status', 'prise_en_charge_ramassage')->count(),
                'ramasse' => $livraisons->where('status', 'ramasse')->count(),
                'en_transit' => $livraisons->where('status', 'en_transit')->count(),
                'prise_en_charge_livraison' => $livraisons->where('status', 'prise_en_charge_livraison')->count(),
                'livre' => $livraisons->where('status', 'livre')->count(),
                'annule' => $livraisons->where('status', 'annule')->count()
            ]
        ];
    }



    private function getStatsNavettes($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $query = Navette::query();

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        if ($wilayaId) {
            $query->where(function ($q) use ($wilayaId) {
                $q->where('wilaya_depart_id', $wilayaId)
                    ->orWhere('wilaya_arrivee_id', $wilayaId)
                    // Correction : utiliser JSON_CONTAINS ou LIKE pour les wilayas de transit
                    ->orWhereJsonContains('wilayas_transit', $wilayaId);
            });
        }

        $navettes = $query->get();

        $revenusNavettes = 0;
        $colisTransportes = 0;

        foreach ($navettes as $navette) {
            $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
            $colisTransportes += $navette->nb_colis;
        }

        return [
            'total' => $navettes->count(),
            'planifiees' => $navettes->where('status', 'planifiee')->count(),
            'en_cours' => $navettes->where('status', 'en_cours')->count(),
            'terminees' => $navettes->where('status', 'terminee')->count(),
            'annulees' => $navettes->where('status', 'annulee')->count(),
            'revenus' => $revenusNavettes,
            'colis_transportes' => $colisTransportes,
            'distance_totale' => $navettes->sum('distance_km') ?? 0,
            'distance_moyenne' => $navettes->count() > 0 ? round($navettes->avg('distance_km') ?? 0, 2) : 0,
            'taux_remplissage_moyen' => $navettes->count() > 0 ? round($navettes->avg('taux_remplissage') ?? 0, 2) : 0
        ];
    }

    private function getBilanFinancier($wilayaId = null, $dateDebut = null, $dateFin = null): array
    {
        $colisQuery = Colis::query();
        if ($dateDebut && $dateFin) {
            $colisQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $colisQuery->whereHas('demandeLivraisons', function ($q) use ($wilayaId) {
                $q->where('wilaya_depot', $wilayaId);
            });
        }
        $valeurColis = $colisQuery->sum('colis_prix') ?? 0;

        $livraisonsQuery = Livraison::where('status', 'livre')->with('demandeLivraison');
        if ($dateDebut && $dateFin) {
            $livraisonsQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $livraisonsQuery->whereHas('demandeLivraison', function ($q) use ($wilayaId) {
                $q->where(function ($sub) use ($wilayaId) {
                    $sub->where('wilaya', $wilayaId)
                        ->orWhere('wilaya_depot', $wilayaId);
                });
            });
        }

        $livraisons = $livraisonsQuery->get();
        $revenusLivraisons = 0;
        foreach ($livraisons as $livraison) {
            if ($livraison->demandeLivraison) {
                $revenusLivraisons += $livraison->demandeLivraison->prix ?? 0;
            }
        }

        $navettesQuery = Navette::where('status', 'terminee');
        if ($dateDebut && $dateFin) {
            $navettesQuery->whereBetween('created_at', [$dateDebut, $dateFin]);
        }
        if ($wilayaId) {
            $navettesQuery->where(function ($q) use ($wilayaId) {
                $q->where('wilaya_depart_id', $wilayaId)
                    ->orWhere('wilaya_arrivee_id', $wilayaId);
            });
        }

        $revenusNavettes = 0;
        foreach ($navettesQuery->get() as $navette) {
            $revenusNavettes += $navette->prix_base + ($navette->nb_colis * $navette->prix_par_colis);
        }

        $total = $valeurColis + $revenusLivraisons + $revenusNavettes;

        return [
            'valeur_colis' => $valeurColis,
            'revenus_livraisons' => $revenusLivraisons,
            'revenus_navettes' => $revenusNavettes,
            'chiffre_affaires_total' => $total,
            'repartition' => [
                'colis' => [
                    'montant' => $valeurColis,
                    'pourcentage' => $total > 0 ? round(($valeurColis / $total) * 100, 2) : 0
                ],
                'livraisons' => [
                    'montant' => $revenusLivraisons,
                    'pourcentage' => $total > 0 ? round(($revenusLivraisons / $total) * 100, 2) : 0
                ],
                'navettes' => [
                    'montant' => $revenusNavettes,
                    'pourcentage' => $total > 0 ? round(($revenusNavettes / $total) * 100, 2) : 0
                ]
            ]
        ];
    }

    private function getTopWilayas($dateDebut = null, $dateFin = null): array
    {
        $queryDeparts = DemandeLivraison::select('wilaya_depot', DB::raw('count(*) as total'))
            ->whereNotNull('wilaya_depot');

        $queryArrivees = DemandeLivraison::select('wilaya', DB::raw('count(*) as total'))
            ->whereNotNull('wilaya');

        if ($dateDebut && $dateFin) {
            $queryDeparts->whereBetween('created_at', [$dateDebut, $dateFin]);
            $queryArrivees->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        $topDeparts = $queryDeparts
            ->groupBy('wilaya_depot')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'wilaya' => $item->wilaya_depot,
                    'nom' => $this->getWilayaName($item->wilaya_depot),
                    'total' => $item->total
                ];
            });

        $topArrivees = $queryArrivees
            ->groupBy('wilaya')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'wilaya' => $item->wilaya,
                    'nom' => $this->getWilayaName($item->wilaya),
                    'total' => $item->total
                ];
            });

        return [
            'departs' => $topDeparts,
            'arrivees' => $topArrivees
        ];
    }

    private function getEvolutionGlobale($dateDebut = null, $dateFin = null): array
    {
        if (!$dateDebut || !$dateFin) {
            $dateFin = Carbon::now();
            $dateDebut = Carbon::now()->subYear();
            $dateDebutPrec = Carbon::now()->subYears(2);
            $dateFinPrec = Carbon::now()->subYear()->subDay();
        } else {
            $duree = $dateDebut->diffInDays($dateFin);
            $dateDebutPrec = $dateDebut->copy()->subDays($duree + 1);
            $dateFinPrec = $dateDebut->copy()->subDay();
        }

        $periodeActuelle = $this->getBilanFinancier(null, $dateDebut, $dateFin);
        $periodePrecedente = $this->getBilanFinancier(null, $dateDebutPrec, $dateFinPrec);

        $evolutionCA = $periodePrecedente['chiffre_affaires_total'] > 0
            ? round((($periodeActuelle['chiffre_affaires_total'] - $periodePrecedente['chiffre_affaires_total']) / $periodePrecedente['chiffre_affaires_total']) * 100, 2)
            : 0;

        return [
            'chiffre_affaires' => [
                'actuel' => $periodeActuelle['chiffre_affaires_total'],
                'precedent' => $periodePrecedente['chiffre_affaires_total'],
                'evolution' => $evolutionCA,
                'tendance' => $evolutionCA >= 0 ? 'hausse' : 'baisse'
            ]
        ];
    }

    private function getEvolutionWilaya($wilayaId, $dateDebut = null, $dateFin = null): array
    {
        if (!$dateDebut || !$dateFin) {
            $dateFin = Carbon::now();
            $dateDebut = Carbon::now()->subYear();
            $dateDebutPrec = Carbon::now()->subYears(2);
            $dateFinPrec = Carbon::now()->subYear()->subDay();
        } else {
            $duree = $dateDebut->diffInDays($dateFin);
            $dateDebutPrec = $dateDebut->copy()->subDays($duree + 1);
            $dateFinPrec = $dateDebut->copy()->subDay();
        }

        $periodeActuelle = $this->getBilanFinancier($wilayaId, $dateDebut, $dateFin);
        $periodePrecedente = $this->getBilanFinancier($wilayaId, $dateDebutPrec, $dateFinPrec);

        $evolutionCA = $periodePrecedente['chiffre_affaires_total'] > 0
            ? round((($periodeActuelle['chiffre_affaires_total'] - $periodePrecedente['chiffre_affaires_total']) / $periodePrecedente['chiffre_affaires_total']) * 100, 2)
            : 0;

        return [
            'chiffre_affaires' => [
                'actuel' => $periodeActuelle['chiffre_affaires_total'],
                'precedent' => $periodePrecedente['chiffre_affaires_total'],
                'evolution' => $evolutionCA,
                'tendance' => $evolutionCA >= 0 ? 'hausse' : 'baisse'
            ]
        ];
    }

    // ==================== UTILITAIRES ====================

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
                return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
        }
    }

    private function getPeriodLibelle($periode, $debut, $fin): string
    {
        if (!$debut || !$fin) {
            return 'Période non définie';
        }

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

    private function getWilayaName($code): string
    {
        if ($code === null) {
            return 'Wilaya inconnue';
        }

        $wilayas = [
            '01' => 'Adrar',
            '02' => 'Chlef',
            '03' => 'Laghouat',
            '04' => 'Oum El Bouaghi',
            '05' => 'Batna',
            '06' => 'Béjaïa',
            '07' => 'Biskra',
            '08' => 'Béchar',
            '09' => 'Blida',
            '10' => 'Bouira',
            '11' => 'Tamanrasset',
            '12' => 'Tébessa',
            '13' => 'Tlemcen',
            '14' => 'Tiaret',
            '15' => 'Tizi Ouzou',
            '16' => 'Alger',
            '17' => 'Djelfa',
            '18' => 'Jijel',
            '19' => 'Sétif',
            '20' => 'Saïda',
            '21' => 'Skikda',
            '22' => 'Sidi Bel Abbès',
            '23' => 'Annaba',
            '24' => 'Guelma',
            '25' => 'Constantine',
            '26' => 'Médéa',
            '27' => 'Mostaganem',
            '28' => 'M\'Sila',
            '29' => 'Mascara',
            '30' => 'Ouargla',
            '31' => 'Oran',
            '32' => 'El Bayadh',
            '33' => 'Illizi',
            '34' => 'Bordj Bou Arréridj',
            '35' => 'Boumerdès',
            '36' => 'El Tarf',
            '37' => 'Tindouf',
            '38' => 'Tissemsilt',
            '39' => 'El Oued',
            '40' => 'Khenchela',
            '41' => 'Souk Ahras',
            '42' => 'Tipaza',
            '43' => 'Mila',
            '44' => 'Aïn Defla',
            '45' => 'Naâma',
            '46' => 'Aïn Témouchent',
            '47' => 'Ghardaïa',
            '48' => 'Relizane',
            '49' => 'Timimoun',
            '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal',
            '52' => 'Béni Abbès',
            '53' => 'In Salah',
            '54' => 'In Guezzam',
            '55' => 'Touggourt',
            '56' => 'Djanet',
            '57' => 'El M\'Ghair',
            '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? 'Wilaya ' . $code;
    }

    // ==================== MÉTHODES D'EXPORT ====================

    public function exportBilanGlobal(Request $request)
    {
        try {
            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'annee');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $data = [
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis(null, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons(null, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes(null, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier(null, $dateDebut, $dateFin),
                'top_wilayas' => $this->getTopWilayas($dateDebut, $dateFin),
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('pdf.bilan-global', ['data' => $data]);
                $pdf->setPaper('A4', 'portrait');
                return $pdf->download('bilan-global-' . Carbon::now()->format('Ymd-His') . '.pdf');
            } else {
                return Excel::download(
                    new BilanGlobalExport($data),
                    'bilan-global-' . Carbon::now()->format('Ymd-His') . '.xlsx'
                );
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportBilanGlobal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }

    public function exportBilanGestionnaire(Request $request, $wilayaId = null)
    {
        try {
            $user = $request->user();

            if ($user->role === 'admin' && $wilayaId) {
                $wilaya = $wilayaId;
            } else {
                $gestionnaire = $user->gestionnaire;
                if (!$gestionnaire) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès non autorisé'
                    ], 403);
                }
                $wilaya = $gestionnaire->wilaya_id;
            }

            $format = $request->get('format', 'excel');
            $periode = $request->get('periode', 'annee');

            $dates = $this->getPeriodDates($periode, $request);
            $dateDebut = $dates[0];
            $dateFin = $dates[1];

            $data = [
                'wilaya_id' => $wilaya,
                'wilaya_nom' => $this->getWilayaName($wilaya),
                'periode' => [
                    'debut' => $dateDebut ? $dateDebut->format('Y-m-d') : 'Toutes',
                    'fin' => $dateFin ? $dateFin->format('Y-m-d') : 'Toutes',
                    'libelle' => $this->getPeriodLibelle($periode, $dateDebut, $dateFin)
                ],
                'colis' => $this->getStatsColis($wilaya, $dateDebut, $dateFin),
                'livraisons' => $this->getStatsLivraisons($wilaya, $dateDebut, $dateFin),
                'navettes' => $this->getStatsNavettes($wilaya, $dateDebut, $dateFin),
                'finances' => $this->getBilanFinancier($wilaya, $dateDebut, $dateFin),
                'date_generation' => Carbon::now()->format('d/m/Y H:i:s')
            ];

            if ($format === 'pdf') {
                $pdf = Pdf::loadView('pdf.bilan-gestionnaire', ['data' => $data]);
                $pdf->setPaper('A4', 'portrait');
                $filename = 'bilan-' . $data['wilaya_nom'] . '-' . Carbon::now()->format('Ymd-His') . '.pdf';
                return $pdf->download($filename);
            } else {
                return Excel::download(
                    new BilanGestionnaireExport($data),
                    'bilan-' . $data['wilaya_nom'] . '-' . Carbon::now()->format('Ymd-His') . '.xlsx'
                );
            }
        } catch (\Exception $e) {
            Log::error('Erreur exportBilanGestionnaire: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export'
            ], 500);
        }
    }

    /**
     * Récupérer l'historique des gains traités (payés et annulés)
     */
    public function historiqueGains(Request $request): JsonResponse
    {
        try {
            $historique = GestionnaireGain::with(['gestionnaire.user', 'livraison'])
                ->whereIn('status', ['paye', 'annule'])
                ->orderBy('updated_at', 'desc')
                ->get();

            $formattedHistorique = $historique->map(function ($gain) {
                return [
                    'id' => $gain->id,
                    'gestionnaire_id' => $gain->gestionnaire_id,
                    'livraison_id' => $gain->livraison_id,
                    'montant_commission' => $gain->montant_commission,
                    'pourcentage_applique' => $gain->pourcentage_applique,
                    'status' => $gain->status,
                    'created_at' => $gain->created_at,
                    'updated_at' => $gain->updated_at,
                    'wilaya_type' => $gain->wilaya_type,
                    'date_calcul' => $gain->date_calcul,
                    'date_demande' => $gain->date_demande,
                    'date_paiement' => $gain->date_paiement,
                    'note_admin' => $gain->note_admin,
                    'gestionnaire' => $gain->gestionnaire ? [
                        'id' => $gain->gestionnaire->id,
                        'user_id' => $gain->gestionnaire->user_id,
                        'wilaya_id' => $gain->gestionnaire->wilaya_id,
                        'status' => $gain->gestionnaire->status,
                        'user' => $gain->gestionnaire->user ? [
                            'id' => $gain->gestionnaire->user->id,
                            'nom' => $gain->gestionnaire->user->nom,
                            'prenom' => $gain->gestionnaire->user->prenom,
                            'email' => $gain->gestionnaire->user->email,
                        ] : null
                    ] : null,
                    'livraison' => $gain->livraison ? [
                        'id' => $gain->livraison->id,
                        'status' => $gain->livraison->status,
                        'code_pin' => $gain->livraison->code_pin,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedHistorique
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur historiqueGains: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }

    /**
     * Supprimer un gain de l'historique
     */
    public function supprimerGain(Request $request, $gainId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $gain = GestionnaireGain::find($gainId);

            if (!$gain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gain non trouvé'
                ], 404);
            }

            // Vérifier que le gain est bien dans l'historique (payé ou annulé)
            if (!in_array($gain->status, ['paye', 'annule'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seuls les gains de l\'historique (payés ou annulés) peuvent être supprimés'
                ], 400);
            }

            // Supprimer le gain
            $gain->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Gain supprimé de l\'historique avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur supprimerGain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Restaurer un gain supprimé (soft delete si vous voulez)
     */
    // Optionnel : si vous voulez un soft delete
}
