<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcript_requests', function (Blueprint $table): void {
            $table->string('cancellation_prior_status')->nullable();
        });
        Schema::create('transcript_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_request_id')->unique()->constrained('transcript_requests')->restrictOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->timestamp('created_at');
            $table->string('reference', 200)->nullable();
            $table->string('stored_path')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        DB::statement('ALTER TABLE transcript_refunds ADD CONSTRAINT transcript_refund_positive CHECK (amount_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_refunds');
        Schema::table('transcript_requests', function (Blueprint $table): void {
            $table->dropColumn('cancellation_prior_status');
        });
    }
};
