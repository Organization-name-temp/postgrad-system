<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_revocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issuance_id')->unique()->constrained('transcript_issuances');
            $table->text('reason');
            $table->unsignedBigInteger('actor_user_id');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_revocations');
    }
};
