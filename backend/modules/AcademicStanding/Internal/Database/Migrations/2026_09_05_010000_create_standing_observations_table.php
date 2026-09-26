<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_observations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->index();
            $table->string('standing');
            $table->string('evidence_fingerprint', 64);
            $table->string('cause_type')->nullable();
            $table->unsignedBigInteger('cause_id')->nullable();
            $table->timestampTz('observed_at');
            $table->unique(['student_id', 'evidence_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_observations');
    }
};
