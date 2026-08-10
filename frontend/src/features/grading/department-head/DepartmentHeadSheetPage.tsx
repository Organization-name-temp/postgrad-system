import { useState } from 'react'
import ArrowBackIcon from '@mui/icons-material/ArrowBack'
import {
  Alert,
  Button,
  CircularProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material'
import { useQueryClient } from '@tanstack/react-query'
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  getTheDepartmentHeadsReviewQueueQueryKey,
  useFinalizeTheSheet,
  useOpenOneSheetForReview,
  useReturnTheSheetWithANote,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { StatusChip } from '../../../shared/ui/StatusChip'
import { formatGradeHistory, readGradeHistory } from '../gradeHistory'
import { gradeSheetChip } from '../gradeSheetChip'
import { coerceNullableObject } from '../incompleteLine'

/**
 * The Department Head's review of one signed Grade Sheet (features/grading, ticket 03): every
 * mark and the Offering's grade distribution, read-only — a Head can never write a mark
 * (authorship always stays with the Instructor). Finalizing makes the round part of the academic
 * record and closes it for good; returning requires a note, which reaches the Instructor.
 * Persona-guarded to the Department-Head role (R06); scope is re-enforced server-side.
 */
export function DepartmentHeadSheetPage() {
  const { t, i18n } = useTranslation('grading')
  const { sheetId: sheetIdParam } = useParams<{ sheetId: string }>()
  const sheetId = Number(sheetIdParam)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isPending, isError } = useOpenOneSheetForReview(sheetId)
  const finalize = useFinalizeTheSheet()
  const returnSheet = useReturnTheSheetWithANote()

  const [noteOpen, setNoteOpen] = useState(false)
  const [note, setNote] = useState('')
  const [actionError, setActionError] = useState<string | null>(null)

  const loaded = data?.data
  const sheet = loaded?.sheet
  const course = loaded?.course
  const lines = loaded?.lines ?? []
  const distribution = loaded?.distribution ?? []
  const status = sheet?.status ?? 'Submitted'
  const isFinalized = status === 'Finalized'

  const afterAction = async () => {
    await queryClient.invalidateQueries({ queryKey: getTheDepartmentHeadsReviewQueueQueryKey() })
    navigate('/grading/review')
  }

  const handleFinalize = async () => {
    setActionError(null)
    try {
      await finalize.mutateAsync({ gradeSheetId: sheetId })
      await afterAction()
    } catch (finalizeError) {
      setActionError(apiErrorMessage(finalizeError) ?? t('departmentHead.review.finalizeError'))
    }
  }

  const handleReturn = async () => {
    setActionError(null)
    if (note.trim() === '') {
      setActionError(t('departmentHead.review.noteRequired'))
      return
    }

    try {
      await returnSheet.mutateAsync({ gradeSheetId: sheetId, data: { note: note.trim() } })
      await afterAction()
    } catch (returnError) {
      setActionError(
        apiFieldErrors(returnError).note?.[0] ??
          apiErrorMessage(returnError) ??
          t('departmentHead.review.returnError'),
      )
    }
  }

  return (
    <>
      <Button
        component={RouterLink}
        to="/grading/review"
        startIcon={<ArrowBackIcon />}
        sx={{ mb: 2 }}
      >
        {t('departmentHead.review.back')}
      </Button>

      <Stack direction="row" spacing={2} sx={{ mb: 1, alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography variant="h4" component="h1">
          {t('departmentHead.review.title')}
        </Typography>
        {sheet && <StatusChip {...gradeSheetChip(status, t(`sheet.status.${status}`))} />}
      </Stack>

      {course && (
        <Typography color="text.secondary" sx={{ mb: 3 }}>
          {`${course.code ?? ''} ${pickLocaleName(course, i18n.language)}`.trim()}
        </Typography>
      )}

      {isPending && <CircularProgress aria-label={t('departmentHead.review.loading')} />}
      {isError && <Alert severity="error">{t('departmentHead.review.loadError')}</Alert>}

      {isFinalized && (
        <Alert severity="success" sx={{ mb: 2 }}>
          {t('departmentHead.review.finalized')}
        </Alert>
      )}

      {loaded && (
        <Stack spacing={3}>
          <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
            <TableContainer sx={{ overflowX: 'auto' }}>
              <Table size="small" aria-label={t('sheet.tableLabel')}>
                <TableHead>
                  <TableRow>
                    <TableCell>{t('sheet.columns.student')}</TableCell>
                    <TableCell align="right">{t('sheet.columns.creditHours')}</TableCell>
                    <TableCell>{t('sheet.columns.mark')}</TableCell>
                    <TableCell>{t('sheet.columns.letter')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {lines.map((line) => {
                    const incomplete = coerceNullableObject<{
                      completion_percentage?: number
                      reason?: string
                    }>(line.incomplete)
                    const historySummary = formatGradeHistory(readGradeHistory(line), t)
                    return (
                      <TableRow key={line.enrollment_id}>
                        <TableCell>
                          <Stack spacing={0.5} sx={{ alignItems: 'flex-start' }}>
                            <Typography variant="body2" component="span" sx={{ fontWeight: 600 }}>
                              {line.student?.name}
                            </Typography>
                            {line.student?.university_id && (
                              <Typography variant="caption" component="span" color="text.secondary">
                                {line.student.university_id}
                              </Typography>
                            )}
                            {historySummary && (
                              <Typography variant="caption" component="span" color="text.secondary">
                                {t('departmentHead.review.history', { grades: historySummary })}
                              </Typography>
                            )}
                          </Stack>
                        </TableCell>
                        <TableCell align="right">{line.credit_hours}</TableCell>
                        {incomplete ? (
                          <TableCell colSpan={2}>
                            <Typography variant="body2" color="text.secondary">
                              {t('departmentHead.review.incompleteNote', {
                                percentage: incomplete.completion_percentage,
                                reason: incomplete.reason,
                              })}
                            </Typography>
                          </TableCell>
                        ) : (
                          <>
                            <TableCell>{line.grade?.mark ?? t('sheet.noLetter')}</TableCell>
                            <TableCell>{line.grade?.letter_grade ?? t('sheet.noLetter')}</TableCell>
                          </>
                        )}
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>

          <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
            <Typography variant="h6" component="h2" sx={{ mb: 1 }}>
              {t('departmentHead.review.distributionTitle')}
            </Typography>
            <Stack direction="row" spacing={2} sx={{ flexWrap: 'wrap' }}>
              {distribution.map((row) => (
                <Stack key={row.letter} sx={{ alignItems: 'center', minWidth: 40 }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {row.letter}
                  </Typography>
                  <Typography variant="body2" color="text.secondary">
                    {row.count}
                  </Typography>
                </Stack>
              ))}
            </Stack>
          </Paper>

          {!isFinalized && (
            <Paper variant="outlined" sx={{ borderRadius: 3, p: { xs: 1.5, sm: 2 } }}>
              {actionError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                  {actionError}
                </Alert>
              )}

              {noteOpen ? (
                <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
                  <TextField
                    label={t('departmentHead.review.noteLabel')}
                    value={note}
                    onChange={(event) => setNote(event.target.value)}
                    multiline
                    minRows={3}
                    fullWidth
                    required
                  />
                  <Stack direction="row" spacing={1}>
                    <Button
                      variant="contained"
                      color="warning"
                      onClick={handleReturn}
                      disabled={returnSheet.isPending}
                    >
                      {t('departmentHead.review.return')}
                    </Button>
                    <Button onClick={() => setNoteOpen(false)}>
                      {t('departmentHead.review.cancel')}
                    </Button>
                  </Stack>
                </Stack>
              ) : (
                <Stack direction="row" spacing={2}>
                  <Button
                    variant="contained"
                    onClick={handleFinalize}
                    disabled={finalize.isPending}
                  >
                    {t('departmentHead.review.finalize')}
                  </Button>
                  <Button variant="outlined" color="warning" onClick={() => setNoteOpen(true)}>
                    {t('departmentHead.review.return')}
                  </Button>
                </Stack>
              )}
            </Paper>
          )}
        </Stack>
      )}
    </>
  )
}
