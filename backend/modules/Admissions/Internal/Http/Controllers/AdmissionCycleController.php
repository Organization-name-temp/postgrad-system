<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Http\Resources\AdmissionCycleResource;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Support\AdmissionCycleCollection;
use Modules\Admissions\Internal\Support\AdmissionCycleExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
     * @queryParam q string Normalized bilingual Cycle name search. Example: Fall
     * @queryParam id string Comma-separated Cycle ids. Example: 1,2
     * @queryParam intake_term string Comma-separated intake terms. Example: Fall
     * @queryParam year string Comma-separated intake years. Example: 2026
     * @queryParam year_min integer Minimum intake year. Example: 2025
     * @queryParam year_max integer Maximum intake year. Example: 2027
     * @queryParam is_active string Active states, true or false. Example: true
     * @queryParam is_open string Derived window states, true or false. Example: true
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: -year,intake_term
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size, capped at 100. Example: 25
     *
     * @response 200 {"data": [{"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}], "links": {"first": "http://localhost/api/v1/admission-cycles?page=1", "last": "http://localhost/api/v1/admission-cycles?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 25, "total": 1, "last_page": 1}}
     */
    public function index(Request $request, AdmissionCycleCollection $collection): AnonymousResourceCollection
    {
        $query = $collection->query($request);

        return AdmissionCycleResource::collection($collection->selection($query)->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString());
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
     * @queryParam q string Normalized bilingual Cycle name search. Example: Fall
     * @queryParam id string Comma-separated Cycle ids. Example: 1,2
     * @queryParam intake_term string Comma-separated intake terms. Example: Fall
     * @queryParam year string Comma-separated intake years. Example: 2026
     * @queryParam year_min integer Minimum intake year. Example: 2025
     * @queryParam year_max integer Maximum intake year. Example: 2027
     * @queryParam is_active string Active states, true or false. Example: true
     * @queryParam is_open string Derived window states, true or false. Example: true
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: -year,intake_term
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size, capped at 100. Example: 100
     *
     * @response 200 {"data": [{"id": 1, "intake_term": "Fall", "year": 2026, "name_en": "Fall 2026", "name_ar": "خريف 2026", "opens_at": "2026-06-01T00:00:00+00:00", "closes_at": "2026-08-01T00:00:00+00:00", "is_active": true, "is_open": true}], "links": {"first": "http://localhost/api/v1/admission-cycles/open?page=1", "last": "http://localhost/api/v1/admission-cycles/open?page=1", "prev": null, "next": null}, "meta": {"current_page": 1, "per_page": 100, "total": 1, "last_page": 1}}
     */
    public function open(Request $request, AdmissionCycleCollection $collection): AnonymousResourceCollection
    {
        $query = $collection->query($request, defaultPerPage: 100);

        return AdmissionCycleResource::collection($collection->selection($query, onlyOpen: true)->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString());
    }

    /**
     * Export Admission Cycles.
     *
     * All matching manager-readable rows, with bilingual names and localized state.
     *
     * @queryParam q string Normalized bilingual Cycle name search. Example: Fall
     * @queryParam id string Comma-separated Cycle ids. Example: 1,2
     * @queryParam intake_term string Comma-separated intake terms. Example: Fall
     * @queryParam year string Comma-separated intake years. Example: 2026
     * @queryParam year_min integer Minimum intake year. Example: 2025
     * @queryParam year_max integer Maximum intake year. Example: 2027
     * @queryParam is_active string Active states, true or false. Example: true
     * @queryParam is_open string Derived window states, true or false. Example: true
     * @queryParam sort string Comma-separated readable fields; '-' descends. Example: -year,intake_term
     * @queryParam page integer Ignored by export. Example: 1
     * @queryParam per_page integer Ignored by export. Example: 25
     *
     * @response 200 scenario="XLSX" <<binary>>
     * @response 403 {"message": "This action is unauthorized."}
     */
    public function export(Request $request, AdmissionCycleExport $export): BinaryFileResponse
    {
        return $export->download($request);
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
