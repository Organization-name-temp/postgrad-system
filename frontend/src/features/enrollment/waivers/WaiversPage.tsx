import { useEffect, useState } from 'react'
import {
  Alert,
  Box,
  Button,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  FormControlLabel,
  FormGroup,
  FormHelperText,
  InputLabel,
  NativeSelect,
  Pagination,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineRounded'
import DownloadIcon from '@mui/icons-material/Download'
import SendIcon from '@mui/icons-material/Send'
import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useForm, type UseFormReturn } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  abandonAWaiverDraft,
  exportMyWaiverRequests,
  getMyWaiverRequestsQueryKey,
  openWaiverTranscriptEvidence,
  useCreateAWaiverDraft,
  useListCourses,
  useMyQualifyingPRSResults,
  useMyWaiverRequests,
  useMyWaiverTargets,
  useMyWaiverTranscriptEvidence,
  useReviseAnEditableWaiverRequest,
  useSubmitOrResubmitAWaiverRequest,
  type ListCourses200DataItem,
  type MyQualifyingPRSResults200DataItem,
  type MyWaiverTranscriptEvidence200DataItem,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { downloadBlob } from '../credit-decisions/downloadBlob'
import { CreditDecisionEffect } from '../credit-decisions/CreditDecisionEffect'
import { useRetryStableActionId } from '../credit-decisions/useRetryStableActionId'
import { waiverDraftSchema, type WaiverDraftValues } from './formSchema'
import type { StudentWaiverRequestRow } from './types'
import { WaiverEvidenceSummary } from './WaiverEvidenceSummary'
import { WaiverStatusChip } from './WaiverStatusChip'
import { WaiverTransitionHistory } from './WaiverTransitionHistory'

const EMPTY_DRAFT: WaiverDraftValues = {
  targetId: '',
  source: 'prs',
  prsEnrollmentId: '',
  legacyCourseId: '',
  legacyGrade: '',
  transcriptDocumentIds: [],
  note: '',
}

const isolateLtr = (value: string | number | undefined) => `\u2066${value ?? ''}\u2069`

function priorResult(values: WaiverDraftValues) {
  return values.source === 'prs'
    ? { source: 'prs', institution: 'E-JUST', enrollment_id: Number(values.prsEnrollmentId) }
    : {
        source: 'legacy_transcript',
        institution: 'E-JUST',
        course_id: Number(values.legacyCourseId),
        letter_grade: values.legacyGrade,
        official_transcript_document_ids: values.transcriptDocumentIds,
      }
}

