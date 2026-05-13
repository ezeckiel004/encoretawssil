<?php
// database/migrations/2024_01_01_000001_create_commission_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateCommissionConfigsTable extends Migration
{
    public function up()
    {
        Schema::create('commission_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 'default_commission_depart', 'default_commission_arrivee'
            $table->decimal('value', 5, 2); // Pourcentage (ex: 25.00)
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Insertion des valeurs par défaut
        DB::table('commission_configs')->insert([
            [
                'key' => 'commission_depart_default',
                'value' => 25.00,
                'description' => 'Commission par défaut pour la wilaya de départ (%)',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'commission_arrivee_default',
                'value' => 25.00,
                'description' => 'Commission par défaut pour la wilaya d\'arrivée (%)',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('commission_configs');
    }
}
