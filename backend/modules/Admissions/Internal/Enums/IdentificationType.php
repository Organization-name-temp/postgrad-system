<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * How an Applicant is identified on their Application (CONTEXT.md) — an Egyptian
 * citizen's 14-digit **National ID**, or an alphanumeric **Passport** number for a
 * non-Egyptian / overseas Applicant. Chosen on the personal-info step; it selects
 * the format the identifier (`national_id`) is validated against, on both the client
 * (Zod) and the server (the authority). The identifier itself stays unique within an
 * Admission Cycle whichever type is chosen (BR-03).
 *
 * Each case **carries its own format**, in the same behaviour-on-the-enum style as
 * {@see DocumentType} and the other Admissions enums (ADR-0008); Admissions-owned and
 * Internal. Not to be confused with {@see DocumentType::NationalId}, the uploaded-file
 * kind that happens to share the word.
 */
enum IdentificationType: string
{
    case NationalId = 'national_id';
    case Passport = 'passport';

    /**
     * Whether the given identifier matches this type's format — a National ID is
     * exactly 14 digits (`^\d{14}$`); a Passport is 6–9 alphanumeric characters
     * (`^[A-Za-z0-9]{6,9}$`). The authoritative check the `national_id` rule calls;
     * the client Zod schema mirrors the same two patterns (ADR-0014).
     */
    public function matches(string $identifier): bool
    {
        return preg_match($this->pattern(), $identifier) === 1;
    }

    /** The delimited PCRE pattern for this type's identifier format. */
    private function pattern(): string
    {
        return match ($this) {
            self::NationalId => '/^\d{14}$/',
            self::Passport => '/^[A-Za-z0-9]{6,9}$/',
        };
    }
}
