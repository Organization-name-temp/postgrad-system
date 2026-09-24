<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $registration_request_id
 * @property int $registration_request_line_id
 * @property int $course_offering_id
 * @property int $course_id
 * @property int|null $department_id
 * @property string $state
 */
final class CapacityOverrideRequirement extends Model
{
    /** @var list<string> */
    protected $fillable = ['registration_request_id', 'registration_request_line_id', 'course_offering_id', 'course_id', 'department_id', 'state', 'decided_by', 'reason', 'decided_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['registration_request_id' => 'integer', 'registration_request_line_id' => 'integer', 'course_offering_id' => 'integer', 'course_id' => 'integer', 'department_id' => 'integer', 'decided_by' => 'integer', 'decided_at' => 'datetime'];
    }
}
