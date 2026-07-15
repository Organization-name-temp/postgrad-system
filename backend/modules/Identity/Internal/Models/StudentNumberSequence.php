<?php

namespace Modules\Identity\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Identity\Internal\Enrollment\UniversityIdGenerator;

/**
 * The per-(year, program) counter row behind a University ID's trailing sequence
 * (ticket 10). Read and bumped under a row lock by {@see UniversityIdGenerator}
 * inside the acceptance transaction. Identity-owned and Internal; never seen outside
 * the generator.
 *
 * @property int $id
 * @property int $year
 * @property int $program_id
 * @property int $next_number
 */
class StudentNumberSequence extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'year',
        'program_id',
        'next_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'program_id' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
