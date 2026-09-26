<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculum_entries', function (Blueprint $table) {
            $table->string('preparatory_requirement_role')->nullable()->after('category');
        });

        // These four university-wide requirements are institutionally fixed. The
        // departmental pair varies by Program and is reconciled explicitly by the
        // CatalogSeeder or an administrator; migration code must not guess its
        // semantics from a mutable Course name or ordinal.
        foreach ([
            'PRE401' => 'EnglishLanguage',
            'PRE402' => 'ArabicLanguage',
            'PRE403' => 'JapaneseLanguage',
            'PRE404' => 'JapaneseCulture',
        ] as $courseCode => $role) {
            DB::table('curriculum_entries')
                ->where('category', 'Preparatory')
                ->whereIn('course_id', DB::table('courses')->select('id')->where('code', $courseCode))
                ->update(['preparatory_requirement_role' => $role]);
        }

        Schema::table('curriculum_entries', function (Blueprint $table) {
            $table->unique(
                ['program_id', 'preparatory_requirement_role'],
                'curriculum_entries_program_prep_role_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_entries', function (Blueprint $table) {
            $table->dropUnique('curriculum_entries_program_prep_role_unique');
            $table->dropColumn('preparatory_requirement_role');
        });
    }
};
