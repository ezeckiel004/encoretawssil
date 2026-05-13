<?php
// database/migrations/2024_01_01_000004_create_gains_livreurs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gains_livreurs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('livreur_id');
            $table->uuid('livraison_id');
            $table->uuid('navette_id')->nullable();
            $table->date('date');
            $table->decimal('montant_brut', 10, 2);
            $table->decimal('frais_navette', 10, 2)->default(0);
            $table->decimal('frais_hub', 10, 2)->default(0);
            $table->decimal('frais_point_relais', 10, 2)->default(0);
            $table->decimal('commission_partenaire1', 10, 2)->default(0);
            $table->decimal('commission_partenaire2', 10, 2)->default(0);
            $table->decimal('montant_societe_mere', 10, 2)->default(0);
            $table->decimal('montant_net_livreur', 10, 2);
            $table->string('periode', 7); // YYYY-MM
            $table->enum('statut_paiement', ['en_attente', 'paye', 'annule'])->default('en_attente');
            $table->date('date_paiement')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('livreur_id')->references('id')->on('livreurs')->onDelete('cascade');
            $table->foreign('livraison_id')->references('id')->on('livraisons')->onDelete('cascade');
            $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('set null');

            $table->unique(['livraison_id', 'livreur_id']);
            $table->index('date');
            $table->index('periode');
            $table->index('statut_paiement');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gains_livreurs');
    }
};
