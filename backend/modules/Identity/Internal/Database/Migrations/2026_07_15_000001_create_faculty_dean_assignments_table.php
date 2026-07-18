<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dean↔Faculty routing (ticket 08). Identity owns which Dean (an R07 user)
// reviews which Faculty's applications; Admissions reads it through the
// Identity\Public DeanAssignments facade to scope the Dean queue.
//
// `faculty_id` is an opaque Catalog Faculty reference with NO foreign key
// (ADR-0012): the module seam holds at the database, so Catalog can evolve its
// own tables without this one dangling a constraint. One row per (Dean, Faculty)
// pair — a Dean may cover several Faculties and a Faculty may have several Deans.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_dean_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('faculty_id');
            $table->timestamps();
            $table->unique(['user_id', 'faculty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_dean_assignments');
    }
};
