<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Holiday Management: one row per month/year.
     */
    public function up(): void
    {
        if (Schema::hasTable('holiday')) {
            return;
        }

        Schema::create('holiday', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('month');   // 1 - 12
            $table->unsignedSmallInteger('year');
            $table->integer('status')->default(1);
            $table->enum('trash', ['YES', 'NO'])->default('NO');
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holiday');
    }
};
