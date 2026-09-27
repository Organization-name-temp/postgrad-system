<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\Role;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\RecordsAccess;
use Modules\Records\Internal\Support\RequestWorkflow;
use Modules\Records\Internal\Support\TranscriptFee;
use Support\Export\XlsxExporter;
use Support\Query\ArabicNormalizer;
use Support\Query\CollectionQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @group Records */
final class RequestController
{
    /**
     * Submit official Transcript Request.
     *
     * @bodyParam delivery_method string required Digital or collection. Example: digital
     *
     * @response 201 {"data":{"id":1,"student_id":1,"delivery_method":"digital","fee":"125.50","currency":"EGP","status":"Submitted","created_at":"2026-09-27T00:00:00Z","transitions":[{"id":1,"from_status":null,"to_status":"Submitted","action":"request_submitted","reason":null,"actor_user_id":1,"at":"2026-09-27T00:00:00Z"}]}}
     */
    public function store(Request $http, StudentDirectory $students, StudentGuard $guard, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['delivery_method' => ['required', Rule::in(['digital', 'collection'])]]);
        $actor = (int) $http->user()?->getAuthIdentifier();
        $student = $students->find($actor);
        abort_if($student === null, 403);
        $request = DB::transaction(function () use ($student, $guard, $actor, $data, $workflow): TranscriptRequest {
            abort_if($guard->lock($student->studentId) === null, 409);
            $fee = DB::table('transcript_fee')->where('id', 1)->sharedLock()->first();
            abort_if($fee === null, 409, __('records.setup_required'));
            abort_if(TranscriptRequest::query()->where('student_id', $student->studentId)->whereNotIn('status', ['Completed', 'Rejected', 'Cancelled'])->exists(), 409, __('records.open_request'));
            $request = new TranscriptRequest([
                'student_id' => $student->studentId, 'user_id' => $actor, 'delivery_method' => $data['delivery_method'],
                'fee_minor' => $fee->fee_minor, 'instructions_en' => $fee->instructions_en, 'instructions_ar' => $fee->instructions_ar,
            ]);
            $workflow->transition($request, 'Submitted', 'request_submitted', $actor, notify: false);

            return $request;
        });

