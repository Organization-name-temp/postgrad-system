import { Box, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

type ExemptionTransition = {
  action?: string
  current_revision?: boolean
  reason?: string | null
}

export function ExemptionTransitionHistory({
  transitions,
}: {
  transitions?: ExemptionTransition[]
}) {
  const { t } = useTranslation('enrollment')

  return (
    <>
      <Typography variant="subtitle2">{t('manualExemptions.history')}</Typography>
      {(transitions ?? []).map((transition, index) => (
        <Box key={`${transition.action}-${index}`}>
          <Typography variant="body2">
            {transition.current_revision
              ? t(`manualExemptions.actions.${transition.action}`)
              : t('manualExemptions.previousReview', {
                  action: t(`manualExemptions.actions.${transition.action}`),
                })}
          </Typography>
          {transition.reason && (
            <Typography variant="body2" color="text.secondary">
              {transition.reason}
            </Typography>
          )}
        </Box>
      ))}
    </>
  )
}
