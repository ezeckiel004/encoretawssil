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
        Schema::create('demande_livraisons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('destinataire_id');
            $table->uuid('colis_id');
            $table->foreign('client_id')->references('id')->on('clients')->constrained()->onDelete('cascade');
            $table->foreign('destinataire_id')->references('id')->on('clients')->constrained()->onDelete('cascade');
            $table->foreign('colis_id')->references('id')->on('colis')->constrained()->onDelete('cascade');

            $table->string('addresse_depot');
            $table->string('addresse_delivery');
            $table->text('info_additionnel')->nullable();

            $table->double('prix')->default(0.0);


            $table->double('lat_depot')->nullable();
            $table->double('lng_depot')->nullable();
            $table->double('lat_delivery')->nullable();
            $table->double('lng_delivery')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demande_livraisons');
    }
};