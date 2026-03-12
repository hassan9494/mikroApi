<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ShelfFillingPermissionsSeeder extends Seeder
{
    public function run()
    {
        Log::info('Starting ShelfFillingPermissionsSeeder...');

        $permissions = [
            'shelf_filling_list_view',
            'shelf_filling_create_transfer',
            'shelf_filling_export',
        ];

        // Create the permissions
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            Log::info("Created permission: {$permission}");
        }

        // Get ALL roles
        $roles = DB::table('roles')->get();
        Log::info("Found " . $roles->count() . " roles");

        // Get permission IDs
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $permissionIds[$permission] = DB::table('permissions')->where('name', $permission)->value('id');
            Log::info("Permission ID for {$permission}: {$permissionIds[$permission]}");
        }

        // shelf_filling_list_view -> ALL roles
        $listViewId = $permissionIds['shelf_filling_list_view'] ?? null;
        if ($listViewId) {
            foreach ($roles as $role) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $listViewId,
                    'role_id' => $role->id
                ]);
                Log::info("Assigned shelf_filling_list_view to {$role->name}");
            }
        }

        // shelf_filling_create_transfer -> all roles EXCEPT Cashier and Manager
        $createTransferId = $permissionIds['shelf_filling_create_transfer'] ?? null;
        if ($createTransferId) {
            $excludedRoles = ['Cashier', 'Manager'];
            foreach ($roles as $role) {
                if (!in_array($role->name, $excludedRoles)) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $createTransferId,
                        'role_id' => $role->id
                    ]);
                    Log::info("Assigned shelf_filling_create_transfer to {$role->name}");
                } else {
                    Log::info("Skipped shelf_filling_create_transfer for {$role->name}");
                }
            }
        }

        // shelf_filling_export -> only super and admin
        $exportId = $permissionIds['shelf_filling_export'] ?? null;
        if ($exportId) {
            $exportRoles = ['super', 'admin'];
            foreach ($exportRoles as $roleName) {
                $role = DB::table('roles')->where('name', $roleName)->first();
                if ($role) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $exportId,
                        'role_id' => $role->id
                    ]);
                    Log::info("Assigned shelf_filling_export to {$role->name}");
                } else {
                    Log::warning("Role '{$roleName}' not found for export permission");
                }
            }
        }

        Log::info('ShelfFillingPermissionsSeeder completed successfully!');
    }
}
