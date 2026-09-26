import { Alert, Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

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
  const { t, i18n } = useTranslation('enrollment')
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
          <Trans
            t={t}
            i18nKey="creditDecisions.revokedMeta"
            values={{
              actor: revoked.revoked_by_user_id,
              date: revoked.created_at
                ? new Intl.DateTimeFormat(
                    i18n.language.startsWith('ar') ? 'ar-EG-u-nu-latn' : 'en-GB-u-nu-latn',
                    { dateStyle: 'medium', timeStyle: 'short' },
                  ).format(new Date(revoked.created_at))
                : '',
            }}
            components={{ actor: <bdi dir="ltr" />, date: <bdi dir="ltr" /> }}
          />
        </Typography>
      </Stack>
    </Alert>
  )
}
