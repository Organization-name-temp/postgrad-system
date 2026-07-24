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
import { useTheSignedInInstructorsOfferingsMyOfferings } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { MeetingTimes } from '../MeetingTimes'

/**
 * The Instructor's "My teaching" list (features/enrollment, ticket 08): every Course
 * Offering the signed-in user teaches, across semesters, with a link into each Offering's
 * attendance register. Persona-guarded to the Instructor role (R04); the API scopes the
 * list to the acting user, and the register itself is authorised by assignment.
 */
export function MyOfferingsPage() {
  const { t, i18n } = useTranslation('enrollment')
  const { data, isPending, isError } = useTheSignedInInstructorsOfferingsMyOfferings()
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
                <TableCell>{t('offerings.columns.semester')}</TableCell>
                <TableCell>{t('offerings.columns.meetings')}</TableCell>
                <TableCell align="right">{t('offerings.columns.students')}</TableCell>
                <TableCell align="right">{t('offerings.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {offerings.map((offering) => {
                const course = offering.course
                const semester = offering.semester
                return (
                  <TableRow key={offering.id}>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {course?.code}
                      </Typography>{' '}
                      {course ? pickLocaleName(course, i18n.language) : ''}
                    </TableCell>
                    <TableCell>{semester ? pickLocaleName(semester, i18n.language) : ''}</TableCell>
                    <TableCell>
                      <MeetingTimes meetings={offering.meetings ?? []} />
                    </TableCell>
                    <TableCell align="right">
                      {t('offerings.studentCount', { count: offering.student_count ?? 0 })}
                    </TableCell>
                    <TableCell align="right">
                      <Button
                        component={RouterLink}
                        to={`/offerings/${offering.id}/register`}
                        size="small"
                      >
                        {t('offerings.openRegister')}
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
