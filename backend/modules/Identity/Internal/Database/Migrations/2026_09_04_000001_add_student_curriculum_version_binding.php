<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Opaque Catalog references: deliberately no cross-module foreign key.
            $table->unsignedBigInteger('curriculum_version_id')->nullable()->after('program_id');
            $table->string('curriculum_version_binding_source')->nullable()->after('curriculum_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['curriculum_version_id', 'curriculum_version_binding_source']);
        });
    }
};
