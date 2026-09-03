import { useCurrentUser } from '../api/generated/endpoints'
import { ADMIN_ROLES } from './roles'

export interface UsePermissions {
  /** Effective permission keys from `/me` (SRS §3.2), mirrored for UX gating. */
  permissions: string[]
  /** Whether the signed-in user holds a given permission key. */
  hasPermission: (permission: string) => boolean
  /** The signed-in user's role codes from `/me` (e.g. 'R09'), mirrored for UX gating. */
  roles: string[]
  /** Whether the signed-in user holds a given role code. */
  hasRole: (role: string) => boolean
  /** Whether the user is a System/Super Administrator (R09/R10) — the admin gate. */
  isAdmin: boolean
}

/**
 * The signed-in user's effective permissions and roles, read from the shared `/me`
 * query (the same cached query `useAuth` uses — no extra request). A display-only
 * mirror of the server's authority: the API re-checks every request (ADR-0008).
 * Lives in `shared` so any feature can gate without importing another feature.
 *
 * Roles are surfaced alongside permissions because the Administration surface
 * (ticket 08) is gated by **role** (R09/R10), not by a permission — admins hold
 * every permission, so a permission check could not tell an admin apart from an
 * ordinary reviewer (ADR-0008).
 */
export function usePermissions(): UsePermissions {
  const me = useCurrentUser()
  const permissions = me.data?.data?.permissions ?? []
  const roles = (me.data?.data?.roles ?? []).map((role) => role.code ?? '').filter(Boolean)

  return {
    permissions,
    hasPermission: (permission) => permissions.includes(permission),
    roles,
    hasRole: (role) => roles.includes(role),
    isAdmin: roles.some((role) => ADMIN_ROLES.some((adminRole) => adminRole === role)),
  }
}
