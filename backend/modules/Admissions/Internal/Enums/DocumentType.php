<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The kinds of document an applicant uploads with their application (SRS §4.2
 * FR-ADM-003, BR-66…69). Each case **carries its own rules** — how strongly it is
 * required, its size ceiling, the formats it accepts, and how many copies may be
 * uploaded — so the upload endpoint (ticket 05) and the finalize completeness
 * check (ticket 06) read one authority rather than scattering the policy. A closed,
 * code-owned set in the same style as the other Admissions enums (ADR-0008);
 * Admissions-owned and Internal.
 *
 * The graduation certificate, transcript, national ID/passport, and personal photo
 * are always required; the English-proficiency certificate and the MSc thesis are
 * {@see DocumentRequirement::Conditional} (owed only by, respectively, an applicant
 * who reported a test and a PhD applicant — resolved at finalize, not here); the CV,
 * recommendation letters (up to two), and additional certificates (up to five) are
 * optional.
 */
enum DocumentType: string
{
    case GraduationCertificate = 'graduation_certificate';
    case Transcript = 'transcript';
    case NationalId = 'national_id';
    case Photo = 'photo';
    case EnglishCertificate = 'english_certificate';
    case MscThesis = 'msc_thesis';
    case Cv = 'cv';
    case RecommendationLetter = 'recommendation_letter';
    case AdditionalCertificate = 'additional_certificate';

    /** A scanned document: PDF or a photographed page. */
    private const SCAN_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** A photograph only (the passport-style personal photo). */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png'];

    /** A text document only (CV / thesis are expected as PDFs). */
    private const PDF_MIMES = ['application/pdf'];

    /** How strongly this document is required (see {@see DocumentRequirement}). */
    public function requirement(): DocumentRequirement
    {
        return match ($this) {
            self::GraduationCertificate, self::Transcript, self::NationalId, self::Photo => DocumentRequirement::Required,
            self::EnglishCertificate, self::MscThesis => DocumentRequirement::Conditional,
            self::Cv, self::RecommendationLetter, self::AdditionalCertificate => DocumentRequirement::Optional,
        };
    }

    /** The largest a single upload of this type may be, in megabytes. */
    public function maxSizeMb(): int
    {
        return match ($this) {
            self::MscThesis => 20,
            self::NationalId, self::Photo => 5,
            default => 10,
        };
    }

    /**
     * The MIME types this document accepts. Validated against the uploaded file's
     * real content type (a bad format → 422).
     *
     * @return list<string>
     */
    public function allowedMimes(): array
    {
        return match ($this) {
            self::Photo => self::IMAGE_MIMES,
            self::MscThesis, self::Cv => self::PDF_MIMES,
            default => self::SCAN_MIMES,
        };
    }

    /**
     * How many copies of this type an application may hold. Most types are singular
     * (a replacement removes the old one first); recommendation letters allow two
     * and additional certificates five (BR-68). An upload past the cap → 422.
     */
    public function maxCount(): int
    {
        return match ($this) {
            self::RecommendationLetter => 2,
            self::AdditionalCertificate => 5,
            default => 1,
        };
    }

    /** The size ceiling expressed in kilobytes, for Laravel's `max` file rule. */
    public function maxSizeKb(): int
    {
        return $this->maxSizeMb() * 1024;
    }
}
