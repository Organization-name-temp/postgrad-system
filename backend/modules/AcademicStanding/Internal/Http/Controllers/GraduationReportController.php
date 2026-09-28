<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\AcademicStanding\Internal\Support\GraduationReportCollection;
use Modules\AcademicStanding\Public\GraduationBlocker;
use Modules\Identity\Public\Contracts\AuditLogger;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Academic Standing */
final class GraduationReportController
{
    /**
     * Advisory Graduation Readiness counts for the authorized full selection.
     *
     * Blocker counts overlap; groups partition the selected Student population.
     * No proposed schedule or formal graduation approval is supplied.
     *
     * @queryParam faculty_id string CSV Faculty IDs. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active. Example: Active
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV field order. Example: student_id
     * @queryParam page integer Accepted but does not truncate summary. Example: 1
     * @queryParam per_page integer Accepted but does not truncate summary. Example: 25
     * @queryParam readiness string CSV ready, not_ready or unavailable groups. Example: not_ready
     * @queryParam blocker string CSV overlapping typed blockers; any selected blocker matches. Example: RemainingRequirements
     * @queryParam blocker_all string Comma-separated blockers all required, intersecting the original blocker OR selection for exact drill-through. Example: CgpaUnavailable
     * @queryParam remaining_ch_min integer Inclusive remaining-CH lower bound. Example: 1
     * @queryParam remaining_ch_max integer Inclusive remaining-CH upper bound. Example: 5
     * @queryParam cgpa_min number Inclusive CGPA lower bound; unknown values excluded. Example: 3
     * @queryParam cgpa_max number Inclusive CGPA upper bound. Example: 4
     * @queryParam cgpa_availability string CSV available or unavailable CGPA. Example: unavailable
     * @queryParam proximity string Remaining 1–5 CH only; not Approaching Graduation. Example: remaining_1_5
     *
     * @response 200 {"data":{"total":2,"groups":{"ready":0,"not_ready":1,"unavailable":1},"blockers":{"RemainingRequirements":1,"CgpaUnavailable":1,"CgpaBelowMinimum":0,"TerminationPending":0}}}
     */
    public function summary(Request $request, GraduationReportCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $groups = ['ready' => 0, 'not_ready' => 0, 'unavailable' => 0];
        $blockers = array_fill_keys(array_column(GraduationBlocker::cases(), 'value'), 0);
        foreach ($rows as $row) {
            $readiness = $row->readiness;
            $groups[$readiness === null ? 'unavailable' : ($readiness->ready ? 'ready' : 'not_ready')]++;
            if ($readiness === null) {
                continue;
            }
            foreach ($readiness->blockers as $blocker) {
                $blockers[$blocker->value]++;
            }
        }

        return response()->json(['data' => ['total' => count($rows), 'groups' => $groups, 'blockers' => $blockers]]);
    }

