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
        Schema::create('commentaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('message');
            $table->string('livreur');
            $table->uuid('livreur_id'); // ID of the livreur who made the comment
            $table->uuid('livraison_id'); // ID of the livraison being commented on
            $table->foreign('livreur_id')->references('id')->on('livreurs')->constrained()->onDelete('cascade');
            $table->foreign('livraison_id')->references('id')->on('livraisons')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commentaires');
    }
};