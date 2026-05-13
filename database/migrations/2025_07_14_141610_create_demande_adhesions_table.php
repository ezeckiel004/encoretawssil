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
        Schema::create('demande_adhesions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->text('message')->nullable();
            $table->uuid('user_id');

            $table->foreign('user_id')->references('id')->on('users')->constrained();

            $table->string('drivers_license')->nullable();
            $table->string('drivers_license_url')->nullable();


            $table->string('matricule_engins')->nullable();


            $table->string('vehicule')->nullable();
            $table->string('vehicule_url')->nullable();

            $table->string('vehicule_type')->nullable();
            $table->string('id_card_type');
            $table->string('id_card_number')->unique();
            $table->string('id_card_image')->nullable();
            $table->string('id_card_image_url')->nullable();

            $table->string(column: 'id_card_expiry_date')->nullable();


            $table->date('date')->nullable();;
            $table->string('info')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demande_adhesions');
    }
};