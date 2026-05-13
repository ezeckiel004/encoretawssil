<?php
// database/migrations/2024_01_01_000001_create_navettes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('navettes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->dateTime('heure_depart');
            $table->dateTime('heure_arrivee');
            $table->string('wilaya_depart_id', 2);
            $table->string('wilaya_transit_id', 2)->nullable();
            $table->string('wilaya_arrivee_id', 2);
            $table->uuid('chauffeur_id')->nullable();
            $table->string('vehicule_immatriculation')->nullable();
            $table->integer('capacite_max')->default(100);
            $table->enum('status', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->dateTime('date_depart');
            $table->dateTime('date_arrivee_prevue');
            $table->dateTime('date_arrivee_reelle')->nullable();
            $table->decimal('prix_base', 10, 2)->default(0);
            $table->decimal('prix_par_colis', 10, 2)->default(0);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('carburant_estime', 10, 2)->nullable();
            $table->decimal('peages_estimes', 10, 2)->nullable();
            $table->uuid('created_by');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('chauffeur_id')->references('id')->on('livreurs')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index('reference');
            $table->index('status');
            $table->index(['wilaya_depart_id', 'wilaya_arrivee_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('navettes');
    }
};
