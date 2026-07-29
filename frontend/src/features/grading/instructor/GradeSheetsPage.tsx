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
import { useMyOfferingsToGrade } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { gradeSheetChip } from '../gradeSheetChip'

/**
 * The Instructor's grading home (features/grading, ticket 01): every Course Offering the
 * signed-in user teaches, with the number of gradable lines each one's Sheet carries, its
 * current state (ticket 03 — unstarted, in progress, awaiting review, returned, finalized),
 * and a link into it. Persona-guarded to the Instructor role (R04); the API scopes the list to
 * the acting user, and each Sheet is separately authorised by the Offering assignment.
 */
export function GradeSheetsPage() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useMyOfferingsToGrade()
  const offerings = data?.data ?? []

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {t('offerings.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {t('offerings.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('offerings.loading')} />}
      {isError && <Alert severity="error">{t('offerings.loadError')}</Alert>}

      {!isPending && !isError && offerings.length === 0 && (
        <Typography color="text.secondary">{t('offerings.empty')}</Typography>
      )}

      {offerings.length > 0 && (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('offerings.columns.course')}</TableCell>
                <TableCell align="right">{t('offerings.columns.students')}</TableCell>
                <TableCell>{t('offerings.columns.state')}</TableCell>
                <TableCell align="right">{t('offerings.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {offerings.map((offering) => {
                const course = offering.course
                const status = offering.sheet_status
                return (
                  <TableRow key={offering.offering_id}>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {course?.code}
                      </Typography>{' '}
                      {course ? pickLocaleName(course, i18n.language) : ''}
                    </TableCell>
                    <TableCell align="right">
                      {t('offerings.gradableCount', { count: offering.gradable_count ?? 0 })}
                    </TableCell>
                    <TableCell>
                      {status ? (
                        <StatusChip {...gradeSheetChip(status, t(`sheet.status.${status}`))} />
                      ) : (
                        <StatusChip
                          tone="neutral"
                          variant="outlined"
                          label={t('offerings.unstarted')}
                        />
                      )}
                    </TableCell>
                    <TableCell align="right">
                      <Button
                        component={RouterLink}
                        to={`/grading/offerings/${offering.offering_id}`}
                        size="small"
                      >
                        {t('offerings.openSheet')}
                      </Button>
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </TableContainer>
      )}
    </>
  )
}
