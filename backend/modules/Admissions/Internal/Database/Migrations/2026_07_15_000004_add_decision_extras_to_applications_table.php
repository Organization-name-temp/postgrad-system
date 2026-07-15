<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Final-decision extras (ticket 10). The Dean's post-interview decision can hold an
// application short of a terminal outcome: `conditions` records the free-text terms of
// a Conditionally Accepted offer, and `waitlist_position` the place of a Waitlisted
// applicant. Both are null for the Accepted/Rejected outcomes (and until a decision is
// made); each is set by its guarded action method on the Application and later cleared
// by the Dean's resolution to Accepted/Rejected. No rules engine — `conditions` is
// free text (spec §Out of Scope).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('conditions')->nullable();
            $table->unsignedSmallInteger('waitlist_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['conditions', 'waitlist_position']);
        });
    }
};
