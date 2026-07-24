<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Registration Request Lines — one line per Course Offering a Student put in a cart
// (ADR-0017, ticket 05): the chosen Offering and the requested mode (Regular / Audit).
// Retake is never a requested mode — it is derived at the mint (ticket 06). The lines
// belong to their Request with a real foreign key: this is an *intra-module* relationship
// (both tables are Enrollment-owned), so the schema-level seam that bars cross-module FKs
// (ADR-0012) does not apply — deleting a Request cascades its lines.
//
// course_offering_id references a same-module course_offerings row but is left WITHOUT a
// database foreign key on purpose: a cart is transient and must not keep an Offering from
// being deleted (the Offering delete-guard deliberately protects only live enrollments /
// sessions, not carts, ADR-0017), so the reference is validated in the app layer and a
// stale line is resolved defensively at read time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_request_id')
                ->constrained('registration_requests')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('course_offering_id'); // → same-module course_offerings.id (no FK — see above)
            $table->string('mode');                           // RegistrationMode: Regular | Audit
            $table->timestamps();

            $table->index('course_offering_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_request_lines');
    }
};
