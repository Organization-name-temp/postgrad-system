<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Application documents (ticket 05) — the certificates and files an applicant
// uploads onto their Application. The bytes live on the private `local` disk under
// an opaque name (never `public`, ADR-0005); this table records only the metadata
// needed to serve, list, and enforce rules on them.
//
// The row's public identity is the `ulid`, not its integer id: the ULID (not a
// filename or row id) is what appears in the streaming-download URL, so a document
// is unguessable and its storage layout stays private. `document_type` is a
// DocumentType value whose per-type rules (size/format/cardinality) are enforced in
// the app layer, not the schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('document_type');            // DocumentType — constrained in the app layer.
            $table->ulid('ulid')->unique();             // Opaque public identifier (URL + resource id).
            $table->string('original_filename');
            $table->string('stored_path');              // Path on the private `local` disk.
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamp('uploaded_at');
            $table->timestamps();

            // The upload endpoint reads "how many of this type does the application
            // already hold?" to enforce the cardinality cap.
            $table->index(['application_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
