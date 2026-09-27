<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Records\Internal\Support\RecordsAccess;
use Modules\Records\Internal\Support\StudentCollection;
use Modules\Records\Internal\Support\TranscriptComposer;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Records */
final class TranscriptController
{
    /**
     * Transcript Student picker.
     *
     * @queryParam q string Search admission names or University ID. Example: Sara
     * @queryParam id string Student ids. Example: 1,2
     * @queryParam university_id string University IDs. Example: 2026-CE-0001
     * @queryParam lifecycle_status string Lifecycle states. Example: Active
     * @queryParam sort string Readable field sort. Example: name_en
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Example: 25
     *
     * @response 200 {"data":[{"id":1,"university_id":"2026-CE-0001","name_en":"Sara","name_ar":"سارة","lifecycle_status":"Active"}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function index(Request $request, StudentCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);

        return response()->json(['data' => array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage),
            'meta' => ['current_page' => $query->page, 'last_page' => max(1, (int) ceil(count($rows) / $query->perPage)),
                'per_page' => $query->perPage, 'total' => count($rows)]]);
    }

    /**
     * Export Transcript Student picker.
     *
     * @queryParam q string Search admission names or University ID. Example: Sara
     * @queryParam id string Student ids. Example: 1,2
     * @queryParam university_id string University IDs. Example: 2026-CE-0001
     * @queryParam lifecycle_status string Lifecycle states. Example: Active
     * @queryParam sort string Readable field sort. Example: name_en
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, StudentCollection $collection, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $query = $collection->query($request->query());
        $actor = (int) $request->user()?->getAuthIdentifier();
        $rows = $collection->rows($actor, $query);
        $audit->record(action: 'records.students_exported', actorUserId: $actor, metadata: ['criteria' => $query->auditCriteria()]);

        return $xlsx->download('transcript-students.xlsx', array_map(fn (string $key): string => __('records.columns.'.$key), ['student_id', 'university_id', 'name_en', 'name_ar', 'lifecycle']),
            array_map(fn (array $row): array => [(string) $row['id'], $row['university_id'], $row['name_en'], $row['name_ar'], __('records.lifecycle.'.$row['lifecycle_status'])], $rows), app()->getLocale() === 'ar');
    }

    /**
     * Current unofficial Transcript.
     *
     * @urlParam student_id integer required Student anchor id. Example: 1
     *
     * @response 200 {"data":{"student_id":1,"identity":{"name_en":"Sara","name_ar":"سارة","identification_type":"passport","identification_number":"AB123456","ready":true},"university_id":"2026-CE-0001","enrollment_term":"Fall 2026","lifecycle_status":"Active","program":{"code":"CE","name_en":"Computer Engineering","name_ar":"هندسة الحاسوب","degree_level":"MSc","department_name_en":"Engineering","department_name_ar":"الهندسة","faculty_name_en":"Engineering","faculty_name_ar":"الهندسة"},"cgpa":null,"semesters":[{"semester":{"id":1,"name_en":"Fall 2026","name_ar":"خريف 2026","starts_at":"2026-09-01"},"gpa":null,"courses":[{"enrollment_id":1,"course":{"code":"CE501","name_en":"Algorithms","name_ar":"الخوارزميات"},"credit_hours":3,"classification":"Core","course_status":"Regular","result":"InProgress","contributes_to_cgpa":false}]}],"credit_decisions":[{"type":"Transfer","target_course_code":"CE501","credit_hours":3,"approved_at":"2026-09-01T00:00:00Z","source_institution":"Example University","source_course_code":"EXT501","source_course_name":"Algorithms","original_grade":"Distinction","normalized_grade":"B","reason_codes":[],"prior_course_code":null,"prior_grade":null}],"credit_hours":{"student_id":1,"curriculum_version_id":1,"categories":[{"category":"Core","baseline_required_ch":6,"adjusted_required_ch":6,"attended_ch":0,"earned_ch":0,"transferred_ch":0,"waived_ch":0,"exempted_ch":0,"satisfied_ch":0,"remaining_ch":6}],"total":{"category":"Total","baseline_required_ch":21,"adjusted_required_ch":21,"attended_ch":0,"earned_ch":0,"transferred_ch":0,"waived_ch":0,"exempted_ch":0,"satisfied_ch":0,"remaining_ch":21}}}}
     * @response 403 {"message":"This action is unauthorized."}
     */
    #[ResponseField('data.cgpa', 'number', nullable: true)]
    #[ResponseField('data.semesters.gpa', 'number', nullable: true)]
    #[ResponseField('data.identity.identification_type', 'string', nullable: true)]
    #[ResponseField('data.credit_decisions.prior_grade', 'string', nullable: true)]
    public function show(Request $request, int $student_id, StudentDirectory $students, RecordsAccess $access, TranscriptComposer $composer): JsonResponse
    {
        $student = $students->findByStudentId($student_id);
        abort_if($student === null, 404);
        abort_unless($access->reads((int) $request->user()?->getAuthIdentifier(), $student), 403);

        return response()->json(['data' => $composer->compose($student)]);
    }
}
