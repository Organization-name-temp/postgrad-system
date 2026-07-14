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
}
