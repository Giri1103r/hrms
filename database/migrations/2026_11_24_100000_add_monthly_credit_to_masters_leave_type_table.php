<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Leave days credited to every employee at the start of each month for
     * this leave type. Unused credit carries over month to month inside the
     * year and resets on 1 January (see App\Models\Leave\LeaveBalance).
     */
    public function up(): void
    {
        if (Schema::hasColumn('masters_leave_type', 'monthly_credit')) {
            return;
        }

        Schema::table('masters_leave_type', function (Blueprint $table) {
            $table->decimal('monthly_credit', 4, 1)->default(1)->after('leave_type_name');
        });
    }

    public function down(): void
    {
        Schema::table('masters_leave_type', function (Blueprint $table) {
            $table->dropColumn('monthly_credit');
        });
    }
};
