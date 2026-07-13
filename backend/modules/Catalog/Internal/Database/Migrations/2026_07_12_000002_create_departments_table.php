<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Departments — an academic unit within a Faculty; owns Programs and Courses.
// Deactivated, never deleted (BR-18 pattern); carries both language names (ADR-0010).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            // restrict, not cascade: the catalog is deactivate-only (BR-18), so a
            // Faculty is never hard-deleted out from under its Departments.
            $table->foreignId('faculty_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
