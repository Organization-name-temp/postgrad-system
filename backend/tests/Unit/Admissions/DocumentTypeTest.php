<?php

use Modules\Admissions\Internal\Enums\DocumentRequirement;
use Modules\Admissions\Internal\Enums\DocumentType;

/**
 * The DocumentType rules, unit-tested directly (spec §Testing Decisions — the pure
 * per-type policy worth an authoritative test below the HTTP seam). Each type
 * carries its own requirement, size ceiling, accepted formats, and cardinality
 * cap; the upload endpoint (ticket 05) and the finalize completeness check (ticket
 * 06) both read exactly these.
 */
it('classifies the four core documents as always required', function () {
    foreach ([
        DocumentType::GraduationCertificate,
        DocumentType::Transcript,
        DocumentType::NationalId,
        DocumentType::Photo,
    ] as $type) {
        expect($type->requirement())->toBe(DocumentRequirement::Required);
    }
});

it('classifies the english certificate and MSc thesis as conditional', function () {
    expect(DocumentType::EnglishCertificate->requirement())->toBe(DocumentRequirement::Conditional)
        ->and(DocumentType::MscThesis->requirement())->toBe(DocumentRequirement::Conditional);
});

it('classifies the CV, recommendation letters, and additional certificates as optional', function () {
    expect(DocumentType::Cv->requirement())->toBe(DocumentRequirement::Optional)
        ->and(DocumentType::RecommendationLetter->requirement())->toBe(DocumentRequirement::Optional)
        ->and(DocumentType::AdditionalCertificate->requirement())->toBe(DocumentRequirement::Optional);
});

it('caps cardinality per type: 2 recommendation letters, 5 additional certificates, otherwise 1', function () {
    expect(DocumentType::RecommendationLetter->maxCount())->toBe(2)
        ->and(DocumentType::AdditionalCertificate->maxCount())->toBe(5)
        ->and(DocumentType::GraduationCertificate->maxCount())->toBe(1)
        ->and(DocumentType::Photo->maxCount())->toBe(1);
});

it('restricts the personal photo to image formats only', function () {
    expect(DocumentType::Photo->allowedMimes())
        ->toContain('image/jpeg')
        ->toContain('image/png')
        ->not->toContain('application/pdf');
});

it('restricts the CV and thesis to PDF only', function () {
    expect(DocumentType::Cv->allowedMimes())->toBe(['application/pdf'])
        ->and(DocumentType::MscThesis->allowedMimes())->toBe(['application/pdf']);
});

it('accepts scanned PDFs or images for the certificate-style documents', function () {
    expect(DocumentType::GraduationCertificate->allowedMimes())
        ->toContain('application/pdf')
        ->toContain('image/jpeg')
        ->toContain('image/png');
});

it('expresses the size ceiling in kilobytes for the file rule', function () {
    expect(DocumentType::MscThesis->maxSizeKb())->toBe(DocumentType::MscThesis->maxSizeMb() * 1024)
        ->and(DocumentType::Photo->maxSizeKb())->toBe(5 * 1024);
});
