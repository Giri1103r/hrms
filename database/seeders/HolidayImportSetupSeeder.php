<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HolidayImportSetupSeeder extends Seeder
{
    /**
     * Registers the Holiday sample file (sample download + upload log type).
     */
    public function run(): void
    {
        $exists = DB::table('template_admin_upload_log_type')
            ->where('key_name', 'holiday')
            ->exists();

        if ($exists) {
            return;
        }

        // The id column has no AUTO_INCREMENT, so the next id is assigned here.
        $nextId = (int) DB::table('template_admin_upload_log_type')->max('id') + 1;

        DB::table('template_admin_upload_log_type')->insert([
            'id' => $nextId,
            'type_name' => 'Holiday',
            'key_name' => 'holiday',
            'sample_file' => 'public/assets/documents/excel/sample_upload/Holiday.xlsx',
            'file_name' => 'Holiday.xlsx',
            'created_by' => 0,
            'status' => 1,
            'trash' => 'NO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
