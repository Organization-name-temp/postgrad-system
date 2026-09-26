<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Enrollment's immutable local receipt of a Public admission-evidence event. It is
 * captured before evaluation so a failed post-commit application remains recoverable.
 *
 * @property int $id
 * @property int $student_id
 * @property int $admission_application_id
 * @property array<string, mixed> $evidence
 * @property Carbon $captured_at
 * @property Carbon|null $reconciled_at
 */
class AutomaticExemptionEvidence extends Model
{
    public $timestamps = false;

    protected $table = 'automatic_exemption_evidence';

    /** @var list<string> */
    protected $fillable = ['student_id', 'admission_application_id', 'evidence', 'captured_at', 'reconciled_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'admission_application_id' => 'integer',
            'evidence' => 'array',
            'captured_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }
}
