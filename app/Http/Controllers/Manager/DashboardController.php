<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\DemandeAdhesion;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Récupérer les statistiques du tableau de bord
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $gestionnaire = $user->gestionnaire;
        $wilayaCode = $gestionnaire->wilaya_id; // Code (ex: "16")
        $wilayaNom = $this->getWilayaName($wilayaCode); // Nom (ex: "Alger")

        // ✅ FILTRAGE PAR WILAYA (code OU nom)
        $livraisons = Livraison::whereHas('demandeLivraison', function ($q) use ($wilayaCode, $wilayaNom) {
            $q->where(function ($query) use ($wilayaCode, $wilayaNom) {
                // Filtrer par code (si le champ contient un code)
                $query->where('wilaya', $wilayaCode)
                      // Filtrer par nom (si le champ contient un nom)
                      ->orWhere('wilaya', 'like', '%' . $wilayaNom . '%')
                      // Filtrer aussi par wilaya_depot
                      ->orWhere('wilaya_depot', $wilayaCode)
                      ->orWhere('wilaya_depot', 'like', '%' . $wilayaNom . '%');
            });
        });

        // ✅ STATISTIQUES DES LIVRAISONS
        $totalLivraisons = (clone $livraisons)->count();
        $enAttente = (clone $livraisons)->where('status', 'en_attente')->count();
        $enCours = (clone $livraisons)
            ->whereNotIn('status', ['en_attente', 'livre', 'annule'])
            ->count();
        $terminees = (clone $livraisons)->where('status', 'livre')->count();
        $annulees = (clone $livraisons)->where('status', 'annule')->count();

        // ✅ LIVREURS DE LA WILAYA (en utilisant le nouveau champ wilaya_id)
        $totalLivreurs = Livreur::where('wilaya_id', $wilayaCode)->count();
        $livreursActifs = Livreur::where('wilaya_id', $wilayaCode)
            ->where('desactiver', false)
            ->count();
        $livreursInactifs = Livreur::where('wilaya_id', $wilayaCode)
            ->where('desactiver', true)
            ->count();

        // ✅ DEMANDES D'ADHÉSION EN ATTENTE
        $demandesAttente = DemandeAdhesion::where('status', 'pending')
            ->whereHas('user', function ($q) use ($wilayaCode) {
                // Si l'utilisateur est lié à un livreur avec wilaya_id
                $q->whereHas('livreur', function ($livreurQuery) use ($wilayaCode) {
                    $livreurQuery->where('wilaya_id', $wilayaCode);
                });
            })->count();

        $stats = [
            'total_livraisons' => $totalLivraisons,
            'livraisons_en_attente' => $enAttente,
            'livraisons_en_cours' => $enCours,
            'livraisons_terminees' => $terminees,
            'livraisons_annulees' => $annulees,
            'total_livreurs' => $totalLivreurs,
            'livreurs_actifs' => $livreursActifs,
            'livreurs_inactifs' => $livreursInactifs,
            'demandes_adhesion_attente' => $demandesAttente,
        ];

        // ✅ ÉVOLUTION DES LIVRAISONS (7 derniers jours)
        $evolution = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $evolution[$date->format('d/m')] = (clone $livraisons)
                ->whereDate('created_at', $date)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'wilaya' => $wilayaCode,
                'wilaya_nom' => $wilayaNom,
                'stats' => $stats,
                'evolution' => $evolution,
                'gestionnaire' => [
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                ]
            ]
        ], 200);
    }

    /**
     * Obtenir le nom de la wilaya à partir du code
     */
    private function getWilayaName($code): string
    {
        $wilayas = [
            '01' => 'Adrar', '02' => 'Chlef', '03' => 'Laghouat', '04' => 'Oum El Bouaghi',
            '05' => 'Batna', '06' => 'Béjaïa', '07' => 'Biskra', '08' => 'Béchar',
            '09' => 'Blida', '10' => 'Bouira', '11' => 'Tamanrasset', '12' => 'Tébessa',
            '13' => 'Tlemcen', '14' => 'Tiaret', '15' => 'Tizi Ouzou', '16' => 'Alger',
            '17' => 'Djelfa', '18' => 'Jijel', '19' => 'Sétif', '20' => 'Saïda',
            '21' => 'Skikda', '22' => 'Sidi Bel Abbès', '23' => 'Annaba', '24' => 'Guelma',
            '25' => 'Constantine', '26' => 'Médéa', '27' => 'Mostaganem', '28' => 'M\'Sila',
            '29' => 'Mascara', '30' => 'Ouargla', '31' => 'Oran', '32' => 'El Bayadh',
            '33' => 'Illizi', '34' => 'Bordj Bou Arréridj', '35' => 'Boumerdès',
            '36' => 'El Tarf', '37' => 'Tindouf', '38' => 'Tissemsilt', '39' => 'El Oued',
            '40' => 'Khenchela', '41' => 'Souk Ahras', '42' => 'Tipaza', '43' => 'Mila',
            '44' => 'Aïn Defla', '45' => 'Naâma', '46' => 'Aïn Témouchent', '47' => 'Ghardaïa',
            '48' => 'Relizane', '49' => 'Timimoun', '50' => 'Bordj Badji Mokhtar',
            '51' => 'Ouled Djellal', '52' => 'Béni Abbès', '53' => 'In Salah',
            '54' => 'In Guezzam', '55' => 'Touggourt', '56' => 'Djanet',
            '57' => 'El M\'Ghair', '58' => 'El Meniaa'
        ];

        return $wilayas[$code] ?? $code;
    }
}