<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $course_id
 * @property int $origin_enrollment_id
 * @property string $category
 * @property int $credit_hours
 * @property string $state
 * @property Carbon $created_at
 */
final class MustRetakeObligation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'student_id', 'course_id', 'origin_enrollment_id', 'category', 'credit_hours', 'state',
        'closed_by_enrollment_id', 'closed_by_exemption_id', 'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['student_id' => 'integer', 'course_id' => 'integer', 'origin_enrollment_id' => 'integer', 'credit_hours' => 'integer', 'closed_at' => 'immutable_datetime'];
    }
}
