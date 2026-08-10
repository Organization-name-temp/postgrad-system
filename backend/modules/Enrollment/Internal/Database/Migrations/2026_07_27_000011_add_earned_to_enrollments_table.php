<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The one bit Grading pushes back at a Sheet's finalize (ADR-0022, phase 4 ticket 03):
// "this Attempt reached C+ and earned its credit hours". Nullable — null until graded, and
// permanently null for Audit/Withdrawal/Fail-Withdrawal Enrollments, which are never graded at
// all. Written **only** through `EnrollmentRecords::recordOutcome()`, in the same transaction
// as a Grade Sheet finalize; Enrollment remains the sole writer of its own column.
//
// This is the one deliberate duplication in the phase-4 design (ADR-0022): the pass/fail fact
// lives in Grading as the Mark and Letter Grade, and again here as one bit, so Enrollment's
// registration-time cart rules (ticket 07) stay pure functions over their own rows with no
// Grading test double.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->boolean('earned')->nullable()->after('credit_hours');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('earned');
        });
    }
};
