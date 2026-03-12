<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StockCountPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'stock_count_create', 'guard_name' => 'web'],
            ['name' => 'stock_count_view', 'guard_name' => 'web'],
            ['name' => 'stock_count_edit', 'guard_name' => 'web'],
            ['name' => 'stock_count_approve', 'guard_name' => 'web'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }

        // Get all existing roles
        $allRoles = Role::all();

        // Give create, view, edit permissions to ALL roles
        foreach ($allRoles as $role) {
            $role->givePermissionTo([
                'stock_count_create',
                'stock_count_view',
                'stock_count_edit',
            ]);
        }

        // Give approve permission only to specific roles
        $adminRoles = Role::whereIn('name', ['super', 'admin', 'Stock Manager'])->get();

        foreach ($adminRoles as $role) {
            $role->givePermissionTo('stock_count_approve');
        }
    }
}
