import { Box, Chip, Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'

type Transition = {
  id?: number
  action?: string
  reason?: string | null
  current_revision?: boolean
  revision?: number
  created_at?: string
  actor_user_id?: number
}

export function TransferHistory({ transitions }: { transitions?: Transition[] }) {
  const { t, i18n } = useTranslation('enrollment')

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
                <Trans
                  t={t}
                  i18nKey="transfers.revision"
                  values={{ count: transition.revision }}
                  components={{ revision: <bdi dir="ltr" /> }}
                />
              </Typography>
            )}
          </Stack>
          {transition.reason && (
            <Typography variant="body2" color="text.secondary">
              {transition.reason}
            </Typography>
          )}
          {transition.actor_user_id !== undefined && transition.created_at && (
            <Typography variant="caption" color="text.secondary">
              <Trans
                t={t}
                i18nKey="transfers.historyMeta"
                values={{
                  actor: transition.actor_user_id,
                  date: new Intl.DateTimeFormat(`${i18n.language}-u-nu-latn`, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                  }).format(new Date(transition.created_at)),
                }}
                components={{ actor: <bdi dir="ltr" />, date: <bdi dir="ltr" /> }}
              />
            </Typography>
          )}
        </Box>
      ))}
    </Stack>
  )
}
