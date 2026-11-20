<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Employee type (Permanent / Contract / ...) comes from masters_employee_type
     * and is stored by name, the same way designation and employee_status are.
     */
    public function up(): void
    {
        if (Schema::hasColumn('masters_employee', 'employee_type')) {
            return;
        }

        Schema::table('masters_employee', function (Blueprint $table) {
            $table->string('employee_type', 50)->nullable()->after('employee_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('masters_employee', 'employee_type')) {
            return;
        }

        Schema::table('masters_employee', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
    }
};
