<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives Admin, User and Manager the Shift Management → Shift Change Request
 * menu (left menu ids 21 parent, 22 child). Who may edit or approve a given
 * request is decided by the workflow in App\Models\Shift\ShiftChangeRequest.
 */
class ShiftChangeRequestPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $menuIds = [21, 22];
        $roleIds = [1, 2, 3]; // Admin, User, Manager

        foreach ($roleIds as $roleId) {
            $role = DB::table('template_user_role')->where('id', $roleId)->first();
            if (!$role) {
                continue;
            }

            $current = $role->role_permission ? explode(',', $role->role_permission) : [];
            $merged = array_values(array_unique(array_merge($current, array_map('strval', $menuIds))));
            DB::table('template_user_role')->where('id', $roleId)->update(['role_permission' => implode(',', $merged)]);

            $permissions = ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 0, 'export' => 0];
            $exists = DB::table('master_role_permission')->where('role_id', $roleId)->where('menu_id', 22)->exists();
            if ($exists) {
                DB::table('master_role_permission')->where('role_id', $roleId)->where('menu_id', 22)
                    ->update($permissions + ['updated_by' => 1, 'updated_at' => now()]);
            } else {
                DB::table('master_role_permission')->insert($permissions + [
                    'role_id' => $roleId,
                    'menu_id' => 22,
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