        return response()->json(['data' => $workflow->detail($request)], 201);
    }

    /**
     * Official Transcript Request details.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function show(Request $http, int $request_id, RecordsAccess $access, RequestWorkflow $workflow): JsonResponse
    {
        $request = TranscriptRequest::findOrFail($request_id);
        $actor = (int) $http->user()?->getAuthIdentifier();
        abort_unless($access->staff($actor) || $request->user_id === $actor, 403);

        $detail = $workflow->detail($request, $access->staff($actor));
        $detail['is_owner'] = $request->user_id === $actor;

        return response()->json(['data' => $detail]);
    }

    /**
     * Review official Transcript Request.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam decision string required Approve or reject. Example: approve
     * @bodyParam reason string required Decision reason. Example: Identity reviewed
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function review(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required', 'string', 'max:10000']]);
        $request = DB::transaction(function () use ($http, $request_id, $workflow, $data): TranscriptRequest {
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
            $workflow->state($request, 'Submitted');
            if ($data['decision'] === 'approve') {
                $request->approved_at = now();
            }
            $workflow->transition($request, $data['decision'] === 'approve' ? 'AwaitingPayment' : 'Rejected',
                $data['decision'] === 'approve' ? 'request_approved' : 'request_rejected', (int) $http->user()?->getAuthIdentifier(), $data['reason']);

            return $request;
        });

        return response()->json(['data' => $workflow->detail($request, true)]);
    }

    /**
     * Official Transcript Request queue and history.
     *
     * @queryParam q string Search admission names or University ID. Example: Sara
     * @queryParam status string Request states. Example: Submitted
     * @queryParam delivery_method string Example: digital
     * @queryParam student_id string Example: 1,2
     * @queryParam sort string Example: -created_at
     * @queryParam page integer Example: 1
     * @queryParam per_page integer Example: 25
     *
     * @response 200 {"data":[{"id":1,"student_id":1,"delivery_method":"digital","fee":"125.50","status":"Submitted","created_at":"2026-09-27T00:00:00Z"}],"meta":{"current_page":1,"last_page":1,"per_page":25,"total":1}}
     */
    public function index(Request $http, RecordsAccess $access, Directory $directory, StudentDirectory $students): JsonResponse
    {
        [$query, $collection] = $this->collection($http, $access, $directory, $students);
        $page = $query->paginate($collection->perPage, ['*'], 'page', $collection->page);

        return response()->json(['data' => $page->getCollection()->map(fn (TranscriptRequest $r): array => [
            'id' => $r->id, 'student_id' => $r->student_id, 'delivery_method' => $r->delivery_method,
            'fee' => TranscriptFee::format($r->fee_minor), 'status' => $r->status, 'created_at' => $r->created_at->toIso8601String(),
        ])->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    /**
     * Export official Transcript Request queue and history.
     *
     * @queryParam q string Search admission names or University ID. Example: Sara
     * @queryParam status string Example: Submitted
     * @queryParam delivery_method string Example: digital
     * @queryParam student_id string Example: 1,2
     * @queryParam sort string Example: -created_at
     *
     * @response 200 <<binary>>
     */
    public function export(Request $http, RecordsAccess $access, Directory $directory, StudentDirectory $students, AuditLogger $audit, XlsxExporter $xlsx): BinaryFileResponse
    {
        [$query, $collection] = $this->collection($http, $access, $directory, $students);
        $audit->record(action: 'records.requests_exported', actorUserId: (int) $http->user()?->getAuthIdentifier(), metadata: ['criteria' => $collection->auditCriteria()]);
        $rows = $query->lazy()->map(fn (TranscriptRequest $r): array => [(string) $r->id, (string) $r->student_id, __('records.delivery.'.$r->delivery_method), TranscriptFee::format($r->fee_minor), 'EGP', __('records.statuses.'.$r->status)]);

        return $xlsx->download('transcript-requests.xlsx', array_map(fn (string $key): string => __('records.columns.'.$key), ['request_id', 'student_id', 'delivery', 'fee', 'currency', 'status']), $rows, app()->getLocale() === 'ar');
    }

    /** @return array{Builder<TranscriptRequest>, CollectionQuery} */
    private function collection(Request $http, RecordsAccess $access, Directory $directory, StudentDirectory $students): array
    {
        $actor = (int) $http->user()?->getAuthIdentifier();
        abort_unless($access->staff($actor) || $directory->hasRole($actor, Role::Student), 403);
        $collection = CollectionQuery::from($http->query(), knownFilters: ['status', 'delivery_method', 'student_id'],
            readableFilters: ['status', 'delivery_method', 'student_id'], sortable: ['id', 'created_at', 'status', 'student_id', 'delivery_method'], integerFilters: ['student_id']);
        $query = TranscriptRequest::query();
        if (! $access->staff($actor)) {
            $query->where('user_id', $actor);
        }
        foreach ($collection->filters as $key => $values) {
            if ($values !== []) {
                $query->whereIn($key, $values);
            }
        }
        foreach ($collection->sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }

        if ($collection->search !== null) {
            $matching = [];
            foreach ($students->findManyByStudentId($students->allStudentIds()) as $student) {
                if (! $access->staff($actor) && $student->userId !== $actor) {
                    continue;
                }
                $text = $student->universityId.' '.($student->identity->nameEn ?? '').' '.($student->identity->nameAr ?? '');
                if (str_contains(ArabicNormalizer::normalize($text), ArabicNormalizer::normalize($collection->search))) {
                    $matching[] = $student->studentId;
                }
            }
            $query->whereIn('student_id', $matching);
        }

        return [$query, $collection];
    }
}
