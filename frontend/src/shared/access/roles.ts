/**
 * Role codes mirrored from the backend `Role` enum
 * (Modules\Identity\Public\Enums\Role). Like the permission keys, the SPA compares
 * these only for UX gating — the API re-checks every request and stays the sole
 * authority (ADR-0008). Lives in `shared` so any feature can gate on a role without
 * reaching into another feature (eslint-plugin-boundaries).
 *
 * Only the roles the UI actually gates on live here. `ADMIN_ROLES` is the pair that
 * opens the Administration surface (System / Super Administrator) — the first
 * role-gated (not permission-gated) surface, ticket 08.
 */
export const ROLE = {
  instructor: 'R04',
  systemAdmin: 'R09',
  superAdmin: 'R10',
} as const

export const ADMIN_ROLES: readonly string[] = [ROLE.systemAdmin, ROLE.superAdmin]
