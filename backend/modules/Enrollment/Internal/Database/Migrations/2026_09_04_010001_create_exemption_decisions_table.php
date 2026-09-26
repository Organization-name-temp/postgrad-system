<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exemption_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->string('preparatory_requirement_role');
            $table->unsignedSmallInteger('credit_hours');
            $table->string('source');
            $table->jsonb('reason_codes');
            $table->jsonb('source_evidence');
            $table->unsignedBigInteger('admission_application_id');
            $table->timestamp('approved_at');

            $table->unique(['student_id', 'target_course_id']);
            $table->index(['student_id', 'approved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exemption_decisions');
    }
};
