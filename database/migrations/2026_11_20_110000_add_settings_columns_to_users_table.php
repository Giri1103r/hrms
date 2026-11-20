<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Per-user settings for the Settings page. theme and language were already
 * written by SettingsController::theme_change and LocalizationController but
 * the columns never existed; preferences holds the remaining appearance and
 * app preferences as JSON (see App\Support\UserPreferences).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 20)->nullable()->after('permission');
            }
            if (!Schema::hasColumn('users', 'language')) {
                $table->string('language', 10)->nullable()->after('theme');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('language');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['preferences', 'timezone', 'language', 'theme'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
