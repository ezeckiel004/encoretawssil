<?php
// database/migrations/2026_03_25_165527_change_navette_colis_to_livraisons.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangeNavetteColisToLivraisons extends Migration
{
    public function up()
    {
        // Vérifier si la table navette_livraison existe déjà
        if (!Schema::hasTable('navette_livraison')) {
            // Créer la nouvelle table navette_livraison
            Schema::create('navette_livraison', function (Blueprint $table) {
                $table->uuid('navette_id');
                $table->uuid('livraison_id');
                $table->integer('ordre_chargement')->default(0);
                $table->timestamp('date_prise_en_charge')->nullable();
                $table->timestamp('date_livraison')->nullable();
                $table->string('qr_code_scan')->nullable();
                $table->text('incident_notes')->nullable();
                $table->timestamps();

                $table->primary(['navette_id', 'livraison_id']);
                $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('cascade');
                $table->foreign('livraison_id')->references('id')->on('livraisons')->onDelete('cascade');
            });

            // Migrer les données si l'ancienne table existe
            if (Schema::hasTable('navette_colis')) {
                $oldData = DB::table('navette_colis')->get();

                foreach ($oldData as $data) {
                    // Trouver la livraison associée au colis
                    // navette_colis.colis_id -> demande_livraisons.colis_id -> livraisons.demande_livraisons_id
                    $demandeLivraison = DB::table('demande_livraisons')->where('colis_id', $data->colis_id)->first();
                    if ($demandeLivraison) {
                        $livraison = DB::table('livraisons')->where('demande_livraisons_id', $demandeLivraison->id)->first();
                        if ($livraison) {
                            DB::table('navette_livraison')->insert([
                                'navette_id' => $data->navette_id,
                                'livraison_id' => $livraison->id,
                                'ordre_chargement' => $data->position_chargement ?? 0,
                                'date_prise_en_charge' => $data->date_chargement,
                                'date_livraison' => $data->date_dechargement,
                                'qr_code_scan' => $data->qr_code_scan ?? null,
                                'incident_notes' => $data->incident_notes ?? null,
                                'created_at' => $data->created_at,
                                'updated_at' => $data->updated_at,
                            ]);
                        }
                    }
                }

                // Supprimer l'ancienne table
                Schema::dropIfExists('navette_colis');
            }
        }

        // Modifier la table navettes
        Schema::table('navettes', function (Blueprint $table) {
            // Supprimer la colonne wilaya_transit_id si elle existe
            if (Schema::hasColumn('navettes', 'wilaya_transit_id')) {
                $table->dropColumn('wilaya_transit_id');
            }

            // Ajouter la colonne JSON si elle n'existe pas
            if (!Schema::hasColumn('navettes', 'wilayas_transit')) {
                $table->json('wilayas_transit')->nullable()->after('wilaya_arrivee_id');
            }
        });

        // Créer la table navette_wilaya_transit si elle n'existe pas
        if (!Schema::hasTable('navette_wilaya_transit')) {
            Schema::create('navette_wilaya_transit', function (Blueprint $table) {
                $table->id();
                $table->uuid('navette_id');
                $table->string('wilaya_code', 2);
                $table->integer('ordre')->default(0);
                $table->timestamps();

                $table->index(['navette_id', 'ordre']);
                $table->foreign('navette_id')
                      ->references('id')
                      ->on('navettes')
                      ->onDelete('cascade');
                $table->foreign('wilaya_code')
                      ->references('code')
                      ->on('wilayas')
                      ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        // Supprimer les tables créées
        Schema::dropIfExists('navette_wilaya_transit');

        Schema::table('navettes', function (Blueprint $table) {
            if (Schema::hasColumn('navettes', 'wilayas_transit')) {
                $table->dropColumn('wilayas_transit');
            }
            if (!Schema::hasColumn('navettes', 'wilaya_transit_id')) {
                $table->string('wilaya_transit_id', 2)->nullable();
            }
        });

        // Recréer l'ancienne structure
        if (!Schema::hasTable('navette_colis') && Schema::hasTable('navette_livraison')) {
            Schema::create('navette_colis', function (Blueprint $table) {
                $table->uuid('navette_id');
                $table->uuid('colis_id');
                $table->integer('position_chargement')->nullable();
                $table->timestamp('date_chargement')->nullable();
                $table->timestamp('date_dechargement')->nullable();
                $table->string('qr_code_scan')->nullable();
                $table->text('incident_notes')->nullable();
                $table->timestamps();

                $table->primary(['navette_id', 'colis_id']);
                $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('cascade');
                $table->foreign('colis_id')->references('id')->on('colis')->onDelete('cascade');
            });

            // Migrer les données en retour
            $newData = DB::table('navette_livraison')->get();
            foreach ($newData as $data) {
                $livraison = DB::table('livraisons')->where('id', $data->livraison_id)->first();
                if ($livraison && $livraison->colis_id) {
                    DB::table('navette_colis')->insert([
                        'navette_id' => $data->navette_id,
                        'colis_id' => $livraison->colis_id,
                        'position_chargement' => $data->ordre_chargement,
                        'date_chargement' => $data->date_prise_en_charge,
                        'date_dechargement' => $data->date_livraison,
                        'qr_code_scan' => $data->qr_code_scan,
                        'incident_notes' => $data->incident_notes,
                        'created_at' => $data->created_at,
                        'updated_at' => $data->updated_at,
                    ]);
                }
            }

            Schema::dropIfExists('navette_livraison');
        }
    }
}
