<?php

namespace Modules\Identity\Public;

/**
 * The personal-identity snapshot carried into the acceptance mint and echoed back
 * on a {@see StudentSummary} (ADR-0013). Admissions captures these on the per-cycle
 * Application; on acceptance they are copied onto the Identity-owned `Student` anchor
 * so a Student's identity is fixed at admission and does not follow later edits to a
 * (by then archived) Application.
 *
 * Every field is a plain scalar — `gender` and `date_of_birth` arrive as their string
 * forms — so the snapshot crosses the seam without dragging any Admissions enum into
 * Identity (the module owning `Gender` is Admissions; Identity never references it).
 * An immutable value object.
 */
final readonly class StudentIdentity
{
    public function __construct(
        public string $nameEn,
        public string $nameAr,
        public string $nationalId,
        public string $dateOfBirth,
        public string $nationality,
        public string $gender,
        public ?string $identificationType = null,
    ) {}

    public function ready(): bool
    {
        return in_array($this->identificationType, ['national_id', 'passport'], true)
            && trim($this->nameEn) !== '' && trim($this->nameAr) !== '' && trim($this->nationalId) !== '';
    }

    /** @return array{name_en: string, name_ar: string, identification_number: string, identification_type: string|null, ready: bool} */
    public function toArray(): array
    {
        return [
            'name_en' => $this->nameEn,
            'name_ar' => $this->nameAr,
            'identification_number' => $this->nationalId,
            'identification_type' => $this->identificationType,
            'ready' => $this->ready(),
        ];
    }
}
