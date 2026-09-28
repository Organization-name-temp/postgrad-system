/**
 * Permission keys mirrored from the backend `Permission` enum
 * (Modules\Identity\Public\Enums\Permission). The SPA compares these keys for
 * UX gating only — the API re-checks every request and remains the sole
 * authority (ADR-0008). Lives in `shared` so every feature can gate on it
 * without reaching into another feature (eslint-plugin-boundaries). Only the
 * keys the UI actually gates on live here.
 */
export const PERMISSION = {
  monitorAcademicStanding: 'standing.monitor',
  screenApplications: 'application.screen',
  scheduleInterview: 'interview.schedule',
  decideApplicants: 'applicant.decide',
  approveCourseRegistration: 'registration.approve',
  registerCoursesForStudent: 'courses.register_for_student',
  viewStudentGrades: 'grades.view_student',
  // Held by Instructors and Student Affairs (§3.2) — the mark-entry gate, and so the gate on
  // the My Grade Sheets Dashboard Card, whose list is the acting user's own Offerings or, for
  // a central-entry user, every Offering (phase 4.5, ticket 05).
  enterGrades: 'grades.enter',
  // Held by Instructors and Department Heads, never by Student Affairs (§3.2) — which is
  // exactly what tells the sheet editor whether the signed-in user could ever sign the sheet
  // they are keying marks into (phase 4 ticket 09).
  finalizeGrades: 'grades.finalize',
  viewCatalog: 'catalog.view',
  manageCatalog: 'catalog.manage',
  manageCalendar: 'calendar.manage',
  submitExemption: 'exemption.submit',
  approveExemption: 'exemption.approve',
  submitTransferWaiver: 'transfer_waiver.submit',
  approveTransferWaiver: 'transfer_waiver.approve',
} as const

export type PermissionCode = (typeof PERMISSION)[keyof typeof PERMISSION]
