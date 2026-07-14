<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Admissions\Internal\Enums\DocumentType;

/**
 * A single file uploaded onto an {@see Application} (ticket 05). The bytes live on
 * the private `local` disk at {@see self::$stored_path}; this row is the metadata —
 * the type, the recorded original filename/mime/size, and the {@see self::$ulid}
 * opaque public identifier under which the file is listed and streamed.
 *
 * Route-model-bound by `ulid` (never the integer id), so the streaming-download and
 * remove routes carry the opaque identifier in their path, not a guessable id.
 *
 * @property int $id
 * @property int $application_id
 * @property DocumentType $document_type
 * @property string $ulid
 * @property string $original_filename
 * @property string $stored_path
 * @property string $mime
 * @property int $size_bytes
 * @property Carbon $uploaded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApplicationDocument extends Model
{
    /** @use HasFactory<ApplicationDocumentFactory> */
    use HasFactory;

    /**
     * Only the descriptive metadata is mass-assignable. `ulid`, `uploaded_at`, and
     * `application_id` are set by {@see self::booted()} / the owning relation — the
     * opaque identifier is minted, never supplied.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_type',
        'original_filename',
        'stored_path',
        'mime',
        'size_bytes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ApplicationDocumentFactory
    {
        return ApplicationDocumentFactory::new();
    }

    /**
     * Mint the opaque ULID and stamp the upload time on creation, so callers set
     * only the descriptive metadata and never the identity of the row.
     */
    protected static function booted(): void
    {
        static::creating(function (ApplicationDocument $document): void {
            $document->ulid ??= (string) Str::ulid();
            $document->uploaded_at ??= now();
        });
    }

    /** The ULID is the public identifier the routes bind on, not the integer id. */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
