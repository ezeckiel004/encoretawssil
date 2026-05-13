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
        Schema::create('responses_avis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('message');
            $table->uuid('admin_id');
            $table->uuid('avis_id');

            $table->foreign('avis_id')->references('id')->on('avis')->constrained('avis')->onDelete('cascade');
            $table->foreign('admin_id')->references('id')->on('users')->constrained('users')->onDelete('cascade');
            $table->timestamps(); //created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};