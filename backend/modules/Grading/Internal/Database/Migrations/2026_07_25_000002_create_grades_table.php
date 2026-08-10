<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Grades — one Instructor-entered Mark per gradable Enrollment on one Grade Sheet round, with
// the Letter Grade and GPA points the ADR-0021 scale derives from it.
//
// The letter and points are **frozen onto the row** rather than derived on read. That is a
// deliberate exception to the project's derive-don't-duplicate rule and ADR-0021 owns it: a
// later change to the scale must never rewrite a grade already given. `letter == scale(mark)`
// stays an invariant because there is no letter override — correcting a letter means
// correcting the mark.
//
// `grade_sheet_id` is intra-module, so it is a real FK that cascades: deleting a round takes
// its lines with it. `enrollment_id` is an **opaque reference with no foreign key** (ADR-0012 —
// Enrollments are Enrollment-owned), validated through the Enrollment\Public facade.
//
// There is no `superseded` column: supersession is **derived** (ADR-0023) — the current Grade
// for an Enrollment is the one on its latest Finalized Sheet, and the earlier one stays visible
// as history. A mark saves one line at a time (the Phase-2 per-step-save precedent), upserting
// on (sheet, enrollment) so re-entering a figure corrects it rather than adding a second line.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_sheet_id')
                ->constrained('grade_sheets')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->decimal('mark', 5, 2);
            $table->string('letter_grade');
            $table->decimal('gpa_points', 3, 2);
            $table->timestamps();

            // One Grade per (round, Enrollment) — the upsert key that makes re-entering a
            // mark a correction rather than a duplicate line.
            $table->unique(['grade_sheet_id', 'enrollment_id']);

            // The per-Enrollment grade history the read model walks (latest Finalized wins).
            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
