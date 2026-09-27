<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_fee', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('fee_minor');
            $table->text('instructions_en');
            $table->text('instructions_ar');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE transcript_fee ADD CONSTRAINT transcript_fee_singleton CHECK (id = 1 AND fee_minor > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_fee');
    }
};
