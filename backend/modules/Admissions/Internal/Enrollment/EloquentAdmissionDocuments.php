<?php

namespace Modules\Admissions\Internal\Enrollment;

use Illuminate\Support\Facades\Storage;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Admissions\Public\AdmissionDocumentSummary;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EloquentAdmissionDocuments implements AdmissionDocuments
{
    public function forApplicant(int $userId): array
    {
        return ApplicationDocument::query()
            ->whereHas('application', fn ($query) => $query->where('user_id', $userId))
            ->orderBy('id')
            ->get()
            ->map(static fn (ApplicationDocument $document): AdmissionDocumentSummary => new AdmissionDocumentSummary(
                id: $document->ulid,
                type: $document->document_type->value,
                originalFilename: $document->original_filename,
            ))
            ->all();
    }

    public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse
    {
        $document = ApplicationDocument::query()
            ->where('ulid', $documentId)
            ->whereHas('application', fn ($query) => $query->where('user_id', $userId))
            ->first();

        if ($document === null) {
            return null;
        }

        return Storage::disk('local')->response(
            $document->stored_path,
            $document->original_filename,
            ['Content-Type' => $document->mime],
        );
    }
}
