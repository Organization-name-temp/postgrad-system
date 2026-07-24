<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Enrollments — a Student taking one Course Offering, minted atomically when an Advisor
// approves a Registration Request line (ADR-0017, ticket 06). This is the record the rest
// of the academic lifecycle hangs off: Phase-4 Grades attach to it, attendance registers
// (ticket 08) read it, and the Credit-Hour Summary / CGPA / Standing read models tally its
// course_status against the Appendix-D matrix.
//
// student_id is an opaque reference into Identity (no FK — the modular-monolith seam holds
// at the schema layer, ADR-0012; validated in the app layer through the Public facade).
// course_offering_id is an *intra-module* reference, so it carries a real foreign key
// (both tables are Enrollment-owned); it restrictsOnDelete as a backstop to the Offering
// delete-guard (CourseOffering::isReferenced) so a live roster is never silently destroyed.
//
// classification and credit_hours are SNAPSHOTS frozen at the mint (ADR-0017): a later
// curriculum edit or credit-split change must never re-classify or re-weight a past
// registration. Display facts (names, code, level, meeting times) are read live via the
// Catalog/Offering facades, never stored.
//
// One Enrollment per (student, offering) is unique — and since (semester, course) is unique
// per Offering, that is exactly the "one Enrollment per (student, course, semester)" rule.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id'); // → Identity students.id (no FK, ADR-0012)
            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->restrictOnDelete();
            $table->string('classification');          // EnrollmentClassification snapshot (ADR-0017)
            $table->string('course_status');           // CourseStatus: Regular | Retake | Audit (+ W from ticket 07)
            $table->unsignedSmallInteger('credit_hours'); // course total CH at registration time (snapshot)
            $table->timestamps();

            // One Enrollment per (student, offering) — equivalently per (student, course,
            // semester), because (semester, course) is unique per Offering. This is the
            // duplicate-against-existing backstop; the app layer reports it gracefully first.
            $table->unique(['student_id', 'course_offering_id']);
            $table->index('student_id'); // the student's My Schedule + retake-derivation lookup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
