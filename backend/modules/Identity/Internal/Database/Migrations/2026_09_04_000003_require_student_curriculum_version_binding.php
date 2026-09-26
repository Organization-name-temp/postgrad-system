<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('curriculum_version_id')->nullable(false)->change();
            $table->string('curriculum_version_binding_source')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('curriculum_version_id')->nullable()->change();
            $table->string('curriculum_version_binding_source')->nullable()->change();
        });
    }
};
