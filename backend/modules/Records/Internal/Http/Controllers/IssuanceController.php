<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Records\Internal\Support\IssuanceCollection;
use Modules\Records\Internal\Support\OfficialTranscript;
use Modules\Records\Internal\Support\RecordsAccess;
use Support\Export\XlsxExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Records */
final class IssuanceController
{
    /**
     * Scoped official issuance history.
     *
     * @queryParam q string Search names, University ID or reference. Example: Sara
     * @queryParam id string Issuance ids. Example: 1
     * @queryParam student_id string Student ids. Example: 1
     * @queryParam status string Valid or Revoked. Example: Valid
     * @queryParam reference string Issuance references. Example: reference
     * @queryParam sort string Readable field sort. Example: -issued_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Example: 25
     *
     * @response 200 {"data":[{"id":1,"student_id":1,"reference":"e053b53d-7295-4843-b89e-06bdd064994e","status":"Valid","issuer_name":"Student Affairs","issued_at":"2026-09-27T12:00:00Z"}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function index(Request $request, IssuanceCollection $collection): JsonResponse
    {
        $query = $collection->query($request->query());
        $rows = $collection->rows((int) $request->user()?->getAuthIdentifier(), $query);

        return response()->json(['data' => array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage),
            'meta' => ['current_page' => $query->page, 'last_page' => max(1, (int) ceil(count($rows) / $query->perPage)), 'per_page' => $query->perPage, 'total' => count($rows)]]);
    }

    /**
     * Export scoped issuance history without bearer tokens.
     *
     * @queryParam q string Search names, University ID or reference. Example: Sara
     * @queryParam id string Issuance ids. Example: 1
     * @queryParam student_id string Student ids. Example: 1
     * @queryParam status string Valid or Revoked. Example: Valid
     * @queryParam reference string Issuance references. Example: reference
     * @queryParam sort string Readable field sort. Example: -issued_at
     *
     * @response 200 <<binary>>
     */
    public function export(Request $request, IssuanceCollection $collection, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        $query = $collection->query($request->query());
        $actor = (int) $request->user()?->getAuthIdentifier();
        $rows = $collection->rows($actor, $query);
        $audit->record(action: 'records.issuances_exported', actorUserId: $actor, metadata: ['criteria' => $query->auditCriteria()]);

        return $xlsx->download('transcript-issuances.xlsx', ['Issuance ID', 'Student ID', 'Reference', 'Status', 'Issuer', 'Issued at'],
            array_map(fn (array $row): array => array_values($row), $rows), app()->getLocale() === 'ar');
    }

    /**
     * Review coherent official Transcript preview.
     *
     * @urlParam request_id integer required Example: 1
     *
     * @responseFile 200 modules/Records/Public/Http/responses/preview.json
     */
    #[ResponseField('data.snapshot.cgpa', 'number', nullable: true)]
    #[ResponseField('data.snapshot.semesters.gpa', 'number', nullable: true)]
    public function preview(Request $request, int $request_id, OfficialTranscript $official): JsonResponse
    {
        return response()->json(['data' => $official->preview($request_id, (int) $request->user()?->getAuthIdentifier())]);
    }

    /**
     * Certify reviewed official Transcript.
     *
     * @urlParam request_id integer required Example: 1
     *
     * @bodyParam review_token string required Reviewed content token. Example: reviewed-token
     *
     * @responseFile 201 modules/Records/Public/Http/responses/issuance.json
     */
    #[ResponseField('data.snapshot.cgpa', 'number', nullable: true)]
    #[ResponseField('data.snapshot.semesters.gpa', 'number', nullable: true)]
    public function issue(Request $request, int $request_id, OfficialTranscript $official): JsonResponse
    {
        $data = $request->validate(['review_token' => ['required', 'string', 'max:64']]);

        return response()->json(['data' => $official->issue($request_id, (int) $request->user()?->getAuthIdentifier(), $data['review_token'])], 201);
    }

    /**
     * Immutable official Transcript detail.
     *
     * @urlParam issuance_id integer required Example: 1
     *
     * @responseFile 200 modules/Records/Public/Http/responses/issuance.json
     */
    #[ResponseField('data.snapshot.cgpa', 'number', nullable: true)]
    #[ResponseField('data.snapshot.semesters.gpa', 'number', nullable: true)]
    public function show(Request $request, int $issuance_id, OfficialTranscript $official, StudentDirectory $students, RecordsAccess $access): JsonResponse
    {
        $detail = $official->detail($issuance_id);
        $student = $students->findByStudentId($detail['student_id']);
        abort_unless($student !== null && $access->reads((int) $request->user()?->getAuthIdentifier(), $student), 403);

        return response()->json(['data' => $detail]);
    }

    /**
     * Public bearer verification of an official copy.
     *
     * @unauthenticated
     *
     * @urlParam token string required Unguessable verification token. Example: unknown-token
     *
     * @responseFile 200 modules/Records/Public/Http/responses/public-issuance.json
     */
    #[ResponseField('data.snapshot.cgpa', 'number', nullable: true)]
    #[ResponseField('data.snapshot.semesters.gpa', 'number', nullable: true)]
    public function verify(string $token, OfficialTranscript $official): JsonResponse
    {
        $issuance = DB::table('transcript_issuances')->where('verification_token', $token)->first();
        abort_if($issuance === null, 404);
        $detail = $official->detail($issuance->id);
        if ($detail['status'] === 'Revoked') {
            return response()->json(['data' => ['reference' => $detail['reference'], 'status' => 'Revoked']])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }
        unset($detail['id'], $detail['student_id'], $detail['verification_url'], $detail['revocation']);
        unset($detail['snapshot']['student_id']);

        return response()->json(['data' => $detail])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
