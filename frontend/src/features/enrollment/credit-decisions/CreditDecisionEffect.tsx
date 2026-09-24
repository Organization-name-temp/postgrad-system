import { Alert, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

type Revocation = {
  revoked_by_user_id?: number | null
  reason?: string
  created_at?: string
}

export function CreditDecisionEffect({
  effective,
  revocation,
  children,
}: {
  effective?: boolean
  revocation?: unknown
  children: React.ReactNode
}) {
  const { t } = useTranslation('enrollment')
  const revoked = (revocation ?? null) as Revocation | null

  if (effective !== false || revoked === null) {
    return <Alert severity="success">{children}</Alert>
  }

  return (
    <Alert severity="warning">
      <Stack spacing={0.5}>
        <Typography component="span" sx={{ fontWeight: 'bold' }}>
          {t('creditDecisions.revoked')}
        </Typography>
        <Typography component="span">{revoked.reason}</Typography>
        <Typography component="span" variant="caption">
          {t('creditDecisions.revokedBy')} <bdi dir="ltr">{revoked.revoked_by_user_id}</bdi>
          {' · '}
          {t('creditDecisions.revokedAt')}{' '}
          <bdi dir="ltr">
            {revoked.created_at
              ? new Intl.DateTimeFormat('en-GB', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                }).format(new Date(revoked.created_at))
              : ''}
          </bdi>
        </Typography>
      </Stack>
    </Alert>
  )
}
