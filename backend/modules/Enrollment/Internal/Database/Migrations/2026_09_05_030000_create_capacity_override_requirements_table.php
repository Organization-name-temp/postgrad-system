<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacity_override_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('registration_request_id')->constrained('registration_requests');
            $table->foreignId('registration_request_line_id')->constrained('registration_request_lines');
            $table->unsignedBigInteger('course_offering_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('state', 24)->default('Pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['registration_request_id', 'registration_request_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_override_requirements');
    }
};
