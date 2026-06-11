<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('demande_livraisons', 'livraison_gratuite')) {
            Schema::table('demande_livraisons', function (Blueprint $table) {
                $table->boolean('livraison_gratuite')->default(false)->after('prix');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('demande_livraisons', 'livraison_gratuite')) {
            Schema::table('demande_livraisons', function (Blueprint $table) {
                $table->dropColumn('livraison_gratuite');
            });
        }
    }
};
