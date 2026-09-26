export const PREPARATORY_REQUIREMENT_ROLES = [
  'EnglishLanguage',
  'ArabicLanguage',
  'JapaneseLanguage',
  'JapaneseCulture',
  'KeySkills',
  'DepartmentalRequirement',
] as const

export type PreparatoryRequirementRole = (typeof PREPARATORY_REQUIREMENT_ROLES)[number]

export function isPreparatoryRequirementRole(
  value: string | null | undefined,
): value is PreparatoryRequirementRole {
  return PREPARATORY_REQUIREMENT_ROLES.some((role) => role === value)
}
