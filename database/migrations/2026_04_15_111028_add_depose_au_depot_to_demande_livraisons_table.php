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
            $table->boolean('depose_au_depot')->default(false)->after('commune');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demande_livraisons', function (Blueprint $table) {
            $table->dropColumn('depose_au_depot');
        });
    }
};
