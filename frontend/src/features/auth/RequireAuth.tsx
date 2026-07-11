import { Box, CircularProgress } from '@mui/material'
import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './useAuth'

interface RequireAuthProps {
  /** When set, the route also requires this permission key (from `/me`). */
  permission?: string
}

/**
 * Route guard for the authenticated shell. While the `/me` probe resolves it
 * shows a spinner; an unauthenticated visitor is redirected to login. When a
 * `permission` is required, an authenticated user who lacks it is sent back to
 * the dashboard — the server still enforces the rule regardless (ADR-0008).
 */
export function RequireAuth({ permission }: RequireAuthProps = {}) {
  const { isPending, isAuthenticated, hasPermission } = useAuth()

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

  return <Outlet />
}
