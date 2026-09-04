<?php

namespace Modules\Catalog\Public\Enums;

/**
 * The stable academic meaning of a Preparatory Curriculum Entry (ADR-0031).
 *
 * Course ids and codes may change between Programs and Curriculum Versions;
 * exemption and progress rules therefore target one of these six roles.
 */
enum PreparatoryRequirementRole: string
{
    case EnglishLanguage = 'EnglishLanguage';
    case ArabicLanguage = 'ArabicLanguage';
    case JapaneseLanguage = 'JapaneseLanguage';
    case JapaneseCulture = 'JapaneseCulture';
    case KeySkills = 'KeySkills';
    case DepartmentalRequirement = 'DepartmentalRequirement';
}
