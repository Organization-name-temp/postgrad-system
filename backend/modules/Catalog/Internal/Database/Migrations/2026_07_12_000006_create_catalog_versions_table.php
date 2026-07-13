<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catalog version history — an append-only snapshot table owned by Catalog
// (ADR-0011). Every create/edit/activate/deactivate of a Program or Course (a
// Curriculum edit is a Program change) writes one row with an incrementing
// version_no per subject; `snapshot` is the full aggregate as JSON. This is domain
// history ("what did version N look like"), deliberately DISTINCT from the Identity
// Audit Log (the security "who did what" trail) — it never flows through
// AuditLogger. Read-only: nothing updates or deletes rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // VersionSubject enum: program | course
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('version_no');
            $table->jsonb('snapshot');
            $table->string('change_note')->nullable();
            // Who made the change; mirrors the Audit Log's actor FK. Nullable +
            // nullOnDelete so history survives a user record being removed.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();

            // One version_no per subject; also the natural lookup index for a
            // subject's history.
            $table->unique(['subject_type', 'subject_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_versions');
    }
};
