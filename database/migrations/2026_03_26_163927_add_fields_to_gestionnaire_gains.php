// database/migrations/2026_03_26_000002_add_fields_to_gestionnaire_gains.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToGestionnaireGains extends Migration
{
    public function up()
    {
        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            if (!Schema::hasColumn('gestionnaire_gains', 'hub_id')) {
                $table->unsignedBigInteger('hub_id')->nullable()->after('gestionnaire_id');
                $table->foreign('hub_id')->references('id')->on('hubs')->onDelete('set null');
            }
            
            if (!Schema::hasColumn('gestionnaire_gains', 'navette_id')) {
                $table->uuid('navette_id')->nullable()->after('livraison_id');
                $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('gestionnaire_gains', function (Blueprint $table) {
            $table->dropForeign(['hub_id']);
            $table->dropForeign(['navette_id']);
            $table->dropColumn(['hub_id', 'navette_id']);
        });
    }
}
