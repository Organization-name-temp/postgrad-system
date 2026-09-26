<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_policy_projections', function (Blueprint $table): void {
            $table->json('remaining_preparatory_targets')->nullable();
            $table->json('remaining_exact_targets')->nullable();
            $table->unsignedSmallInteger('remaining_elective_ch')->nullable();
            $table->unsignedSmallInteger('total_remaining_ch')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('registration_policy_projections', function (Blueprint $table): void {
            $table->dropColumn(['remaining_preparatory_targets', 'remaining_exact_targets', 'remaining_elective_ch', 'total_remaining_ch']);
        });
    }
};
