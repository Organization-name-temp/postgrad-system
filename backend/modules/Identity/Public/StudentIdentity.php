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
    ) {}
}
