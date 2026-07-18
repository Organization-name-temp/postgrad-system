<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Applications — one per registering applicant per Admission Cycle. Registration
// (ticket 03) creates the row in the Draft status, owned by the new User and tied
// to the open Cycle it was started in; "registering is starting the application",
// so there is no orphan-account state.
//
// This migration lays down only the identity of an Application (owner, cycle,
// status). The per-cycle personal-info snapshot, program selection, decision
// extras, and timestamps arrive with the wizard/workflow tickets (04, 06, 07…)
// that first populate them — each a forward-only migration on this table.
//
// user_id keeps a real FK to Identity's users (both live in the same database).
// The Catalog program references, by contrast, are stored later as opaque ids
// with NO FK — that seam is enforced in code (ADR-0012), not by the schema here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admission_cycle_id')->constrained('admission_cycles');
            $table->string('status')->default('draft'); // ApplicationStatus — constrained in the app layer.
            $table->timestamps();

            // One application per applicant per cycle: registering starts the one
            // Draft. A rejected applicant re-applies in a *different* Cycle.
            $table->unique(['user_id', 'admission_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
