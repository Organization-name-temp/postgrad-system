<?php

namespace Modules\Identity\Internal\Models;

use Database\Factories\DepartmentHeadAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Department-Head↔Department routing row (phase 4, ticket 02): the Head (an R06
 * {@see User}) who reviews the signed Grade Sheets of a single Catalog Department.
 * Identity-owned; Grading never touches this model — it reads the mapping through
 * the Identity\Public DepartmentHeadAssignments facade (ADR-0012), the same seam
 * {@see FacultyDeanAssignment} sits behind for the Dean queue.
 *
 * `department_id` is an opaque Catalog reference with no foreign key (ADR-0012), so
 * there is deliberately no `department()` relation; the owning Head *is* an Identity
 * User, referenced by a real FK within the module.
 *
 * A **null** `department_id` is the university-wide Preparatory scope, not a missing
 * Department — see the migration and ADR-0023.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $department_id
 */
class DepartmentHeadAssignment extends Model
{
    /** @use HasFactory<DepartmentHeadAssignmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'department_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'department_id' => 'integer',
        ];
    }

    protected static function newFactory(): DepartmentHeadAssignmentFactory
    {
        return DepartmentHeadAssignmentFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
