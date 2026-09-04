<?php

namespace Modules\Identity\Internal\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Identity\Public\Enums\CurriculumVersionBindingSource;
use Modules\Identity\Public\StudentSummary;

/**
 * The Student anchor (ticket 10, ADR-0013): the thin 1:1-with-User record minted at
 * acceptance that turns an applicant into a Student. Identity-owned and Internal — it
 * crosses the seam only as a {@see StudentSummary} through
 * the StudentDirectory facade.
 *
 * The Program and Cycle are opaque references (no FK, ADR-0012); the advisor and owner
 * are Identity Users (real FKs within the module). The identity columns are a snapshot
 * frozen at admission, not a live mirror of the (archived) Application.
 *
 * @property int $id
 * @property int $user_id
 * @property string $university_id
 * @property int $program_id
 * @property int $curriculum_version_id
 * @property CurriculumVersionBindingSource $curriculum_version_binding_source
 * @property string $enrollment_term
 * @property int $admission_cycle_id
 * @property int $advisor_id
 * @property string $name_en
 * @property string $name_ar
 * @property string $national_id
 * @property Carbon $date_of_birth
 * @property string $nationality
 * @property string $gender
 * @property array<string, mixed>|null $admission_evidence
 * @property Carbon $admitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'university_id',
        'program_id',
        'curriculum_version_id',
        'curriculum_version_binding_source',
        'enrollment_term',
        'admission_cycle_id',
        'advisor_id',
        'name_en',
        'name_ar',
        'national_id',
        'date_of_birth',
        'nationality',
        'gender',
        'admission_evidence',
        'admitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'program_id' => 'integer',
            'curriculum_version_id' => 'integer',
            'curriculum_version_binding_source' => CurriculumVersionBindingSource::class,
            'admission_cycle_id' => 'integer',
            'advisor_id' => 'integer',
            'date_of_birth' => 'date',
            'admitted_at' => 'datetime',
            'admission_evidence' => 'array',
        ];
    }

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }
}
