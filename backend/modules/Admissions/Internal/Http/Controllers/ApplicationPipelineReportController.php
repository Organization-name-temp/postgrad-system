<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Admissions\Public\ApplicationPipelines;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Support\Query\CollectionQuery;

/**
 * @group Admissions
 */
final class ApplicationPipelineReportController
{
    /**
     * Application Pipeline Report.
     *
     * Live actor-scoped counts and measured conversions from Admissions' owner
     * aggregate. Reads never reconcile or mutate Applications. Counts include
     * zero stages; a conversion with no eligible Applications has a null rate.
     * Requires application.screen. Paging never limits a Report summary.
     *
     * @queryParam admission_cycle_id integer required Selected Admission Cycle. Example: 1
     * @queryParam program_id integer Optional primary Program. Example: 1
     * @queryParam degree_level string Optional MSc or PhD degree. Example: MSc
     * @queryParam page integer Ignored by the summary. Example: 1
     * @queryParam per_page integer Ignored by the summary. Example: 25
     *
     * @response 200 {"data":{"total":2,"statuses":{"draft":0,"submitted":0,"email_verified":0,"under_sa_review":1,"sa_approved":0,"returned_for_revision":0,"under_dean_review":1,"interview_scheduled":0,"interview_completed":0,"accepted":0,"conditionally_accepted":0,"waitlisted":0,"rejected":0},"conversions":[{"from_status":"under_sa_review","to_status":"sa_approved","eligible":2,"converted":1,"rate":50.0}]},"meta":{"admission_cycle_id":1,"program_id":null,"degree_level":null}}
     * @response 403 {"message":"This action is unauthorized."}
     */
    #[ResponseField('data.conversions.*.rate', 'number', nullable: true)]
    #[ResponseField('meta.program_id', 'integer', nullable: true)]
    #[ResponseField('meta.degree_level', 'string', nullable: true)]
    public function __invoke(Request $request, ApplicationPipelines $pipelines): JsonResponse
    {
        $fields = ['admission_cycle_id', 'program_id', 'degree_level'];
        CollectionQuery::from($request->query(), $fields, $fields, ['id'], integerFilters: ['admission_cycle_id', 'program_id']);
        foreach (['q', 'sort'] as $unsupported) {
            if ($request->filled($unsupported)) {
                throw ValidationException::withMessages([$unsupported => __('support.unknown_collection_query_field', ['field' => $unsupported])]);
            }
        }
        // Query parameters
        $criteria = Validator::make($request->query(), [
            'admission_cycle_id' => ['required', 'integer', 'min:1'],
            'program_id' => ['nullable', 'integer', 'min:1'],
            'degree_level' => ['nullable', Rule::enum(DegreeLevel::class)],
        ])->validate();
        $cycle = (int) $criteria['admission_cycle_id'];
        $program = isset($criteria['program_id']) ? (int) $criteria['program_id'] : null;
        $degree = $criteria['degree_level'] ?? null;
        $summary = $pipelines->summarize((int) $request->user()?->getAuthIdentifier(), $cycle, $program, $degree);

        return response()->json([
            'data' => ['total' => $summary->total, 'statuses' => $summary->statuses, 'conversions' => $summary->conversions],
            'meta' => ['admission_cycle_id' => $cycle, 'program_id' => $program, 'degree_level' => $degree],
        ]);
    }
}
