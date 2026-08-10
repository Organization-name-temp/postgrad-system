<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;

/**
 * An Enrollment — a Student taking one {@see CourseOffering}, minted when an Advisor
 * approves a Registration Request line (ADR-0017, ticket 06). It is the first record in
 * the system a Grade can attach to, and the spine every later phase reads.
 *
 * It references its Student by opaque id (no cross-module FK — ADR-0012) and its Offering
 * by same-module id (a real FK). Two facts are **snapshotted** at the mint and never
 * recomputed: the five-way {@see EnrollmentClassification} and the course's total
 * credit hours ({@see $credit_hours}). Its {@see CourseStatus} carries the Appendix-D
 * counting semantics; in Phase 3 only Regular / Retake / Audit are set here (W from
 * ticket 07). {@see $pre_incomplete_status} is private bookkeeping (phase 4 ticket 05): the
 * status held immediately before an Incomplete declaration, remembered so resolution can
 * restore it — never exposed across `Enrollment\Public` (ADR-0012), so `CourseStatus` stays
 * Internal. Display facts (course code/names, meeting times, instructor) are read live
 * through the Catalog/Offering facades, never stored.
 *
 * @property int $id
 * @property int $student_id
 * @property int $course_offering_id
 * @property EnrollmentClassification $classification
 * @property CourseStatus $course_status
 * @property CourseStatus|null $pre_incomplete_status
 * @property int $credit_hours
 * @property bool|null $earned
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'course_offering_id',
        'classification',
        'course_status',
        'pre_incomplete_status',
        'credit_hours',
        'earned',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'course_offering_id' => 'integer',
            'classification' => EnrollmentClassification::class,
            'course_status' => CourseStatus::class,
            'pre_incomplete_status' => CourseStatus::class,
            'credit_hours' => 'integer',
            'earned' => 'boolean',
        ];
    }

    protected static function newFactory(): EnrollmentFactory
    {
        return EnrollmentFactory::new();
    }

    /**
     * The Offering this Enrollment is for — a same-module reference with a real FK.
     *
     * @return BelongsTo<CourseOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    /**
     * Constrain a query to **active** Enrollments — everything except a Withdrawal (W).
     * A withdrawn Student keeps the transcript record but leaves the live roster, frees no
     * seat implicitly, and drops out of future sessions; seat counts, clash checks and
     * rosters all read the active set. (W is unreachable until ticket 07, so this is a
     * no-op filter in Phase 3 — encoded up front so the meaning is fixed.)
     *
     * @param  Builder<Enrollment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('course_status', '!=', CourseStatus::Withdrawn->value);
    }
}
