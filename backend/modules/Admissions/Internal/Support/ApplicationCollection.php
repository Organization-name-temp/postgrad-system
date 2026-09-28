<?php

namespace Modules\Admissions\Internal\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Admissions\Internal\Enums\ApplicationCollectionScope;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Support\Query\CollectionQuery;
use Support\Query\SqlCollectionQuery;

final readonly class ApplicationCollection
{
    private const COLUMNS = ['id' => 'id', 'status' => 'status', 'program_id' => 'primary_program_id', 'admission_cycle_id' => 'admission_cycle_id',
        'degree_level' => 'applying_for_degree', 'name_en' => 'name_en', 'name_ar' => 'name_ar', 'national_id' => 'national_id', 'finalized_at' => 'finalized_at'];

    public function __construct(private ProgramCatalog $catalog, private Directory $directory, private DeanAssignments $deans) {}

    public function query(Request $request): CollectionQuery
    {
        $request->validate(['age_days_min' => ['sometimes', 'integer', 'min:0'], 'age_days_max' => ['sometimes', 'integer', 'min:0']]);
        $input = $request->query();
        $input['sort'] ??= 'finalized_at';

        return CollectionQuery::from($input,
            knownFilters: ['status', 'faculty_id', 'program_id', 'admission_cycle_id', 'degree_level', 'age_days_min', 'age_days_max'],
            readableFilters: ['status', 'faculty_id', 'program_id', 'admission_cycle_id', 'degree_level', 'age_days_min', 'age_days_max'],
            sortable: ['id', 'status', 'name_en', 'name_ar', 'national_id', 'finalized_at', 'age_days'], integerFilters: ['faculty_id', 'program_id', 'admission_cycle_id']);
    }

    /**
     * @param  list<ApplicationStatus>  $statuses
     * @return Builder<Application>
     */
    private function scoped(int $actor, array $statuses, ApplicationCollectionScope $scope): Builder
    {
        $builder = Application::query()->with('statusTransitions')->whereIn('status', $statuses);
        if ($scope === ApplicationCollectionScope::AwaitingInterview) {
            $builder->whereDoesntHave('interview');
            if ($this->directory->isAdministrator($actor) || $this->directory->hasRole($actor, Role::StudentAffairs) || $this->directory->hasRole($actor, Role::Dean)) {
                return $builder;
            }

            return $builder->whereRaw('1 = 0');
        }
        if ($this->directory->isAdministrator($actor) || ($scope === ApplicationCollectionScope::Screening && $this->directory->hasRole($actor, Role::StudentAffairs))) {
            return $builder;
        }
        $faculties = $this->directory->hasRole($actor, Role::Dean) ? $this->deans->facultiesForDean($actor) : [];
        $ids = $builder->clone()->whereNotNull('primary_program_id')->distinct()->pluck('primary_program_id')->all();
        $permitted = [];
        foreach (array_chunk($ids, 100) as $batch) {
            foreach ($this->catalog->findMany($batch) as $program) {
                if (in_array($program->facultyId, $faculties, true)) {
                    $permitted[] = $program->id;
                }
            }
        }

        return $builder->whereIn('primary_program_id', $permitted);
    }

    /**
     * @param  list<ApplicationStatus>  $statuses
     * @return LengthAwarePaginator<int, Application>
     */
    public function page(Request $request, CollectionQuery $query, array $statuses, ApplicationCollectionScope $scope = ApplicationCollectionScope::Screening): LengthAwarePaginator
    {
        $builder = $this->selection((int) $request->user()?->getAuthIdentifier(), $query, $statuses, $scope);
        if (! $this->derived($query)) {
            return $builder->paginate($query->perPage, ['*'], 'page', $query->page)->withQueryString();
        }
        $rows = $this->derivedRows($builder, $query);

        return new LengthAwarePaginator(array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage), count($rows), $query->perPage, $query->page,
            ['path' => $request->url(), 'query' => $request->query()]);
    }

    /**
     * Pagination-free selection shared with the sibling export; stored queries stream
     * in bounded chunks, while derived queries retain truthful complete selection.
     *
     * @param  list<ApplicationStatus>  $statuses
     * @return \Generator<int, Application>
     */
    public function rows(int $actor, CollectionQuery $query, array $statuses, ApplicationCollectionScope $scope = ApplicationCollectionScope::Screening): \Generator
    {
        $builder = $this->selection($actor, $query, $statuses, $scope);
        if ($this->derived($query)) {
            yield from $this->derivedRows($builder, $query);
        } else {
            yield from $builder->lazy(100);
        }
    }

    /**
     * @param  list<ApplicationStatus>  $statuses
     * @return Builder<Application>
     */
    private function selection(int $actor, CollectionQuery $query, array $statuses, ApplicationCollectionScope $scope): Builder
    {
        $builder = SqlCollectionQuery::apply($this->scoped($actor, $statuses, $scope), $query, self::COLUMNS, ['name_en', 'name_ar', 'national_id'], ! $this->derived($query));
        if (isset($query->filters['faculty_id'])) {
            $faculties = array_map('intval', $query->filters['faculty_id']);
            $programIds = $builder->clone()->reorder()->whereNotNull('primary_program_id')->distinct()->pluck('primary_program_id')->all();
            $matching = [];
            foreach (array_chunk($programIds, 100) as $batch) {
                foreach ($this->catalog->findMany($batch) as $program) {
                    if (in_array($program->facultyId, $faculties, true)) {
                        $matching[] = $program->id;
                    }
                }
            }
            $builder->whereIn('primary_program_id', $matching);
        }

        return $builder;
    }

    private function derived(CollectionQuery $query): bool
    {
        return isset($query->filters['age_days_min']) || isset($query->filters['age_days_max']) || in_array('age_days', array_column($query->sorts, 'field'), true);
    }

    /**
     * @param  Builder<Application>  $builder
     * @return list<Application>
     */
    private function derivedRows(Builder $builder, CollectionQuery $query): array
    {
        $rows = $builder->get()->filter(function (Application $application) use ($query): bool {
            $age = $this->age($application);

            return (! isset($query->filters['age_days_min']) || ($age !== null && $age >= (int) $query->filters['age_days_min'][0]))
                && (! isset($query->filters['age_days_max']) || ($age !== null && $age <= (int) $query->filters['age_days_max'][0]));
        })->all();
        usort($rows, function (Application $a, Application $b) use ($query): int {
            foreach ($query->sorts as $sort) {
                $field = $sort['field'];
                $first = $this->sortValue($a, $field);
                $second = $this->sortValue($b, $field);
                // Match PostgreSQL's ASC NULLS LAST / DESC NULLS FIRST ordering.
                $comparison = ($first === null) !== ($second === null)
                    ? ($first === null ? 1 : -1)
                    : $first <=> $second;
                if ($comparison !== 0) {
                    return $sort['direction'] === 'asc' ? $comparison : -$comparison;
                }
            }

            return 0;
        });

        return $rows;
    }

    private function sortValue(Application $application, string $field): mixed
    {
        return match ($field) {
            'age_days' => $this->age($application),
            'status' => $application->status->value,
            default => $application->getAttribute(self::COLUMNS[$field]),
        };
    }

    private function age(Application $application): ?int
    {
        $entered = $application->enteredStatusAt($application->status);

        return $entered === null ? null : (int) $entered->diffInDays(now());
    }
}
