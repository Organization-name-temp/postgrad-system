import type { ComponentType } from 'react'
import { MyApplicationCard } from '../features/admissions/dashboard/MyApplicationCard'
import { ScreeningQueueCard } from '../features/admissions/dashboard/ScreeningQueueCard'
import { CatalogSummaryCard } from '../features/catalog/dashboard/CatalogSummaryCard'
import { AdviseeRequestsCard } from '../features/enrollment/dashboard/AdviseeRequestsCard'
import { MyRegistrationCard } from '../features/enrollment/dashboard/MyRegistrationCard'
import { AcademicRecordCard } from '../features/grading/dashboard/AcademicRecordCard'
import { MyGradeSheetsCard } from '../features/grading/dashboard/MyGradeSheetsCard'
import { SheetsAwaitingReviewCard } from '../features/grading/dashboard/SheetsAwaitingReviewCard'
import { SemesterWindowsCard } from '../features/admin/dashboard/SemesterWindowsCard'
import { PERMISSION } from '../shared/access/permissions'
import { ROLE } from '../shared/access/roles'

export interface DashboardCardEntry {
  /** Stable identity for the grid's React key and for tests to name a Card by. */
  key: string
  /**
   * The Card component, supplied by the feature that owns its data. It fetches and fails on
   * its own, so one Card erroring never costs the user the rest of the grid.
   */
  component: ComponentType
  /** Effective permission required to see this Card — the default gate. */
  permission?: string
  /**
   * Role codes any of which reveals the Card — the **persona** gate. A Card whose data hangs
   * off a record the acting user may not possess (a Student anchor, an Applicant's
   * Application) gates on the Role, never on the Permission an administrator inherits under
   * ADR-0008, or it would render broken for them. The existing nav convention, applied to
   * Cards (ADR-0024).
   */
  roles?: readonly string[]
  /**
   * Membership of the **administration set**: the only Cards a System/Super Administrator
   * (R09/R10) sees. Holding every Permission is an authorization fact, not a description of
   * an administrator's job, so they get an explicit set rather than the union of everyone
   * else's queues.
   *
   * Membership is *orthogonal* to the gate above, because the administrator's set is not all
   * administrator-exclusive: the two configuration Cards (phase 4.5, ticket 06) belong to it
   * **and** are seen by the Student Affairs / Dean holders of `calendar.manage` /
   * `catalog.manage` the §3.2 matrix gives them to. So this flag says what an administrator
   * sees, and `permission`/`roles` say what everyone else sees; a Card exclusive to
   * administrators sets `adminSet` and gates on the `ADMIN_ROLES` pair, which is what refuses
   * it to everyone else.
   */
  adminSet?: boolean
}

/**
 * The permission-to-Card registry (ADR-0024) — a **declarative table**, beside
 * {@link ./nav.tsx}'s navigation table and read the same way. It is *data, not logic*: there
 * is deliberately no selection function, because the table is the specification and
 * `DashboardPage` reads it directly. Adding a Card is one row here plus one component inside
 * the owning feature; Phase 5 and Phase 6 extend the Dashboard without touching each other.
 *
 * A Card exists here because a **feature shipped it**. Permission is a Card's gate, never its
 * trigger — the Permissions whose features arrive in later phases contribute no row and no
 * placeholder.
 *
 * Three gate kinds, all understood by the page: a single `permission` (the default), `roles`
 * (persona Cards), and `adminSet` (the administration set, which an administrator sees
 * *instead of* everything else).
 */
export const dashboardCards: DashboardCardEntry[] = [
  // My Application (phase 4.5, ticket 03) — the Applicant's own Application status, next
  // action and a link into its timeline. A persona Card on the Applicant role (R01): an
  // administrator inherits `application.view_status` but has no Application.
  {
    key: 'myApplication',
    component: MyApplicationCard,
    roles: [ROLE.applicant],
  },

  // Screening Queue (phase 4.5, ticket 03) — pending and overdue counts with the three
  // oldest, closing SRS FR-ADM-005.5. Student Affairs / Dean hold `application.screen`.
  {
    key: 'screeningQueue',
    component: ScreeningQueueCard,
    permission: PERMISSION.screenApplications,
  },

  // My Registration (phase 4.5, ticket 04) — this Semester's credit-hour load against the
  // 6–12 bounds, the open period and its deadline. A persona Card on the Student role (R02):
  // an administrator inherits `courses.register_self` but has no Student anchor.
  {
    key: 'myRegistration',
    component: MyRegistrationCard,
    roles: [ROLE.student],
  },

  // Advisee Requests (phase 4.5, ticket 04) — the registration requests waiting on the acting
  // reviewer, scoped to their own advisees server-side. Advisor / Student Affairs hold
  // `registration.approve`.
  {
    key: 'adviseeRequests',
    component: AdviseeRequestsCard,
    permission: PERMISSION.approveCourseRegistration,
  },

  // My Grade Sheets (phase 4.5, ticket 05) — the current teaching term's Offerings, how many
  // Sheets are not yet Finalized, and the passed-deadline flag. Instructor / Student Affairs
  // hold `grades.enter`; a central-entry user's list is every Offering, as their existing
  // Grade Sheets screen already is.
  {
    key: 'myGradeSheets',
    component: MyGradeSheetsCard,
    permission: PERMISSION.enterGrades,
  },

  // Sheets Awaiting Review (phase 4.5, ticket 05) — the Department Head's review queue,
  // scoped by the Head↔Department assignment server-side. Gated on `grades.finalize`, which
  // Instructors hold too (§3.2) — they see the Card's empty state, because the assignment,
  // not the Permission, is what fills it.
  {
    key: 'sheetsAwaitingReview',
    component: SheetsAwaitingReviewCard,
    permission: PERMISSION.finalizeGrades,
  },

  // My Academic Record (phase 4.5, ticket 01) — CGPA, latest Semester GPA, earned credit
  // hours. A persona Card on the Student role (R02): an administrator inherits
  // `grades.view_own` but has no Student anchor, so the server 403s the endpoint.
  {
    key: 'myAcademicRecord',
    component: AcademicRecordCard,
    roles: [ROLE.student],
  },

  // Semester & Open Windows and Catalog Summary (phase 4.5, ticket 06) — the two Cards about
  // how the system is *configured* rather than about a queue of work. Both are members of the
  // administration set **and** permission-gated for the §3.2 holders of `calendar.manage` /
  // `catalog.manage`, which is the case `adminSet` exists to express.
  {
    key: 'semesterWindows',
    component: SemesterWindowsCard,
    permission: PERMISSION.manageCalendar,
    adminSet: true,
  },
  {
    key: 'catalogSummary',
    component: CatalogSummaryCard,
    permission: PERMISSION.manageCatalog,
    adminSet: true,
  },
]
