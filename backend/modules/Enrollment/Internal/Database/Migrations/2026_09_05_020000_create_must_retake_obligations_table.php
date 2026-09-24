<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('must_retake_obligations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            $table->unsignedBigInteger('origin_enrollment_id');
            $table->string('category', 32);
            $table->unsignedSmallInteger('credit_hours');
            $table->string('state', 32)->default('Active');
            $table->unsignedBigInteger('closed_by_enrollment_id')->nullable();
            $table->unsignedBigInteger('closed_by_exemption_id')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->foreign('origin_enrollment_id')->references('id')->on('enrollments');
            $table->foreign('closed_by_enrollment_id')->references('id')->on('enrollments');
        });
        DB::statement("CREATE UNIQUE INDEX must_retake_one_active_per_course ON must_retake_obligations (student_id, course_id) WHERE state = 'Active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('must_retake_obligations');
    }
};
