<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Semesters — the single authority for the academic term (ADR-0018, ticket 01):
// a Term (Fall/Spring) of one year carrying its five period windows. The
// registration/add-drop/late-add/withdrawal state is always derived in the app
// layer from these dates, never a stored flag (the AdmissionCycle precedent). A
// Semester is edited in place, never hard-deleted, so academic records built on it
// are never orphaned. (term, year) is unique — one Term names at most one Semester
// per year; the bilingual display name is derived from the two, not stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('term'); // Term enum {Fall, Spring} — constrained in the app layer.
            $table->unsignedSmallInteger('year');

            // The five period windows, in their invariant order (ADR-0018). Stored
            // explicitly — the SRS's "Week 1–2 / Week 3" language is seed data, not
            // computed weeks.
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('registration_opens_at');
            $table->timestamp('registration_closes_at');
            $table->timestamp('add_drop_ends_at');
            $table->timestamp('late_add_ends_at');
            $table->timestamp('withdrawal_deadline_at');

            $table->timestamps();

            $table->unique(['term', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};
