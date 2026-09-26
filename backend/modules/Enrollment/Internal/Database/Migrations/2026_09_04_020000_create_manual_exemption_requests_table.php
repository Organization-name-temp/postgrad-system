<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exemption_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('curriculum_version_id');
            $table->unsignedBigInteger('target_course_id');
            $table->string('target_course_code');
            $table->string('preparatory_requirement_role');
            $table->unsignedSmallInteger('credit_hours');
            $table->jsonb('evidence');
            $table->text('student_note')->nullable();
            $table->string('status');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['target_course_id', 'status']);
        });

        Schema::create('exemption_request_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exemption_request_id')->constrained('exemption_requests')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->unsignedBigInteger('actor_user_id');
            $table->unsignedInteger('revision');
            $table->jsonb('evidence_snapshot');
            $table->uuid('action_id')->nullable()->unique();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['exemption_request_id', 'created_at']);
        });

        Schema::table('exemption_decisions', function (Blueprint $table) {
            $table->unsignedBigInteger('admission_application_id')->nullable()->change();
            $table->foreignId('manual_request_id')->nullable()->unique()->constrained('exemption_requests');
        });
    }

    public function down(): void
    {
        DB::table('exemption_decisions')->whereNotNull('manual_request_id')->delete();
        Schema::table('exemption_decisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_request_id');
            $table->unsignedBigInteger('admission_application_id')->nullable(false)->change();
        });
        Schema::dropIfExists('exemption_request_transitions');
        Schema::dropIfExists('exemption_requests');
    }
};
