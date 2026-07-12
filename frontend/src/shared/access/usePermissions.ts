import { useCurrentUser } from '../api/generated/endpoints'

export interface UsePermissions {
  /** Effective permission keys from `/me` (SRS §3.2), mirrored for UX gating. */
  permissions: string[]
  /** Whether the signed-in user holds a given permission key. */
  hasPermission: (permission: string) => boolean
}

/**
 * The signed-in user's effective permissions, read from the shared `/me` query
 * (the same cached query `useAuth` uses — no extra request). A display-only
 * mirror of the server's authority: the API re-checks every request (ADR-0008).
 * Lives in `shared` so any feature can gate without importing another feature.
 */
export function usePermissions(): UsePermissions {
  const me = useCurrentUser()
  const permissions = me.data?.data?.permissions ?? []

  return {
    permissions,
    hasPermission: (permission) => permissions.includes(permission),
  }
}
