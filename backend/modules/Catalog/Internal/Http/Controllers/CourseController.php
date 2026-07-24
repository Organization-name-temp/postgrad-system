<?php

namespace Modules\Catalog\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Http\Resources\CourseResource;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Support\CreditHours;
use Modules\Catalog\Internal\Versioning\CatalogVersionRecorder;
use Modules\Catalog\Public\Enums\CourseLevel;

/**
 * @group Catalog
 *
 * Manage Courses — standalone teachable units. Reading requires the catalog.view
 * permission; creating/editing/activating requires catalog.manage (Department
 * Head, Dean, or an administrator). A Course is owned by a Department or is
 * university-wide (nullable department; NULL = the shared Preparatory courses).
 * Its Lecture + Tutorial + Lab hours must equal its Total (FR-PCM-003.2), the code
 * is unique, and both language names are required. Courses are deactivated, never
 * deleted, so historical academic records are never orphaned (FR-PCM-003.3;
 * ADR-0010). A Course carries no Category of its own — Category is assigned per
 * Program (ticket 05).
 */
class CourseController
{
    public function __construct(private readonly CatalogVersionRecorder $versions) {}

    /**
     * List Courses.
     *
     * @response 200 {"data": [{"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return CourseResource::collection(Course::query()->orderBy('code')->get());
    }

    /**
     * Create a Course.
     *
     * Omit `department_id` (or send null) to mark the Course university-wide — the
     * shape used for the shared Preparatory language/culture courses.
     *
     * @bodyParam department_id integer The owning Department id; omit for a university-wide Course. Example: 1
     * @bodyParam code string required Unique short code for the Course. Example: DEP501
     * @bodyParam name_en string required English name. Example: Advanced Algorithms
     * @bodyParam name_ar string required Arabic name. Example: خوارزميات متقدمة
     * @bodyParam level integer required One of 400, 500, 600, 700. Example: 500
     * @bodyParam research_type string The Research Type for a research course — one of PBL, Seminar; omit for a non-research course. Example: PBL
     * @bodyParam lecture_ch integer required Lecture credit hours. Example: 3
     * @bodyParam tutorial_ch integer required Tutorial credit hours. Example: 0
     * @bodyParam lab_ch integer required Lab credit hours. Example: 0
     * @bodyParam total_ch integer required Total credit hours; must equal lecture + tutorial + lab. Example: 3
     *
     * @response 201 {"data": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}}
     * @response 422 {"message": "The lecture, tutorial and lab hours must add up to the total.", "errors": {"total_ch": ["The lecture, tutorial and lab hours must add up to the total."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $course = Course::create($validated);
        $this->versions->recordCourse($course, 'created');

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    /**
     * Show a Course.
     *
     * Returns the Course's full detail plus its `placements` — which Programs and
     * Categories it appears in (via its Curriculum Entries) — for the detail view's
     * "appears in" section (FR-PCM-002). Version history is a separate endpoint.
     *
     * @urlParam course_id integer required The ID of the course. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true, "placements": [{"category": "ProgramCore", "program": {"id": 1, "code": "MSC-CE", "name_en": "MSc in Computer Engineering", "name_ar": "ماجستير في هندسة الحاسوب"}}]}}
     */
    public function show(Course $course): CourseResource
    {
        // Eager-load the placements' Programs so CourseResource can emit `placements`
        // (an empty array when the Course sits in no Program).
        $course->load('curriculumEntries.program');

        return new CourseResource($course);
    }

    /**
     * Update a Course.
     *
     * @urlParam course_id integer required The ID of the course. Example: 1
     *
     * @bodyParam department_id integer The owning Department id; omit for a university-wide Course. Example: 1
     * @bodyParam code string required Unique short code for the Course. Example: DEP501
     * @bodyParam name_en string required English name. Example: Advanced Algorithms
     * @bodyParam name_ar string required Arabic name. Example: خوارزميات متقدمة
     * @bodyParam level integer required One of 400, 500, 600, 700. Example: 500
     * @bodyParam research_type string The Research Type for a research course — one of PBL, Seminar; omit for a non-research course. Example: PBL
     * @bodyParam lecture_ch integer required Lecture credit hours. Example: 3
     * @bodyParam tutorial_ch integer required Tutorial credit hours. Example: 0
     * @bodyParam lab_ch integer required Lab credit hours. Example: 0
     * @bodyParam total_ch integer required Total credit hours; must equal lecture + tutorial + lab. Example: 3
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}}
     */
    public function update(Request $request, Course $course): CourseResource
    {
        $validated = $this->validated($request, $course);

        $course->update($validated);
        $this->versions->recordCourse($course, 'updated');

        return new CourseResource($course);
    }

