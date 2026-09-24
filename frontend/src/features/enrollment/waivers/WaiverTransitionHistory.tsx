import { Box, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

type Verification = {
  coverage_percentage?: number
  equivalency_note?: string
  verified_by?: number
  verified_at?: string
}

type Transition = {
  action?: string
  current_revision?: boolean
  reason?: string | null
  verification_snapshot?: Verification | null
  created_at?: string
}

export function WaiverTransitionHistory({ transitions }: { transitions?: Transition[] }) {
  const { t, i18n } = useTranslation('enrollment')
  const locale = i18n.resolvedLanguage?.startsWith('ar') ? 'ar-u-nu-latn' : 'en-u-nu-latn'

  return (
    <Stack spacing={1}>
      <Typography variant="subtitle2">{t('waivers.history')}</Typography>
      {(transitions ?? []).map((transition, index) => {
        const label = t(`waivers.actions.${transition.action}`)
        const verification = transition.verification_snapshot
        return (
          <Box key={`${transition.action}-${transition.created_at ?? index}`}>
            <Typography variant="body2">
              {transition.current_revision ? label : t('waivers.previousReview', { action: label })}
            </Typography>
            {transition.created_at && (
              <Typography variant="caption" color="text.secondary">
                <bdi dir="ltr">
                  {new Intl.DateTimeFormat(locale, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  }).format(new Date(transition.created_at))}
                </bdi>
              </Typography>
            )}
            {transition.reason && (
              <Typography color="text.secondary">{transition.reason}</Typography>
            )}
            {verification && (
              <Stack spacing={0.25} sx={{ mt: 0.5 }}>
                <Typography variant="body2">
                  <bdi dir="ltr">{verification.coverage_percentage}%</bdi>
                </Typography>
                {verification.equivalency_note && (
                  <Typography variant="body2" color="text.secondary">
                    {verification.equivalency_note}
                  </Typography>
                )}
              </Stack>
            )}
          </Box>
        )
      })}
    </Stack>
  )
}
