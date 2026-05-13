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
        Schema::create('code_promo_livreur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('code_promo_id');
            $table->uuid('livreur_id');
            $table->integer('utilisations')->default(0);
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('code_promo_id')
                  ->references('id')
                  ->on('codes_promo')
                  ->onDelete('cascade');
                  
            $table->foreign('livreur_id')
                  ->references('id')
                  ->on('livreurs')
                  ->onDelete('cascade');
                  
            // Unicité : un livreur ne peut avoir qu'une seule entrée par code promo
            $table->unique(['code_promo_id', 'livreur_id'], 'unique_code_livreur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_promo_livreur');
    }
};