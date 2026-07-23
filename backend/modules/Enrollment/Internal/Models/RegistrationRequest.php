<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\RegistrationRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;

/**
 * A Registration Request — a Student's submitted cart for one Semester (ADR-0017): the
 * aggregate the assigned Advisor decides line-by-line (ticket 06). It references its
 * Student and Semester by opaque id (no cross-module foreign key — ADR-0012; each is
 * validated through that module's Public facade), carries its {@see RegistrationKind} and
 * its {@see RegistrationStatus} in the review pipeline, and owns one or more
 * {@see RegistrationRequestLine} rows.
 *
 * Born {@see RegistrationStatus::Pending} on submit; there is no server-side Draft (the
 * cart lives in the SPA until submit). At most one Pending request exists per (student,
 * semester) — enforced by a partial unique index and an app-layer check — so a Rejected
 * request can be replaced by a fresh cart while the window is open.
 *
 * @property int $id
 * @property int $student_id
 * @property int $semester_id
 * @property RegistrationKind $kind
 * @property RegistrationStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RegistrationRequest extends Model
{
    /** @use HasFactory<RegistrationRequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'semester_id',
        'kind',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'semester_id' => 'integer',
            'kind' => RegistrationKind::class,
            'status' => RegistrationStatus::class,
        ];
    }

    protected static function newFactory(): RegistrationRequestFactory
    {
        return RegistrationRequestFactory::new();
    }

    /**
     * The cart's lines — one per chosen Course Offering — ordered for a stable display.
     *
     * @return HasMany<RegistrationRequestLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RegistrationRequestLine::class)->orderBy('id');
    }

    /**
     * Constrain a query to Pending requests — the "one Pending per (student, semester)"
     * check and the advisor queue (ticket 06) both read this.
     *
     * @param  Builder<RegistrationRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Pending);
    }
}
