<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The selected lines of one Correction round (phase 4 ticket 06). A correction round contains
// only the Enrollments the Instructor chose to change, and it must remember that membership
// before any new mark has been saved — unlike `grades`, which only appears once a line is
// decided. `enrollment_id` stays an opaque cross-module reference with no FK (ADR-0012); the
// Grade Sheet is Grading-owned and carries a real FK.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_sheet_correction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_sheet_id')->constrained('grade_sheets')->cascadeOnDelete();
            $table->unsignedBigInteger('enrollment_id');
            $table->timestamps();

            $table->unique(['grade_sheet_id', 'enrollment_id'], 'grade_sheet_correction_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_sheet_correction_lines');
    }
};
