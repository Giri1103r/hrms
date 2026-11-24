<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives Admin, User and Manager the Attendance Management → Permission and
 * Compensatory Off menus (left menu ids 28 parent, 30 and 31 children). Who
 * may edit or approve a given request is decided by the workflow in
 * App\Models\Attendance\PermissionRequest / App\Models\Attendance\CompoffRequest.
 */
class AttendancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $parentMenuId = 28;
        $childMenuIds = [30, 31]; // Permission, Compo Off
        $roleIds = [1, 2, 3]; // Admin, User, Manager

        foreach ($roleIds as $roleId) {
            $role = DB::table('template_user_role')->where('id', $roleId)->first();
            if (!$role) {
                continue;
            }

            $current = $role->role_permission ? explode(',', $role->role_permission) : [];
            $merged = array_values(array_unique(array_merge(
                $current,
                array_map('strval', array_merge([$parentMenuId], $childMenuIds))
            )));
            DB::table('template_user_role')->where('id', $roleId)->update(['role_permission' => implode(',', $merged)]);

            $permissions = ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0, 'export' => 0];
            foreach ($childMenuIds as $menuId) {
                $exists = DB::table('master_role_permission')->where('role_id', $roleId)->where('menu_id', $menuId)->exists();
                if ($exists) {
                    DB::table('master_role_permission')->where('role_id', $roleId)->where('menu_id', $menuId)
                        ->update($permissions + ['updated_by' => 1, 'updated_at' => now()]);
                } else {
                    DB::table('master_role_permission')->insert($permissions + [
                        'role_id' => $roleId,
                        'menu_id' => $menuId,
                        'created_by' => 1,
                        'updated_by' => 1,
                        'status' => 1,
                        'trash' => 'NO',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
