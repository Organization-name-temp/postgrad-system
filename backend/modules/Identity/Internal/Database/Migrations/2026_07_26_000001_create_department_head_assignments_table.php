<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Department-Head↔Department routing (phase 4, ticket 02). Identity owns which Head
// (an R06 user) reviews which Department's signed Grade Sheets; Grading reads it
// through the Identity\Public DepartmentHeadAssignments facade to route a Sheet and
// to scope the Head's review queue.
//
// `department_id` is an opaque Catalog Department reference with NO foreign key
// (ADR-0012), exactly as `faculty_dean_assignments.faculty_id` is — and it is
// **nullable**, which is the whole point of the table's shape. NULL is not "not yet
// assigned": it is the **university-wide Preparatory scope**, mirroring
// `Course.department_id` (ADR-0010), where NULL means a Course shared across the
// university rather than a Course missing a Department. A Sheet for a NULL-department
// Course therefore matches the NULL assignment through the same equality every other
// Sheet uses, with no special case (ADR-0023).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_head_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->timestamps();

            // The routing lookup: "who reviews this Department?" — including the
            // university-wide scope, where the column is NULL.
            $table->index('department_id');
        });

        // One row per (Head, scope). A plain UNIQUE would not do: SQL's default is that
        // NULLs are distinct from each other, so a Head could be given the university-wide
        // scope any number of times and the duplicates would sit invisibly in every
        // routing read. `NULLS NOT DISTINCT` (PostgreSQL 15+; this build runs 17) makes
        // NULL behave like the value it actually is here, so the one constraint covers a
        // Department and the university-wide scope alike — the same absence of a special
        // case the nullable column buys everywhere else.
        //
        // It is load-bearing, not decorative: the controller's read-then-write duplicate
        // check is not atomic, so two concurrent identical POSTs both pass it. This is
        // what stops the second row; the controller catches the violation and answers
        // with the same 422.
        DB::statement(
            'ALTER TABLE department_head_assignments
             ADD CONSTRAINT department_head_assignments_user_id_department_id_unique
             UNIQUE NULLS NOT DISTINCT (user_id, department_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('department_head_assignments');
    }
};
