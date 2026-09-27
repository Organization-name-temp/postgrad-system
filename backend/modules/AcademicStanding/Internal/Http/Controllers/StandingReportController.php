<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AcademicStanding\Internal\Support\AcademicCohort;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\AcademicStanding\Public\StandingRecords;
use Support\Query\CollectionQuery;

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
    public function summary(Request $request, AcademicCohort $cohort, StandingRecords $records): JsonResponse
    {
        $filters = ['faculty_id', 'department_id', 'program_id', 'degree_level', 'lifecycle', 'standing'];
        $query = CollectionQuery::from($request->query(), $filters, $filters,
            ['student_id', 'name_en', 'name_ar', 'university_id', 'program_id', 'lifecycle', 'standing', 'cgpa'],
            stableKey: 'student_id', integerFilters: ['faculty_id', 'department_id', 'program_id']);
        $selection = $cohort->select((int) $request->user()?->getAuthIdentifier(), $query);
        $values = $records->currentForStudents(array_keys($selection->students));
        $counts = array_fill_keys(array_column(AcademicStanding::cases(), 'value'), 0);
        $standingFilter = $query->filters['standing'] ?? [];
        foreach ($values as $value) {
            if ($standingFilter === [] || in_array($value->standing->value, $standingFilter, true)) {
                $counts[$value->standing->value]++;
            }
        }

        return response()->json(['data' => ['total' => array_sum($counts), 'counts' => $counts]]);
    }
}
