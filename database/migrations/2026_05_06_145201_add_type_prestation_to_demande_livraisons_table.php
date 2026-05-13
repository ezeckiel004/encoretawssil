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
        Schema::table('demande_livraisons', function (Blueprint $table) {
            $table->string('type_livraison')->default('Livraison')->after('prix');
            $table->string('prestation')->default('A domicile')->after('type_livraison');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demande_livraisons', function (Blueprint $table) {
            $table->dropColumn(['type_livraison', 'prestation']);
        });
    }
};
