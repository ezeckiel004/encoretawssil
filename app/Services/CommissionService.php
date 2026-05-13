<?php
// app/Services/CommissionService.php

namespace App\Services;

use App\Models\CommissionConfig;
use App\Models\Livraison;
use App\Models\GestionnaireGain;
use App\Models\Gestionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Récupère la configuration d'une commission
     */
    public function getCommissionConfig(string $key): float
    {
        $config = CommissionConfig::where('key', $key)->first();
        return $config ? (float) $config->value : 0;
    }

    /**
     * Met à jour une configuration de commission
     */
    public function updateCommissionConfig(string $key, float $value): bool
    {
        try {
            $config = CommissionConfig::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour commission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Calcule les commissions pour une livraison terminée
     */
    public function calculerCommissionsLivraison(Livraison $livraison): array
    {
        try {
            DB::beginTransaction();

            // Vérifier que la livraison est terminée
            if ($livraison->status !== 'livre') {
                throw new \Exception('La livraison doit être terminée pour calculer les commissions');
            }

            $demande = $livraison->demandeLivraison;
            if (!$demande) {
                throw new \Exception('Demande de livraison non trouvée');
            }

            $prixLivraison = (float) $demande->prix;

            // Récupérer les pourcentages de commission
            $pourcentageDepart = $this->getCommissionConfig('commission_depart_default');
            $pourcentageArrivee = $this->getCommissionConfig('commission_arrivee_default');

            // Calculer les montants
            $montantDepart = round($prixLivraison * ($pourcentageDepart / 100), 2);
            $montantArrivee = round($prixLivraison * ($pourcentageArrivee / 100), 2);
            $montantAdmin = $prixLivraison - $montantDepart - $montantArrivee;

            // Récupérer les gestionnaires
            $gestionnaireDepart = $this->getGestionnaireByWilaya($demande->wilaya_depot);
            $gestionnaireArrivee = $this->getGestionnaireByWilaya($demande->wilaya);

            $resultats = [];

            // Enregistrer le gain pour la wilaya de départ
            if ($gestionnaireDepart && $montantDepart > 0) {
                $gainDepart = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireDepart->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'depart',
                    'montant_commission' => $montantDepart,
                    'pourcentage_applique' => $pourcentageDepart,
                    'date_calcul' => now(),
                    'status' => 'calcule'
                ]);
                $resultats['depart'] = $gainDepart;
            }

            // Enregistrer le gain pour la wilaya d'arrivée
            if ($gestionnaireArrivee && $montantArrivee > 0) {
                $gainArrivee = GestionnaireGain::create([
                    'gestionnaire_id' => $gestionnaireArrivee->id,
                    'livraison_id' => $livraison->id,
                    'wilaya_type' => 'arrivee',
                    'montant_commission' => $montantArrivee,
                    'pourcentage_applique' => $pourcentageArrivee,
                    'date_calcul' => now(),
                    'status' => 'calcule'
                ]);
                $resultats['arrivee'] = $gainArrivee;
            }

            DB::commit();

            return [
                'success' => true,
                'data' => [
                    'prix_livraison' => $prixLivraison,
                    'pourcentage_depart' => $pourcentageDepart,
                    'montant_depart' => $montantDepart,
                    'pourcentage_arrivee' => $pourcentageArrivee,
                    'montant_arrivee' => $montantArrivee,
                    'montant_admin' => $montantAdmin,
                    'gestionnaire_depart' => $gestionnaireDepart?->user?->nom,
                    'gestionnaire_arrivee' => $gestionnaireArrivee?->user?->nom,
                    'gains_enregistres' => $resultats
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur calcul commissions: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Récupère le gestionnaire d'une wilaya
     */
    private function getGestionnaireByWilaya($wilayaId)
    {
        if (!$wilayaId) return null;

        return Gestionnaire::where('wilaya_id', $wilayaId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Calcule les gains totaux d'un gestionnaire sur une période
     */
    public function getGainsGestionnaire($gestionnaireId, $dateDebut = null, $dateFin = null)
    {
        $query = GestionnaireGain::where('gestionnaire_id', $gestionnaireId)
            ->with('livraison.demandeLivraison');

        if ($dateDebut && $dateFin) {
            $query->whereBetween('created_at', [$dateDebut, $dateFin]);
        }

        $gains = $query->get();

        return [
            'total_gains' => $gains->sum('montant_commission'),
            'nombre_livraisons' => $gains->count(),
            'moyenne_par_livraison' => $gains->count() > 0
                ? round($gains->sum('montant_commission') / $gains->count(), 2)
                : 0,
            'details' => $gains
        ];
    }

    /**
     * Vérifie si les pourcentages sont valides (total <= 100)
     */
    public function validerPourcentages(float $depart, float $arrivee): bool
    {
        return ($depart + $arrivee) <= 100;
    }
}
