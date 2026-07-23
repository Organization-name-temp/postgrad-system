<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Course Offerings — one Catalog Course taught in one Calendar Semester (ADR-0017,
// ticket 03): "what actually runs this semester." It carries the assigned Instructor
// (an Identity user), an optional seat capacity, and — in the child offering_meetings
// table — its weekly meeting times. The Enrollment record a Phase-4 grade attaches to
// is born from this row.
//
// semester_id, course_id and instructor_id are opaque references into other modules
// (Calendar, Catalog, Identity) with NO database foreign key — the modular-monolith
// seam holds at the schema layer too (ADR-0012); each is validated in the app layer
// through that module's Public facade. (semester, course) is unique now — one Course
// runs at most once per term (relaxable to sections later per ADR-0017).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('semester_id');   // → Calendar semesters.id (no FK, ADR-0012)
            $table->unsignedBigInteger('course_id');      // → Catalog courses.id (no FK, ADR-0012)
            $table->unsignedBigInteger('instructor_id');  // → Identity users.id (no FK, ADR-0012)
            $table->unsignedInteger('capacity')->nullable(); // NULL = uncapped seats
            $table->timestamps();

            $table->unique(['semester_id', 'course_id']);
            $table->index('instructor_id'); // the instructor's "my offerings" lookup (Phase 4)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};
