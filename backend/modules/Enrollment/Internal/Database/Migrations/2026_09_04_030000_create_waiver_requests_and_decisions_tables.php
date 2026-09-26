<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiver_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->unsignedSmallInteger('credit_hours');
            $table->unsignedBigInteger('prior_course_id');
            $table->string('prior_course_code');
            $table->unsignedSmallInteger('prior_course_level');
            $table->string('prior_result_source');
            $table->unsignedBigInteger('prior_enrollment_id')->nullable();
            $table->string('prior_letter_grade')->nullable();
            $table->jsonb('evidence');
            $table->text('student_note')->nullable();
            $table->string('status');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('coverage_percentage', 5, 2)->nullable();
            $table->text('equivalency_note')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['target_course_id', 'status']);
        });

        Schema::create('waiver_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waiver_request_id')->constrained('waiver_requests')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedInteger('revision');
            $table->jsonb('evidence_snapshot');
            $table->jsonb('verification_snapshot')->nullable();
            $table->uuid('action_id')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['waiver_request_id', 'created_at']);
        });

        Schema::create('waiver_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('waiver_request_id')->unique()->constrained('waiver_requests')->restrictOnDelete();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->unsignedSmallInteger('credit_hours');
            $table->unsignedBigInteger('prior_course_id');
            $table->string('prior_course_code');
            $table->unsignedSmallInteger('prior_course_level');
            $table->string('prior_result_source');
            $table->string('prior_letter_grade')->nullable();
            $table->decimal('coverage_percentage', 5, 2);
            $table->unsignedBigInteger('verified_by');
            $table->timestamp('verified_at');
            $table->unsignedBigInteger('approved_by');
            $table->timestamp('approved_at');

            $table->unique(['student_id', 'target_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiver_decisions');
        Schema::dropIfExists('waiver_request_transitions');
        Schema::dropIfExists('waiver_requests');
    }
};
