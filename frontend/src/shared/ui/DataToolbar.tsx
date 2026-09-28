import { useState } from 'react'
import { Alert, Button, Pagination, Stack, Typography } from '@mui/material'
import { useTranslation } from 'react-i18next'

export function DataToolbar({
  total,
  page,
  lastPage,
  onPageChange,
  onExport,
}: {
  total: number
  page: number
  lastPage: number
  onPageChange: (page: number) => void
  onExport?: () => Promise<void>
}) {
  const { t } = useTranslation('common')
  const [exporting, setExporting] = useState(false)
  const [failed, setFailed] = useState(false)
  return (
    <Stack spacing={1} sx={{ '@media print': { display: 'none' } }}>
      <Stack direction="row" useFlexGap spacing={2} sx={{ alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography>{t('collection.total', { count: total })}</Typography>
        <Pagination
          page={page}
          count={Math.max(1, lastPage)}
          onChange={(_, value) => onPageChange(value)}
        />
        {onExport && (
          <Button
            disabled={exporting}
            onClick={() => {
              setExporting(true)
              setFailed(false)
              void onExport()
                .catch(() => setFailed(true))
                .finally(() => setExporting(false))
            }}
          >
            {t('collection.export')}
          </Button>
        )}
      </Stack>
      {failed && <Alert severity="error">{t('collection.exportError')}</Alert>}
    </Stack>
  )
}
