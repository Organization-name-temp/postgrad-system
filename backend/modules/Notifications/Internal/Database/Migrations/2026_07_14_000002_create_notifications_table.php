<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// In-app notifications (ticket 02). Append-only in spirit — created_at only, no
// updated_at — except that read_at flips once when the recipient reads it; the
// content is a type + JSON payload, never pre-rendered text (the SPA renders it
// bilingually). A notification belongs to exactly one User (opaque id, but the
// DB still enforces the reference); deleting the User removes their notifications.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // NotificationType enum — constrained in the app layer.
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // The two access patterns: a user's list (newest-first) and their
            // unread count.
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
