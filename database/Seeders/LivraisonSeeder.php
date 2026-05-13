<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Livraison;
use App\Models\DemandeLivraison;
use App\Models\Livreur;
use Carbon\Carbon;

class LivraisonSeeder extends Seeder
{
    public function run(): void
    {
        $demande = DemandeLivraison::first();
        $livreur = Livreur::first();

        Livraison::firstOrCreate([
            'demande_livraisons_id' => $demande->id,
            'client_id' => $demande->client_id,
            'livreur_distributeur_id' => $livreur->id,
            'status' => 'en_attente',
            'date_ramassage' => Carbon::now()->toDateString(),
        ]);
    }
}
