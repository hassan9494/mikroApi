<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ContactPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create permissions
        $permissions = [
            'contact_list_view',
            'contact_add',
            'contact_edit',
            'contact_delete',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Get all roles (for list, add, edit)
        $allRoles = Role::all();
        foreach ($allRoles as $role) {
            $role->givePermissionTo(['contact_list_view', 'contact_add', 'contact_edit']);
        }

        // Assign delete only to specific role IDs (1,2,6,10)
        $deleteRoleIds = [1, 2, 6, 10];
        $deletePermission = Permission::where('name', 'contact_delete')->first();
        if ($deletePermission) {
            $roles = Role::whereIn('id', $deleteRoleIds)->get();
            foreach ($roles as $role) {
                $role->givePermissionTo($deletePermission);
            }
        }

        // Reset cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
