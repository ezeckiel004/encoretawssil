<?php

namespace App\Listeners;

use App\Events\NavetteTerminee;
use App\Models\GestionnaireGain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculerGainsNavette
{
    public function handle(NavetteTerminee $event)
    {
        $navette = $event->navette;

        try {
            DB::beginTransaction();

            // Récupérer toutes les livraisons de la navette
            $livraisons = $navette->livraisons()->with('demandeLivraison')->get();

            if ($livraisons->isEmpty()) {
                Log::info("Navette {$navette->id} terminée sans livraison");
                DB::commit();
                return;
            }

            // Récupérer la répartition des acteurs
            $acteurs = $navette->acteurs;

            if ($acteurs->isEmpty()) {
                Log::warning("Navette {$navette->id} terminée sans acteurs définis");
                DB::commit();
                return;
            }

            $totalCommissions = 0;
            $totalPrixLivraisons = 0;

            // Pour chaque livraison
            foreach ($livraisons as $livraison) {
                $prixLivraison = $livraison->demandeLivraison->prix ?? 0;
                $totalPrixLivraisons += $prixLivraison;

                if ($prixLivraison <= 0) {
                    Log::warning("Livraison {$livraison->id} sans prix défini");
                    continue;
                }

                // Pour chaque acteur, calculer sa part
                foreach ($acteurs as $acteur) {
                    $montantCommission = ($prixLivraison * $acteur->part_pourcentage) / 100;

                    // Déterminer le type de wilaya pour la commission
                    $wilayaType = null;
                    if ($acteur->type === 'gestionnaire') {
                        if ($acteur->wilaya_code == $navette->wilaya_depart_id) {
                            $wilayaType = 'depart';
                        } elseif ($acteur->wilaya_code == $navette->wilaya_arrivee_id) {
                            $wilayaType = 'arrivee';
                        } else {
                            $wilayaType = 'transit';
                        }
                    }

                    // Préparer les données du gain
                    $gainData = [
                        'livraison_id' => $livraison->id,
                        'navette_id' => $navette->id,
                        'wilaya_type' => $wilayaType,
                        'montant_commission' => $montantCommission,
                        'pourcentage_applique' => $acteur->part_pourcentage,
                        'date_calcul' => now(),
                        'status' => 'en_attente'
                    ];

                    // Ajouter l'acteur concerné
                    if ($acteur->type === 'gestionnaire') {
                        $gainData['gestionnaire_id'] = $acteur->acteur_id;
                    } elseif ($acteur->type === 'hub') {
                        $gainData['hub_id'] = $acteur->acteur_id;
                    }

                    // Créer le gain
                    GestionnaireGain::create($gainData);
                    $totalCommissions += $montantCommission;
                }
            }

            // Calculer la part société mère (admin)
            $partSocieteMere = $totalPrixLivraisons - $totalCommissions;

            Log::info("========================================");
            Log::info("Navette {$navette->id} ({$navette->reference}) - Gains calculés");
            Log::info("  - Prix total des livraisons: {$totalPrixLivraisons} DA");
            Log::info("  - Total commissions versées: {$totalCommissions} DA");
            Log::info("  - Part société mère (admin): {$partSocieteMere} DA");
            Log::info("  - Nombre d'acteurs: " . $acteurs->count());
            Log::info("========================================");

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erreur calcul gains navette {$navette->id}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}
