<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\RemediationState;

/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int $revocation_id
 * @property RemediationState $state
 * @property int|null $opportunity_offering_id
 * @property int|null $checkpoint_semester_id
 * @property Carbon|null $effective_deadline_at
 */
final class RemediationObligation extends Model
{
    /** @var list<string> */
    protected $fillable = ['student_id', 'course_id', 'revocation_id', 'state', 'opportunity_offering_id', 'checkpoint_semester_id', 'official_deadline_at', 'effective_deadline_at', 'released_by_exemption_id', 'closed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['student_id' => 'integer', 'course_id' => 'integer', 'revocation_id' => 'integer', 'state' => RemediationState::class, 'opportunity_offering_id' => 'integer', 'checkpoint_semester_id' => 'integer', 'official_deadline_at' => 'datetime', 'effective_deadline_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
