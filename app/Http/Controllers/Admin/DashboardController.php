<?php
// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Livreur;
use App\Models\Livraison;
use App\Models\Client;
use App\Models\DemandeAdhesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Récupérer toutes les statistiques pour le dashboard admin
     */
    public function index(): JsonResponse
    {
        try {
            Log::info('DashboardController: Début du chargement des statistiques');

            // Statistiques des utilisateurs - Version sécurisée
            $users = [
                'total' => User::count(),
                'clients' => User::where('role', 'client')->count(),
                'livreurs' => User::where('role', 'livreur')->count(),
                'admins' => User::where('role', 'admin')->count(),
                'actifs' => User::where('actif', true)->count(),
                'inactifs' => User::where('actif', false)->count(),
            ];

            Log::info('DashboardController: Statistiques utilisateurs OK', $users);

            // Statistiques des livreurs
            $livreurs = [
                'total' => Livreur::count(),
                'actifs' => Livreur::where('desactiver', false)->count(),
                'inactifs' => Livreur::where('desactiver', true)->count(),
                'distributeurs' => Livreur::where('type', 'distributeur')->count(),
                'ramasseurs' => Livreur::where('type', 'ramasseur')->count(),
            ];

            Log::info('DashboardController: Statistiques livreurs OK');

            // Statistiques des livraisons
            $livraisons = [
                'total' => Livraison::count(),
                'terminees' => Livraison::where('status', 'livre')->count(),
                'annulees' => Livraison::where('status', 'annule')->count(),
                'en_attente' => Livraison::where('status', 'en_attente')->count(),
                'en_cours' => Livraison::whereNotIn('status', ['livre', 'annule', 'en_attente'])->count(),
            ];

            Log::info('DashboardController: Statistiques livraisons OK');

            // Demandes d'adhésion en attente
            $demandes_attente = DemandeAdhesion::where('status', 'pending')->count();

            // Évolution des inscriptions (7 derniers jours)
            $evolution_inscriptions = [];
            $evolution_livraisons = [];

            try {
                $startDate = Carbon::now()->subDays(7);

                // Inscriptions par jour
                $inscriptions = User::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total')
                )
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                foreach ($inscriptions as $item) {
                    $evolution_inscriptions[Carbon::parse($item->date)->format('d/m')] = $item->total;
                }

                // Livraisons par jour
                $livraisonsJour = Livraison::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total')
                )
                    ->where('created_at', '>=', $startDate)
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                foreach ($livraisonsJour as $item) {
                    $evolution_livraisons[Carbon::parse($item->date)->format('d/m')] = $item->total;
                }
            } catch (\Exception $e) {
                Log::warning('DashboardController: Erreur lors du calcul des évolutions', [
                    'error' => $e->getMessage()
                ]);
            }

            // Activités récentes (10 dernières livraisons)
            $recent_activities = [];

            try {
                $recentLivraisons = Livraison::with([
                    'client.user',
                    'livreurDistributeur.user',
                    'livreurRamasseur.user'
                ])
                    ->orderBy('updated_at', 'desc')
                    ->limit(10)
                    ->get();

                foreach ($recentLivraisons as $livraison) {
                    $clientName = 'Client inconnu';
                    if ($livraison->client && $livraison->client->user) {
                        $clientName = trim($livraison->client->user->prenom . ' ' . $livraison->client->user->nom);
                    }

                    $livreurName = 'Non attribué';
                    if ($livraison->livreurDistributeur && $livraison->livreurDistributeur->user) {
                        $livreurName = trim($livraison->livreurDistributeur->user->prenom . ' ' . $livraison->livreurDistributeur->user->nom);
                    } elseif ($livraison->livreurRamasseur && $livraison->livreurRamasseur->user) {
                        $livreurName = trim($livraison->livreurRamasseur->user->prenom . ' ' . $livraison->livreurRamasseur->user->nom);
                    }

                    $recent_activities[] = [
                        'id' => $livraison->id,
                        'numero' => $livraison->code_pin ?? '#' . substr($livraison->id, 0, 8),
                        'status' => $livraison->status ?? 'inconnu',
                        'client' => $clientName,
                        'livreur' => $livreurName,
                        'date' => $livraison->updated_at,
                        'created_at' => $livraison->created_at
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('DashboardController: Erreur lors du chargement des activités récentes', [
                    'error' => $e->getMessage()
                ]);
            }

            $response = [
                'success' => true,
                'data' => [
                    'users' => $users,
                    'livreurs' => $livreurs,
                    'livraisons' => $livraisons,
                    'demandes_attente' => $demandes_attente,
                    'evolution' => [
                        'inscriptions' => $evolution_inscriptions,
                        'livraisons' => $evolution_livraisons,
                    ],
                    'recent_activities' => $recent_activities,
                ]
            ];

            Log::info('DashboardController: Données chargées avec succès');

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('DashboardController: Erreur fatale', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques détaillées pour les graphiques
     */
    public function charts(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'week');

            $startDate = match ($period) {
                'week' => Carbon::now()->subDays(7),
                'month' => Carbon::now()->subDays(30),
                'year' => Carbon::now()->subDays(365),
                default => Carbon::now()->subDays(7),
            };

            // Données pour les graphiques
            $usersByDay = User::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $livraisonsByDay = Livraison::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "livre" THEN 1 ELSE 0 END) as terminees')
            )
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Répartition des rôles
            $roles = User::select('role', DB::raw('COUNT(*) as total'))
                ->groupBy('role')
                ->get();

            // Répartition des statuts de livraison
            $status = Livraison::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'users_by_day' => $usersByDay,
                    'livraisons_by_day' => $livraisonsByDay,
                    'roles_distribution' => $roles,
                    'status_distribution' => $status,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('DashboardController.charts: Erreur', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des graphiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
