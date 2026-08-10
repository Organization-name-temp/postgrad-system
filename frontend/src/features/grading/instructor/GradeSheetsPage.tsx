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
import { useOfferingsToGrade } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { gradeSheetChip } from '../gradeSheetChip'

/**
 * The grading home (features/grading, ticket 01) — one screen serving two personas, because the
 * API serves them one list:
 *
 * - an **Instructor** sees every Course Offering they teach, with the number of gradable lines
 *   each one's Sheet carries and its current state (ticket 03 — unstarted, in progress, awaiting
 *   review, returned, finalized);
 * - a **central-entry** user (ticket 09 — Student Affairs, holding `grades.enter` without
 *   `grades.finalize`) sees the picker over *every* Offering, so grades arriving on paper can be
 *   keyed in for courses they do not teach, and reaches the same Sheet editor from it rather than
 *   a second screen.
 *
 * Which list came back is the server's own `meta.scope`, never re-derived here from the user's
 * permissions: the API applied the rule, so it is the API that says which rule it applied. The
 * Instructor column only appears on the central list — on an Instructor's own it would repeat
 * their name on every row.
 */
export function GradeSheetsPage() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useOfferingsToGrade()
  const offerings = data?.data ?? []
  const isCentral = data?.meta?.scope === 'central'

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {isCentral ? t('offerings.centralTitle') : t('offerings.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {isCentral ? t('offerings.centralIntro') : t('offerings.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('offerings.loading')} />}
      {isError && <Alert severity="error">{t('offerings.loadError')}</Alert>}

      {!isPending && !isError && offerings.length === 0 && (
        <Typography color="text.secondary">
          {isCentral ? t('offerings.centralEmpty') : t('offerings.empty')}
        </Typography>
      )}

      {offerings.length > 0 && (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('offerings.columns.course')}</TableCell>
                <TableCell>{t('offerings.columns.semester')}</TableCell>
                {isCentral && <TableCell>{t('offerings.columns.instructor')}</TableCell>}
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
                    <TableCell>
                      {offering.semester ? pickLocaleName(offering.semester, i18n.language) : ''}
                    </TableCell>
                    {isCentral && <TableCell>{offering.instructor?.name ?? ''}</TableCell>}
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
