<?php
// database/migrations/[timestamp]_create_gestionnaire_gains_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGestionnaireGainsTable extends Migration
{
    public function up()
    {
        Schema::create('gestionnaire_gains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gestionnaire_id');
            $table->uuid('livraison_id');
            $table->enum('wilaya_type', ['depart', 'arrivee'])->nullable();
            $table->decimal('montant_commission', 10, 2);
            $table->decimal('pourcentage_applique', 5, 2);
            $table->timestamp('date_calcul')->nullable();
            $table->string('status')->default('en_attente');
            $table->timestamps();

            // Clés étrangères
            $table->foreign('gestionnaire_id')
                  ->references('id')
                  ->on('gestionnaires')
                  ->onDelete('cascade');

            $table->foreign('livraison_id')
                  ->references('id')
                  ->on('livraisons')
                  ->onDelete('cascade');

            // Index pour les performances
            $table->index('status');
            $table->index('gestionnaire_id');
            $table->index('livraison_id');
            $table->index('date_calcul');
            $table->index(['gestionnaire_id', 'status']);
            $table->index(['gestionnaire_id', 'date_calcul']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('gestionnaire_gains');
    }
}
