<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('termination_cases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('fingerprint', 64)->index();
            $table->string('state', 40)->default('AwaitingAdvisor');
            $table->jsonb('evidence');
            $table->unsignedBigInteger('advisor_user_id')->nullable();
            $table->text('advisor_assessment')->nullable();
            $table->string('advisor_recommendation', 32)->nullable();
            $table->timestampTz('advisor_reviewed_at')->nullable();
            $table->unsignedBigInteger('department_head_user_id')->nullable();
            $table->text('department_head_reason')->nullable();
            $table->timestampTz('department_head_reviewed_at')->nullable();
            $table->unsignedBigInteger('council_user_id')->nullable();
            $table->text('council_reason')->nullable();
            $table->timestampTz('council_decided_at')->nullable();
            $table->string('closed_reason', 40)->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement("CREATE UNIQUE INDEX termination_one_open_per_student ON termination_cases (student_id) WHERE state IN ('AwaitingAdvisor','AwaitingDepartmentHead','AwaitingCouncil')");
    }

    public function down(): void
    {
        Schema::dropIfExists('termination_cases');
    }
};
