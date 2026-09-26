<?php

namespace Modules\AcademicStanding\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\AcademicStanding\Public\AcademicStanding;

/** @property int $id
 * @property int $student_id
 * @property AcademicStanding $standing
 * @property string $evidence_fingerprint
 * @property string|null $cause_type
 * @property int|null $cause_id
 * @property Carbon $observed_at
 */
final class StandingObservation extends Model
{
    public $timestamps = false;

    protected $fillable = ['student_id', 'standing', 'evidence_fingerprint', 'cause_type', 'cause_id', 'observed_at'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Standing observations are append-only.'));
        self::deleting(fn () => throw new \LogicException('Standing observations are append-only.'));
    }

    protected function casts(): array
    {
        return ['student_id' => 'integer', 'standing' => AcademicStanding::class, 'cause_id' => 'integer', 'observed_at' => 'datetime'];
    }
}
