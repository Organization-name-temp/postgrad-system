import { Box, CircularProgress } from '@mui/material'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './useAuth'

interface RequireAuthProps {
  /** When set, the route also requires this permission key (from `/me`). */
  permission?: string
  /**
   * When set, the route requires the user to hold one of these Role codes — a
   * **persona** guard (e.g. `/apply` is for actual Applicants, R01), distinct from a
   * capability `permission` an admin would inherit (ADR-0015, ticket 11).
   */
  roles?: readonly string[]
}

/**
 * Route guard for the authenticated shell. While the `/me` probe resolves it
 * shows a spinner; an unauthenticated visitor is redirected to login. When a
 * `permission` or `roles` requirement is set, an authenticated user who does not
 * satisfy it is sent back to the dashboard — the server still enforces the rule
 * regardless (ADR-0008).
 */
export function RequireAuth({ permission, roles }: RequireAuthProps = {}) {
  const { isPending, isAuthenticated, hasPermission, hasRole } = useAuth()

  if (isPending) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', py: 8 }}>
        <CircularProgress aria-label="Checking your session" />
      </Box>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  if (permission && !hasPermission(permission)) {
    return <Navigate to="/" replace />
  }

  if (roles && !roles.some((role) => hasRole(role))) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}
