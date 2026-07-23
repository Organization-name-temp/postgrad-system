<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the long-deferred Research Type to a Course (the Phase 1 deferral — BR-25).
// Nullable: a Course may carry a Research Type (PBL / Seminar) or not, independent
// of its level. A level-700 Course without one simply never matches a Research
// Course slot at registration. The value is constrained to the ResearchType enum in
// the app layer, matching the existing `level` column's posture (ADR-0010).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('research_type')->nullable()->after('level');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('research_type');
        });
    }
};
