<?php
// database/migrations/2026_03_18_115406_add_status_to_gestionnaire_gains_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddStatusToGestionnaireGainsTable extends Migration
{
    public function up()
    {
        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            // Utiliser string au lieu de enum
            $table->string('status', 20)->default('en_attente')->change();

            // Ajouter les nouvelles colonnes
            if (!Schema::hasColumn('gestionnaire_gains', 'date_demande')) {
                $table->timestamp('date_demande')->nullable();
            }

            if (!Schema::hasColumn('gestionnaire_gains', 'date_paiement')) {
                $table->timestamp('date_paiement')->nullable();
            }

            if (!Schema::hasColumn('gestionnaire_gains', 'note_admin')) {
                $table->text('note_admin')->nullable();
            }
        });

        // Optionnel : Ajouter une contrainte CHECK pour MySQL
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE `gestionnaire_gains` DROP CHECK `gestionnaire_gains_status_check`");
            } catch (\Exception $e) {
                // Ignorer si la contrainte n'existe pas
            }

            DB::statement("ALTER TABLE `gestionnaire_gains` ADD CONSTRAINT `gestionnaire_gains_status_check` CHECK (`status` IN ('en_attente', 'demande_envoyee', 'paye', 'annule'))");
        }
    }

    public function down()
    {
        // Supprimer la contrainte CHECK
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE `gestionnaire_gains` DROP CHECK `gestionnaire_gains_status_check`");
            } catch (\Exception $e) {
                // Ignorer
            }
        }

        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            $table->dropColumn(['date_demande', 'date_paiement', 'note_admin']);
            $table->string('status')->default('en_attente')->change();
        });
    }
}
