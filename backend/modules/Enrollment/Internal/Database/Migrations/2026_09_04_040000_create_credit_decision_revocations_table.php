<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exemption_decisions', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'target_course_id']);
            $table->index(['student_id', 'target_course_id']);
        });
        Schema::table('waiver_decisions', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'target_course_id']);
            $table->index(['student_id', 'target_course_id']);
        });

        Schema::create('credit_decision_revocations', function (Blueprint $table) {
            $table->id();
            $table->string('decision_type');
            $table->unsignedBigInteger('decision_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('target_course_id');
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->text('reason');
            $table->timestamp('created_at');

            $table->unique(['decision_type', 'decision_id']);
            $table->index(['student_id', 'target_course_id']);
        });

        Schema::table('transfer_request_transitions', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
            $table->string('system_cause_type')->nullable();
            $table->unsignedBigInteger('system_cause_id')->nullable();
        });
        Schema::table('waiver_request_transitions', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
            $table->string('system_cause_type')->nullable();
            $table->unsignedBigInteger('system_cause_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('waiver_request_transitions', function (Blueprint $table) {
            $table->dropColumn(['system_cause_type', 'system_cause_id']);
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
        });
        Schema::table('transfer_request_transitions', function (Blueprint $table) {
            $table->dropColumn(['system_cause_type', 'system_cause_id']);
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
        });
        Schema::dropIfExists('credit_decision_revocations');
        Schema::table('waiver_decisions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'target_course_id']);
            $table->unique(['student_id', 'target_course_id']);
        });
        Schema::table('exemption_decisions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'target_course_id']);
            $table->unique(['student_id', 'target_course_id']);
        });
    }
};
