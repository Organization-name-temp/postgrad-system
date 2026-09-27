<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\AcademicStanding\Internal\Support\StandingReportCollection;
use Modules\AcademicStanding\Internal\Support\StandingReportRow;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\Identity\Public\Contracts\AuditLogger;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Academic Standing */
final class StandingReportController
{
    /**
     * Current Academic Standing counts for the authorized full selection.
     *
     * @queryParam faculty_id string CSV Faculty IDs narrowing permitted Students. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV Active or Terminated; default Active. Example: Active
     * @queryParam standing string CSV authoritative Standing values. Example: AcademicWarning
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV stored or derived field order. Example: -cgpa
     * @queryParam page integer Accepted but does not truncate summary. Example: 1
     * @queryParam per_page integer Accepted but does not truncate summary. Example: 25
     *
     * @response 200 {"data":{"total":3,"counts":{"TerminationPending":0,"NotYetAssessed":1,"AcademicProbation":0,"AcademicWarning":1,"GoodStanding":1}}}
     */
    public function summary(Request $request, StandingReportCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $counts = array_fill_keys(array_column(AcademicStanding::cases(), 'value'), 0);
        foreach ($rows as $row) {
            $counts[$row->standing->standing->value]++;
        }

        return response()->json(['data' => ['total' => array_sum($counts), 'counts' => $counts]]);
    }

    /**
     * Current Standing Student List for the same authorized Report selection.
     *
     * @queryParam faculty_id string CSV Faculty IDs. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active. Example: Active
     * @queryParam standing string CSV Standing values. Example: AcademicWarning
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV field order; nullable values sort last. Example: -cgpa
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Page size capped at 100. Example: 25
     *
     * @response 200 {"data":[{"student_id":1,"university_id":"001-STUDENT","name_en":null,"name_ar":null,"program":{"id":1,"code":"CSE-MSC","name_en":"Computer Science","name_ar":"علوم الحاسب","degree_level":"MSc","department_id":1,"faculty_id":1},"lifecycle":"Active","cgpa":3.2,"standing":"AcademicWarning","explanation":"effective_grade_below_c_plus","unavailable":["student_name"]}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    #[ResponseField('data.cgpa', 'number', nullable: true)]
    #[ResponseField('data.program', 'object', nullable: true)]
    #[ResponseField('data.name_en', 'string', nullable: true)]
    #[ResponseField('data.name_ar', 'string', nullable: true)]
    public function students(Request $request, StandingReportCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $total = count($rows);

        return response()->json([
            'data' => array_map(fn (StandingReportRow $row): array => $row->toArray(), array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage)),
            'meta' => ['current_page' => $query->page, 'last_page' => max(1, (int) ceil($total / $query->perPage)), 'per_page' => $query->perPage, 'total' => $total],
        ]);
    }

    /**
     * Export every Student in the authorized Standing Report selection.
     *
     * @queryParam faculty_id string CSV Faculty IDs. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active. Example: Active
     * @queryParam standing string CSV Standing values. Example: AcademicWarning
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV field order. Example: -cgpa
     * @queryParam page integer Accepted but does not truncate export. Example: 1
     * @queryParam per_page integer Accepted but does not truncate export. Example: 25
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, StandingReportCollection $collection, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $query = $collection->query($request->query());
        $rows = $collection->rows($actor, $query);
        $arabic = app()->getLocale() === 'ar';
        $headers = array_map(fn (string $key): string => __('academic_standing.report.headers.'.$key),
            ['student_id', 'university_id', 'name_en', 'name_ar', 'program_code', 'program', 'degree_level', 'lifecycle', 'cgpa', 'standing', 'explanation', 'unavailable']);
        $values = (function () use ($rows, $arabic): \Generator {
            foreach ($rows as $row) {
                $unavailable = $row->unavailable();
                yield [
                    (string) $row->student->studentId, $row->student->universityId,
                    $row->student->identity?->nameEn, $row->student->identity?->nameAr,
                    $row->program?->code,
                    $row->program === null ? __('academic_standing.report.unavailable.program')
                        : ($arabic ? $row->program->nameAr : $row->program->nameEn),
                    $row->program === null ? null : __('academic_standing.report.degrees.'.$row->program->degreeLevel->value),
                    __('academic_standing.report.lifecycle.'.$row->student->lifecycleStatus->value),
                    $row->standing->cgpa,
                    __('academic_standing.report.standings.'.$row->standing->standing->value),
                    __('academic_standing.report.explanations.'.$row->standing->explanation),
                    implode(', ', array_map(fn (string $key): string => __('academic_standing.report.unavailable.'.$key), $unavailable)),
                ];
            }
        })();
        $response = $xlsx->download('academic-standing-students.xlsx', $headers, $values, $arabic);
        $criteria = $query->auditCriteria();
        $criteria['lifecycle'] ??= ['Active'];
        $audit->record('academic_standing.report.exported', $actor,
            ['criteria' => $criteria, 'row_count' => count($rows)], 'academic_standing_report');

        return $response;
    }
}
