<?php

namespace App\Http\Controllers;

use App\Models\Livraison;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LivreurStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Récupère le livreur connecté et son type
     */
    private function getLivreur()
    {
        $user = auth()->user();
        if (!$user || !$user->livreur) {
            abort(403, 'Accès refusé : vous n\'êtes pas un livreur.');
        }
        return $user->livreur;
    }

    /**
     * Récupère toutes les statistiques du livreur en une seule requête
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = auth()->user();
            $livreur = $this->getLivreur();

            // Base query pour les courses du livreur
            $baseQuery = Livraison::query();
            
            if ($livreur->type === 'distributeur') {
                $baseQuery->where('livreur_distributeur_id', $livreur->id);
            } elseif ($livreur->type === 'ramasseur') {
                $baseQuery->where('livreur_ramasseur_id', $livreur->id);
            } else {
                $baseQuery->where(function ($q) use ($livreur) {
                    $q->where('livreur_distributeur_id', $livreur->id)
                      ->orWhere('livreur_ramasseur_id', $livreur->id);
                });
            }

            // Statistiques selon la structure attendue par Flutter
            $stats = [
                'total_courses' => (clone $baseQuery)->count(),
                'courses_en_attente' => (clone $baseQuery)->where('status', 'en_attente')->count(),
                'courses_ramasse' => (clone $baseQuery)->where('status', 'ramasse')->count(),
                'courses_en_transit' => (clone $baseQuery)->where('status', 'en_transit')->count(),
                'courses_livrees' => (clone $baseQuery)->where('status', 'livre')->count(),
                'courses_reportees' => (clone $baseQuery)->where('status', 'reporte')->count(),
                'courses_incidente' => (clone $baseQuery)->where('status', 'incident')->count(),
                'courses_annulees' => (clone $baseQuery)->where('status', 'annule')->count(),
            ];

            // Compter les colis
            $coursesWithColis = (clone $baseQuery)->with('demandeLivraison.colis')->get();
            $totalColis = $coursesWithColis->sum(function($course) {
                return $course->demandeLivraison && $course->demandeLivraison->colis ? 1 : 0;
            });

            $stats['total_colis'] = $totalColis;

            // Informations du livreur selon le format attendu
            $stats['livreur_name'] = trim($user->nom . ' ' . $user->prenom) ?: 'Livreur';
            $stats['livreur_type'] = $livreur->type ?? 'livreur';

            // Gains (à adapter selon votre logique métier)
            $stats['gain_total'] = 0.0;
            $stats['gain_aujourdhui'] = 0.0;

            return response()->json($stats, 200);

        } catch (\Exception $e) {
            \Log::error('Erreur dans LivreurStatsController::dashboard: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Erreur lors de la récupération des statistiques',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques détaillées par période
     */
    public function detailedStats(Request $request): JsonResponse
    {
        $livreur = $this->getLivreur();
        
        // Période (par défaut: 30 derniers jours)
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days)->startOfDay();

        $baseQuery = Livraison::where('created_at', '>=', $startDate);
        
        if ($livreur->type === 'distributeur') {
            $baseQuery->where('livreur_distributeur_id', $livreur->id);
        } elseif ($livreur->type === 'ramasseur') {
            $baseQuery->where('livreur_ramasseur_id', $livreur->id);
        } else {
            $baseQuery->where(function ($q) use ($livreur) {
                $q->where('livreur_distributeur_id', $livreur->id)
                  ->orWhere('livreur_ramasseur_id', $livreur->id);
            });
        }

        // Statistiques détaillées
        $stats = [
            'periode' => $days . ' derniers jours',
            'total_courses_periode' => (clone $baseQuery)->count(),
            'courses_terminees' => (clone $baseQuery)->where('status', 'livre')->count(),
            'courses_en_cours' => (clone $baseQuery)->whereNotIn('status', ['livre', 'annule'])->count(),
            'courses_annulees' => (clone $baseQuery)->where('status', 'annule')->count(),
            
            // Taux de réussite
            'taux_reussite' => 0,
        ];

        if ($stats['total_courses_periode'] > 0) {
            $stats['taux_reussite'] = round(
                ($stats['courses_terminees'] / $stats['total_courses_periode']) * 100, 
                2
            );
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ], 200);
    }
}