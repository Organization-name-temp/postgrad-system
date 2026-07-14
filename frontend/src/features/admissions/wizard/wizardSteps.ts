/**
 * The applicant wizard's steps and how the server's Application status maps onto
 * them. The status is the single source of truth (the API drives the workflow);
 * the SPA only derives which step to show, so a reload resumes where the applicant
 * left off. Ticket 04 builds the first two steps (personal info + email verify);
 * documents, program selection, and review arrive with later tickets, shown here
 * as upcoming so the wizard's shape is visible from the start.
 */
export const WIZARD_STEP_KEYS = [
  'personalInfo',
  'verifyEmail',
  'documents',
  'program',
  'review',
] as const

export type WizardStepKey = (typeof WIZARD_STEP_KEYS)[number]

export const WIZARD_STEP = {
  personalInfo: 0,
  verifyEmail: 1,
  documents: 2,
  program: 3,
  review: 4,
} as const

/**
 * The active step for a given Application status. `draft` is still filling Step 1;
 * `submitted` is awaiting email verification; anything past that (`email_verified`
 * onward) has cleared the ticket-04 steps and rests at documents — the next
 * ticket's surface. An unknown/absent status starts at the beginning.
 */
export function stepForStatus(status: string | undefined): number {
  switch (status) {
    case 'draft':
      return WIZARD_STEP.personalInfo
    case 'submitted':
      return WIZARD_STEP.verifyEmail
    case undefined:
      return WIZARD_STEP.personalInfo
    default:
      return WIZARD_STEP.documents
  }
}
