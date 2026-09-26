<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_exemption_evidence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique();
            $table->unsignedBigInteger('admission_application_id')->unique();
            $table->jsonb('evidence');
            $table->timestamp('captured_at');
            $table->timestamp('reconciled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_exemption_evidence');
    }
};
