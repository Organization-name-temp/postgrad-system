<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Http\Resources\AdmissionCycleResource;
use Modules\Admissions\Internal\Models\AdmissionCycle;

/**
 * @group Admissions
 *
 * Manage Admission Cycles — the windowed Fall/Spring intake (e.g. "Fall 2026")
 * every Application hangs off. Listing/creating/editing/activating requires the
 * calendar.manage permission (Student Affairs or a Dean). A Cycle is "open" when
 * it is active and the current moment falls inside its window — derived, never a
 * stored status column (ADR-0012, ticket 01). Cycles are deactivated, never
 * deleted. The {@see self::open()} lookup is public — it feeds the
 * unauthenticated registration screen.
 */
class AdmissionCycleController
{
    /**
     * List Admission Cycles.
     *
     * Newest intake first. Requires calendar.manage — this is the management
     * list; the public registration screen uses the open-cycle lookup instead.
     *
     * @response 200 {"data": [{"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}]}
     */
    public function index(): AnonymousResourceCollection
    {
        return AdmissionCycleResource::collection(
            AdmissionCycle::query()->orderByDesc('year')->orderBy('intake_term')->get(),
        );
    }

    /**
     * List currently-open Admission Cycles.
     *
     * Public, unauthenticated: the front door the registration screen reads to
     * know whether (and into which intake) a stranger may apply. Returns only
     * Cycles that are active and whose window contains the current moment.
     *
     * @unauthenticated
     *
     * @response 200 {"data": [{"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}]}
     */
    public function open(): AnonymousResourceCollection
    {
        return AdmissionCycleResource::collection(
            AdmissionCycle::query()->currentlyOpen()->orderByDesc('year')->orderBy('intake_term')->get(),
        );
    }

    /**
     * Create an Admission Cycle.
     *
     * @bodyParam intake_term string required Fall or Spring. Example: Fall
     * @bodyParam year integer required The intake year. Example: 2026
     * @bodyParam name_en string required English name. Example: Fall 2026
     * @bodyParam name_ar string required Arabic name. Example: خريف 2026
     * @bodyParam opens_at string required When the application window opens (ISO 8601). Example: 2026-06-01T00:00:00+00:00
     * @bodyParam closes_at string required When the application window closes (ISO 8601, after opens_at). Example: 2026-08-01T00:00:00+00:00
     *
     * @response 201 {"data": {"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}}
     * @response 422 {"message": "The intake term has already been taken.", "errors": {"intake_term": ["The intake term has already been taken."]}}
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));

        $cycle = AdmissionCycle::create($validated);

        return (new AdmissionCycleResource($cycle))->response()->setStatusCode(201);
    }

    /**
     * Show an Admission Cycle.
     *
     * @urlParam admission_cycle_id integer required The ID of the admission cycle. Example: 1
     *
     * @response 200 {"data": {"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}}
     */
    public function show(AdmissionCycle $admissionCycle): AdmissionCycleResource
    {
        return new AdmissionCycleResource($admissionCycle);
    }

    /**
     * Update an Admission Cycle.
     *
     * @urlParam admission_cycle_id integer required The ID of the admission cycle. Example: 1
     *
     * @bodyParam intake_term string required Fall or Spring. Example: Fall
     * @bodyParam year integer required The intake year. Example: 2026
     * @bodyParam name_en string required English name. Example: Fall 2026
     * @bodyParam name_ar string required Arabic name. Example: خريف 2026
     * @bodyParam opens_at string required When the application window opens (ISO 8601). Example: 2026-06-01T00:00:00+00:00
     * @bodyParam closes_at string required When the application window closes (ISO 8601, after opens_at). Example: 2026-08-01T00:00:00+00:00
     *
     * @response 200 {"data": {"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}}
     */
    public function update(Request $request, AdmissionCycle $admissionCycle): AdmissionCycleResource
    {
        $validated = $request->validate($this->rules($request, $admissionCycle));

        $admissionCycle->update($validated);

        return new AdmissionCycleResource($admissionCycle);
    }

    /**
     * Activate an Admission Cycle.
     *
     * @urlParam admission_cycle_id integer required The ID of the admission cycle. Example: 1
     *
     * @response 200 {"data": {"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}}
     */
    public function activate(AdmissionCycle $admissionCycle): AdmissionCycleResource
    {
        $admissionCycle->update(['is_active' => true]);

        return new AdmissionCycleResource($admissionCycle);
    }

    /**
     * Deactivate an Admission Cycle.
     *
     * Deactivating closes the Cycle regardless of its window — no new application
     * can start against it — without deleting it or its Applications.
     *
     * @urlParam admission_cycle_id integer required The ID of the admission cycle. Example: 1
     *
     * @response 200 {"data": {"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": false, "is_open": false}}
     */
    public function deactivate(AdmissionCycle $admissionCycle): AdmissionCycleResource
    {
        $admissionCycle->update(['is_active' => false]);

        return new AdmissionCycleResource($admissionCycle);
    }

    /**
     * Validation rules for creating/editing a Cycle. (intake_term, year) is
     * unique — an intake term names at most one Cycle per year; on update the
     * check ignores the Cycle being edited. The window must be forward-ordered.
     *
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?AdmissionCycle $cycle = null): array
    {
        return [
            'intake_term' => [
                'required',
                Rule::enum(IntakeTerm::class),
                Rule::unique('admission_cycles', 'intake_term')
                    ->where('year', $request->integer('year'))
                    ->ignore($cycle?->getKey()),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
        ];
    }
}
