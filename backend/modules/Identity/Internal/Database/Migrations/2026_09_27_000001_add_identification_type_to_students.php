<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('identification_type')->nullable();
        });
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_identification_type_check CHECK (identification_type IN ('national_id', 'passport'))");
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('identification_type');
        });
    }
};
