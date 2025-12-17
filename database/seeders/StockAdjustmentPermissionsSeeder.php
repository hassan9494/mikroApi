<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class StockAdjustmentPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'stock_adjustment_request', 'guard_name' => 'web'],
            ['name' => 'stock_adjustment_approve', 'guard_name' => 'web'],
            ['name' => 'stock_adjustment_list_view', 'guard_name' => 'web'],
            ['name' => 'stock_adjustment_history_view', 'guard_name' => 'web'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }

        // Get all existing roles
        $allRoles = Role::all();

        // Give stock_adjustment_request permission to ALL roles
        foreach ($allRoles as $role) {
            $role->givePermissionTo('stock_adjustment_request');
        }

        // Give admin permissions only to specific roles
        $adminRoles = Role::whereIn('name', ['super', 'admin', 'Stock Manager'])->get();

        foreach ($adminRoles as $role) {
            $role->givePermissionTo([
                'stock_adjustment_approve',
                'stock_adjustment_list_view',
                'stock_adjustment_history_view',
            ]);
        }
    }
}
