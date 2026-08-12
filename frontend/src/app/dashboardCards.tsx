import type { ComponentType } from 'react'
import { AcademicRecordCard } from '../features/grading/dashboard/AcademicRecordCard'
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
   */
  adminOnly?: boolean
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
 * (persona Cards), and `adminOnly` (the administration set, which an administrator sees
 * *instead of* everything else). The set is empty until the Administration Card ships, so an
 * administrator deliberately lands on a correct, empty Dashboard rather than accumulating
 * every other role's queue and having it taken away later.
 */
export const dashboardCards: DashboardCardEntry[] = [
  // My Academic Record (phase 4.5, ticket 01) — CGPA, latest Semester GPA, earned credit
  // hours. A persona Card on the Student role (R02): an administrator inherits
  // `grades.view_own` but has no Student anchor, so the server 403s the endpoint.
  {
    key: 'myAcademicRecord',
    component: AcademicRecordCard,
    roles: [ROLE.student],
  },
]
