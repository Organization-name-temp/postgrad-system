<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Offering meetings — a Course Offering's weekly meeting times (ADR-0017, ticket 03):
// one row per (weekday + start/end), one or more per Offering. Class Sessions (ticket
// 08) are generated from these rows across the Semester's dates. They belong to their
// Offering with a real foreign key: this is an *intra-module* relationship (both
// tables are Enrollment-owned), so the schema-level seam that bars cross-module FKs
// (ADR-0012) does not apply — deleting an Offering cascades its meetings.
//
// An Offering's own meeting rows must not overlap each other (a course cannot be
// scheduled against itself); that invariant is enforced in the app layer through the
// interval-overlap support seam, not the schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offering_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')
                ->constrained('course_offerings')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // Weekday enum 0 (Sunday) .. 6 (Saturday)
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offering_meetings');
    }
};
