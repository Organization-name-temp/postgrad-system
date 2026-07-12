<?php

namespace Modules\Catalog\Internal\Models;

use Database\Factories\FacultyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Faculty — a top-level academic division (FoE, BAS, CSIT) at the root of the
 * catalog tree; it contains Departments (ADR-0010, CONTEXT.md).
 *
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property bool $is_active
 */
class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'is_active',
    ];

    /**
     * A Faculty is active on creation; deactivation is an explicit action. Set
     * in-memory (not only as a DB default) so a freshly created model reports it
     * without a reload.
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

    protected static function newFactory(): FacultyFactory
    {
        return FacultyFactory::new();
    }
}
