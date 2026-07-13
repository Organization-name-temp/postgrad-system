<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Internal\Enums\CourseCategory;
use Modules\Catalog\Internal\Enums\CourseLevel;
use Modules\Catalog\Internal\Enums\DegreeLevel;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;

/**
 * Seeds a realistic, bilingual E-JUST catalog so the browse tree, Course detail,
 * and every later phase have credible data to develop and demo against (spec §Seeder,
 * FR-PCM-002). Faithful to the FR-PCM-002 example: three Faculties, with
 * FoE → Computer Engineering → MSc & PhD fully populated (Preparatory PRE401–404 +
 * DEP501/502, two Program Core, three Research Elective), the shared university-wide
 * Preparatory courses linked into every Program (ADR-0010), and the other Faculties
 * seeded lightly. Idempotent (keyed by code) so it is safe to re-run on the dev DB.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Three Faculties (FR-PCM-002). FoE is fully built; BAS and CSIT stay shallow.
        $foe = $this->faculty('FoE', 'Faculty of Engineering', 'كلية الهندسة');
        $bas = $this->faculty('BAS', 'Faculty of Basic & Applied Sciences', 'كلية العلوم الأساسية والتطبيقية');
        $csit = $this->faculty('CSIT', 'Faculty of Computer Science & Information Technology', 'كلية علوم الحاسب وتكنولوجيا المعلومات');

        // The shared university-wide Preparatory courses — owned by no Department, and
        // linked into every Program below (ADR-0010, the FR-PCM-002 example).
        $sharedPrep = [
            $this->course('PRE401', 'English Language', 'اللغة الإنجليزية', CourseLevel::Foundation, 1),
            $this->course('PRE402', 'Arabic Language', 'اللغة العربية', CourseLevel::Foundation, 1),
            $this->course('PRE403', 'Japanese Language', 'اللغة اليابانية', CourseLevel::Foundation, 1),
            $this->course('PRE404', 'Japanese Culture', 'الثقافة اليابانية', CourseLevel::Foundation, 1),
        ];

        $this->seedComputerEngineering($foe, $sharedPrep);

        // Lighter Faculties/Departments: one Program each, carrying only the shared
        // Preparatory courses (enough to show Faculty grouping + shared-course reuse).
        $this->preparatoryOnlyProgram(
            $this->department($foe, 'EE', 'Electrical Engineering', 'الهندسة الكهربية'),
            'MSC-EE', 'MSc in Electrical Engineering', 'ماجستير العلوم في الهندسة الكهربية', $sharedPrep,
        );
        $this->preparatoryOnlyProgram(
            $this->department($bas, 'MATH', 'Mathematics', 'الرياضيات'),
            'MSC-MATH', 'MSc in Applied Mathematics', 'ماجستير العلوم في الرياضيات التطبيقية', $sharedPrep,
        );
        $this->preparatoryOnlyProgram(
            $this->department($csit, 'IT', 'Information Technology', 'تكنولوجيا المعلومات'),
            'MSC-IT', 'MSc in Information Technology', 'ماجستير العلوم في تكنولوجيا المعلومات', $sharedPrep,
        );
    }

    /**
     * FoE → Computer Engineering → MSc & PhD, fully populated per FR-PCM-002.
     *
     * @param  list<Course>  $sharedPrep  the university-wide PRE401–404
     */
    private function seedComputerEngineering(Faculty $foe, array $sharedPrep): void
    {
        $ce = $this->department($foe, 'CE', 'Computer Engineering', 'هندسة الحاسوب');

        // The two departmental Preparatory requirements (owned by CE), joining the
        // shared university Preparatory courses in each Program's Preparatory section.
        $deptPrep = [
            $this->course('DEP501', 'Departmental Requirement I', 'متطلب قسمي (1)', CourseLevel::Master, 1, department: $ce),
            $this->course('DEP502', 'Departmental Requirement II', 'متطلب قسمي (2)', CourseLevel::Master, 1, department: $ce),
        ];

        // MSc — Core/Elective at level 500, the research course a 700-level PBL.
        $msc = $this->program($ce, 'MSC-CE', 'MSc in Computer Engineering', 'ماجستير العلوم في هندسة الحاسوب', DegreeLevel::MSc);
        $this->place($msc, CourseCategory::Preparatory, [...$sharedPrep, ...$deptPrep]);
        $this->place($msc, CourseCategory::ProgramCore, [
            $this->course('CSE511', 'Advanced Algorithms', 'خوارزميات متقدمة', CourseLevel::Master, 3, department: $ce),
            // A lecture + lab breakdown — architecture carries a lab component.
            $this->course('CSE512', 'Advanced Computer Architecture', 'معمارية الحاسوب المتقدمة', CourseLevel::Master, 2, 0, 1, department: $ce),
        ]);
        $this->place($msc, CourseCategory::ResearchElective, [
            $this->course('CSE531', 'Machine Learning', 'تعلّم الآلة', CourseLevel::Master, 3, department: $ce),
            $this->course('CSE532', 'Distributed Systems', 'الأنظمة الموزّعة', CourseLevel::Master, 3, department: $ce),
            $this->course('CSE571', 'Project-Based Learning', 'التعلّم القائم على المشاريع', CourseLevel::Advanced, 3, department: $ce),
        ]);

        // PhD — Core/Elective at level 600, the research course a 700-level Seminar.
        $phd = $this->program($ce, 'PHD-CE', 'PhD in Computer Engineering', 'دكتوراه الفلسفة في هندسة الحاسوب', DegreeLevel::PhD);
        $this->place($phd, CourseCategory::Preparatory, [...$sharedPrep, ...$deptPrep]);
        $this->place($phd, CourseCategory::ProgramCore, [
            $this->course('CSE611', 'Advanced Topics in Computing', 'موضوعات متقدمة في الحوسبة', CourseLevel::Doctoral, 3, department: $ce),
            $this->course('CSE612', 'Research Methodology', 'منهجية البحث العلمي', CourseLevel::Doctoral, 3, department: $ce),
        ]);
        $this->place($phd, CourseCategory::ResearchElective, [
            $this->course('CSE631', 'Deep Learning', 'التعلّم العميق', CourseLevel::Doctoral, 3, department: $ce),
            $this->course('CSE632', 'Cloud Computing', 'الحوسبة السحابية', CourseLevel::Doctoral, 3, department: $ce),
            $this->course('CSE671', 'Doctoral Seminar', 'الحلقة النقاشية للدكتوراه', CourseLevel::Advanced, 3, department: $ce),
        ]);
    }

    /** A shallow Program that carries only the shared Preparatory courses. */
    private function preparatoryOnlyProgram(Department $department, string $code, string $nameEn, string $nameAr, array $sharedPrep): void
    {
        $program = $this->program($department, $code, $nameEn, $nameAr, DegreeLevel::MSc);
        $this->place($program, CourseCategory::Preparatory, $sharedPrep);
    }

    private function faculty(string $code, string $nameEn, string $nameAr): Faculty
    {
        return Faculty::firstOrCreate(
            ['code' => $code],
            ['name_en' => $nameEn, 'name_ar' => $nameAr, 'is_active' => true],
        );
    }

    private function department(Faculty $faculty, string $code, string $nameEn, string $nameAr): Department
    {
        return Department::firstOrCreate(
            ['code' => $code],
            ['faculty_id' => $faculty->getKey(), 'name_en' => $nameEn, 'name_ar' => $nameAr, 'is_active' => true],
        );
    }

    private function program(Department $department, string $code, string $nameEn, string $nameAr, DegreeLevel $degreeLevel): Program
    {
        return Program::firstOrCreate(
            ['code' => $code],
            [
                'department_id' => $department->getKey(),
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'degree_level' => $degreeLevel,
                'prep_required_ch' => 6,
                'core_required_ch' => 6,
                'elective_required_ch' => 9,
                'is_active' => true,
            ],
        );
    }

    /**
     * A Course, keyed by its unique code. Credit hours default to a pure lecture
     * breakdown (tutorial/lab zero) so the L + T + Lab = Total invariant always holds;
     * omit $department for a university-wide Course (the shared Preparatory ones).
     */
    private function course(string $code, string $nameEn, string $nameAr, CourseLevel $level, int $lecture, int $tutorial = 0, int $lab = 0, ?Department $department = null): Course
    {
        return Course::firstOrCreate(
            ['code' => $code],
            [
                'department_id' => $department?->getKey(),
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'level' => $level,
                'lecture_ch' => $lecture,
                'tutorial_ch' => $tutorial,
                'lab_ch' => $lab,
                'total_ch' => $lecture + $tutorial + $lab,
                'is_active' => true,
            ],
        );
    }

    /**
     * Place the given Courses into a Program's Category as Curriculum Entries, in
     * order (ordinal 0…n). Keyed by (program, course, category) so re-running the
     * seeder never duplicates a placement (ADR-0010).
     *
     * @param  list<Course>  $courses
     */
    private function place(Program $program, CourseCategory $category, array $courses): void
    {
        foreach (array_values($courses) as $ordinal => $course) {
            CurriculumEntry::firstOrCreate(
                [
                    'program_id' => $program->getKey(),
                    'course_id' => $course->getKey(),
                    'category' => $category->value,
                ],
                ['ordinal' => $ordinal],
            );
        }
    }
}
