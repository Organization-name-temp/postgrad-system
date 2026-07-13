<?php

namespace Modules\Catalog\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Catalog\Internal\Enums\VersionSubject;

/**
 * An append-only version snapshot of a Program or Course (ADR-0011). Each records
 * the full aggregate as it stood after one create/edit/activate/deactivate, with a
 * per-subject incrementing `version_no`. Catalog-owned domain history — distinct
 * from the Identity Audit Log. Rows are never updated or deleted, so it carries no
 * updated_at (only its own `changed_at`).
 *
 * @property int $id
 * @property VersionSubject $subject_type
 * @property int $subject_id
 * @property int $version_no
 * @property array<string, mixed> $snapshot
 * @property string|null $change_note
 * @property int|null $changed_by
 * @property Carbon $changed_at
 */
class CatalogVersion extends Model
{
    /** Append-only: no created_at/updated_at, only the explicit changed_at. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'subject_type',
        'subject_id',
        'version_no',
        'snapshot',
        'change_note',
        'changed_by',
        'changed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'subject_type' => VersionSubject::class,
            'subject_id' => 'integer',
            'version_no' => 'integer',
            'snapshot' => 'array',
            'changed_by' => 'integer',
            'changed_at' => 'datetime',
        ];
    }
}
