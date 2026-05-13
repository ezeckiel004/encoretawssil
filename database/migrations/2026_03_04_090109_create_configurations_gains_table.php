<?php
// database/migrations/2024_01_01_000005_create_configurations_gains_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configurations_gains', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->json('regles_json');
            $table->json('bareme_navette_json');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('active')->default(true);
            $table->uuid('created_by')->nullable(); // Rendre nullable
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('active');
            $table->index(['date_debut', 'date_fin']);
        });

        // Insérer la configuration par défaut avec created_by = NULL
        DB::table('configurations_gains')->insert([
            'nom' => 'Configuration standard 2024',
            'description' => 'Répartition des gains selon le tableau fourni',
            'regles_json' => json_encode([
                'tranches' => [
                    ['min' => 0, 'max' => 499, 'societe_mere' => 100, 'hub' => 0, 'point_relais' => 0, 'partenaire1' => 0, 'partenaire2' => 0],
                    ['min' => 500, 'max' => 699, 'societe_mere' => 0, 'hub' => 35, 'point_relais' => 35, 'partenaire1' => 35, 'partenaire2' => 0],
                    ['min' => 700, 'max' => 899, 'societe_mere' => 25, 'hub' => 25, 'point_relais' => 25, 'partenaire1' => 25, 'partenaire2' => 0],
                    ['min' => 900, 'max' => 1199, 'societe_mere' => 50, 'hub' => 0, 'point_relais' => 0, 'partenaire1' => 50, 'partenaire2' => 0],
                    ['min' => 1200, 'max' => 999999, 'societe_mere' => 50, 'hub' => 0, 'point_relais' => 0, 'partenaire1' => 0, 'partenaire2' => 50],
                ]
            ]),
            'bareme_navette_json' => json_encode([
                ['min' => 0, 'max' => 499, 'frais' => 0],
                ['min' => 500, 'max' => 799, 'frais' => 100],
                ['min' => 800, 'max' => 1199, 'frais' => 200],
                ['min' => 1200, 'max' => 999999, 'frais' => 400],
            ]),
            'date_debut' => '2024-01-01',
            'active' => true,
            'created_by' => null, // Mettre NULL au lieu d'un UUID
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('configurations_gains');
    }
};
