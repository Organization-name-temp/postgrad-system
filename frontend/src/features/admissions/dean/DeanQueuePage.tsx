import {
  Alert,
  Button,
  Chip,
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
import { useDeanReviewQueue } from '../../../shared/api/generated/endpoints'
import type { DeanReviewQueue200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

/**
 * The Dean review queue (ticket 08). Lists the Student-Affairs-approved applications
 * (`under_dean_review`) for the signed-in Dean's faculties, oldest-first — the
 * faculty scope is enforced server-side via the Dean↔Faculty assignment mapping, so a
 * Dean with no assignment (or a non-Dean reviewer) simply sees an empty queue. Gated
 * on `application.screen` (route + API). Opening a row goes to the review screen.
 */
export function DeanQueuePage() {
  const { t, i18n } = useTranslation('admissions')
  const { data, isPending, isError } = useDeanReviewQueue()
  const applications = data?.data ?? []

  const applicantName = (application: DeanReviewQueue200DataItem): string =>
    pickLocaleName(application, i18n.language, application.national_id ?? '')

  const programName = (application: DeanReviewQueue200DataItem): string => {
    const program = application.primary_program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('deanReview.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('deanReview.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('deanReview.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('deanReview.loadError')}
        </Alert>
      )}

      {!isPending && !isError && applications.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('deanReview.empty')}
        </Typography>
      )}

      {applications.length > 0 && (
        <TableContainer sx={{ mt: 2 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('screening.columns.applicant')}</TableCell>
                <TableCell>{t('screening.columns.nationalId')}</TableCell>
                <TableCell>{t('screening.columns.degree')}</TableCell>
                <TableCell>{t('screening.columns.program')}</TableCell>
                <TableCell>{t('screening.columns.waiting')}</TableCell>
                <TableCell align="right">{t('screening.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {applications.map((application) => (
                <TableRow key={application.id} hover>
                  <TableCell>{applicantName(application)}</TableCell>
                  <TableCell>{application.national_id}</TableCell>
                  <TableCell>{application.applying_for_degree}</TableCell>
                  <TableCell>{programName(application)}</TableCell>
                  <TableCell>
                    <Chip
                      size="small"
                      label={t('screening.waitingDays', { count: application.age_days ?? 0 })}
                    />
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/dean/${application.id}`}
                      size="small"
                      variant="outlined"
                    >
                      {t('screening.open')}
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
