<?php
// database/migrations/2026_03_26_180000_make_gestionnaire_id_nullable_in_gestionnaire_gains.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeGestionnaireIdNullableInGestionnaireGains extends Migration
{
    public function up()
    {
        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            // Modifier la colonne gestionnaire_id pour accepter NULL
            $table->string('gestionnaire_id', 36)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            $table->string('gestionnaire_id', 36)->nullable(false)->change();
        });
    }
}
