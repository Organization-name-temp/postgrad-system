<?php

namespace Modules\Catalog\Internal\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Department — an academic unit within a Faculty; it owns Programs and Courses
 * (ADR-0010, CONTEXT.md).
 *
 * @property int $id
 * @property int $faculty_id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property bool $is_active
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'faculty_id',
        'code',
        'name_en',
        'name_ar',
        'is_active',
    ];

    /**
     * Active on creation; deactivation is an explicit action. Set in-memory (not
     * only as a DB default) so a freshly created model reports it without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): DepartmentFactory
    {
        return DepartmentFactory::new();
    }

    /** @return BelongsTo<Faculty, $this> */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * The Programs offered by this Department — the next level down the catalog tree.
     *
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
