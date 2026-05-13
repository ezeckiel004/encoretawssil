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
        Schema::create('colis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('colis_type');
            $table->string('colis_label')->unique();
            $table->string('colis_photo')->nullable();
            $table->string('colis_photo_url')->nullable();
            $table->string('colis_description')->nullable();

            $table->decimal('poids', 8, 2)->default(0.0);   // Poids en kilogrammes
            $table->decimal('hauteur', 8, 2)->default(0.0); // Hauteur en centimètres
            $table->decimal('largeur', 8, 2)->default(0.0); // Longueur en centimètres
            $table->decimal('colis_prix', 10, 2)->nullable(); // Prix du colis défini par le client

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colis');
    }
};