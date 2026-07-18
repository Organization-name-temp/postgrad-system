<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Admission Cycles — a single windowed intake (one Intake Term of one year, e.g.
// "Fall 2026") every Application hangs off (CONTEXT.md, ADR-0012). Whether a
// Cycle is currently *open* is derived in the app layer (is_active AND now falls
// inside the window), never a stored status column (ROADMAP §Phase 2, ticket 01).
// A Cycle is deactivated, never deleted. (intake_term, year) is unique — an
// intake term can name at most one Cycle per year.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('intake_term'); // IntakeTerm enum — constrained in the app layer.
            $table->unsignedSmallInteger('year');
            $table->string('name_en');
            $table->string('name_ar');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['intake_term', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_cycles');
    }
};
