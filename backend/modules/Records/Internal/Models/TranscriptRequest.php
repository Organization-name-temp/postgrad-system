<?php

namespace Modules\Records\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $user_id
 * @property string $delivery_method
 * @property int $fee_minor
 * @property string $instructions_en
 * @property string $instructions_ar
 * @property string $status
 * @property int|null $verified_minor
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TranscriptRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['student_id' => 'integer', 'user_id' => 'integer', 'fee_minor' => 'integer', 'approved_at' => 'datetime', 'verified_minor' => 'integer', 'verified_by' => 'integer', 'verified_at' => 'datetime'];
    }
}
