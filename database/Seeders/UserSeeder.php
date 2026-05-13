<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Créer un livreur
        $livreur = User::create([
            'nom' => 'Jean',
            'prenom' => 'Dupont',
            'email' => 'livreur@test.com',
            'password' => Hash::make('password'),
            'role' => 'livreur',
            'actif' => true,
        ]);
        $livreurRole = Role::where('name', 'livreur')->first();
        $livreur->roles()->attach($livreurRole);

        // Créer un client
        $client = User::create([
            'nom' => 'Alice',
            'prenom' => 'Martin',
            'email' => 'client@test.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'actif' => true,
        ]);
        $clientRole = Role::where('name', 'client')->first();
        $client->roles()->attach($clientRole);
    }
}
