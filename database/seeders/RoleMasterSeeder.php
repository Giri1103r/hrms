<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Resets the role master (template_user_role) to Admin / User / Manager.
 *
 * Admin (id 1) inherits the old Superadmin + Admin menu permissions, so the
 * existing Super Admin login (users.role = 1) and its master_role_permission
 * rows keep working. Users / employees on the old "User" role (id 9) move to
 * the new User role (id 2). Any other retired role assignment falls back to User.
 */
class RoleMasterSeeder extends Seeder
{
    public function run(): void
    {
        $old = DB::table('template_user_role')->get()->keyBy('id');

        $perm = function (...$ids) use ($old) {
            $all = [];
            foreach ($ids as $id) {
                $value = $old[$id]->role_permission ?? '';
                $all = array_merge($all, $value === '' || $value === null ? [] : explode(',', $value));
            }
            $all = array_values(array_unique(array_filter($all, 'strlen')));
            return $all ? implode(',', $all) : null;
        };

        // old role id => new role id
        $map = [1 => 1, 2 => 1, 9 => 2];

        $roles = [
            ['id' => 1, 'role_id' => 'ROLE-00001', 'role_name' => 'Admin',   'role_permission' => $perm(1, 2)],
            ['id' => 2, 'role_id' => 'ROLE-00002', 'role_name' => 'User',    'role_permission' => $perm(9)],
            ['id' => 3, 'role_id' => 'ROLE-00003', 'role_name' => 'Manager', 'role_permission' => null],
        ];

        $remap = function ($value) use ($map) {
            $ids = array_filter(explode(',', (string) $value), 'strlen');
            $new = array_values(array_unique(array_map(fn ($id) => $map[(int) $id] ?? 2, $ids)));
            return $new ? implode(',', $new) : $value;
        };

        DB::table('template_user_role')->truncate();

        DB::transaction(function () use ($roles, $remap) {
            foreach ($roles as $role) {
                DB::table('template_user_role')->insert($role + [
                    'status' => 1,
                    'trash' => 'NO',
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (DB::table('users')->select('id', 'role')->get() as $user) {
                DB::table('users')->where('id', $user->id)->update(['role' => $remap($user->role)]);
            }

            foreach (DB::table('masters_employee')->select('id', 'user_role')->get() as $emp) {
                DB::table('masters_employee')->where('id', $emp->id)->update(['user_role' => $remap($emp->user_role)]);
            }

            // Permission rows for retired roles no longer point anywhere.
            DB::table('master_role_permission')->whereNotIn('role_id', [1, 2, 3])->delete();
        });
    }
}
