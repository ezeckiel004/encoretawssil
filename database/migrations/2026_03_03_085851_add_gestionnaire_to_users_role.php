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
        // Si le champ est enum, on doit modifier
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(255) DEFAULT 'client'");
        
        // On peut aussi ajouter une contrainte si nécessaire
        // Mais comme on utilise le système de rôles/permissions en plus,
        // on peut laisser le champ en string simple
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas besoin de revenir en arrière
    }
};