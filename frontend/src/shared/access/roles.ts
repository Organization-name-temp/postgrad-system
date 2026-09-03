/**
 * Role codes mirrored from the backend `Role` enum
 * (Modules\Identity\Public\Enums\Role). Like the permission keys, the SPA compares
 * these only for UX gating — the API re-checks every request and stays the sole
 * authority (ADR-0008). Lives in `shared` so any feature can gate on a role without
 * reaching into another feature (eslint-plugin-boundaries).
 *
 * `applicant`, `student` and `instructor` gate the **persona** nav items — "My
 * application", "My registration", "My interviews" — on the user's actual Role, not on
 * an inherited Permission, so an admin (who holds every Permission, ADR-0008) does not
 * see another role's home screen (ADR-0015, ticket 11). A Student's registration screen
 * is one such surface: an admin inherits `courses.register_self` but has no Student
 * record, so it gates on the Role (Phase 3, ticket 04).
 */
export const ROLE = {
  applicant: 'R01',
  student: 'R02',
  advisor: 'R03',
  instructor: 'R04',
  studentAffairs: 'R05',
  departmentHead: 'R06',
  dean: 'R07',
  council: 'R08',
  systemAdmin: 'R09',
  superAdmin: 'R10',
} as const

export type RoleCode = (typeof ROLE)[keyof typeof ROLE]

export const ADMIN_ROLES: readonly RoleCode[] = [ROLE.systemAdmin, ROLE.superAdmin]
