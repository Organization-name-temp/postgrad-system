<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Courses — standalone teachable units. A Course is owned by a Department or is
// university-wide (nullable department_id; NULL = the shared Preparatory
// language/culture courses, which belong to no Department — ADR-0010). Its
// Lecture/Tutorial/Lab hours must sum to Total (FR-PCM-003.2, enforced in the app
// layer). A Course carries NO Category of its own — its Category is assigned per
// Program by a Curriculum Entry (ticket 05). Deactivated, never deleted (BR-18).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            // Nullable: NULL means university-wide. restrict, not cascade: the
            // catalog is deactivate-only (BR-18), so a Department is never
            // hard-deleted out from under its Courses.
            $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->unsignedSmallInteger('level'); // CourseLevel enum — constrained in the app layer.
            // Credit-hour breakdown; lecture + tutorial + lab = total is an app-layer
            // invariant (ADR-0010). Small non-negative counts.
            $table->unsignedSmallInteger('lecture_ch');
            $table->unsignedSmallInteger('tutorial_ch');
            $table->unsignedSmallInteger('lab_ch');
            $table->unsignedSmallInteger('total_ch');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
