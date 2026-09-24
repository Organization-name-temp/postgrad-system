<?php

namespace Modules\Admissions\Public;

final readonly class AdmissionDocumentSummary
{
    public function __construct(
        public string $id,
        public string $type,
        public string $originalFilename,
    ) {}
}
