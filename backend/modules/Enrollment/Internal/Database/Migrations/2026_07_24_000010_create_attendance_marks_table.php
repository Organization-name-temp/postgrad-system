<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Attendance Marks — one binary present/absent record per (Class Session, Enrollment),
// taken by the Offering's assigned Instructor (ADR-0019, ticket 08). No late/excused
// taxonomy — that is attendance policy with zero SRS backing. Marks are CORRECTABLE: a save
// upserts on (class_session_id, enrollment_id), so re-marking overwrites rather than
// duplicating.
//
// Both references are intra-module (Enrollment owns class_sessions and enrollments) so both
// carry real FKs and cascadeOnDelete: a hard-dropped Enrollment (a free drop leaves no trace,
// ADR-0017) takes its marks with it, and a deleted session likewise. A withdrawn (W)
// Enrollment is NOT deleted, so it keeps its historical marks but drops off the active roster
// — future sessions are never marked for it.
//
// The attendance percentage is DERIVED on demand (present ÷ non-cancelled recorded sessions),
// never stored — the project's standing rule for derived read models (ADR-0019).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')
                ->constrained('class_sessions')
                ->cascadeOnDelete();
            $table->foreignId('enrollment_id')
                ->constrained('enrollments')
                ->cascadeOnDelete();
            $table->boolean('present');
            $table->timestamps();

            // One mark per (session, enrollment) — the upsert key that makes a correction
            // overwrite the prior mark instead of adding a second.
            $table->unique(['class_session_id', 'enrollment_id']);
            $table->index('enrollment_id'); // the per-enrollment percentage tally
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_marks');
    }
};
