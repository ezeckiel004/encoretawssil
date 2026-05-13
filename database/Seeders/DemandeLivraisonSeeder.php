<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DemandeLivraison;
use App\Models\Client;
use App\Models\Colis;

class DemandeLivraisonSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::first();
        $colis = Colis::first();

        DemandeLivraison::firstOrCreate([
            'client_id' => $client->id,
            'destinataire_id' => $client->id,
            'colis_id' => $colis->id,
            'prix' => 5000,
            'addresse_depot' => '123 Rue A',
            'addresse_delivery' => '456 Rue B',
            'lat_depot' => 6.5,
            'lng_depot' => 2.5,
            'lat_delivery' => 6.6,
            'lng_delivery' => 2.6,
            'info_additionnel' => 'Livraison rapide',
        ]);
    }
}
