<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Registration Requests — a Student's submitted cart for a Semester (ADR-0017, ticket
// 05): the aggregate the assigned Advisor decides line-by-line (ticket 06). It carries
// its kind (Initial / LateAdd), its status in the review pipeline (Pending / Approved /
// Rejected), and — in the child registration_request_lines table — one line per chosen
// Course Offering. There is no server-side Draft: the cart lives in the SPA until submit.
//
// student_id and semester_id are opaque references into other modules (Identity, Calendar)
// with NO database foreign key — the modular-monolith seam holds at the schema layer too
// (ADR-0012); each is validated in the app layer through that module's Public facade.
//
// At most one Pending request may exist per (student, semester); that is enforced by a
// partial unique index (Postgres) as a backstop to the app-layer check, so a Rejected or
// Approved request never blocks a fresh submission but a second Pending cannot be created.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');  // → Identity students.id (no FK, ADR-0012)
            $table->unsignedBigInteger('semester_id');  // → Calendar semesters.id (no FK, ADR-0012)
            $table->string('kind');                     // RegistrationKind: Initial | LateAdd
            $table->string('status');                   // RegistrationStatus: Pending | Approved | Rejected
            $table->timestamps();

            $table->index(['student_id', 'semester_id']); // the student's own-requests lookup
        });

        // One Pending request per (student, semester) — a partial unique index so a
        // Rejected/Approved request never blocks a new cart while the window is open.
        DB::statement(
            'CREATE UNIQUE INDEX registration_requests_one_pending_per_student_semester '
            ."ON registration_requests (student_id, semester_id) WHERE status = 'Pending'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};
