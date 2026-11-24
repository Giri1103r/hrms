<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Attendance Management → Daily Attendance.
     *
     * attendance_request      one row per employee per marked day; request_status:
     *                         0 = Pending, 1 = Approved, 2 = Rejected. attendance_type:
     *                         1 = Present, 2 = Half Day, 3 = Work From Home.
     * attendance_request_log  every action taken on a request (history).
     */
    public function up(): void
    {
        if (!Schema::hasTable('attendance_request')) {
            Schema::create('attendance_request', function (Blueprint $table) {
                $table->increments('id');
                $table->string('request_no', 20)->nullable()->unique();
                $table->unsignedInteger('employee_id');          // masters_employee.id
                $table->string('emp_id', 50);                    // masters_employee.emp_id (snapshot)
                $table->date('attendance_date');
                $table->unsignedTinyInteger('attendance_type')->default(1);
                $table->text('reason')->nullable();
                $table->unsignedTinyInteger('request_status')->default(0);
                $table->boolean('is_direct')->default(false);    // marked by a manager, no approval step
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

                $table->index(['employee_id', 'request_status']);
                $table->index(['employee_id', 'attendance_date']);
                $table->index('approver_user_id');
            });
        }

        if (!Schema::hasTable('attendance_request_log')) {
            Schema::create('attendance_request_log', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('request_id');
                $table->string('action', 30);   // created, approved, rejected, direct_approved
                $table->text('remarks')->nullable();
                $table->integer('action_by')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_request_log');
        Schema::dropIfExists('attendance_request');
    }
};
