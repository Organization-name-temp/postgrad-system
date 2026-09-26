<?php

namespace Modules\Admissions\Public;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface AdmissionDocuments
{
    /** @return list<AdmissionDocumentSummary> */
    public function forApplicant(int $userId): array;

    /** Stream one opaque document only when it belongs to the named applicant. */
    public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse;
}
