<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeImportSetupSeeder extends Seeder
{
    /**
     * Registers the Employee sample file (sample download + upload log) and
     * lets the Employee List menu carry add / edit / import permissions, so the
     * role permission screen can grant them.
     */
    public function run(): void
    {
        $exists = DB::table('template_admin_upload_log_type')
            ->where('key_name', 'employee')
            ->exists();

        if (!$exists) {
            // The id column has no AUTO_INCREMENT, so the next id is assigned here.
            $nextId = (int) DB::table('template_admin_upload_log_type')->max('id') + 1;

            DB::table('template_admin_upload_log_type')->insert([
                'id' => $nextId,
                'type_name' => 'Employee',
                'key_name' => 'employee',
                'sample_file' => 'public/assets/documents/excel/sample_upload/Employee.xlsx',
                'file_name' => 'Employee.xlsx',
                'created_by' => 0,
                'status' => 1,
                'trash' => 'NO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('template_left_menu')
            ->where('link', 'employee-management/employee/list')
            ->update([
                'permission' => '[{view,add,edit,import}]',
                'updated_at' => now(),
            ]);
    }
}
