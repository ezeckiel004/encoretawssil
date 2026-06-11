// database/migrations/2026_01_01_000000_add_return_status_to_livraisons_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReturnStatusToLivraisonsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->enum('return_status', [
                'chez_livreurs',
                'retour_en_traitement',
                'retour_prets'
            ])->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livraisons', function (Blueprint $table) {
            $table->dropColumn('return_status');
        });
    }
}
