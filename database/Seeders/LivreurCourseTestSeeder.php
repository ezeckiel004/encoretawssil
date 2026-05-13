<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Livreur;
use App\Models\Client;
use App\Models\Colis;
use App\Models\DemandeLivraison;
use App\Models\Livraison;
use App\Models\DemandeAdhesion;

class LivreurCourseTestSeeder extends Seeder
{
    public function run()
    {
        // --- 1. Créer le livreur ---
        $livreurUser = User::firstOrCreate(
            ['email' => 'livreur@test.com'], // on vérifie sur l'email unique
            [
                'nom' => 'Jean',
                'prenom' => 'Dupont',
                'password' => Hash::make('password'),
                'role' => 'livreur',
                'actif' => 1,
                'id' => Str::uuid(),
            ]
        );

        $demandeAdhesion = DemandeAdhesion::firstOrCreate(
            ['user_id' => $livreurUser->id],
            [
                'id_card_type' => 'CNI',
                'id_card_number' => 'LV12345678',
                'id_card_expiry_date' => now()->addYears(5),
                'status' => 'approved',
                'id' => Str::uuid(),
            ]
        );

        $livreur = Livreur::firstOrCreate(
            ['user_id' => $livreurUser->id],
            [
                'type' => 'distributeur',
                'desactiver' => false,
                'demande_adhesions_id' => $demandeAdhesion->id,
                'id' => Str::uuid(),
            ]
        );

        // --- 2. Créer un client ---
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
            [
                'status' => 'active',
                'id' => Str::uuid(),
            ]
        );

        // --- 3. Créer des colis ---
        $colis1 = Colis::firstOrCreate(
            ['colis_label' => 'Colis Test 1'],
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
            ['colis_label' => 'Colis Test 2'],
            [
                'colis_type' => 'Moyen',
                'poids' => 3.0,
                'hauteur' => 20,
                'largeur' => 25,
                'colis_description' => 'Test colis 2',
                'id' => Str::uuid(),
            ]
        );

        // --- 4. Créer des demandes de livraison ---
        $demande1 = DemandeLivraison::firstOrCreate(
            ['colis_id' => $colis1->id],
            [
                'client_id' => $client->id,
                'destinataire_id' => $client->id,
                'addresse_depot' => 'Depot 1',
                'addresse_delivery' => 'Destination 1',
                'lat_depot' => 6.5,
                'lng_depot' => 2.4,
                'lat_delivery' => 6.6,
                'lng_delivery' => 2.5,
                'info_additionnel' => 'Fragile',
                'prix' => 5000,
                'id' => Str::uuid(),
            ]
        );

        $demande2 = DemandeLivraison::firstOrCreate(
            ['colis_id' => $colis2->id],
            [
                'client_id' => $client->id,
                'destinataire_id' => $client->id,
                'addresse_depot' => 'Depot 2',
                'addresse_delivery' => 'Destination 2',
                'lat_depot' => 6.7,
                'lng_depot' => 2.6,
                'lat_delivery' => 6.8,
                'lng_delivery' => 2.7,
                'info_additionnel' => 'Livrer rapidement',
                'prix' => 8000,
                'id' => Str::uuid(),
            ]
        );

        // --- 5. Créer les livraisons associées ---
        Livraison::firstOrCreate(
            ['demande_livraisons_id' => $demande1->id],
            [
                'client_id' => $client->id,
                'livreur_distributeur_id' => $livreur->id,
                'status' => 'en_attente',
                'code_pin' => rand(1000, 9999),
                'date_ramassage' => now(),
                'id' => Str::uuid(),
            ]
        );

        Livraison::firstOrCreate(
            ['demande_livraisons_id' => $demande2->id],
            [
                'client_id' => $client->id,
                'livreur_distributeur_id' => $livreur->id,
                'status' => 'en_attente',
                'code_pin' => rand(1000, 9999),
                'date_ramassage' => now(),
                'id' => Str::uuid(),
            ]
        );

        $this->command->info("Seeder LivreurCourseTest terminé sans doublon !");
    }
}
