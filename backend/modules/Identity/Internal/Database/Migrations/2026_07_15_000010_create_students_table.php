<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The Student anchor (ticket 10, ADR-0013) — the thin 1:1-with-User record minted
// when an Application is Accepted. It is Identity-owned: Admissions triggers its
// creation through the Identity\Public StudentEnrollment facade but never touches
// this table.
//
// `program_id` and `admission_cycle_id` are opaque references with NO foreign key
// (ADR-0012) — the same code-level seam the Application uses for its Program refs —
// so Catalog and Admissions can evolve their tables without a constraint dangling
// here. `advisor_id`, by contrast, points at an Identity User (a real FK within the
// module), as does the owning `user_id` (unique — one Student per User).
//
// The identity columns are a snapshot copied from the Application at admission
// (ADR-0013): a Student's identity is fixed at acceptance and does not follow later
// edits to the by-then-archived Application.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('university_id')->unique(); // {year}-{programCode}-{NNNN}, Identity-minted.

            // Opaque Catalog/Admissions refs — NO ->constrained(); the seam is code-level.
            $table->unsignedBigInteger('program_id');
            $table->string('enrollment_term'); // e.g. "Fall 2026".
            $table->unsignedBigInteger('admission_cycle_id');

            // The advisor is an Identity User — a real FK within the module.
            $table->foreignId('advisor_id')->constrained('users');

            // Identity snapshot, frozen at admission.
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('national_id');
            $table->date('date_of_birth');
            $table->string('nationality');
            $table->string('gender');

            $table->timestamp('admitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
