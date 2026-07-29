<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The two-party signature loop (ADR-0023, phase 4 ticket 03): the Instructor who signed a
// round and when, the Department Head who reviewed it and when, and the note that travels
// back with a Return. All four columns are opaque Identity user references with no foreign
// key (ADR-0012) and all nullable — a Draft round has none of them yet, and `reviewed_*` are
// overwritten on every Submitted -> {Finalized, Returned} transition rather than accumulated,
// since a round carries one live review outcome at a time.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('signed_by')->nullable()->after('status');
            $table->timestamp('signed_at')->nullable()->after('signed_by');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('signed_at');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('grade_sheets', function (Blueprint $table) {
            $table->dropColumn(['signed_by', 'signed_at', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