export function WaiversPage() {
  const { t, i18n } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const params = { sort: '-updated_at', page }
  const targets = useMyWaiverTargets()
  const priorResults = useMyQualifyingPRSResults()
  const documents = useMyWaiverTranscriptEvidence()
  const courses = useListCourses()
  const requests = useMyWaiverRequests(params)
  const create = useCreateAWaiverDraft()
  const form = useForm<WaiverDraftValues>({
    resolver: zodResolver(waiverDraftSchema),
    mode: 'onBlur',
    defaultValues: EMPTY_DRAFT,
  })
  const refresh = () => queryClient.invalidateQueries({ queryKey: getMyWaiverRequestsQueryKey() })
  const rows: StudentWaiverRequestRow[] = requests.data?.data ?? []
  const lastPage = Math.max(1, requests.data?.meta?.last_page ?? 1)

  useEffect(() => {
    if (!requests.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, requests.isPending])

  const saveDraft = async (values: WaiverDraftValues) => {
    await create.mutateAsync({
      data: {
        target_course_id: Number(values.targetId),
        prior_result: priorResult(values),
        student_note: values.note || undefined,
      },
    })
    form.reset(EMPTY_DRAFT)
    await refresh()
  }

  const loading =
    requests.isPending ||
    targets.isPending ||
    priorResults.isPending ||
    courses.isPending ||
    documents.isPending
  const failed =
    requests.isError ||
    targets.isError ||
    priorResults.isError ||
    courses.isError ||
    documents.isError

  return (
    <Stack spacing={3}>
      <Box>
        <Typography component="h1" variant="h4">
          {t('waivers.title')}
        </Typography>
        <Typography color="text.secondary">{t('waivers.intro')}</Typography>
      </Box>

      <Paper
        variant="outlined"
        sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
      >
        <Stack component="form" spacing={2} noValidate onSubmit={form.handleSubmit(saveDraft)}>
          <Typography component="h2" variant="h6">
            {t('waivers.newDraft')}
          </Typography>
          <WaiverDraftFields
            form={form}
            targets={targets.data?.data ?? []}
            results={priorResults.data?.data ?? []}
            courses={(courses.data?.data ?? []).filter((course) => (course.level ?? 0) >= 400)}
            documents={documents.data?.data ?? []}
            locale={i18n.language}
          />
          <Button
            type="submit"
            variant="contained"
            disabled={!form.formState.isValid || create.isPending}
          >
            {t('waivers.saveDraft')}
          </Button>
          {create.isError && <Alert severity="error">{t('waivers.saveError')}</Alert>}
        </Stack>
      </Paper>

      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
        <Typography component="h2" variant="h5">
          {t('waivers.myRequests')}
        </Typography>
        <Button
          startIcon={<DownloadIcon />}
          onClick={() =>
            void exportMyWaiverRequests(params).then((blob) =>
              downloadBlob(blob, 'waiver-requests.xlsx'),
            )
          }
        >
          {t('waivers.export')}
        </Button>
      </Stack>

      {loading && !failed && (
        <Stack spacing={2} aria-label={t('waivers.loading')}>
          <Skeleton variant="rounded" sx={(theme) => ({ height: theme.spacing(24) })} />
          <Skeleton variant="rounded" sx={(theme) => ({ height: theme.spacing(24) })} />
        </Stack>
      )}
      {failed && (
        <Alert
          severity="error"
          action={
            <Button
              color="inherit"
              onClick={() => {
                void requests.refetch()
                void targets.refetch()
                void priorResults.refetch()
                void courses.refetch()
                void documents.refetch()
              }}
            >
              {t('waivers.retry')}
            </Button>
          }
        >
          {t('waivers.loadError')}
        </Alert>
      )}
      {!loading && !failed && rows.length === 0 && (
        <Stack spacing={1} sx={{ alignItems: 'center', py: 4 }}>
          <WorkspacePremiumOutlinedIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary" sx={{ textAlign: 'center' }}>
            {t('waivers.empty')}
          </Typography>
        </Stack>
      )}
      {!loading &&
        !failed &&
        rows.map((row) => (
          <WaiverRequestCard
            key={row.id}
            row={row}
            results={priorResults.data?.data ?? []}
            courses={(courses.data?.data ?? []).filter((course) => (course.level ?? 0) >= 400)}
            documents={documents.data?.data ?? []}
            locale={i18n.language}
            onChanged={refresh}
          />
        ))}
      {!loading && !failed && lastPage > 1 && (
        <Pagination
          page={page}
          count={lastPage}
          onChange={(_, next) => setPage(next)}
          sx={{ alignSelf: 'center' }}
        />
      )}
    </Stack>
  )
}

function WaiverDraftFields({
  form,
  targets,
  results,
  courses,
  documents,
  locale,
  hideTarget = false,
}: {
  form: UseFormReturn<WaiverDraftValues>
  targets: {
    target_course_id?: number
    target_course_code?: string
    credit_hours?: number
    already_recognized?: boolean
  }[]
  results: MyQualifyingPRSResults200DataItem[]
  courses: ListCourses200DataItem[]
  documents: MyWaiverTranscriptEvidence200DataItem[]
  locale: string
  hideTarget?: boolean
}) {
  const { t } = useTranslation('enrollment')
  const source = form.watch('source')
  const error = (name: keyof WaiverDraftValues) => {
    const message = form.formState.errors[name]?.message
    return message === 'too_long'
      ? t('waivers.tooLong')
      : message
        ? t('waivers.required')
        : undefined
  }

  return (
    <>
      {!hideTarget && (
        <NativeField
          id="waiver-target"
          label={t('waivers.target')}
          error={error('targetId')}
          input={form.register('targetId')}
        >
          <option value="" />
          {targets.map((target) => (
            <option
              key={target.target_course_id}
              value={target.target_course_id}
              disabled={target.already_recognized}
            >
              {isolateLtr(target.target_course_code)} — {isolateLtr(target.credit_hours)}{' '}
              {t('waivers.ch')}
            </option>
          ))}
        </NativeField>
      )}
      <NativeField id="waiver-source" label={t('waivers.source')} input={form.register('source')}>
        <option value="prs">{t('waivers.sources.prs')}</option>
        <option value="legacy_transcript">{t('waivers.sources.legacy')}</option>
      </NativeField>
      {source === 'prs' ? (
        <NativeField
          id={`waiver-prs-result-${hideTarget ? 'edit' : 'new'}`}
          label={t('waivers.priorResult')}
          error={error('prsEnrollmentId')}
          input={form.register('prsEnrollmentId')}
        >
          <option value="" />
          {results.map((result) => (
            <option key={result.enrollment_id} value={result.enrollment_id}>
              {isolateLtr(result.course?.code)} — {pickLocaleName(result.course ?? {}, locale)} (
              {isolateLtr(result.course?.level)})
            </option>
          ))}
        </NativeField>
      ) : (
        <>
          <NativeField
            id={`waiver-legacy-course-${hideTarget ? 'edit' : 'new'}`}
            label={t('waivers.legacyCourse')}
            error={error('legacyCourseId')}
            input={form.register('legacyCourseId')}
            dir="ltr"
          >
            <option value="" />
            {courses.map((course) => (
              <option key={course.id} value={course.id}>
                {course.code} — {course.level}
              </option>
            ))}
          </NativeField>
          <NativeField
            id={`waiver-grade-${hideTarget ? 'edit' : 'new'}`}
            label={t('waivers.legacyGrade')}
            error={error('legacyGrade')}
            input={form.register('legacyGrade')}
            dir="ltr"
          >
            <option value="" />
            {['A+', 'A', 'B+', 'B', 'C+'].map((grade) => (
              <option key={grade}>{grade}</option>
            ))}
          </NativeField>
          <FormControl error={Boolean(error('transcriptDocumentIds'))} component="fieldset">
            <Typography component="legend" variant="body2">
              {t('waivers.transcriptDocuments')}
            </Typography>
            <FormGroup>
              {documents.map((document) => (
                <FormControlLabel
                  key={document.id}
                  control={
                    <Checkbox {...form.register('transcriptDocumentIds')} value={document.id} />
                  }
                  label={<bdi dir="ltr">{document.original_filename ?? document.id}</bdi>}
                />
              ))}
            </FormGroup>
            <FormHelperText role={error('transcriptDocumentIds') ? 'alert' : undefined}>
              {error('transcriptDocumentIds') ?? t('waivers.transcriptHelp')}
            </FormHelperText>
          </FormControl>
        </>
      )}
      <TextField
        {...form.register('note')}
        label={t('waivers.studentNote')}
        multiline
        minRows={2}
        error={Boolean(error('note'))}
        helperText={error('note')}
      />
    </>
  )
}

function NativeField({
  id,
  label,
  input,
  error,
  dir,
  children,
}: {
  id: string
  label: string
  input: ReturnType<UseFormReturn<WaiverDraftValues>['register']>
  error?: string
  dir?: 'ltr'
  children: React.ReactNode
}) {
  return (
    <FormControl error={Boolean(error)}>
      <InputLabel htmlFor={id} shrink>
        {label}
      </InputLabel>
      <NativeSelect {...input} inputProps={{ id, dir, 'aria-describedby': `${id}-help` }}>
        {children}
      </NativeSelect>
      <FormHelperText id={`${id}-help`} role={error ? 'alert' : undefined}>
        {error}
      </FormHelperText>
    </FormControl>
  )
}

function WaiverRequestCard({
  row,
  results,
  courses,
  documents,
  locale,
  onChanged,
}: {
  row: StudentWaiverRequestRow
  results: MyQualifyingPRSResults200DataItem[]
  courses: ListCourses200DataItem[]
  documents: MyWaiverTranscriptEvidence200DataItem[]
  locale: string
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const revise = useReviseAnEditableWaiverRequest()
  const submit = useSubmitOrResubmitAWaiverRequest()
  const actionIds = useRetryStableActionId()
  const [confirmAbandon, setConfirmAbandon] = useState(false)
  const [downloadingDocument, setDownloadingDocument] = useState<string>()
  const [downloadFailed, setDownloadFailed] = useState(false)
  const evidence = row.evidence as typeof row.evidence & { enrollment_id?: number }
  const form = useForm<WaiverDraftValues>({
    resolver: zodResolver(waiverDraftSchema),
    mode: 'onBlur',
    defaultValues: {
      targetId: String(row.target_course_id ?? ''),
      source: row.prior_result_source === 'legacy_transcript' ? 'legacy_transcript' : 'prs',
      prsEnrollmentId: String(row.prior_enrollment_id ?? evidence?.enrollment_id ?? ''),
      legacyCourseId: String(row.prior_course_id ?? ''),
      legacyGrade: (row.prior_letter_grade as WaiverDraftValues['legacyGrade']) ?? '',
      transcriptDocumentIds: evidence?.official_transcript_document_ids ?? [],
      note: row.student_note ?? '',
    },
  })
  const id = row.id ?? 0
  const revision = row.revision ?? 1
  const latestReturn = Math.max(
    0,
    ...(row.transitions ?? [])
      .filter((transition) => transition.action?.startsWith('returned_by_'))
      .map((transition) => transition.revision ?? 0),
  )
  const canSubmit = row.status === 'Draft' || latestReturn < revision

  const save = async (values: WaiverDraftValues) => {
    await revise.mutateAsync({
      waiverRequestId: id,
      data: {
        expected_revision: revision,
        prior_result: priorResult(values),
        student_note: values.note,
      },
    })
    await onChanged()
  }
  const submitRequest = async () => {
    const key = JSON.stringify([id, revision, 'submit'])
    try {
      await submit.mutateAsync({
        waiverRequestId: id,
        data: { expected_revision: revision, action_id: actionIds.forAction(key) },
      })
      actionIds.complete(key)
      await onChanged()
    } catch {
      // Keep the retry-stable id; mutation state renders the recoverable error.
    }
  }

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ gap: 1, justifyContent: 'space-between' }}
        >
          <Box>
            <Typography component="h3" variant="h6" dir="ltr">
              {row.target_course_code}
            </Typography>
            <Typography color="text.secondary">
              <Trans
                t={t}
                i18nKey="waivers.requestMeta"
                values={{ revision, hours: row.credit_hours }}
                components={{ number: <bdi dir="ltr" />, hours: <bdi dir="ltr" /> }}
              />
            </Typography>
          </Box>
          <WaiverStatusChip status={row.status} />
        </Stack>
        <WaiverEvidenceSummary
          evidence={{
            ...evidence,
            source: evidence?.source ?? row.prior_result_source,
            letter_grade: evidence?.letter_grade ?? row.prior_letter_grade,
          }}
          evidenceDocuments={(evidence?.official_transcript_document_ids ?? []).map(
            (documentId) => {
              const metadata = documents.find((document) => document.id === documentId)
              return {
                ...metadata,
                id: documentId,
                pending: downloadingDocument === documentId,
                onOpen: () => {
                  setDownloadFailed(false)
                  setDownloadingDocument(documentId)
                  void openWaiverTranscriptEvidence(id, documentId)
                    .then((blob) =>
                      downloadBlob(blob, metadata?.original_filename ?? 'transcript.pdf'),
                    )
                    .catch(() => setDownloadFailed(true))
                    .finally(() => setDownloadingDocument(undefined))
                },
              }
            },
          )}
          priorCourseCode={row.prior_course_code}
          priorCourseLevel={row.prior_course_level}
        />
        {downloadFailed && <Alert severity="error">{t('waivers.downloadError')}</Alert>}
        {row.student_note && <Typography>{row.student_note}</Typography>}
        {row.head_verification && (
          <Stack spacing={0.5}>
            <Typography variant="subtitle2">{t('waivers.verification')}</Typography>
            <Typography>
              <bdi dir="ltr">{row.head_verification.coverage_percentage}%</bdi>
            </Typography>
            <Typography color="text.secondary">{row.head_verification.equivalency_note}</Typography>
          </Stack>
        )}
        {row.decision && (
          <CreditDecisionEffect
            effective={row.decision.effective}
            revocation={row.decision.revocation}
          >
            {t('waivers.decision', { count: row.decision.credit_hours })}
          </CreditDecisionEffect>
        )}
        <WaiverTransitionHistory transitions={row.transitions} />
        {row.editable && (
          <Stack component="form" spacing={2} noValidate onSubmit={form.handleSubmit(save)}>
            <WaiverDraftFields
              form={form}
              targets={[]}
              results={results}
              courses={courses}
              documents={documents}
              locale={locale}
              hideTarget
            />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              <Button type="submit" disabled={!form.formState.isValid || revise.isPending}>
                {t('waivers.saveChanges')}
              </Button>
              <Button
                variant="outlined"
                startIcon={
                  <SendIcon
                    sx={(theme) => ({
                      transform: theme.direction === 'rtl' ? 'scaleX(-1)' : undefined,
                    })}
                  />
                }
                disabled={!canSubmit || submit.isPending}
                onClick={() => void submitRequest()}
              >
                {row.status === 'Returned' ? t('waivers.resubmit') : t('waivers.submit')}
              </Button>
              {row.status === 'Draft' && (
                <Button
                  color="error"
                  startIcon={<DeleteOutlineIcon />}
                  onClick={() => setConfirmAbandon(true)}
                >
                  {t('waivers.abandon')}
                </Button>
              )}
            </Stack>
            {!canSubmit && <Alert severity="info">{t('waivers.saveBeforeResubmit')}</Alert>}
          </Stack>
        )}
        {(revise.isError || submit.isError) && (
          <Alert severity="error">{t('waivers.actionError')}</Alert>
        )}
        <Dialog open={confirmAbandon} onClose={() => setConfirmAbandon(false)}>
          <DialogTitle>{t('waivers.abandon')}</DialogTitle>
          <DialogContent>
            <DialogContentText>{t('waivers.abandonConfirm')}</DialogContentText>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setConfirmAbandon(false)}>{t('waivers.cancel')}</Button>
            <Button
              color="error"
              variant="contained"
              startIcon={<DeleteOutlineIcon />}
              onClick={() => {
                setConfirmAbandon(false)
                void abandonAWaiverDraft(id, { expected_revision: revision }).then(onChanged)
              }}
            >
              {t('waivers.confirmAbandon')}
            </Button>
          </DialogActions>
        </Dialog>
      </Stack>
    </Paper>
  )
}
