import type { ReactNode } from 'react'
import { Alert, Box, Stack } from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useAuth } from '../auth/useAuth'

/** Keeps academic history visible while disabling mutation controls after termination. */
export function LifecycleReadOnly({ children }: { children: ReactNode }) {
  const { t } = useTranslation('common')
  const { user } = useAuth()
  const terminated = user?.student?.lifecycle_status === 'Terminated'

  if (!terminated) return children

  return (
    <Stack spacing={2}>
      <Alert severity="warning">{t('lifecycle.terminatedReadOnly')}</Alert>
      <Box
        component="fieldset"
        disabled
        sx={{ border: 0, m: 0, minWidth: 0, p: 0 }}
        aria-label={t('lifecycle.readOnlyContent')}
      >
        {children}
      </Box>
    </Stack>
  )
}
