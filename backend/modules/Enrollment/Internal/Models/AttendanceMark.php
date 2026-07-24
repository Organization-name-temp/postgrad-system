<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\AttendanceMarkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One binary present/absent mark for a Student's {@see Enrollment} at one
 * {@see ClassSession} (ADR-0019, ticket 08) — the auditable unit the derived attendance
 * percentage tallies. Recorded and correctable by the Offering's assigned Instructor; a save
 * upserts on (session, enrollment), so a correction overwrites rather than duplicating. No
 * late/excused nuance — attendance policy is out of scope.
 *
 * @property int $id
 * @property int $class_session_id
 * @property int $enrollment_id
 * @property bool $present
 */
class AttendanceMark extends Model
{
    /** @use HasFactory<AttendanceMarkFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'class_session_id',
        'enrollment_id',
        'present',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'class_session_id' => 'integer',
            'enrollment_id' => 'integer',
            'present' => 'boolean',
        ];
    }

    protected static function newFactory(): AttendanceMarkFactory
    {
        return AttendanceMarkFactory::new();
    }

    /**
     * The session this mark was taken at.
     *
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * The Enrollment this mark is about.
     *
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }
}
