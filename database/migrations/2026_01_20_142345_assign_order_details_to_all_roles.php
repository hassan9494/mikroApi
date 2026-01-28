<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AssignOrderDetailsToAllRoles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get the order_details permission ID
        $permissionId = DB::table('permissions')
            ->where('name', 'order_details')
            ->value('id');

        if ($permissionId) {
            // Get all role IDs that don't already have this permission
            $existingRoleIds = DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->pluck('role_id')
                ->toArray();

            // Get all role IDs from the roles table
            $allRoleIds = DB::table('roles')->pluck('id')->toArray();

            // Find roles that need the permission
            $missingRoleIds = array_diff($allRoleIds, $existingRoleIds);

            // Prepare data for insertion
            $data = [];
            foreach ($missingRoleIds as $roleId) {
                $data[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ];
            }

            // Insert the missing permissions
            if (!empty($data)) {
                DB::table('role_has_permissions')->insert($data);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Get the order_details permission ID
        $permissionId = DB::table('permissions')
            ->where('name', 'order_details')
            ->value('id');

        if ($permissionId) {
            // Remove from all roles except super and admin (roles 1 and 2)
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->whereNotIn('role_id', [1, 2])
                ->delete();
        }
    }
}
