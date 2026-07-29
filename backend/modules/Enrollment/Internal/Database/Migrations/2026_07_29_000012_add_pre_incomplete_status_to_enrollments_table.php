<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The Course Status an Enrollment held immediately before it became Incomplete (phase 4
// ticket 05, spec §Incomplete and Fail Withdrawal): private bookkeeping so resolution can
// restore it — a Retake comes back a Retake, never a Regular. Written only by
// `EnrollmentRecords::markIncomplete()`/`resolveIncomplete()`; never exposed on
// `EnrollmentSummary`, so `CourseStatus` stays `Enrollment\Internal` (ADR-0012).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('pre_incomplete_status')->nullable()->after('course_status');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('pre_incomplete_status');
        });
    }
};
