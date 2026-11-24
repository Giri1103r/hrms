<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Daily Attendance (left menu id 29, created earlier under Attendance
 * Management) is opened up to Admin, User and Manager — everyone marks their
 * own attendance. Attendance Approval is a new child menu under the same
 * parent (28), created here, and is restricted to Admin and Manager only:
 * it is where a reporting manager bulk-approves attendance their reports
 * marked themselves. See App\Models\Attendance\AttendanceRequest for the
 * workflow rules.
 */
class DailyAttendancePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $parentMenuId = 28;
        $dailyAttendanceMenuId = 29;

        $approvalMenuId = DB::table('template_left_menu')->where('link', 'attendance-management/attendance-approval/list')->value('id');
        if (!$approvalMenuId) {
            $approvalMenuId = DB::table('template_left_menu')->insertGetId([
                'name' => 'Attendance Approval',
                'link' => 'attendance-management/attendance-approval/list',
                'icon' => 'fa-solid fa-folder',
                'parent_id' => $parentMenuId,
                'permission' => '[{view,add}]',
                'is_parent' => 0,
                'is_module' => 1,
                'sort_order' => 4,
                'status' => 1,
                'trash' => 'NO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Admin, User, Manager: everyone can see and use Daily Attendance.
        $this->grant([1, 2, 3], $dailyAttendanceMenuId, $parentMenuId);

        // Admin, Manager only: Attendance Approval.
        $this->grant([1, 3], $approvalMenuId, $parentMenuId);
    }

    private function grant(array $roleIds, int $menuId, int $parentMenuId): void
    {
        foreach ($roleIds as $roleId) {
            $role = DB::table('template_user_role')->where('id', $roleId)->first();
            if (!$role) {
                continue;
            }

            $current = $role->role_permission ? explode(',', $role->role_permission) : [];
            $merged = array_values(array_unique(array_merge(
                $current,
                array_map('strval', [$parentMenuId, $menuId])
            )));
            DB::table('template_user_role')->where('id', $roleId)->update(['role_permission' => implode(',', $merged)]);

            $permissions = ['view' => 1, 'add' => 1, 'edit' => 0, 'delete' => 0, 'export' => 0];
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
