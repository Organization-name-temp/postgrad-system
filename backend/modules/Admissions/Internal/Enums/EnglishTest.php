<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The English-proficiency test an applicant reports on the Step-1 snapshot
 * (SRS §4.2 FR-ADM-001). The supported values come from the MVP UML
 * supported-values list; {@see self::None} means the applicant reports no test
 * (and so needs no score — the submit validation makes the score required unless
 * the test is None). Whether a chosen test also makes uploading the IELTS/TOEFL
 * certificate conditionally required is the document layer's concern (ticket 05),
 * not this enum's. Admissions-owned, Internal (ADR-0008).
 */
enum EnglishTest: string
{
    case Ielts = 'IELTS';
    case ToeflIbt = 'TOEFL iBT';
    case ToeflPbt = 'TOEFL PBT';
    case Pte = 'PTE';
    case CambridgeC1 = 'Cambridge C1';
    case CambridgeC2 = 'Cambridge C2';
    case CambridgeB2 = 'Cambridge B2';
    case Duolingo = 'Duolingo';
    case None = 'None';

    /**
     * The official score range `[min, max]` a reported score for this test must fall
     * within — an IELTS band 0–9, a TOEFL iBT 0–120, a TOEFL PBT 310–677, a PTE
     * 10–90, a Duolingo 10–160, a Cambridge (C1/C2/B2) score on the 80–230
     * Cambridge Scale. {@see self::None} carries **no** score, so its range is null
     * (the score is required only for a real test — {@see snapshotRules}).
     *
     * Behaviour on the enum (ADR-0008) so the `english_score` rule and its client Zod
     * mirror (ADR-0014) read one range table rather than each hard-coding per-test
     * bounds. IELTS's half-band step is deliberately not enforced here — the range is
     * the authoritative gate; a stricter step check is out of scope for this pass.
     *
     * @return array{0: float, 1: float}|null
     */
    public function scoreRange(): ?array
    {
        return match ($this) {
            self::Ielts => [0.0, 9.0],
            self::ToeflIbt => [0.0, 120.0],
            self::ToeflPbt => [310.0, 677.0],
            self::Pte => [10.0, 90.0],
            self::CambridgeC1, self::CambridgeC2, self::CambridgeB2 => [80.0, 230.0],
            self::Duolingo => [10.0, 160.0],
            self::None => null,
        };
    }

    /**
     * Whether the given score is valid for this test — within {@see self::scoreRange}.
     * {@see self::None} accepts no score, so any score is invalid for it (the presence
     * rule keeps a None application score-less in the first place).
     */
    public function allowsScore(float $score): bool
    {
        $range = $this->scoreRange();

        return $range !== null && $score >= $range[0] && $score <= $range[1];
    }
}
