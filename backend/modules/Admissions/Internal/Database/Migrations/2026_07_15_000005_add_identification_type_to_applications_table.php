<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Identification Type (phase 2.5, ticket 06). The applicant now declares whether the
// identifier they entered is a National ID or a Passport, which selects the format it
// is validated against (IdentificationType enum). It joins the Step-1 personal-info
// snapshot, so like the rest of that snapshot it is nullable — a fresh Draft carries
// none of it, and it is filled through the wizard; completeness is enforced at submit
// by the controller, not by the schema.
//
// Phase 2 is not amended: this is a new column on the existing `applications` table.
// `nationality` and `phone` keep their existing string columns — their *contents* now
// hold an ISO 3166-1 alpha-2 code and an E.164 number respectively, a validation
// change (app layer), not a schema one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // IdentificationType enum {national_id, passport}. Placed next to the
            // identifier it governs; the (national_id, admission_cycle_id) uniqueness
            // index from the Step-1 snapshot migration is unaffected.
            $table->string('id_type')->nullable()->after('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('id_type');
        });
    }
};
