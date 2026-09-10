import { Box, Chip, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

type Transition = {
  id?: number
  action?: string
  reason?: string | null
  current_revision?: boolean
  revision?: number
  created_at?: string
}

export function TransferHistory({ transitions }: { transitions?: Transition[] }) {
  const { t } = useTranslation('enrollment')

  return (
    <Stack spacing={1}>
      <Typography variant="subtitle2">{t('transfers.history')}</Typography>
      {(transitions ?? []).map((transition, index) => (
        <Box key={transition.id ?? `${transition.action}-${index}`}>
          <Stack direction="row" spacing={1} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
            <Typography variant="body2">{t(`transfers.actions.${transition.action}`)}</Typography>
            {!transition.current_revision && (
              <Chip size="small" label={t('transfers.previousRevision')} />
            )}
            {transition.revision && (
              <Typography variant="caption" color="text.secondary">
                {t('transfers.revision', { count: transition.revision })}
              </Typography>
            )}
          </Stack>
          {transition.reason && (
            <Typography variant="body2" color="text.secondary">
              {transition.reason}
            </Typography>
          )}
        </Box>
      ))}
    </Stack>
  )
}
