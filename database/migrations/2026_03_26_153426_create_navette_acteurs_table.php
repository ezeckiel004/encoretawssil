<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNavetteActeursTable extends Migration
{
    public function up()
    {
        Schema::create('navette_acteurs', function (Blueprint $table) {
            $table->id();
            $table->uuid('navette_id');
            $table->string('type'); // 'gestionnaire' ou 'hub'
            $table->string('acteur_id'); // ID du gestionnaire ou du hub
            $table->string('wilaya_code')->nullable(); // Pour les gestionnaires
            $table->decimal('part_pourcentage', 5, 2)->default(0); // Part attribuée
            $table->timestamps();

            $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('navette_acteurs');
    }
}
