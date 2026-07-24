<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Class Sessions — the attendance register's rows (ADR-0019, ticket 08, a deliberate SRS
// extension). One row per calendar date a Course Offering meets, generated from the
// Offering's weekly meeting rows across its Semester's start→end dates (the pure
// SessionSchedule seam). Generation is idempotent: (course_offering_id, session_date,
// starts_at) is unique, so re-opening a register never duplicates a session.
//
// The meeting time is DENORMALISED onto the session (starts_at / ends_at) rather than
// pointing at an offering_meetings row: a session is a fact about a date that happened, and
// must survive an later edit to the Offering's meeting pattern — the same "snapshot the fact"
// discipline the Enrollment uses for classification/credit_hours (ADR-0017).
//
// A session the Instructor CANCELS (Eid, illness — also the holiday answer, so no holiday
// calendar) carries a cancelled_at stamp and never counts in any attendance denominator;
// cancellation is correctable (the stamp is cleared to reinstate). course_offering_id is an
// intra-module reference (both tables are Enrollment-owned) so it carries a real FK; it
// restrictsOnDelete behind the Offering delete-guard (CourseOffering::isReferenced) so a live
// register is never silently destroyed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->restrictOnDelete();
            $table->date('session_date');
            $table->time('starts_at');            // denormalised from the meeting slot (snapshot)
            $table->time('ends_at');
            $table->timestamp('cancelled_at')->nullable(); // set = does not happen, counts nowhere
            $table->timestamps();

            // Idempotent generation key: one session per (offering, date, start time), so a
            // second generation pass is a no-op and two same-day meetings stay distinct.
            $table->unique(['course_offering_id', 'session_date', 'starts_at']);
            $table->index('course_offering_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
