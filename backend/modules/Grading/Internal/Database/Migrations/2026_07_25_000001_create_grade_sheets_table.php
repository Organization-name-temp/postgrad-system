<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Grade Sheets — one **submission round** for one Course Offering (ADR-0023), the unit the
// Instructor signs and the Department Head reviews. Deliberately NOT unique per Offering: an
// Offering accumulates rounds — one `Initial` covering every gradable Enrollment, then a
// follow-up round per batch of Incomplete resolutions or corrections, each containing only the
// affected lines. That one generalisation is what lets a Finalized Sheet stay immutable while
// a later change never disturbs a settled grade.
//
// `course_offering_id` is an **opaque reference with no foreign key** (ADR-0012 — Offerings are
// Enrollment-owned); it is validated through the Enrollment\Public facade, never joined.
//
// Sheets are created **lazily**, the first time an Instructor or Student Affairs opens one —
// never eagerly at semester end. Ticket 01 reaches only the Draft state; the signer/reviewer
// columns and the review note arrive with ticket 03's two-party loop, so this table carries no
// column nothing yet writes (ADR-0018's no-anticipatory-schema rule, the Phase-3 precedent of
// add_advisor_decision_to_registration_requests).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_offering_id');
            $table->string('kind');
            $table->string('status');
            $table->timestamps();

            // The Offering's rounds, newest last — the lookup every read starts from. Not
            // unique: rounds accumulate.
            $table->index(['course_offering_id', 'kind']);
        });

        // ADR-0023's one structural invariant, enforced by the database rather than trusted:
        // an Offering has **exactly one Initial round** ("one `Initial` round covering every
        // gradable Enrollment, then a follow-up round for each batch of `IncompleteResolution`s
        // or `Correction`s"). A *partial* unique index is the precise shape — it constrains
        // Initial alone and lets the follow-up kinds accumulate freely.
        //
        // It is load-bearing, not decorative: the Sheet is created lazily on first open, and
        // `firstOrCreate` is a read-then-write that is not atomic on its own. Two concurrent
        // first opens — two tabs, or a retried GET — would otherwise each see no round and each
        // create one, permanently splitting an Offering's grades across two Initial Sheets.
        DB::statement(
            'CREATE UNIQUE INDEX grade_sheets_one_initial_round_per_offering
             ON grade_sheets (course_offering_id) WHERE kind = \'Initial\''
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_sheets');
    }
};
