<?php

namespace Modules\Calendar\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Internal\Http\Resources\SemesterResource;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Internal\Support\SemesterCalendar;
use Modules\Calendar\Public\Enums\Term;
use Modules\Calendar\Public\Events\SemesterChanged;

/**
 * @group Calendar
 *
 * Manage Semesters — the academic term (Fall/Spring + year) the university runs
 * (ADR-0018, FR-CAL-001), the single authority every later Phase-3/4 record reads
 * from. Creating and editing require the calendar.manage permission (Student
 * Affairs / Dean / SysAdmin); reading is open to any authenticated user who needs
 * the calendar. A Semester's period state (registration open, running, …) is
 * derived from its dates, never a stored flag; the period dates — the five
 * registration windows plus the grade-submission deadline that closes them (phase 4
 * ticket 09) — must be ordered (ADR-0018) and (term, year) is unique. Semesters are
 * edited in place, never hard-deleted (no delete verb → 405).
 */
class SemesterController
{
    /**
     * List Semesters.
     *
     * Newest term first. Available to any authenticated user who needs the calendar.
     *
     * @response 200 {"data": [{"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-30T00:00:00+00:00", "grade_submission_deadline_at": "2027-01-10T00:00:00+00:00", "is_registration_open": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return SemesterResource::collection(
            Semester::query()->orderByDesc('year')->orderBy('term')->get(),
        );
    }

    /**
     * Show a Semester.
     *
     * @urlParam semester_id integer required The ID of the semester. Example: 1
     *
     * @response 200 {"data": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-30T00:00:00+00:00", "grade_submission_deadline_at": "2027-01-10T00:00:00+00:00", "is_registration_open": true}}
     */
    public function show(Semester $semester): SemesterResource
    {
        return new SemesterResource($semester);
    }

    /**
     * Create a Semester.
     *
     * @bodyParam term string required Fall or Spring. Example: Fall
     * @bodyParam year integer required The academic year. Example: 2026
     * @bodyParam starts_at string required When the semester starts (ISO 8601). Example: 2026-09-01T00:00:00+00:00
     * @bodyParam ends_at string required When the semester ends (ISO 8601). Example: 2026-12-20T00:00:00+00:00
     * @bodyParam registration_opens_at string required When registration opens (ISO 8601). Example: 2026-08-01T00:00:00+00:00
     * @bodyParam registration_closes_at string required When registration closes (ISO 8601). Example: 2026-09-05T00:00:00+00:00
     * @bodyParam add_drop_ends_at string required End of the free add/drop period (ISO 8601). Example: 2026-09-14T00:00:00+00:00
     * @bodyParam late_add_ends_at string required End of the late-add period (ISO 8601). Example: 2026-09-21T00:00:00+00:00
     * @bodyParam withdrawal_deadline_at string required The withdrawal deadline (ISO 8601). Example: 2026-11-30T00:00:00+00:00
     * @bodyParam grade_submission_deadline_at string required When instructors must have submitted grades — on or after the semester ends (ISO 8601). Example: 2027-01-10T00:00:00+00:00
     *
     * @response 201 {"data": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-30T00:00:00+00:00", "grade_submission_deadline_at": "2027-01-10T00:00:00+00:00", "is_registration_open": true}}
     * @response 422 {"message": "The term has already been taken.", "errors": {"term": ["The term has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $this->assertOrdering($validated);

        $semester = Semester::create($validated);

        return (new SemesterResource($semester))->response()->setStatusCode(201);
    }

    /**
     * Update a Semester.
     *
     * Edits the term, year, and period dates in place — the correction path that
     * never orphans records (ADR-0018). Same invariants as creation.
     *
     * @urlParam semester_id integer required The ID of the semester. Example: 1
     *
     * @bodyParam term string required Fall or Spring. Example: Fall
     * @bodyParam year integer required The academic year. Example: 2026
     * @bodyParam starts_at string required When the semester starts (ISO 8601). Example: 2026-09-01T00:00:00+00:00
     * @bodyParam ends_at string required When the semester ends (ISO 8601). Example: 2026-12-20T00:00:00+00:00
     * @bodyParam registration_opens_at string required When registration opens (ISO 8601). Example: 2026-08-01T00:00:00+00:00
     * @bodyParam registration_closes_at string required When registration closes (ISO 8601). Example: 2026-09-05T00:00:00+00:00
     * @bodyParam add_drop_ends_at string required End of the free add/drop period (ISO 8601). Example: 2026-09-14T00:00:00+00:00
     * @bodyParam late_add_ends_at string required End of the late-add period (ISO 8601). Example: 2026-09-21T00:00:00+00:00
     * @bodyParam withdrawal_deadline_at string required The withdrawal deadline (ISO 8601). Example: 2026-11-30T00:00:00+00:00
     * @bodyParam grade_submission_deadline_at string required When instructors must have submitted grades — on or after the semester ends (ISO 8601). Example: 2027-01-10T00:00:00+00:00
     *
     * @response 200 {"data": {"id": 1, "term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "starts_at": "2026-09-01T00:00:00+00:00", "ends_at": "2026-12-20T00:00:00+00:00", "registration_opens_at": "2026-08-01T00:00:00+00:00", "registration_closes_at": "2026-09-05T00:00:00+00:00", "add_drop_ends_at": "2026-09-14T00:00:00+00:00", "late_add_ends_at": "2026-09-21T00:00:00+00:00", "withdrawal_deadline_at": "2026-11-30T00:00:00+00:00", "grade_submission_deadline_at": "2027-01-10T00:00:00+00:00", "is_registration_open": true}}
     */
    public function update(Request $request, Semester $semester): SemesterResource
    {
        $validated = $request->validate($this->rules($request, $semester));
        $this->assertOrdering($validated);

        $semester->update($validated);
        event(new SemesterChanged((int) $semester->id));

        return new SemesterResource($semester);
    }

