<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Application status transitions (ticket 07) — the append-only timeline every named
// workflow action writes one row to (from/to/actor/note/occurred_at). It is both the
// applicant's visible status history and the decision audit trail, and is distinct
// from the Identity Audit Log (which records identity/security events, not workflow
// moves). Rows are never edited or deleted: the model is timestamp-free and the app
// layer only ever appends.
//
// `from_status` is nullable to allow an "initial" row (no prior state); in practice
// every recorded move has a from-status, since registration seeds the Draft directly.
// `actor_user_id` is nullable — a system/auto-forward step has no human actor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('from_status')->nullable();   // ApplicationStatus — null = no prior state.
            $table->string('to_status');                 // ApplicationStatus.
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();            // Reviewer instruction / decision reason.
            $table->timestamp('occurred_at');
            // No timestamps(): the log is append-only, ordered by (occurred_at, id).

            // The timeline reads an application's rows in order; the queue derives
            // aging from the most recent entry into a review status.
            $table->index(['application_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_status_transitions');
    }
};
