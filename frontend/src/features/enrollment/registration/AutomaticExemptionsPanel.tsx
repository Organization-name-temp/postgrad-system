import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined'
import { Alert, Box, Button, Divider, Paper, Skeleton, Stack, Typography } from '@mui/material'
import { Trans, useTranslation } from 'react-i18next'
import type { MyEffectiveExemptions200DataItem } from '../../../shared/api/generated/endpoints'

interface AutomaticExemptionsPanelProps {
  exemptions: MyEffectiveExemptions200DataItem[]
  isPending: boolean
  isError: boolean
  isReconciling: boolean
  onRetry: () => void
}

/** Effective automatic decisions are visible before registration choices begin. */
export function AutomaticExemptionsPanel({
  exemptions,
  isPending,
  isError,
  isReconciling,
  onRetry,
}: AutomaticExemptionsPanelProps) {
  const { t } = useTranslation('enrollment')

  return (
    <Paper
      component="section"
      aria-labelledby="automatic-exemptions-heading"
      variant="outlined"
      sx={{ borderRadius: 3, p: { xs: 2, sm: 3 }, mb: 3 }}
    >
      <Typography id="automatic-exemptions-heading" variant="h6" component="h2">
        {t('exemptions.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5, mb: 2 }}>
        {t('exemptions.intro')}
      </Typography>
      {isPending && (
        <Stack spacing={1} role="status" aria-label={t('exemptions.loading')}>
          <Skeleton variant="text" width="55%" />
          <Skeleton variant="text" width="80%" />
        </Stack>
      )}
      {isError && (
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={onRetry}>
              {t('exemptions.retry')}
            </Button>
          }
        >
          {t(isReconciling ? 'exemptions.pending' : 'exemptions.loadError')}
        </Alert>
      )}
      {!isPending && !isError && exemptions.length === 0 && (
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <InfoOutlinedIcon aria-hidden="true" color="action" />
          <Typography color="text.secondary">{t('exemptions.empty')}</Typography>
        </Stack>
      )}
      {!isPending && !isError && exemptions.length > 0 && (
        <Stack divider={<Divider flexItem />} spacing={1.5}>
          {exemptions.map((exemption) => (
            <Stack key={exemption.id ?? exemption.target_course_id} spacing={0.25}>
              <Typography variant="subtitle2">
                <Trans
                  t={t}
                  i18nKey={`exemptions.items.${exemption.preparatory_requirement_role}`}
                  values={{
                    course: exemption.target_course_code,
                    count: exemption.credit_hours,
                  }}
                  components={{
                    code: <Box component="span" dir="ltr" />,
                    hours: <Box component="span" dir="ltr" />,
                  }}
                />
              </Typography>
              {(exemption.reason_codes ?? []).map((reason) => (
                <Typography key={reason} variant="body2" color="text.secondary">
                  {t(`exemptions.reasons.${reason}`)}
                </Typography>
              ))}
            </Stack>
          ))}
        </Stack>
      )}
    </Paper>
  )
}
