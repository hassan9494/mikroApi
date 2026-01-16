<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStockManagerRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insert the Stock Manager role
        DB::table('roles')->insert([
            [
                'name' => 'Stock Manager',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Get the ID of the newly inserted role
        $roleId = DB::table('roles')->where('name', 'Stock Manager')->value('id');

        // Define the permissions for Stock Manager
        $permissionNames = [
            'home',
            'stock_list_view',
            'stock2_list_view',
            'stock3_list_view',
            'stock_save',
            'stock_available',
            'stock_normal_price',
            'stock_sale_price',
            'stock_real_price',
            'stock_dist_price',
            'stock_available_read_only',
            'stock2_minimum_quantity',
            'stock2_mikro_sku',
            'stock2_source_sku',
            'stock2_save',
            'stock3_save',
            'stock3_minimum_quantity',
            'stock3_mikro_sku',
            'stock3_available',
            'stock3_source',
            'stock3_location',
            'edit_stock',
            'product_list_view',
            'category_list_view',
            'sub_category_list_view',
            'product_add',
            'product_edit',
            'stock_list_view',
            'stock2_list_view',
            'stock3_list_view',
            'order_reorder',
            'order_add',
            'invoice_list_view',
            'brand_list_view',
            'source_list_view',
            'product_variants_list_view',
            'location_list_view',
            'deleted_product_list_view',
            'order_details'
        ];

        // Get permission IDs
        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        // Assign permissions to the role
        $rolePermissions = [];
        foreach ($permissionIds as $permissionId) {
            $rolePermissions[] = [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ];
        }

        if (!empty($rolePermissions)) {
            DB::table('role_has_permissions')->insert($rolePermissions);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Get the role ID
        $roleId = DB::table('roles')->where('name', 'Stock Manager')->value('id');

        if ($roleId) {
            // Remove the role from users
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();

            // Remove permissions from the role
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();

            // Remove the role
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
}
