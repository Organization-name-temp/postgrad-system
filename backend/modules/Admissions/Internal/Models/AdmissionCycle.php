<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\AdmissionCycleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\IntakeTerm;

/**
 * An Admission Cycle — a single windowed intake (one Intake Term of one year,
 * e.g. "Fall 2026") that every Application belongs to (CONTEXT.md, ADR-0012).
 *
 * Whether the Cycle is *open* is derived from {@see self::isOpen()} — it is
 * active and the current moment falls inside its window — never stored, so the
 * flag cannot drift (ROADMAP §Phase 2). A Cycle is deactivated, never deleted.
 *
 * @property int $id
 * @property IntakeTerm $intake_term
 * @property int $year
 * @property string $name_en
 * @property string $name_ar
 * @property Carbon $opens_at
 * @property Carbon $closes_at
 * @property bool $is_active
 * @property-read bool $is_open
 */
class AdmissionCycle extends Model
{
    /** @use HasFactory<AdmissionCycleFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'intake_term',
        'year',
        'name_en',
        'name_ar',
        'opens_at',
        'closes_at',
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
            'intake_term' => IntakeTerm::class,
            'year' => 'integer',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AdmissionCycleFactory
    {
        return AdmissionCycleFactory::new();
    }

    /**
     * Whether the Cycle is currently open: active and the present moment falls
     * within [opens_at, closes_at]. Derived, never stored — the single source of
     * the "open" rule, mirrored by {@see scopeCurrentlyOpen()} so a query and a
     * loaded model always agree (ADR-0012).
     *
     * @return Attribute<bool, never>
     */
    protected function isOpen(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->is_active
                && $this->opens_at->lessThanOrEqualTo(now())
                && $this->closes_at->greaterThanOrEqualTo(now()),
        );
    }

    /**
     * Constrain a query to the Cycles that are currently open — the same rule as
     * {@see self::isOpen()}, expressed in SQL for the public open-cycle lookup.
     *
     * @param  Builder<AdmissionCycle>  $query
     */
    public function scopeCurrentlyOpen(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>=', now());
    }
}
