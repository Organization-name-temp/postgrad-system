import { Box } from '@mui/material'
import { useTranslation } from 'react-i18next'

/**
 * The E-JUST lockup for the app bar. On dark surfaces it sits on a white chip so
 * the black form stays legible — the Phase 0.5 approach while there's no
 * transparent/knockout asset (design-system.md §1). The logo is never mirrored
 * in RTL.
 */
export function AppBrand() {
  const { t } = useTranslation('common')
  return (
    <Box
      sx={[
        { display: 'inline-flex', alignItems: 'center', borderRadius: 1 },
        (theme) =>
          theme.applyStyles('dark', {
            backgroundColor: theme.vars.palette.common.white,
            padding: theme.spacing(0.25, 0.75),
          }),
      ]}
    >
      <Box
        component="img"
        src="/name.png"
        alt={t('appName')}
        sx={{ height: 28, display: 'block' }}
      />
    </Box>
  )
}