    /**
     * Base validation rules. (term, year) is unique — a term names at most one
     * Semester per year; on update the check ignores the Semester being edited. The
     * cross-field ordering invariant is checked separately in {@see self::assertOrdering()}.
     *
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?Semester $semester = null): array
    {
        return [
            'term' => [
                'required',
                Rule::enum(Term::class),
                Rule::unique('semesters', 'term')
                    ->where('year', $request->integer('year'))
                    ->ignore($semester?->getKey()),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date'],
            'registration_opens_at' => ['required', 'date'],
            'registration_closes_at' => ['required', 'date'],
            'add_drop_ends_at' => ['required', 'date'],
            'late_add_ends_at' => ['required', 'date'],
            'withdrawal_deadline_at' => ['required', 'date'],
            'grade_submission_deadline_at' => ['required', 'date'],
        ];
    }

    /**
     * Enforce the cross-field ordering invariant through the one pure authority
     * ({@see SemesterCalendar}), raising a 422 keyed per offending field so the SPA
     * mirror and the form both read naturally (ADR-0018). Each violation the calendar
     * reports is already a `calendar.php` translation key, so the message an administrator
     * reads is bilingual without the rule living in two places.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertOrdering(array $validated): void
    {
        $calendar = new SemesterCalendar(
            startsAt: Carbon::parse($validated['starts_at']),
            endsAt: Carbon::parse($validated['ends_at']),
            registrationOpensAt: Carbon::parse($validated['registration_opens_at']),
            registrationClosesAt: Carbon::parse($validated['registration_closes_at']),
            addDropEndsAt: Carbon::parse($validated['add_drop_ends_at']),
            lateAddEndsAt: Carbon::parse($validated['late_add_ends_at']),
            withdrawalDeadlineAt: Carbon::parse($validated['withdrawal_deadline_at']),
            gradeSubmissionDeadlineAt: Carbon::parse($validated['grade_submission_deadline_at']),
        );

        $violations = $calendar->orderingViolations();

        if ($violations === []) {
            return;
        }

        $messages = [];
        foreach ($violations as $field => $reasonKey) {
            $messages[$field] = __('calendar.'.$reasonKey);
        }

        throw ValidationException::withMessages($messages);
    }
}
