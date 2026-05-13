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
        Schema::create('bordereaux', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->string('numero')->unique();
            $table->string('photo_reception');
            $table->string('photo_reception_url')->nullable();

            $table->string('signed_by')->nullable();
            $table->text('commentaire')->nullable();
            $table->integer('note')->default(0)->nullable();

            $table->foreign('client_id')->references('id')->on('clients')->constrained()->onDelete('cascade');
            $table->unique('client_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bordereaux');
    }
};
