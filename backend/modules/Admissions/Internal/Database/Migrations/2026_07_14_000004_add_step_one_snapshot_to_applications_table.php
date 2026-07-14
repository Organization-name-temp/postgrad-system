<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Step-1 personal-info snapshot (ticket 04). The wizard fills these onto the Draft
// created at registration (ticket 03) via per-step "save & continue", so every
// column is nullable: a fresh Draft carries none of them, and they accrete as the
// applicant progresses. Completeness is enforced at *submit* (the controller
// re-validates the whole snapshot), not by the schema.
//
// The snapshot is per-cycle, not a shared profile: re-applying in a later Cycle
// captures fresh data on a new Application. `(national_id, admission_cycle_id)` is
// unique so the same person cannot hold two Applications in one intake (BR-03) —
// the app layer validates it first (→ 422); this index is the backstop. national_id
// is nullable, and Postgres treats NULLs as distinct, so many in-progress Drafts
// with no national_id yet coexist freely.
//
// `applying_for_degree` is a Catalog DegreeLevel value (MSc/PhD) but is stored as a
// plain validated string here: the Admissions → Catalog\Public seam is not opened
// until program selection (ticket 06, ADR-0012), which will bind it to the enum and
// cross-check it against the chosen Program's degree level.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Identity & contact
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('gender')->nullable();               // Gender enum
            $table->string('address_country')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_street')->nullable();

            // Academic background
            $table->string('previous_university')->nullable();
            $table->string('graduation_certificate_name')->nullable();
            $table->date('graduation_date')->nullable();
            $table->string('previous_degree')->nullable();      // PreviousDegree enum
            $table->string('previous_major')->nullable();
            $table->decimal('graduation_gpa', 5, 2)->nullable();
            $table->decimal('graduation_cgpa', 5, 2)->nullable();
            $table->string('gpa_scale')->nullable();            // GpaScale enum
            $table->boolean('is_ejust_graduate')->nullable();
            $table->string('applying_for_degree')->nullable();  // Catalog DegreeLevel (MSc/PhD) — bound to the enum in ticket 06

            // English proficiency & language
            $table->string('english_test')->nullable();         // EnglishTest enum
            $table->decimal('english_score', 5, 2)->nullable(); // null when english_test = None
            $table->boolean('native_arabic_speaker')->nullable();

            // When the applicant submitted Step 1 (Draft → Submitted / EmailVerified).
            $table->timestamp('submitted_at')->nullable();

            // One person per intake. Validated in the app layer for a 422; this is
            // the database backstop.
            $table->unique(['national_id', 'admission_cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['national_id', 'admission_cycle_id']);

            $table->dropColumn([
                'name_en',
                'name_ar',
                'national_id',
                'phone',
                'date_of_birth',
                'nationality',
                'gender',
                'address_country',
                'address_city',
                'address_street',
                'previous_university',
                'graduation_certificate_name',
                'graduation_date',
                'previous_degree',
                'previous_major',
                'graduation_gpa',
                'graduation_cgpa',
                'gpa_scale',
                'is_ejust_graduate',
                'applying_for_degree',
                'english_test',
                'english_score',
                'native_arabic_speaker',
                'submitted_at',
            ]);
        });
    }
};
