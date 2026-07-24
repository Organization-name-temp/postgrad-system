<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\RegistrationRequestLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationMode;

/**
 * One line of a {@see RegistrationRequest} — a chosen {@see CourseOffering} and the
 * requested {@see RegistrationMode} (Regular / Audit). Retake is never a requested mode;
 * it is derived at the mint from the Student's record (ticket 06).
 *
 * The Offering is same-module, so a convenience {@see self::offering()} relation loads it
 * even though the column carries no database foreign key (a cart must not pin an Offering
 * against deletion — ADR-0017); a stale reference resolves to `null` and is handled
 * defensively at read time.
 *
 * @property int $id
 * @property int $registration_request_id
 * @property int $course_offering_id
 * @property RegistrationMode $mode
 * @property LineDecision|null $decision
 */
class RegistrationRequestLine extends Model
{
    /** @use HasFactory<RegistrationRequestLineFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'course_offering_id',
        'mode',
        'decision',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'course_offering_id' => 'integer',
            'mode' => RegistrationMode::class,
            'decision' => LineDecision::class,
        ];
    }

    protected static function newFactory(): RegistrationRequestLineFactory
    {
        return RegistrationRequestLineFactory::new();
    }

    /**
     * The Request this line belongs to.
     *
     * @return BelongsTo<RegistrationRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(RegistrationRequest::class, 'registration_request_id');
    }

    /**
     * The chosen Course Offering — a same-module reference resolved by id (no database FK;
     * a dropped Offering yields `null`).
     *
     * @return BelongsTo<CourseOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }
}
