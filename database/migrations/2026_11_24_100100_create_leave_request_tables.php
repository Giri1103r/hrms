<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Leave Management → Apply Leave.
     *
     * leave_request      one row per leave application.
     *                    duration_type: 1 = One Day, 2 = Half Day, 3 = More Than One Day.
     *                    leave_status:  1 = Pending, 2 = Confirmed, 3 = Cancelled.
     *                    total_days = paid_days + lop_days (Sundays and holidays skipped).
     * leave_request_log  every action taken on a request (history).
     */
    public function up(): void
    {
        if (!Schema::hasTable('leave_request')) {
            Schema::create('leave_request', function (Blueprint $table) {
                $table->increments('id');
                $table->string('request_no', 20)->nullable()->unique();
                $table->unsignedInteger('employee_id');          // masters_employee.id
                $table->string('emp_id', 50);                    // masters_employee.emp_id (snapshot)
                $table->unsignedInteger('leave_type_id');        // masters_leave_type.id
                $table->unsignedTinyInteger('duration_type');
                $table->unsignedTinyInteger('half_day_session')->nullable(); // 1 = First Half, 2 = Second Half
                $table->date('from_date');
                $table->date('to_date');
                $table->unsignedSmallInteger('leave_year');      // year the leave is counted against
                $table->decimal('total_days', 5, 1);
                $table->decimal('paid_days', 5, 1)->default(0);
                $table->decimal('lop_days', 5, 1)->default(0);
                $table->text('reason')->nullable();
                $table->unsignedTinyInteger('leave_status')->default(1);
                $table->boolean('is_direct')->default(false);    // applied by the approver, no approval step
                $table->unsignedInteger('approver_user_id')->nullable(); // users.id of the reporting manager
                $table->unsignedInteger('action_by')->nullable();
                $table->timestamp('action_at')->nullable();
                $table->text('action_remarks')->nullable();
                $table->integer('status')->default(1);
                $table->enum('trash', ['YES', 'NO'])->default('NO');
                $table->integer('created_by')->nullable();
                $table->integer('updated_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index(['employee_id', 'leave_type_id', 'leave_year', 'leave_status'], 'leave_request_balance_index');
                $table->index(['employee_id', 'from_date', 'to_date']);
                $table->index('approver_user_id');
            });
        }

        if (!Schema::hasTable('leave_request_log')) {
            Schema::create('leave_request_log', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('request_id');
                $table->string('action', 30);   // applied, updated, confirmed, cancelled, direct_confirmed
                $table->text('remarks')->nullable();
                $table->integer('action_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_log');
        Schema::dropIfExists('leave_request');
    }
};
