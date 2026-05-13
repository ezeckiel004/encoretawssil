<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Désactiver les contraintes de clés étrangères
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 1. Créer une table temporaire avec la bonne structure
        Schema::create('code_promo_livreur_temp', function (Blueprint $table) {
            $table->id(); // Auto-incrémenté
            $table->uuid('code_promo_id');
            $table->uuid('livreur_id');
            $table->integer('utilisations')->default(0);
            $table->timestamps();

            // Index et contraintes
            $table->unique(['code_promo_id', 'livreur_id'], 'unique_code_promo_livreur');
            $table->index('code_promo_id', 'idx_code_promo_id');
            $table->index('livreur_id', 'idx_livreur_id');
        });

        // 2. Vérifier si la table originale existe et a des données
        if (Schema::hasTable('code_promo_livreur')) {
            // Copier les données de l'ancienne table vers la nouvelle
            DB::statement('INSERT INTO code_promo_livreur_temp (code_promo_id, livreur_id, utilisations, created_at, updated_at) SELECT code_promo_id, livreur_id, utilisations, created_at, updated_at FROM code_promo_livreur');
        }

        // 3. Supprimer l'ancienne table
        Schema::dropIfExists('code_promo_livreur');

        // 4. Renommer la table temporaire
        Schema::rename('code_promo_livreur_temp', 'code_promo_livreur');

        // Réactiver les contraintes de clés étrangères
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Recréer la table avec l'ancienne structure (UUID)
        Schema::create('code_promo_livreur_old', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_promo_id');
            $table->uuid('livreur_id');
            $table->integer('utilisations')->default(0);
            $table->timestamps();

            $table->unique(['code_promo_id', 'livreur_id'], 'unique_code_promo_livreur_old');
            $table->index('code_promo_id', 'idx_code_promo_id_old');
            $table->index('livreur_id', 'idx_livreur_id_old');
        });

        // Générer des UUIDs pour les nouvelles lignes
        DB::statement('INSERT INTO code_promo_livreur_old (id, code_promo_id, livreur_id, utilisations, created_at, updated_at) SELECT UUID(), code_promo_id, livreur_id, utilisations, created_at, updated_at FROM code_promo_livreur');

        Schema::dropIfExists('code_promo_livreur');
        Schema::rename('code_promo_livreur_old', 'code_promo_livreur');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
