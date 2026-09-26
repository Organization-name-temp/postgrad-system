<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcript_requests', function (Blueprint $table): void {
            $table->unsignedInteger('verified_minor')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
        });
        Schema::create('transcript_payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_request_id')->constrained('transcript_requests')->restrictOnDelete();
            $table->string('reference', 200);
            $table->string('stored_path');
            $table->string('mime');
            $table->unsignedBigInteger('submitted_by');
            $table->timestamp('created_at');
        });
        Schema::create('transcript_payment_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained('transcript_payment_submissions')->restrictOnDelete();
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->unsignedInteger('amount_minor')->nullable();
            $table->unsignedBigInteger('actor_user_id');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_payment_decisions');
        Schema::dropIfExists('transcript_payment_submissions');
        Schema::table('transcript_requests', function (Blueprint $table): void {
            $table->dropColumn(['verified_minor', 'verified_by', 'verified_at']);
        });
    }
};
