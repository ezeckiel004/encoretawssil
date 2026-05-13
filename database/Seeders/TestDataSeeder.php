<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Colis;
use App\Models\DemandeLivraison;
use App\Models\Livraison;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run()
    {
        // --- Création d'un utilisateur livreur ---
        $livreurUser = User::firstOrCreate(
            ['email' => 'livreur@test.com'],
            [
                'nom' => 'Jean',
                'prenom' => 'Dupont',
                'password' => Hash::make('password'),
                'role' => 'livreur',
                'actif' => 1,
                'id' => Str::uuid(),
            ]
        );

        $livreur = Livreur::firstOrCreate(
            ['user_id' => $livreurUser->id],
            [
                'type' => 'distributeur',
                'desactiver' => false,
                'id' => Str::uuid(),
            ]
        );

        // --- Création d'un utilisateur client ---
        $clientUser = User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'nom' => 'Alice',
                'prenom' => 'Martin',
                'password' => Hash::make('password'),
                'role' => 'client',
                'actif' => 1,
                'id' => Str::uuid(),
            ]
        );

        $client = Client::firstOrCreate(
            ['user_id' => $clientUser->id],
            ['status' => 'actif', 'id' => Str::uuid()]
        );

        // --- Création de quelques colis ---
        $colis1 = Colis::firstOrCreate(
            ['colis_label' => 'Colis 1'],
            [
                'colis_type' => 'Petit',
                'poids' => 1.5,
                'hauteur' => 10,
                'largeur' => 15,
                'colis_description' => 'Test colis 1',
                'id' => Str::uuid(),
            ]
        );

        $colis2 = Colis::firstOrCreate(
            ['colis_label' => 'Colis 2'],
            [
                'colis_type' => 'Moyen',
                'poids' => 3.2,
                'hauteur' => 20,
                'largeur' => 25,
                'colis_description' => 'Test colis 2',
                'id' => Str::uuid(),
            ]
        );

        // --- Création de demandes de livraison ---
        $demande1 = DemandeLivraison::firstOrCreate(
            ['colis_id' => $colis1->id],
            [
                'client_id' => $client->id,
                'destinataire_id' => $client->id,
                'prix' => 5000,
                'addresse_depot' => 'Depot 1',
                'addresse_delivery' => 'Destination 1',
                'lat_depot' => 6.5,
                'lng_depot' => 2.4,
                'lat_delivery' => 6.6,
                'lng_delivery' => 2.5,
                'info_additionnel' => 'Fragile',
                'id' => Str::uuid(),
            ]
        );

        $demande2 = DemandeLivraison::firstOrCreate(
            ['colis_id' => $colis2->id],
            [
                'client_id' => $client->id,
                'destinataire_id' => $client->id,
                'prix' => 8000,
                'addresse_depot' => 'Depot 2',
                'addresse_delivery' => 'Destination 2',
                'lat_depot' => 6.7,
                'lng_depot' => 2.6,
                'lat_delivery' => 6.8,
                'lng_delivery' => 2.7,
                'info_additionnel' => 'Livrer rapidement',
                'id' => Str::uuid(),
            ]
        );

        // --- Création des livraisons associées ---
        Livraison::firstOrCreate(
            ['demande_livraisons_id' => $demande1->id],
            [
                'client_id' => $client->id,
                'livreur_distributeur_id' => $livreur->id,
                'livreur_ramasseur_id' => null,
                'status' => 'en_attente',
                'code_pin' => rand(1000, 9999),
                'date_ramassage' => now(),
                'date_livraison' => null,
                'bordereau_id' => null,
                'id' => Str::uuid(),
            ]
        );

        Livraison::firstOrCreate(
            ['demande_livraisons_id' => $demande2->id],
            [
                'client_id' => $client->id,
                'livreur_distributeur_id' => $livreur->id,
                'livreur_ramasseur_id' => null,
                'status' => 'en_attente',
                'code_pin' => rand(1000, 9999),
                'date_ramassage' => now(),
                'date_livraison' => null,
                'bordereau_id' => null,
                'id' => Str::uuid(),
            ]
        );

        $this->command->info("Données de test créées avec succès !");
    }
}
