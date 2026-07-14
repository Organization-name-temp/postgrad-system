<?php

namespace Modules\Catalog\Internal\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Internal\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\DegreeLevel;

/**
 * A Program — a degree offering (MSc/PhD) under a Department. It carries its
 * required credit hours per Course Category (Preparatory / Program Core /
 * Research Elective); the total is derived from those three, never stored, so it
 * cannot drift (ADR-0010, CONTEXT.md).
 *
 * @property int $id
 * @property int $department_id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property DegreeLevel $degree_level
 * @property int $prep_required_ch
 * @property int $core_required_ch
 * @property int $elective_required_ch
 * @property-read int $total_required_ch
 * @property bool $is_active
 */
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'department_id',
        'code',
        'name_en',
        'name_ar',
        'degree_level',
        'prep_required_ch',
        'core_required_ch',
        'elective_required_ch',
        'is_active',
    ];

    /**
     * Active on creation; deactivation is an explicit action. Set in-memory (not
     * only as a DB default) so a freshly created model reports it without a reload.
     *
     * The per-Category credit hours are intentionally not given an in-memory
     * default here: the controller applies the bylaw split from
     * {@see CourseCategory::defaultRequiredCh()} on create (the authoritative
     * domain default), with a matching DB-level default in the migration as a
     * safety net for direct inserts.
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
            'degree_level' => DegreeLevel::class,
            'prep_required_ch' => 'integer',
            'core_required_ch' => 'integer',
            'elective_required_ch' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): ProgramFactory
    {
        return ProgramFactory::new();
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The Program's Curriculum — its Curriculum Entries placing Courses into the
     * three Categories (ADR-0010).
     *
     * @return HasMany<CurriculumEntry, $this>
     */
    public function curriculumEntries(): HasMany
    {
        return $this->hasMany(CurriculumEntry::class);
    }

    /**
     * The Program's total required credit hours — the sum of its three per-Category
     * requirements. Derived rather than stored so it always agrees with the parts
     * (ADR-0010); defaults to 21 for the 6/6/9 split.
     *
     * @return Attribute<int, never>
     */
    protected function totalRequiredCh(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->prep_required_ch + $this->core_required_ch + $this->elective_required_ch,
        );
    }
}