    /**
     * Advisory readiness Student List for the authorized Report selection.
     *
     * @queryParam faculty_id string CSV Faculty IDs. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active. Example: Active
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV field order. Example: student_id
     * @queryParam page integer Accepted but does not truncate summary. Example: 1
     * @queryParam per_page integer Accepted but does not truncate summary. Example: 25
     * @queryParam readiness string CSV ready, not_ready or unavailable groups. Example: not_ready
     * @queryParam blocker string CSV overlapping typed blockers; any selected blocker matches. Example: RemainingRequirements
     * @queryParam blocker_all string Comma-separated blockers all required, intersecting the original blocker OR selection for exact drill-through. Example: CgpaUnavailable
     * @queryParam remaining_ch_min integer Inclusive remaining-CH lower bound. Example: 1
     * @queryParam remaining_ch_max integer Inclusive remaining-CH upper bound. Example: 5
     * @queryParam cgpa_min number Inclusive CGPA lower bound; unknown values excluded. Example: 3
     * @queryParam cgpa_max number Inclusive CGPA upper bound. Example: 4
     * @queryParam cgpa_availability string CSV available or unavailable CGPA. Example: unavailable
     * @queryParam proximity string Remaining 1–5 CH only; not Approaching Graduation. Example: remaining_1_5
     *
     * @response 200 {"data":[{"student_id":1,"university_id":"001-STUDENT","name_en":null,"name_ar":null,"program":{"id":1,"code":"CSE-MSC","name_en":"Computer Science","name_ar":"علوم الحاسب","degree_level":"MSc","department_id":1,"faculty_id":1},"lifecycle":"Active","readiness":{"ready":false,"remaining_credit_hours":3,"cgpa":null,"approaching_graduation":false,"blockers":["RemainingRequirements","CgpaUnavailable"]},"readiness_group":"not_ready","unavailable":["student_name"]}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    #[ResponseField('data.readiness', 'object', nullable: true)]
    #[ResponseField('data.readiness.cgpa', 'number', nullable: true)]
    #[ResponseField('data.program', 'object', nullable: true)]
    #[ResponseField('data.name_en', 'string', nullable: true)]
    #[ResponseField('data.name_ar', 'string', nullable: true)]
    public function students(Request $request, GraduationReportCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);
        $total = count($rows);

        return response()->json([
            'data' => array_map(fn ($row): array => $row->toArray(), array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage)),
            'meta' => ['current_page' => $query->page, 'last_page' => max(1, (int) ceil($total / $query->perPage)), 'per_page' => $query->perPage, 'total' => $total],
        ]);
    }

    /**
     * Export every Student in the authorized advisory readiness selection.
     *
     * @queryParam faculty_id string CSV Faculty IDs. Example: 1
     * @queryParam department_id string CSV Department IDs. Example: 1
     * @queryParam program_id string CSV Program IDs. Example: 1
     * @queryParam degree_level string CSV MSc or PhD. Example: MSc
     * @queryParam lifecycle string CSV lifecycle; default Active. Example: Active
     * @queryParam q string Bilingual name or University-ID search. Example: Ahmed
     * @queryParam sort string CSV field order. Example: student_id
     * @queryParam page integer Accepted but does not truncate summary. Example: 1
     * @queryParam per_page integer Accepted but does not truncate summary. Example: 25
     * @queryParam readiness string CSV ready, not_ready or unavailable groups. Example: not_ready
     * @queryParam blocker string CSV overlapping typed blockers; any selected blocker matches. Example: RemainingRequirements
     * @queryParam blocker_all string Comma-separated blockers all required, intersecting the original blocker OR selection for exact drill-through. Example: CgpaUnavailable
     * @queryParam remaining_ch_min integer Inclusive remaining-CH lower bound. Example: 1
     * @queryParam remaining_ch_max integer Inclusive remaining-CH upper bound. Example: 5
     * @queryParam cgpa_min number Inclusive CGPA lower bound; unknown values excluded. Example: 3
     * @queryParam cgpa_max number Inclusive CGPA upper bound. Example: 4
     * @queryParam cgpa_availability string CSV available or unavailable CGPA. Example: unavailable
     * @queryParam proximity string Remaining 1–5 CH only; not Approaching Graduation. Example: remaining_1_5
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, GraduationReportCollection $collection, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $actor = (int) $request->user()?->getAuthIdentifier();
        $query = $collection->query($request->query());
        $rows = $collection->rows($actor, $query);
        $arabic = app()->getLocale() === 'ar';
        $headers = array_map(fn (string $key): string => __('academic_standing.report.headers.'.$key),
            ['student_id', 'university_id', 'name_en', 'name_ar', 'program_code', 'program', 'degree_level', 'lifecycle']);
        foreach (['readiness', 'remaining_ch', 'cgpa', 'approaching', 'blockers', 'advisory', 'unavailable'] as $key) {
            $headers[] = __('academic_standing.graduation_report.headers.'.$key);
        }
        $values = (function () use ($rows, $arabic): \Generator {
            foreach ($rows as $row) {
                yield [
                    (string) $row->student->studentId, $row->student->universityId,
                    $row->student->identity?->nameEn, $row->student->identity?->nameAr,
                    $row->program?->code,
                    $row->program === null ? __('academic_standing.report.unavailable.program') : ($arabic ? $row->program->nameAr : $row->program->nameEn),
                    $row->program === null ? null : __('academic_standing.report.degrees.'.$row->program->degreeLevel->value),
                    __('academic_standing.report.lifecycle.'.$row->student->lifecycleStatus->value),
                    __('academic_standing.graduation_report.groups.'.$row->group()),
                    $row->readiness?->remainingCreditHours, $row->readiness?->cgpa,
                    $row->readiness === null ? null : __('academic_standing.graduation_report.boolean.'.($row->readiness->approachingGraduation ? 'yes' : 'no')),
                    implode(', ', array_map(fn ($blocker): string => __('academic_standing.graduation_report.blockers.'.$blocker->value), $row->readiness->blockers ?? [])),
                    __('academic_standing.graduation_report.advisory.'.$row->student->lifecycleStatus->value),
                    implode(', ', array_map(fn (string $key): string => $key === 'curriculum'
                        ? __('academic_standing.curriculum_unavailable') : __('academic_standing.report.unavailable.'.$key), $row->unavailable())),
                ];
            }
        })();
        $response = $xlsx->download('graduation-readiness-students.xlsx', $headers, $values, $arabic);
        $criteria = $query->auditCriteria();
        $criteria['lifecycle'] ??= ['Active'];
        $audit->record('academic_standing.graduation_report.exported', $actor,
            ['criteria' => $criteria, 'row_count' => count($rows)], 'graduation_readiness_report');

        return $response;
    }
}
