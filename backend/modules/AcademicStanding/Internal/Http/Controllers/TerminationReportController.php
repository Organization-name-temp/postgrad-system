<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\AcademicStanding\Internal\Support\TerminationReportCollection;
use Modules\AcademicStanding\Internal\Support\TerminationReportReviewAccess;
use Modules\AcademicStanding\Internal\Support\TerminationReportRow;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Academic Standing */
final class TerminationReportController
{
    /**
     * Scoped Termination Case counts and distinct Students.
     *
     * AwaitingCouncil means a Department Head recommendation, distinct from initial
     * flagging and Advisor assessment. GET never reconciles or replaces Cases.
     *
     * @queryParam faculty_id string CSV Faculty IDs narrowing permitted Students. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active and Terminated. Example: Active,Terminated
     * @queryParam state string CSV existing Case states. Example: AwaitingCouncil
     * @queryParam history string default includes open Cases and Terminated; include adds all closed outcomes. Example: include
     * @queryParam created_from string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-01
     * @queryParam created_to string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-31
     * @queryParam q string Bilingual Student name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV order; stable Case ID breaks ties and unknown timestamps sort last. Example: -created_at
     * @queryParam page integer Page number; summary/export retain the full selection. Example: 1
     * @queryParam per_page integer List size capped at 100; summary/export retain the full selection. Example: 25
     *
     * @response 200 {"data":{"case_count":4,"student_count":4,"states":{"AwaitingAdvisor":1,"AwaitingDepartmentHead":1,"AwaitingCouncil":1,"Dismissed":0,"Terminated":1,"TriggerCleared":0,"EvidenceChanged":0}}}
     */
    public function summary(Request $request, TerminationReportCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $states = array_fill_keys(TerminationReportCollection::STATES, 0);
        foreach ($rows as $row) {
            $states[$row->case->state]++;
        }

        return response()->json(['data' => ['case_count' => count($rows),
            'student_count' => count(array_unique(array_map(fn ($row): int => $row->student->studentId, $rows))), 'states' => $states]]);
    }