    /**
     * Activate a Course.
     *
     * @urlParam course_id integer required The ID of the course. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": true}}
     */
    public function activate(Course $course): CourseResource
    {
        $course->update(['is_active' => true]);
        $this->versions->recordCourse($course, 'activated');

        return new CourseResource($course);
    }

    /**
     * Deactivate a Course.
     *
     * Deactivating retires a Course without deleting it — it stays readable and its
     * historical academic records are unaffected (BR-18, FR-PCM-003.3).
     *
     * @urlParam course_id integer required The ID of the course. Example: 1
     *
     * @response 200 {"data": {"id": 1, "department_id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "research_type": "PBL", "lecture_ch": 3, "tutorial_ch": 0, "lab_ch": 0, "total_ch": 3, "is_active": false}}
     */
    public function deactivate(Course $course): CourseResource
    {
        $course->update(['is_active' => false]);
        $this->versions->recordCourse($course, 'deactivated');

        return new CourseResource($course);
    }

    /**
     * Validate a create/edit payload, including the credit-hour breakdown invariant
     * (lecture + tutorial + lab = total), and return the validated attributes.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Course $course = null): array
    {
        $validator = Validator::make($request->all(), $this->rules($course));

        $validator->after(function (ValidatorInstance $validator) use ($request): void {
            // Only check the sum once each component has passed its own rules;
            // otherwise a missing/non-integer field would raise a confusing
            // "doesn't add up" error on top of its own.
            $components = ['lecture_ch', 'tutorial_ch', 'lab_ch', 'total_ch'];
            foreach ($components as $field) {
                if ($validator->errors()->has($field)) {
                    return;
                }
            }

            $breakdown = new CreditHours(
                lecture: $request->integer('lecture_ch'),
                tutorial: $request->integer('tutorial_ch'),
                lab: $request->integer('lab_ch'),
                total: $request->integer('total_ch'),
            );

            if (! $breakdown->isBalanced()) {
                $validator->errors()->add('total_ch', __('catalog.credit_hours_unbalanced'));
            }
        });

        $validated = $validator->validate();

        // Every required field aside, a create/edit is a full replacement of the
        // Course. department_id and research_type are the nullable fields, and
        // Laravel drops an absent nullable key from the validated set — so pin each
        // to null when omitted, which also clears it on edit (department → university
        // -wide, research_type → not a research course) rather than silently keeping
        // the old value (ADR-0010).
        $validated['department_id'] ??= null;
        $validated['research_type'] ??= null;

        return $validated;
    }

    /**
     * Validation rules for creating/editing a Course. On update, the code's
     * uniqueness check ignores the Course being edited. `department_id` is nullable
     * — a Course may be university-wide (ADR-0010). The lecture/tutorial/lab = total
     * invariant is checked separately in {@see self::validated()}.
     *
     * @return array<string, mixed>
     */
    private function rules(?Course $course = null): array
    {
        return [
            // Nullable: a university-wide Course has no Department. When present it
            // must reference a real one.
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course?->getKey())],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            // Optional: only a research course carries one, and it is not coupled to
            // level here — the Phase 3 registration classifier simply won't match a
            // level-700 research slot without it (BR-25, the Phase 1 deferral).
            'research_type' => ['nullable', Rule::enum(ResearchType::class)],
            // Credit hours are small non-negative integers; max:255 is a defensive
            // sanity bound that keeps an absurd input a 422 rather than a DB
            // overflow 500 (well within the unsignedSmallInteger column).
            'lecture_ch' => ['required', 'integer', 'min:0', 'max:255'],
            'tutorial_ch' => ['required', 'integer', 'min:0', 'max:255'],
            'lab_ch' => ['required', 'integer', 'min:0', 'max:255'],
            'total_ch' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }
}
