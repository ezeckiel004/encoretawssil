<?php
// database/migrations/2026_03_26_000001_add_prix_par_livraison_to_navettes.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPrixParLivraisonToNavettes extends Migration
{
    public function up()
    {
        Schema::table('navettes', function (Blueprint $table) {
            // Ajouter la colonne prix_par_livraison si elle n'existe pas
            if (!Schema::hasColumn('navettes', 'prix_par_livraison')) {
                $table->decimal('prix_par_livraison', 10, 2)->default(0)->after('prix_base');
            }
        });
    }

    public function down()
    {
        Schema::table('navettes', function (Blueprint $table) {
            if (Schema::hasColumn('navettes', 'prix_par_livraison')) {
                $table->dropColumn('prix_par_livraison');
            }
        });
    }
}
