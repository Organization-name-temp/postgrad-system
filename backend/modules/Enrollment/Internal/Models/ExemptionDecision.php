<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\ExemptionSource;

/**
 * One immutable effective Exemption decision. It is standalone academic credit,
 * never an Enrollment and never updated in place (ADR-0030).
 *
 * @property int $id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property PreparatoryRequirementRole $preparatory_requirement_role
 * @property int $credit_hours
 * @property ExemptionSource $source
 * @property list<string> $reason_codes
 * @property array<string, mixed> $source_evidence
 * @property int $admission_application_id
 * @property Carbon $approved_at
 */
class ExemptionDecision extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'curriculum_version_id',
        'target_course_id',
        'target_course_code',
        'preparatory_requirement_role',
        'credit_hours',
        'source',
        'reason_codes',
        'source_evidence',
        'admission_application_id',
        'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'curriculum_version_id' => 'integer',
            'target_course_id' => 'integer',
            'preparatory_requirement_role' => PreparatoryRequirementRole::class,
            'credit_hours' => 'integer',
            'source' => ExemptionSource::class,
            'reason_codes' => 'array',
            'source_evidence' => 'array',
            'admission_application_id' => 'integer',
            'approved_at' => 'datetime',
        ];
    }
}
