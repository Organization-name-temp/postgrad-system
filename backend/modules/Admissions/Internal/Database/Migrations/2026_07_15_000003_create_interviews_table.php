<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Interviews (ticket 09) — one per Application (unique), created when Student Affairs
// schedules an approved applicant into an open InterviewSlot. It tracks the booking's
// own confirmation state (`status` — pending until the applicant confirms) separately
// from the Application's status (which stays `interview_scheduled` throughout), the
// single reschedule the applicant is allowed (`reschedule_count`, capped at 1 in the
// app layer), the assigned interviewer's evaluation (score + notes, row-level
// authorized), and when Student Affairs marked it complete.
//
// `interview_slot_id` is the current slot: a reschedule repoints it, which frees the
// former slot (its `interviews` row no longer references it) — so slot availability
// needs no separate flag. The slot FK is restrict-on-delete so a booked slot cannot
// be deleted out from under an interview.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            // Capacity 1: at most one interview may point at a slot at a time, enforced
            // at the schema (a backstop under the app-layer open-check). A reschedule
            // repoints this FK to a new slot, freeing the former one, so the constraint
            // holds across reschedules.
            $table->foreignId('interview_slot_id')->unique()->constrained('interview_slots');
            $table->string('status')->default('pending_confirmation'); // InterviewStatus.
            $table->unsignedTinyInteger('reschedule_count')->default(0);
            $table->unsignedTinyInteger('evaluation_score')->nullable(); // 1..10, set by the interviewer.
            $table->text('evaluation_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
