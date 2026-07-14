<?php

namespace Modules\Identity\Internal\Models;

use Database\Factories\FacultyDeanAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Dean↔Faculty routing row (ticket 08): the Dean (an R07 {@see User}) who
 * reviews the applications of a single Catalog Faculty. Identity-owned; Admissions
 * never touches this model — it reads the mapping through the Identity\Public
 * DeanAssignments facade (ADR-0012).
 *
 * `faculty_id` is an opaque Catalog reference with no foreign key (ADR-0012), so
 * there is deliberately no `faculty()` relation; the owning Dean *is* an Identity
 * User, referenced by a real FK within the module.
 *
 * @property int $id
 * @property int $user_id
 * @property int $faculty_id
 */
class FacultyDeanAssignment extends Model
{
    /** @use HasFactory<FacultyDeanAssignmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'faculty_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'faculty_id' => 'integer',
        ];
    }

    protected static function newFactory(): FacultyDeanAssignmentFactory
    {
        return FacultyDeanAssignmentFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
