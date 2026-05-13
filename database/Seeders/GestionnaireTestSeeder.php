<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Gestionnaire;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GestionnaireTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Récupérer le rôle gestionnaire
        $role = Role::where('name', 'gestionnaire')->first();
        
        if (!$role) {
            $this->command->error('❌ Rôle "gestionnaire" non trouvé. Exécutez d\'abord GestionnairePermissionsSeeder');
            return;
        }

        // 2. Créer un utilisateur gestionnaire pour Alger
        $user1 = User::firstOrCreate(
            ['email' => 'gestionnaire.alger@example.com'],
            [
                'id' => (string) Str::uuid(),
                'nom' => 'Gestionnaire',
                'prenom' => 'Alger',
                'password' => Hash::make('password'),
                'telephone' => '0550000001',
                'role' => 'gestionnaire',
                'actif' => true,
            ]
        );

        // 3. Créer son profil gestionnaire avec wilaya 16 (Alger)
        Gestionnaire::firstOrCreate(
            ['user_id' => $user1->id],
            [
                'id' => (string) Str::uuid(),
                'wilaya_id' => '16',
                'status' => 'active',
            ]
        );

        // 4. Créer un autre gestionnaire pour Oran
        $user2 = User::firstOrCreate(
            ['email' => 'gestionnaire.oran@example.com'],
            [
                'id' => (string) Str::uuid(),
                'nom' => 'Gestionnaire',
                'prenom' => 'Oran',
                'password' => Hash::make('password'),
                'telephone' => '0550000002',
                'role' => 'gestionnaire',
                'actif' => true,
            ]
        );

        Gestionnaire::firstOrCreate(
            ['user_id' => $user2->id],
            [
                'id' => (string) Str::uuid(),
                'wilaya_id' => '31',
                'status' => 'active',
            ]
        );

        // 5. Associer le rôle aux utilisateurs AVEC gestion des IDs
        $this->attachRoleToUser($user1->id, $role->id);
        $this->attachRoleToUser($user2->id, $role->id);

        $this->command->info('✅ Gestionnaires de test créés !');
    }

    /**
     * Attacher un rôle à un utilisateur en gérant l'ID
     */
    private function attachRoleToUser($userId, $roleId)
    {
        // Vérifier si la relation existe déjà
        $exists = DB::table('user_role')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if (!$exists) {
            DB::table('user_role')->insert([
                'id' => (string) Str::uuid(), // ⚠️ IMPORTANT: Générer un ID !
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}