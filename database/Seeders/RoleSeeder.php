<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = ['admin', 'livreur', 'client'];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'id' => (string) Str::uuid(),
                'name' => $role
            ]);
        }
    }
}
