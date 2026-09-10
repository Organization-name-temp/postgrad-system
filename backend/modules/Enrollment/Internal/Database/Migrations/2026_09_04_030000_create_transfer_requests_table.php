<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->string('target_category');
            $table->unsignedSmallInteger('target_credit_hours');
            $table->string('external_institution', 500);
            $table->string('external_course_code', 200);
            $table->string('external_course_name', 500);
            $table->decimal('source_credit_hours', 8, 2);
            $table->string('external_grade', 200);
            $table->jsonb('document_ids');
            $table->text('student_note')->nullable();
            $table->string('normalized_grade')->nullable();
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('status');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['target_course_id', 'status']);
        });

        Schema::create('transfer_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_request_id')->constrained('transfer_requests')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedInteger('revision');
            $table->jsonb('evidence_snapshot');
            $table->jsonb('review_snapshot')->nullable();
            $table->uuid('action_id')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['transfer_request_id', 'created_at']);
        });

        Schema::create('transfer_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->string('target_category');
            $table->unsignedSmallInteger('target_credit_hours');
            $table->string('external_institution', 500);
            $table->string('external_course_code', 200);
            $table->string('external_course_name', 500);
            $table->decimal('source_credit_hours', 8, 2);
            $table->string('external_grade', 200);
            $table->jsonb('document_ids');
            $table->string('normalized_grade');
            $table->unsignedBigInteger('verified_by_user_id');
            $table->timestamp('verified_at');
            $table->unsignedBigInteger('approved_by_user_id');
            $table->timestamp('approved_at');
            $table->foreignId('transfer_request_id')->unique()->constrained('transfer_requests')->restrictOnDelete();

            $table->index(['student_id', 'target_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_decisions');
        Schema::dropIfExists('transfer_request_transitions');
        Schema::dropIfExists('transfer_requests');
    }
};
