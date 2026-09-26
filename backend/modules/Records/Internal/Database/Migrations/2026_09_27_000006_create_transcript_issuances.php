<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_request_id')->constrained('transcript_requests');
            $table->char('token_hash', 64)->unique();
            $table->char('content_hash', 64);
            $table->unsignedBigInteger('actor_user_id');
            $table->timestampTz('created_at');
        });
        Schema::create('transcript_issuances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transcript_request_id')->unique()->constrained('transcript_requests');
            $table->unsignedBigInteger('student_id')->index();
            $table->uuid('reference')->unique();
            $table->char('verification_token', 64)->unique();
            $table->unsignedBigInteger('issuer_user_id');
            $table->string('issuer_name');
            $table->jsonb('snapshot');
            $table->timestampTz('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_issuances');
        Schema::dropIfExists('transcript_reviews');
    }
};
