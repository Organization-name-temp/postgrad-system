<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The per-(year, program) counter behind the University ID's trailing sequence
// (ticket 10). One row per (intake year, program) holds the next number to hand out;
// UniversityIdGenerator locks the row (SELECT … FOR UPDATE) inside the acceptance
// transaction, so two concurrent admissions into the same year+program can never mint
// the same {NNNN}. Identity-owned, like the Student anchor it numbers.
//
// `program_id` is the same opaque Catalog reference (NO foreign key, ADR-0012) the
// Student and Application carry. The UNIQUE(year, program_id) index is the backstop
// that keeps exactly one counter per bucket even under a first-row insert race.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('program_id'); // opaque Catalog ref — no FK.
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['year', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_number_sequences');
    }
};
