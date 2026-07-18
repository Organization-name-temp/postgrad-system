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
import { useDeanDecisionsQueue } from '../../../shared/api/generated/endpoints'
import type { DeanDecisionsQueue200DataItem } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { ApplicationStatusChip } from '../ApplicationStatusChip'

/**
 * The Dean's admission-decisions queue (ticket 12). Lists the applications awaiting the
 * Dean's final decision after interview — those resting at `interview_completed` and the
 * held `conditionally_accepted` / `waitlisted` offers — for the signed-in Dean's
 * faculties, oldest-first. The faculty scope is enforced server-side (a Dean with no
 * assignment simply sees an empty queue). Gated on `applicant.decide` (route + API).
 * Opening a row goes to the decision screen.
 */
export function DeanDecisionQueuePage() {
  const { t, i18n } = useTranslation('admissions')
  const { data, isPending, isError } = useDeanDecisionsQueue()
  const applications = data?.data ?? []

  const applicantName = (application: DeanDecisionsQueue200DataItem): string =>
    pickLocaleName(application, i18n.language, application.national_id ?? '')

  const programName = (application: DeanDecisionsQueue200DataItem): string => {
    const program = application.primary_program
    return program ? pickLocaleName(program, i18n.language, program.code ?? '—') : '—'
  }

  return (
    <>
      <Typography variant="h4" component="h1" gutterBottom>
        {t('decisions.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('decisions.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('decisions.loading')} sx={{ mt: 2 }} />}
      {isError && (
        <Alert severity="error" sx={{ mt: 2 }}>
          {t('decisions.loadError')}
        </Alert>
      )}

      {!isPending && !isError && applications.length === 0 && (
        <Typography color="text.secondary" sx={{ mt: 2 }}>
          {t('decisions.empty')}
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
                <TableCell>{t('deanReview.decide')}</TableCell>
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
                    <ApplicationStatusChip status={application.status} />
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/dean/decisions/${application.id}`}
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
