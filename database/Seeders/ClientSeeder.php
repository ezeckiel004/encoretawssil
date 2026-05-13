<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\User;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('role', 'client')->first();
        Client::firstOrCreate(['user_id' => $user->id, 'status' => 'actif']);
    }
}
