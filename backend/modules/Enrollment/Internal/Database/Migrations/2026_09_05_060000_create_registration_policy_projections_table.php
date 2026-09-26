<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_policy_projections', function (Blueprint $table): void {
            $table->unsignedBigInteger('student_id')->primary();
            $table->unsignedTinyInteger('registration_ceiling_ch')->nullable();
            $table->string('standing', 40);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_policy_projections');
    }
};
