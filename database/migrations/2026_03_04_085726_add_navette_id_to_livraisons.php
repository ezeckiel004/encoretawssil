<?php
// database/migrations/2024_01_01_000003_add_navette_id_to_livraisons.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->uuid('navette_id')->nullable()->after('bordereau_id');
            $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('set null');
            $table->index('navette_id');
        });
    }

    public function down()
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropForeign(['navette_id']);
            $table->dropColumn('navette_id');
        });
    }
};
