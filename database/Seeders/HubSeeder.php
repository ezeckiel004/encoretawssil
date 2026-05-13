<?php
// database/seeders/HubSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Hub;

class HubSeeder extends Seeder
{
    public function run()
    {
        $hubs = [
            [
                'nom' => 'Hub Alger Centre',
                'email' => 'hub.alger@example.com',
            ],
            [
                'nom' => 'Hub Oran',
                'email' => 'hub.oran@example.com',
            ],
            [
                'nom' => 'Hub Constantine',
                'email' => 'hub.constantine@example.com',
            ],
            [
                'nom' => 'Hub Annaba',
                'email' => 'hub.annaba@example.com',
            ],
            [
                'nom' => 'Hub Sétif',
                'email' => 'hub.setif@example.com',
            ],
        ];

        foreach ($hubs as $hub) {
            Hub::create($hub);
        }
    }
}
