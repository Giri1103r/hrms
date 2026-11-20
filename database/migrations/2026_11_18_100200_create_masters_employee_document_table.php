<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One row per uploaded file. An employee can have several files for one
     * document type; the Add form allows each document type only once.
     */
    public function up(): void
    {
        if (Schema::hasTable('masters_employee_document')) {
            return;
        }

        Schema::create('masters_employee_document', function (Blueprint $table) {
            $table->increments('id');
            // masters_employee.id is a signed INT, masters_document_type.id an unsigned INT.
            $table->integer('employee_id');
            $table->unsignedInteger('document_type_id');
            $table->string('file_orgname');
            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_extension', 10)->nullable();
            $table->integer('status')->default(1);
            $table->enum('trash', ['YES', 'NO'])->default('NO');
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('employee_id')->references('id')->on('masters_employee')->cascadeOnDelete();
            $table->foreign('document_type_id')->references('id')->on('masters_document_type')->restrictOnDelete();
            $table->index(['employee_id', 'document_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('masters_employee_document');
    }
};
