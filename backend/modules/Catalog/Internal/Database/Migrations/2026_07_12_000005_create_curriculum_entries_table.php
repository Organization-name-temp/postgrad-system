<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Curriculum Entries — the placement of one Course into one Program under one
// Category (ADR-0010, CONTEXT.md). Category lives here, never on the Course, so the
// same Course can appear in several Programs under different Categories (a shared
// Preparatory course, or one Program's Core offered as another's Research
// Elective). Unlike the catalog entities, an Entry is a join record that is added
// and removed outright — its history is captured in the Program's version snapshot
// (ADR-0011), so it needs no soft-delete.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_entries', function (Blueprint $table) {
            $table->id();
            // restrict, not cascade: the catalog is deactivate-only, so neither the
            // owning Program nor the linked Course is hard-deleted out from under an
            // Entry. Removing the Entry itself is the explicit "remove from
            // curriculum" action.
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('category'); // CourseCategory enum — constrained in the app layer.
            $table->unsignedSmallInteger('ordinal')->default(0);
            $table->timestamps();
            // A Course sits at most once in a given Program+Category; it may still
            // appear under a different Category or in a different Program.
            $table->unique(['program_id', 'course_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_entries');
    }
};
