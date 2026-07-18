<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Interview slots (ticket 09) — the pool Student Affairs sets up so approved
// applicants can be scheduled. Each slot is a single appointment at a time, of a
// duration, with an assigned interviewer and a mode (in person / virtual). Capacity
// is exactly one: a slot holds at most one Interview, so "open vs taken" is derived
// from whether an `interviews` row currently points at it — never stored here.
//
// `interviewer_id` keeps a real FK to Identity's users (both modules share the
// database); the interviewer is a faculty User resolved for display through the
// Identity\Public directory facade, never an Eloquent relation across the seam
// (ADR-0002). `location` and `meeting_link` are both nullable — which one is
// meaningful is decided by `mode`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_slots', function (Blueprint $table) {
            $table->id();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->foreignId('interviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode');                     // InterviewMode — constrained in the app layer.
            $table->string('location')->nullable();     // In-person location (mode = in_person).
            $table->string('meeting_link')->nullable(); // Virtual meeting link (mode = virtual).
            $table->timestamps();

            // The slot lists filter by interviewer and order by time.
            $table->index(['interviewer_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_slots');
    }
};
