<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Employees are no longer assigned to a Unit; they are assigned to a Shift
 * from the Shift master (masters_shift) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masters_employee', function (Blueprint $table) {
            if (!Schema::hasColumn('masters_employee', 'shift')) {
                $table->unsignedInteger('shift')->nullable()->after('location');
            }
            if (Schema::hasColumn('masters_employee', 'unit')) {
                $table->dropColumn('unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('masters_employee', function (Blueprint $table) {
            if (!Schema::hasColumn('masters_employee', 'unit')) {
                $table->string('unit', 50)->nullable()->after('location');
            }
            if (Schema::hasColumn('masters_employee', 'shift')) {
                $table->dropColumn('shift');
            }
        });
    }
};
