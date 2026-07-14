<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Program selection + finalize (ticket 06). The applicant picks one primary and an
// optional alternative Program, then finalizes a complete application into Student
// Affairs review.
//
// The two program references are Catalog Program ids stored as PLAIN INTEGERS WITH
// NO FOREIGN KEY to `programs` (ADR-0012): the Admissions ↔ Catalog seam is held in
// code (deptrac) and read only through the Catalog\Public ProgramCatalog facade, so
// binding the two modules' tables with a database constraint is exactly what we
// avoid. Both are nullable — a Draft has neither until the applicant reaches the
// program step; the controller validates each id exists via the facade (→ 422).
//
// `finalized_at` is stamped by the finalize action (EmailVerified → UnderSAReview);
// null until then.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Opaque Catalog Program refs — NO ->constrained(); the seam is code-level.
            $table->unsignedBigInteger('primary_program_id')->nullable();
            $table->unsignedBigInteger('alternative_program_id')->nullable();

            $table->timestamp('finalized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'primary_program_id',
                'alternative_program_id',
                'finalized_at',
            ]);
        });
    }
};
