<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The Advisor's decision, recorded on a Registration Request and its lines (ticket 06).
// A decision is line-item: each line is approved or rejected, the request carries an
// optional advisor note and who decided it when, and — on approval — one Enrollment is
// minted per approved line in the same transaction (the ADR-0013 accept→mint precedent).
//
// decided_by is an opaque Identity user id (no FK — ADR-0012). The per-line `decision`
// is null while the request is Pending and set to the outcome the moment it is decided,
// so the Student's request view shows exactly which lines the Advisor kept or trimmed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->text('advisor_note')->nullable()->after('status');
            $table->timestamp('decided_at')->nullable()->after('advisor_note');
            $table->unsignedBigInteger('decided_by')->nullable()->after('decided_at'); // → Identity users.id (no FK)
        });

        Schema::table('registration_request_lines', function (Blueprint $table) {
            $table->string('decision')->nullable()->after('mode'); // 'approved' | 'rejected' | null while Pending
        });
    }

    public function down(): void
    {
        Schema::table('registration_request_lines', function (Blueprint $table) {
            $table->dropColumn('decision');
        });

        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn(['advisor_note', 'decided_at', 'decided_by']);
        });
    }
};
