<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cash_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('expediteur_id'); // Gestionnaire qui envoie
            $table->uuid('destinataire_id'); // Gestionnaire qui reçoit
            $table->decimal('montant', 15, 2);
            $table->text('motif')->nullable();
            $table->enum('status', [
                'en_attente',   // Demande envoyée
                'accepte',      // Accepté par le destinataire
                'refuse',       // Refusé par le destinataire
                'annule'        // Annulé par l'expéditeur
            ])->default('en_attente');
            $table->timestamp('date_envoi')->nullable();
            $table->timestamp('date_reponse')->nullable();
            $table->string('reference')->unique();
            $table->timestamps();

            $table->index(['expediteur_id', 'status']);
            $table->index(['destinataire_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cash_deliveries');
    }
};
