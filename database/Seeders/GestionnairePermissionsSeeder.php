<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GestionnairePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Créer ou récupérer le rôle "gestionnaire"
        $roleGestionnaire = Role::firstOrCreate(
            ['name' => 'gestionnaire'],
            ['id' => (string) Str::uuid()]
        );

        // 2. Créer les permissions pour le gestionnaire
        $permissionNames = [
            'gestionnaire.full_access',
            'gestionnaire.no_delete',
            'gestionnaire.view_dashboard',
            'gestionnaire.manage_livraisons',
            'gestionnaire.manage_livreurs',
            'gestionnaire.manage_codes_promo',
        ];

        $permissionIds = [];
        
        foreach ($permissionNames as $permName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permName],
                ['id' => (string) Str::uuid()]
            );
            $permissionIds[] = $permission->id;
        }

        // 3. Associer toutes les permissions au rôle gestionnaire
        // ⚠️ IMPORTANT: Insertion manuelle dans permission_role avec gestion de l'ID
        foreach ($permissionIds as $permissionId) {
            // Vérifier si la relation existe déjà
            $exists = DB::table('permission_role')
                ->where('role_id', $roleGestionnaire->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert([
                    'id' => (string) Str::uuid(), // ⚠️ Générer un ID UUID
                    'role_id' => $roleGestionnaire->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Permissions du gestionnaire créées avec succès !');
    }
}