<?php

namespace Modules\AcademicStanding\Internal\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $student_id
 * @property string $fingerprint
 * @property string $state
 * @property array<string, mixed> $evidence
 */
final class TerminationCase extends Model
{
    /** @var list<string> */
    protected $fillable = ['student_id', 'fingerprint', 'state', 'evidence', 'advisor_user_id', 'advisor_assessment', 'advisor_recommendation', 'advisor_reviewed_at', 'department_head_user_id', 'department_head_reason', 'department_head_reviewed_at', 'council_user_id', 'council_reason', 'council_decided_at', 'closed_reason', 'closed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['student_id' => 'integer', 'evidence' => 'array', 'advisor_reviewed_at' => 'datetime', 'department_head_reviewed_at' => 'datetime', 'council_decided_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
