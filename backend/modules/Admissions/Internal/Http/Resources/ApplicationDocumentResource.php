<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\ApplicationDocument;

/**
 * An uploaded {@see ApplicationDocument} in the { data: ... } envelope. Its public
 * `id` is the opaque ULID (never the integer row id), and `download_url` points at
 * the Sanctum-authed streaming route — so the SPA lists, previews, and removes a
 * document without ever seeing its storage path or database id.
 *
 * @mixin ApplicationDocument
 */
class ApplicationDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ApplicationDocument $document */
        $document = $this->resource;

        return [
            'id' => $document->ulid,
            'document_type' => $document->document_type->value,
            'original_filename' => $document->original_filename,
            'mime' => $document->mime,
            'size_bytes' => $document->size_bytes,
            'uploaded_at' => $document->uploaded_at->toIso8601String(),
            'download_url' => route('admissions.documents.download', ['document' => $document->ulid]),
        ];
    }
}
