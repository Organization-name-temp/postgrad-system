import {
  Alert,
  Button,
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
import { Link as RouterLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMyOutstandingIncompletes } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

/** A locale-formatted calendar date for an Incomplete's derived resolution deadline. */
function deadlineDisplay(iso: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(iso))
}

/**
 * The Instructor's outstanding Incompletes (ticket 05, SRS §4.7.3.4): every Incomplete they
 * declared that is not yet resolved or converted, each with its derived resolution deadline —
 * `null` while the next Semester does not exist in the Calendar yet, shown as "no deadline yet"
 * rather than blank. "Resolve" opens (or resumes) the IncompleteResolution round, which reuses
 * the same Sheet editor, signature and Department-Head review as any other Grade.
 *
 * Persona-guarded to the Instructor role (R04), beside "Grade sheets" in the same nav section;
 * the API scopes the list to Offerings the acting user teaches.
 */
export function OutstandingIncompletesPage() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useMyOutstandingIncompletes()
  const incompletes = data?.data ?? []

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {t('outstandingIncompletes.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {t('outstandingIncompletes.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('outstandingIncompletes.loading')} />}
      {isError && <Alert severity="error">{t('outstandingIncompletes.loadError')}</Alert>}

      {!isPending && !isError && incompletes.length === 0 && (
        <Typography color="text.secondary">{t('outstandingIncompletes.empty')}</Typography>
      )}

      {incompletes.length > 0 && (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('outstandingIncompletes.columns.student')}</TableCell>
                <TableCell>{t('outstandingIncompletes.columns.course')}</TableCell>
                <TableCell>{t('outstandingIncompletes.columns.completion')}</TableCell>
                <TableCell>{t('outstandingIncompletes.columns.reason')}</TableCell>
                <TableCell>{t('outstandingIncompletes.columns.deadline')}</TableCell>
                <TableCell align="right">{t('outstandingIncompletes.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {incompletes.map((incomplete) => (
                <TableRow key={incomplete.incomplete_id}>
                  <TableCell>
                    <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                      {incomplete.student?.name}
                    </Typography>
                  </TableCell>
                  <TableCell>
                    <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                      {incomplete.course?.code}
                    </Typography>{' '}
                    {incomplete.course ? pickLocaleName(incomplete.course, i18n.language) : ''}
                  </TableCell>
                  <TableCell>
                    {t('outstandingIncompletes.completion', {
                      percentage: incomplete.completion_percentage,
                    })}
                  </TableCell>
                  <TableCell>{incomplete.reason}</TableCell>
                  <TableCell>
                    {incomplete.deadline
                      ? t('outstandingIncompletes.deadline', {
                          date: deadlineDisplay(incomplete.deadline, i18n.language),
                        })
                      : t('outstandingIncompletes.noDeadline')}
                  </TableCell>
                  <TableCell align="right">
                    <Button
                      component={RouterLink}
                      to={`/grading/incompletes/${incomplete.incomplete_id}`}
                      size="small"
                    >
                      {t('outstandingIncompletes.resolve')}
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
