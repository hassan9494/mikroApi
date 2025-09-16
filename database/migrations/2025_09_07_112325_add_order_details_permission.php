<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOrderDetailsPermission extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Insert the new permission
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'order_details',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign the permission to the super admin role (role_id = 1)
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => 1, // super admin role
        ]);

        // Assign the permission to the admin role (role_id = 2)
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => 2, // admin role
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove the permission assignments
        $permission = DB::table('permissions')->where('name', 'order_details')->first();
        if ($permission) {
            DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
}
