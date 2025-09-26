<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignStock3PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the permission IDs for stock3
        $stock3PermissionIds = DB::table('permissions')
            ->whereIn('name', [
                'stock3_list_view',
                'stock3_save',
                'stock3_minimum_quantity',
                'stock3_mikro_sku',
                'stock3_available',
                'stock3_source',
                'stock3_location'
            ])
            ->pluck('id');

        // Get role IDs that have stock1 or stock2 permissions
        $roleIds = DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('permissions.name', ['stock_list_view', 'stock2_list_view'])
            ->pluck('role_has_permissions.role_id')
            ->unique();

        // Assign stock3 permissions to these roles
        foreach ($roleIds as $roleId) {
            foreach ($stock3PermissionIds as $permissionId) {
                // Check if the permission is already assigned to avoid duplicates
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }
}
