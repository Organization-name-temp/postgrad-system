import { Chip, Paper, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useAuth } from './useAuth'

/**
 * The authenticated landing. Shows the signed-in identity, the roles the user
 * holds, and their effective permissions. (Phase 0's permission-gated demo widget
 * was retired when the real Catalog module landed — its "Browse catalog" nav item
 * is now the permission-gated surface.)
 */
export function DashboardPage() {
  const { t } = useTranslation('auth')
  const { user, permissions } = useAuth()

  // RequireAuth guarantees an authenticated user before this renders.
  if (!user) {
    return null
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('dashboard.title')}
      </Typography>

      <Paper variant="outlined" sx={{ p: 3, mt: 2 }}>
        <Typography variant="h6">{user.name}</Typography>
        <Typography variant="body2" color="text.secondary" gutterBottom>
          {user.email}
        </Typography>

        <Typography variant="subtitle2" sx={{ mt: 2 }}>
          {t('dashboard.roles')}
        </Typography>
        <Stack direction="row" sx={{ mt: 1, flexWrap: 'wrap', gap: 1 }}>
          {user.roles?.map((role) => (
            <Chip key={role.code} label={`${role.code} · ${role.name}`} />
          ))}
        </Stack>

        <Typography variant="subtitle2" sx={{ mt: 3 }}>
          {t('dashboard.permissions')}
        </Typography>
        <Stack direction="row" sx={{ mt: 1, flexWrap: 'wrap', gap: 1 }}>
          {permissions.map((permission) => (
            <Chip key={permission} label={permission} size="small" variant="outlined" />
          ))}
        </Stack>
      </Paper>
    </>
  )
}
