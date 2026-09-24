<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remediation_obligations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->foreignId('revocation_id')->constrained('credit_decision_revocations');
            $table->string('state', 32)->default('AwaitingOffering');
            $table->unsignedBigInteger('opportunity_offering_id')->nullable();
            $table->unsignedBigInteger('checkpoint_semester_id')->nullable();
            $table->timestampTz('official_deadline_at')->nullable();
            $table->timestampTz('effective_deadline_at')->nullable();
            $table->unsignedBigInteger('released_by_exemption_id')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->unique('revocation_id');
        });
        Schema::create('remediation_obligation_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('remediation_obligation_id')->constrained('remediation_obligations');
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->jsonb('evidence');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_obligation_transitions');
        Schema::dropIfExists('remediation_obligations');
    }
};
