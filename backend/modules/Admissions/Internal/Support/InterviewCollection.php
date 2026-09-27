<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Admissions\Internal\Enums\InterviewCollectionScope;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Identity\Public\Contracts\Directory;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

final readonly class InterviewCollection
{
    private const COLUMNS = ['id' => 'interviews.id', 'application_id' => 'interviews.application_id', 'status' => 'interviews.status',
        'evaluation_score' => 'interviews.evaluation_score', 'evaluation_score_min' => 'interviews.evaluation_score', 'evaluation_score_max' => 'interviews.evaluation_score',
        'reschedule_count' => 'interviews.reschedule_count', 'completed_at' => 'interviews.completed_at', 'interviewer_id' => 'interview_slots.interviewer_id',
        'scheduled_at' => 'interview_slots.scheduled_at', 'scheduled_at_min' => 'interview_slots.scheduled_at', 'scheduled_at_max' => 'interview_slots.scheduled_at',
        'mode' => 'interview_slots.mode', 'name_en' => 'applications.name_en', 'name_ar' => 'applications.name_ar', 'national_id' => 'applications.national_id',
        'program_id' => 'applications.primary_program_id', 'admission_cycle_id' => 'applications.admission_cycle_id', 'degree_level' => 'applications.applying_for_degree'];

    public function __construct(private Directory $directory) {}

    public function query(Request $request, InterviewCollectionScope $scope): CollectionQuery
    {
        $request->validate(['evaluation_score_min' => ['sometimes', 'integer', 'between:1,10'], 'evaluation_score_max' => ['sometimes', 'integer', 'between:1,10'],
            'scheduled_at_min' => ['sometimes', 'date'], 'scheduled_at_max' => ['sometimes', 'date']]);
        $input = $request->query();
        $input['sort'] ??= $scope === InterviewCollectionScope::Assigned ? 'scheduled_at' : '-id';
        $filters = ['id', 'application_id', 'status', 'interviewer_id', 'program_id', 'admission_cycle_id', 'degree_level', 'mode',
            'evaluation_score_min', 'evaluation_score_max', 'scheduled_at_min', 'scheduled_at_max'];

        return CollectionQuery::from($input, $filters, $filters, ['id', 'application_id', 'status', 'evaluation_score', 'reschedule_count', 'completed_at',
            'interviewer_id', 'scheduled_at', 'mode', 'name_en', 'name_ar', 'national_id'], integerFilters: ['id', 'application_id', 'interviewer_id', 'program_id', 'admission_cycle_id']);
    }

    /** @return Builder<Interview> */
    public function selection(Request $request, CollectionQuery $query, InterviewCollectionScope $scope): Builder
    {
        abort_unless($request->user() !== null, 401);
        if ($scope === InterviewCollectionScope::Booked) {
            abort_unless($request->user()->can('interview.schedule'), 403);
        }
        // All joined tables belong to Admissions; foreign staff remain opaque IDs.
        $builder = Interview::query()->select('interviews.*')->with(['slot', 'application'])
            ->leftJoin('interview_slots', 'interview_slots.id', '=', 'interviews.interview_slot_id')
            ->leftJoin('applications', 'applications.id', '=', 'interviews.application_id');
        $actor = (int) $request->user()->getAuthIdentifier();
        if ($scope === InterviewCollectionScope::Assigned && ! $this->directory->isAdministrator($actor)) {
            $builder->where('interview_slots.interviewer_id', $actor);
        }

        return SqlCollectionQuery::apply($builder, $query, self::COLUMNS, ['applications.name_en', 'applications.name_ar', 'applications.national_id']);
    }
}
