<?php
// database/migrations/2024_01_01_000002_create_navette_colis_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('navette_colis', function (Blueprint $table) {
            $table->uuid('navette_id');
            $table->uuid('colis_id');
            $table->integer('position_chargement')->nullable();
            $table->dateTime('date_chargement')->nullable();
            $table->dateTime('date_dechargement')->nullable();
            $table->string('qr_code_scan')->nullable();
            $table->text('incident_notes')->nullable();
            $table->timestamps();

            $table->primary(['navette_id', 'colis_id']);
            $table->foreign('navette_id')->references('id')->on('navettes')->onDelete('cascade');
            $table->foreign('colis_id')->references('id')->on('colis')->onDelete('cascade');
            $table->index('date_chargement');
            $table->index('date_dechargement');
        });
    }

    public function down()
    {
        Schema::dropIfExists('navette_colis');
    }
};
