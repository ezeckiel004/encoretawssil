<?php
// database/migrations/2026_03_16_120143_update_navettes_table_rename_chauffeur_id.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('navettes', function (Blueprint $table) {
            // Pour MySQL/MariaDB, on doit utiliser change() après avoir défini la nouvelle colonne
            if (Schema::hasColumn('navettes', 'chauffeur_id')) {
                // 1. Supprimer l'ancienne clé étrangère si elle existe
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $foreignKeys = $sm->listTableForeignKeys('navettes');

                foreach ($foreignKeys as $foreignKey) {
                    if (in_array('chauffeur_id', $foreignKey->getLocalColumns())) {
                        $table->dropForeign($foreignKey->getName());
                        break;
                    }
                }

                // 2. Renommer la colonne (méthode compatible MySQL)
                $table->renameColumn('chauffeur_id', 'livreur_id');
            }
        });

        // 3. Ajouter la nouvelle contrainte de clé étrangère
        Schema::table('navettes', function (Blueprint $table) {
            $table->foreign('livreur_id')
                  ->references('id')
                  ->on('livreurs')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('navettes', function (Blueprint $table) {
            // Supprimer la clé étrangère
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $foreignKeys = $sm->listTableForeignKeys('navettes');

            foreach ($foreignKeys as $foreignKey) {
                if (in_array('livreur_id', $foreignKey->getLocalColumns())) {
                    $table->dropForeign($foreignKey->getName());
                    break;
                }
            }

            // Renommer la colonne
            if (Schema::hasColumn('navettes', 'livreur_id')) {
                $table->renameColumn('livreur_id', 'chauffeur_id');
            }
        });

        // Remettre l'ancienne clé étrangère
        Schema::table('navettes', function (Blueprint $table) {
            $table->foreign('chauffeur_id')
                  ->references('id')
                  ->on('livreurs')
                  ->onDelete('set null');
        });
    }
};
