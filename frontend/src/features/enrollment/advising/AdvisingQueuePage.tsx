import {
  Alert,
  Button,
  CircularProgress,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material'
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useTheAdvisorsRegistrationQueue } from '../../../shared/api/generated/endpoints'
import type { TheAdvisorsRegistrationQueue200DataItem as QueueItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

/**
 * The Advisor's registration-review queue (features/enrollment, ticket 06): the Pending
 * requests awaiting the signed-in reviewer, oldest-first. An Advisor sees their own
 * advisees (live-scoped, server-side); Student Affairs and administrators see every
 * advisor's advisees unscoped. Opening a row goes to the line-item review screen. Gated on
 * `registration.approve` (route + API).
 */
export function AdvisingQueuePage() {
  const { t, i18n } = useTranslation('enrollment')
  const { data, isPending, isError } = useTheAdvisorsRegistrationQueue()
  const requests = data?.data ?? []

  const programName = (item: QueueItem): string => {
    const program = item.student?.program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  }

  const submittedOn = (iso?: string): string =>
    iso ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(iso)) : ''

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('advising.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('advising.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('advising.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('advising.loadError')}
        </Alert>
      )}

      {!isPending && !isError && requests.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('advising.empty')}
        </Typography>
      )}

      {requests.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('advising.columns.student')}</TableCell>
                <TableCell>{t('advising.columns.program')}</TableCell>
                <TableCell>{t('advising.columns.degree')}</TableCell>
                <TableCell align="right">{t('advising.columns.courses')}</TableCell>
                <TableCell>{t('advising.columns.submitted')}</TableCell>
                <TableCell align="right">{t('advising.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {requests.map((item) => (
                <TableRow key={item.id} hover>
                  <TableCell>
                    <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                      {item.student?.name}
                    </Typography>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>
                      {item.student?.university_id}
                    </Typography>
                  </TableCell>
                  <TableCell>{programName(item)}</TableCell>
                  <TableCell>{item.student?.degree_level ?? '—'}</TableCell>
                  <TableCell align="right">
                    {t('advising.courseCount', { count: item.course_count ?? 0 })}
                  </TableCell>
                  <TableCell>{submittedOn(item.submitted_at)}</TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/advising/${item.id}`}
                      size="small"
                      variant="outlined"
                    >
                      {t('advising.open')}
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </>
  )
}
