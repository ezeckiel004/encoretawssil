<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Colis;

class ColisSeeder extends Seeder
{
    public function run(): void
    {
        Colis::firstOrCreate([
            'colis_type' => 'Fragile',
            'colis_label' => 'Box 1',
            'colis_description' => 'Exemple fragile',
            'poids' => 2.5,
            'hauteur' => 30,
            'largeur' => 20
        ]);
    }
}
