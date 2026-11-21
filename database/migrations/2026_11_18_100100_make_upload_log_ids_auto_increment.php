<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * These two tables were created without a primary key / AUTO_INCREMENT, so
     * any insert that leaves out `id` fails ("Field 'id' doesn't have a default
     * value"), which breaks every import that records row errors.
     */
    private array $tables = ['template_admin_upload_log_error', 'template_admin_upload_log_type'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $id = DB::selectOne("SHOW COLUMNS FROM `{$table}` WHERE Field = 'id'");
            if (!$id || str_contains($id->Extra, 'auto_increment')) {
                continue;
            }

            $duplicates = DB::table($table)->select('id')->groupBy('id')->havingRaw('COUNT(*) > 1')->count();
            $nulls = DB::table($table)->whereNull('id')->count();
            if ($duplicates || $nulls) {
                throw new RuntimeException("{$table}.id has duplicate or empty values; fix them before running this migration.");
            }

            $primary = $id->Key === 'PRI' ? '' : ', ADD PRIMARY KEY (`id`)';
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT{$primary}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL");
            DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY");
        }
    }
};
