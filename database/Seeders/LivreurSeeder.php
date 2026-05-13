<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Livreur;
use App\Models\User;

class LivreurSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'livreur')->first();
        Livreur::firstOrCreate([
            'user_id' => $user->id,
            'type' => 'distributeur',
            'desactiver' => false,
        ]);
    }
}
