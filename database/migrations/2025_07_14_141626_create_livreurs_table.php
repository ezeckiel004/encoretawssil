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
        Schema::create('livreurs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('demande_adhesions_id');
            $table->foreign('user_id')->references('id')->on('users')->constrained();
            $table->foreign('demande_adhesions_id')->references('id')->on('demande_adhesions')->constrained()->onDelete('cascade');

            $table->enum('type', ['distributeur', 'ramasseur'])->default('ramasseur');
            $table->boolean(column: 'desactiver')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livreurs');
    }
};