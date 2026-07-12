import type { ReactNode } from 'react'
import { usePermissions } from './usePermissions'

interface PermissionGateProps {
  permission: string
  children: ReactNode
}

/**
 * Renders its children only when the signed-in user holds the given permission.
 * A UX affordance mirroring the server's authority — the API still enforces
 * access on every request (ADR-0008). Shared so any feature can use it.
 */
export function PermissionGate({ permission, children }: PermissionGateProps) {
  const { hasPermission } = usePermissions()

  if (!hasPermission(permission)) {
    return null
  }

  return <>{children}</>
}
