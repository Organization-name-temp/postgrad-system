<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exemption_request_transitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
            $table->string('system_cause_type')->nullable();
            $table->unsignedBigInteger('system_cause_id')->nullable();
        });
        Schema::create('registration_request_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_request_id')->constrained('registration_requests')->restrictOnDelete();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->string('action', 80);
            $table->string('system_cause_type');
            $table->unsignedBigInteger('system_cause_id');
            $table->timestampTz('created_at');
            $table->unique(['registration_request_id', 'system_cause_type', 'system_cause_id']);
        });
        Schema::create('academic_request_closeouts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('request_type', 40);
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('termination_case_id');
            $table->timestampTz('created_at');
            $table->unique(['request_type', 'request_id', 'termination_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_request_closeouts');
        Schema::dropIfExists('registration_request_transitions');
        Schema::table('exemption_request_transitions', function (Blueprint $table): void {
            $table->dropColumn(['system_cause_type', 'system_cause_id']);
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
        });
    }
};
