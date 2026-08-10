<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The grade-submission deadline (phase 4 ticket 09) — the **one** column ADR-0018 promised
// Calendar would gain in Phase 4, joining the existing order-validated invariant as
// `ends_at <= grade_submission_deadline_at`. Exam-period and holiday columns stay deferred:
// Phase 4 gives them no consumer, and that ADR forbids anticipatory schema.
//
// Required, like every other period date, so no consumer has to phrase "a Semester with no
// deadline". Added nullable, backfilled to each Semester's own `ends_at` — the earliest instant
// the invariant permits, so an already-entered calendar stays well-ordered without an
// administrator guessing — and only then made NOT NULL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->timestamp('grade_submission_deadline_at')->nullable()->after('withdrawal_deadline_at');
        });

        DB::table('semesters')->update(['grade_submission_deadline_at' => DB::raw('ends_at')]);

        Schema::table('semesters', function (Blueprint $table) {
            $table->timestamp('grade_submission_deadline_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('grade_submission_deadline_at');
        });
    }
};
