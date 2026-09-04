<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('deduplication_key')->nullable();
            $table->unique(['user_id', 'type', 'deduplication_key'], 'notifications_deduplication_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_deduplication_unique');
            $table->dropColumn('deduplication_key');
        });
    }
};
