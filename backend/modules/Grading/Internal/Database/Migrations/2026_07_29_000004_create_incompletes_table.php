<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The Incomplete lifecycle (phase 4 ticket 05, SRS §4.7.3): one row per declaration, carrying
// the Instructor's attestation and how it was settled. `enrollment_id` is an opaque
// cross-module reference with no foreign key (ADR-0012); `declared_on_grade_sheet_id` and
// `resolution_grade_sheet_id`/`resolved_grade_id` are intra-module and carry real FKs.
//
// No deadline column (spec): the resolution deadline is always derived from the Calendar —
// the Enrollment's Semester, the next one after it, plus 63 days — and never stored, so
// correcting a Semester's start date moves every outstanding deadline with it.
//
// Outstanding is `resolved_grade_id IS NULL AND converted_at IS NULL`; the two are mutually
// exclusive terminal states, never both set.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incompletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('declared_on_grade_sheet_id')->constrained('grade_sheets');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('declared_by');
            $table->unsignedTinyInteger('completion_percentage');
            $table->text('reason');
            $table->foreignId('resolution_grade_sheet_id')->nullable()->constrained('grade_sheets');
            $table->foreignId('resolved_grade_id')->nullable()->constrained('grades');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index('enrollment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incompletes');
    }
};