    /**
     * Scoped minimal Termination Case List.
     *
     * Review URLs require operational permission, persona, stage and live access.
     * No evidence, fingerprints, private identity or review narratives are exposed.
     * All available workflow timestamps are UTC; date filters describe Case creation.
     *
     * @queryParam faculty_id string CSV Faculty IDs narrowing permitted Students. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active and Terminated. Example: Active,Terminated
     * @queryParam state string CSV existing Case states. Example: AwaitingCouncil
     * @queryParam history string default includes open Cases and Terminated; include adds all closed outcomes. Example: include
     * @queryParam created_from string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-01
     * @queryParam created_to string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-31
     * @queryParam q string Bilingual Student name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV order; stable Case ID breaks ties and unknown timestamps sort last. Example: -created_at
     * @queryParam page integer Page number; summary/export retain the full selection. Example: 1
     * @queryParam per_page integer List size capped at 100; summary/export retain the full selection. Example: 25
     *
     * @response 200 {"data":[{"student_id":1,"university_id":"001-STUDENT","name_en":null,"name_ar":null,"program":{"id":1,"code":"CSE-MSC","name_en":"Computer Science","name_ar":"علوم الحاسب","degree_level":"MSc","department_id":1,"faculty_id":1},"lifecycle":"Active","case_id":1,"state":"AwaitingCouncil","created_at":"2026-01-01T00:00:00+00:00","advisor_reviewed_at":null,"department_head_reviewed_at":null,"council_decided_at":null,"closed_at":null,"unavailable":["student_name"],"review_url":null}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    #[ResponseField('data.program', 'object', nullable: true)]
    #[ResponseField('data.name_en', 'string', nullable: true)]
    #[ResponseField('data.name_ar', 'string', nullable: true)]
    #[ResponseField('data.created_at', 'string', nullable: true)]
    #[ResponseField('data.advisor_reviewed_at', 'string', nullable: true)]
    #[ResponseField('data.department_head_reviewed_at', 'string', nullable: true)]
    #[ResponseField('data.council_decided_at', 'string', nullable: true)]
    #[ResponseField('data.closed_at', 'string', nullable: true)]
    #[ResponseField('data.review_url', 'string', nullable: true)]
    public function cases(Request $request, TerminationReportCollection $collection, Directory $directory, DepartmentHeadAssignments $heads): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $total = count($rows);
        $access = new TerminationReportReviewAccess((int) $request->user()?->getAuthIdentifier(), $directory, $heads, Gate::allows('termination.recommend'), Gate::allows('termination.approve'));

        return response()->json([
            'data' => array_map(fn (TerminationReportRow $row): array => $row->toArray($access->forRow($row)), array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage)),
            'meta' => ['current_page' => $query->page, 'last_page' => max(1, (int) ceil($total / $query->perPage)), 'per_page' => $query->perPage, 'total' => $total],
        ]);
    }

    /**
     * Export the complete scoped Termination Case selection.
     *
     * Localized stages preserve recommendation meaning. Identifiers are strings;
     * the workbook contains minimal oversight facts and no private evidence.
     *
     * @queryParam faculty_id string CSV Faculty IDs narrowing permitted Students. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active and Terminated. Example: Active,Terminated
     * @queryParam state string CSV existing Case states. Example: AwaitingCouncil
     * @queryParam history string default includes open Cases and Terminated; include adds all closed outcomes. Example: include
     * @queryParam created_from string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-01
     * @queryParam created_to string Inclusive Case-created UTC date, YYYY-MM-DD. Example: 2026-01-31
     * @queryParam q string Bilingual Student name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV order; stable Case ID breaks ties and unknown timestamps sort last. Example: -created_at
     * @queryParam page integer Page number; summary/export retain the full selection. Example: 1
     * @queryParam per_page integer List size capped at 100; summary/export retain the full selection. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, TerminationReportCollection $collection, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $query = $collection->query($request->query());
        $rows = $collection->rows($actor, $query);
        $arabic = app()->getLocale() === 'ar';
        $headers = [__('academic_standing.exports.headers.case_id')];
        foreach (['student_id', 'university_id', 'name_en', 'name_ar', 'program', 'program_code', 'degree_level', 'lifecycle'] as $key) {
            $headers[] = __('academic_standing.report.headers.'.$key);
        }
        foreach (['state', 'created_at', 'advisor_reviewed_at', 'department_head_reviewed_at', 'council_decided_at', 'closed_at', 'unavailable'] as $key) {
            $headers[] = __('academic_standing.termination_report.headers.'.$key);
        }
        $values = (function () use ($rows, $arabic): \Generator {
            foreach ($rows as $row) {
                $data = $row->toArray();
                yield [
                    (string) $row->case->id, (string) $row->student->studentId, $row->student->universityId,
                    $row->student->identity?->nameEn, $row->student->identity?->nameAr,
                    $row->program === null ? __('academic_standing.report.unavailable.program') : ($arabic ? $row->program->nameAr : $row->program->nameEn),
                    $row->program?->code,
                    $row->program === null ? null : __('academic_standing.report.degrees.'.$row->program->degreeLevel->value),
                    __('academic_standing.report.lifecycle.'.$row->student->lifecycleStatus->value),
                    __('academic_standing.termination_report.states.'.$row->case->state),
                    $data['created_at'], $data['advisor_reviewed_at'], $data['department_head_reviewed_at'], $data['council_decided_at'], $data['closed_at'],
                    implode(', ', array_map(fn (string $key): string => __('academic_standing.report.unavailable.'.$key), $row->unavailable())),
                ];
            }
        })();
        $response = $xlsx->download('termination-report-cases.xlsx', $headers, $values, $arabic);
        $criteria = $query->auditCriteria();
        $criteria['history'] ??= ['default'];
        $audit->record('academic_standing.termination_report.exported', $actor,
            ['criteria' => $criteria, 'row_count' => count($rows), 'student_count' => count(array_unique(array_map(fn ($row): int => $row->student->studentId, $rows)))], 'termination_report');

        return $response;
    }
}
