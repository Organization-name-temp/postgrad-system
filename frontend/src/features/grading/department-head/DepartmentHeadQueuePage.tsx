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
import { useTheDepartmentHeadsReviewQueue } from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'

/**
 * The Department Head's review queue (features/grading, ticket 03): every signed Grade Sheet
 * routed to the acting Head's Department, including — for a Head assigned university-wide —
 * the department-less Preparatory Offerings a Department-scoped Head never sees. Persona-guarded
 * to the Department-Head role (R06); the API does the actual scoping
 * ({@see ScopesToDepartmentHead} server-side), so this page only renders whatever it returns.
 */
export function DepartmentHeadQueuePage() {
  const { t, i18n } = useTranslation('grading')
  const { data, isPending, isError } = useTheDepartmentHeadsReviewQueue()
  const sheets = data?.data ?? []

  return (
    <>
      <Typography variant="h4" component="h1" sx={{ mb: 1 }}>
        {t('departmentHead.queue.title')}
      </Typography>
      <Typography color="text.secondary" sx={{ mb: 3 }}>
        {t('departmentHead.queue.intro')}
      </Typography>

      {isPending && <CircularProgress aria-label={t('departmentHead.queue.loading')} />}
      {isError && <Alert severity="error">{t('departmentHead.queue.loadError')}</Alert>}

      {!isPending && !isError && sheets.length === 0 && (
        <Typography color="text.secondary">{t('departmentHead.queue.empty')}</Typography>
      )}

      {sheets.length > 0 && (
        <TableContainer component={Paper} variant="outlined" sx={{ borderRadius: 3 }}>
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>{t('departmentHead.queue.columns.course')}</TableCell>
                <TableCell>{t('departmentHead.queue.columns.instructor')}</TableCell>
                <TableCell>{t('departmentHead.queue.columns.signedAt')}</TableCell>
                <TableCell align="right">{t('departmentHead.queue.columns.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {sheets.map((sheet) => {
                const course = sheet.course
                return (
                  <TableRow key={sheet.id}>
                    <TableCell>
                      <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                        {course?.code}
                      </Typography>{' '}
                      {course ? pickLocaleName(course, i18n.language) : ''}
                    </TableCell>
                    <TableCell>{sheet.instructor?.name}</TableCell>
                    <TableCell>
                      {sheet.signed_at
                        ? new Date(sheet.signed_at).toLocaleDateString(i18n.language)
                        : ''}
                    </TableCell>
                    <TableCell align="right">
                      <Button
                        component={RouterLink}
                        to={`/grading/review/${sheet.id}`}
                        size="small"
                      >
                        {t('departmentHead.queue.review')}
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
