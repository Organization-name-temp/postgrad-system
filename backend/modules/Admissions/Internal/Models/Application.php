<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\ApplicationStatus;

/**
 * A prospective student's Application to a single Admission Cycle (CONTEXT.md).
 * Created in the {@see ApplicationStatus::Draft} state by self-registration
 * (ticket 03) and carried through the 13-state workflow by later tickets.
 *
 * The applicant (owner) is Identity's `User`, referenced here by a real FK — both
 * modules share the database. Program selection (ticket 06) is instead stored as
 * an opaque Catalog id with no FK, per the module seam (ADR-0012).
 *
 * @property int $id
 * @property int $user_id
 * @property int $admission_cycle_id
 * @property ApplicationStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'admission_cycle_id',
    ];

    /**
     * A fresh Application is a Draft. `status` is deliberately **not** fillable:
     * it is set here (in-memory, so a just-created model reports it without a
     * reload) and, from ticket 07 on, only ever moved by the transition machine's
     * guarded action methods — never by mass assignment.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ApplicationStatus::Draft->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
        ];
    }

    protected static function newFactory(): ApplicationFactory
    {
        return ApplicationFactory::new();
    }

    /** @return BelongsTo<AdmissionCycle, $this> */
    public function admissionCycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class);
    }
}
