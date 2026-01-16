<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransferOrderPermissionsSeeder extends Seeder
{
    public function run()
    {
        Log::info('Starting TransferOrderPermissionsSeeder...');

        $permissions = [
            'transfer_order_list_view',
            'transfer_order_create',
            'transfer_order_edit',
            'transfer_order_complete',
            'transfer_order_cancel',
            'transfer_order_print',
            'transfer_order_details',
            'transfer_order_delete'
        ];

        Log::info('Permissions to create: ' . implode(', ', $permissions));

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

        // Get ALL role IDs
        $roles = DB::table('roles')->get();

        Log::info("Found " . $roles->count() . " roles");

        // Get permission IDs
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $permissionIds[$permission] = DB::table('permissions')->where('name', $permission)->value('id');
            Log::info("Permission ID for {$permission}: {$permissionIds[$permission]}");
        }

        // Assign permissions to each role
        foreach ($roles as $role) {
            Log::info("Processing role: {$role->name} (ID: {$role->id})");

            // For ALL roles, assign all permissions EXCEPT delete
            foreach ($permissionIds as $permissionName => $permissionId) {
                // Skip delete permission for now (we'll handle it separately)
                if ($permissionName !== 'transfer_order_delete') {
                    if ($permissionId && $role->id) {
                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $permissionId,
                            'role_id' => $role->id
                        ]);
                        Log::info("Assigned {$permissionName} to {$role->name}");
                    }
                }
            }
        }

        // Now assign delete permission ONLY to super_admin, admin, and stock_manager
        $deletePermissionId = $permissionIds['transfer_order_delete'] ?? null;

        if ($deletePermissionId) {
            $rolesForDelete = ['super_admin', 'admin', 'stock_manager'];

            foreach ($rolesForDelete as $roleName) {
                $role = DB::table('roles')->where('name', $roleName)->first();

                if ($role) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $deletePermissionId,
                        'role_id' => $role->id
                    ]);
                    Log::info("Assigned delete permission to {$role->name}");
                } else {
                    Log::warning("Role '{$roleName}' not found for delete permission assignment");
                }
            }
        } else {
            Log::error("Delete permission ID not found!");
        }

        Log::info('TransferOrderPermissionsSeeder completed successfully!');
    }
}
