<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\CourseOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;

/**
 * A Course Offering — one Catalog Course taught in one Calendar Semester (ADR-0017):
 * "what actually runs this semester." It references its Semester, Course, and
 * Instructor by opaque id (no cross-module foreign key — ADR-0012; each is validated
 * through that module's Public facade), carries an optional seat capacity, and owns
 * one or more weekly {@see OfferingMeeting} rows. (semester, course) is unique.
 *
 * This is the record the rest of Phase 3 hangs off: Enrollments point at an Offering,
 * Class Sessions are generated from its meetings, and Phase-4 grading reads the
 * Instructor→Course link born here. Because of that it is deletable only while nothing
 * references it ({@see self::isReferenced()}) — a live roster or register is never
 * silently destroyed.
 *
 * @property int $id
 * @property int $semester_id
 * @property int $course_id
 * @property int $instructor_id
 * @property int|null $capacity
 */
class CourseOffering extends Model
{
    /** @use HasFactory<CourseOfferingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'semester_id',
        'course_id',
        'instructor_id',
        'capacity',
    ];

    /**
     * The tables that reference an Offering by `course_offering_id` and must keep it
     * from being deleted (ADR-0017/0019). They arrive in later Phase-3 tickets
     * (enrollments: 05/06; class_sessions: 08); {@see self::isReferenced()} guards each
     * with a table-existence check, so this model needs no change when they land.
     *
     * @var list<string>
     */
    private const REFERENCING_TABLES = ['enrollments', 'class_sessions'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'semester_id' => 'integer',
            'course_id' => 'integer',
            'instructor_id' => 'integer',
            'capacity' => 'integer',
        ];
    }

    protected static function newFactory(): CourseOfferingFactory
    {
        return CourseOfferingFactory::new();
    }

    /**
     * The Offering's weekly meeting slots (weekday + start/end), ordered for a stable
     * display and for the self-overlap check.
     *
     * @return HasMany<OfferingMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(OfferingMeeting::class)
            ->orderBy('weekday')
            ->orderBy('starts_at');
    }

    /**
     * Whether anything references this Offering — a minted Enrollment or a generated
     * Class Session (ADR-0017/0019). An Offering may be deleted only while this is
     * false, so live rosters and registers survive. The referencing tables arrive in
     * later tickets, so each is guarded by a table-existence check and simply reports
     * "unreferenced" until it exists.
     */
    public function isReferenced(): bool
    {
        foreach (self::REFERENCING_TABLES as $table) {
            if (Schema::hasTable($table)
                && DB::table($table)->where('course_offering_id', $this->getKey())->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The seats still available, or `null` when the Offering is uncapped (a display fact
     * on the eligible list, user story 15). Derived, never stored, so it cannot drift:
     * capacity minus the count of **active** Enrollments (ADR-0017). That count is read in
     * one batched query keyed by offering id and stashed as `enrolled_count`
     * ({@see CountsEnrollments}); an Offering
     * with no stash (before any mint — the eligibility list's first render) reports its
     * full capacity, so tickets 04/05 behaviour is preserved. Never negative.
     */
    public function seatsRemaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $enrolled = (int) ($this->getAttribute('enrolled_count') ?? 0);

        return max(0, $this->capacity - $enrolled);
    }
}
