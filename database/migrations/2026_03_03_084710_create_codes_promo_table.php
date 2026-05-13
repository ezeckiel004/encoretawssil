<?php

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
        Schema::create('codes_promo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed']); // % ou montant fixe
            $table->decimal('valeur', 10, 2);
            $table->decimal('min_commande', 10, 2)->nullable();
            $table->integer('max_utilisations')->nullable();
            $table->integer('utilisations_actuelles')->default(0);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->uuid('gestionnaire_id');
            $table->enum('status', ['actif', 'inactif', 'expire'])->default('actif');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('gestionnaire_id')
                  ->references('id')
                  ->on('gestionnaires')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codes_promo');
    }
};