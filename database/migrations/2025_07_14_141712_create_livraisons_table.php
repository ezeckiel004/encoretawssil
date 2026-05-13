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
        Schema::create('livraisons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('demande_livraisons_id')->nullable();
            $table->uuid('livreur_distributeur_id')->nullable();
            $table->uuid('livreur_ramasseur_id')->nullable();
            $table->uuid('bordereau_id')->nullable();
            
            $table->foreign('client_id')->references('id')->on('clients')->constrained()->onDelete('cascade');
            $table->foreign('demande_livraisons_id')->references('id')->on('demande_livraisons')->constrained()->onDelete('cascade');
            $table->foreign('livreur_distributeur_id')->references('id')->on('livreurs')->nullable()->constrained('livreurs')->onDelete('set null');
            $table->foreign('livreur_ramasseur_id')->references('id')->on('livreurs')->nullable()->constrained('livreurs')->onDelete('set null');
            $table->foreign('bordereau_id')->references('id')->on('bordereaux')->nullable()->constrained('bordereaux')->onDelete('cascade');

            $table->string(column: 'code_pin');
            $table->date('date_ramassage')->nullable();
            $table->date('date_livraison')->nullable();

            $table->enum('status', [
                'en_attente',
                'prise_en_charge_ramassage',
                'ramasse',
                'en_transit',
                'prise_en_charge_livraison',
                'livre',
                'annule',
            ])->default('en_attente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};
