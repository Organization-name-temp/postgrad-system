import {
  Alert,
  Box,
  CircularProgress,
  Paper,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { useTranslation } from 'react-i18next'
import { useMyTargetAwareCreditHourSummary } from '../../shared/api/generated/endpoints'
import { tabularNums } from '../../app/theme/fonts'

const metrics = [
  'baseline_required_ch',
  'adjusted_required_ch',
  'attended_ch',
  'earned_ch',
  'transferred_ch',
  'waived_ch',
  'exempted_ch',
  'satisfied_ch',
  'remaining_ch',
] as const

type SummaryRow = { category?: string } & Partial<Record<(typeof metrics)[number], number>>

export function CreditHourSummaryPage() {
  const { t } = useTranslation('academicStanding')
  const { data, isPending, isError } = useMyTargetAwareCreditHourSummary()
  const summary = data?.data
  const rows: SummaryRow[] = summary
    ? [...(summary.categories ?? []), ...(summary.total ? [summary.total] : [])]
    : []

  return (
    <Box>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('creditHours.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {t('creditHours.intro')}
      </Typography>
      {isPending && <CircularProgress aria-label={t('creditHours.loading')} />}
      {isError && <Alert severity="error">{t('creditHours.loadError')}</Alert>}
      {!isPending && !isError && (
        <TableContainer component={Paper} variant="outlined">
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('creditHours.category')}</TableCell>
                {metrics.map((metric) => (
                  <TableCell key={metric} align="center">
                    {t(`creditHours.metrics.${metric}`)}
                  </TableCell>
                ))}
              </TableRow>
            </TableHead>
            <TableBody>
              {rows.map((row) => (
                <TableRow
                  key={row.category}
                  sx={row.category === 'Total' ? { '& th, & td': { fontWeight: 700 } } : undefined}
                >
                  <TableCell component="th" scope="row">
                    {t(`creditHours.categories.${row.category}`)}
                  </TableCell>
                  {metrics.map((metric) => (
                    <TableCell key={metric} align="center" sx={tabularNums}>
                      {row[metric] ?? 0}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
      <Typography variant="body2" color="text.secondary" sx={{ mt: 2 }}>
        {t('creditHours.explanation')}
      </Typography>
    </Box>
  )
}
