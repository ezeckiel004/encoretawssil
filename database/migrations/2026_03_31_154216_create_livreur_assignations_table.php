<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLivreurAssignationsTable extends Migration
{
    public function up()
    {
        Schema::create('livreur_assignations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('livreur_id');
            $table->uuid('gestionnaire_id');
            $table->string('wilaya_cible'); // Wilaya où le livreur est assigné
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->enum('status', ['active', 'terminee', 'annulee'])->default('active');
            $table->text('motif')->nullable();
            $table->uuid('created_by')->nullable(); // ID de l'admin qui a créé l'assignation
            $table->timestamps();

            $table->foreign('livreur_id')->references('id')->on('livreurs')->onDelete('cascade');
            $table->foreign('gestionnaire_id')->references('id')->on('gestionnaires')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['livreur_id', 'gestionnaire_id', 'status']);
            $table->index(['wilaya_cible', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('livreur_assignations');
    }
}
