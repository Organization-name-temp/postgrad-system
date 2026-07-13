<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Programs — a degree offering (MSc/PhD) under a Department. Each Program stores
// its required credit hours per Course Category (Preparatory / Program Core /
// Research Elective), defaulting to the 6/6/9 bylaw split (BR-13…14, FR-PCM-002).
// The total is derived from the three, not stored, so it can never drift. A
// Program is deactivated, never deleted, and never cascades (BR-18; ADR-0010).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            // restrict, not cascade: the catalog is deactivate-only (BR-18), so a
            // Department is never hard-deleted out from under its Programs.
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('degree_level'); // DegreeLevel enum — constrained in the app layer.
            $table->unsignedSmallInteger('prep_required_ch')->default(6);
            $table->unsignedSmallInteger('core_required_ch')->default(6);
            $table->unsignedSmallInteger('elective_required_ch')->default(9);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
