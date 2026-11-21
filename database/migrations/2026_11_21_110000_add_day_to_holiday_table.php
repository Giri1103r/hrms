<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Day of the month the holiday falls on (1 - 31).
     * Nullable so rows created before this column existed are kept.
     */
    public function up(): void
    {
        if (Schema::hasColumn('holiday', 'day')) {
            return;
        }

        Schema::table('holiday', function (Blueprint $table) {
            $table->unsignedTinyInteger('day')->nullable()->after('id');
            $table->index(['year', 'month', 'day']);
        });
    }

    public function down(): void
    {
        Schema::table('holiday', function (Blueprint $table) {
            $table->dropIndex(['year', 'month', 'day']);
            $table->dropColumn('day');
        });
    }
};
