import { useEffect, useState } from 'react'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Divider,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControl,
  FormHelperText,
  InputLabel,
  MenuItem,
  Paper,
  Pagination,
  Select,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import DownloadIcon from '@mui/icons-material/Download'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineRounded'
import SendIcon from '@mui/icons-material/Send'
import WorkspacePremiumOutlinedIcon from '@mui/icons-material/WorkspacePremiumOutlined'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Controller, useForm, type UseFormReturn } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  exportMyManualExemptions,
  getMyManualExemptionRequestsQueryKey,
  type ListPrograms200DataItem,
  useAbandonADraftRequest,
  useCreateAManualExemptionDraft,
  useListPrograms,
  useMyBoundPreparatoryExemptionTargets,
  useMyManualExemptionRequests,
  useReviseAnEditableDraftOrReturnedRequest,
  useSubmitOrResubmitAnExemptionRequest,
} from '../../../shared/api/generated/endpoints'
import { pickLocaleName } from '../../../shared/i18n/localeName'
import { downloadBlob } from '../credit-decisions/downloadBlob'
import { ExemptionEvidenceSummary } from './ExemptionEvidenceSummary'
import { ExemptionStatusChip } from './ExemptionStatusChip'
import { ExemptionTransitionHistory } from './ExemptionTransitionHistory'
import {
  FormErrorText,
  evidenceSchema,
  newDraftSchema,
  type EvidenceFormValues,
} from './formSchema'
import type { StudentExemptionRequestRow } from './types'
import { useRetryStableActionId } from '../credit-decisions/useRetryStableActionId'

function evidencePayload(values: EvidenceFormValues) {
  return values.basis === 'english'
    ? { english_test: values.englishTest, english_score: Number(values.score) }
    : values.basis === 'ejust_bsc'
      ? { previous_degree: 'BSc', is_ejust_graduate: true }
      : {
          previous_degree: 'MSc',
          is_ejust_graduate: true,
          previous_ejust_program_id: Number(values.previousProgramId),
        }
}

export function ManualExemptionsPage() {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const targets = useMyBoundPreparatoryExemptionTargets()
  const [page, setPage] = useState(1)
  const requestParams = { sort: '-updated_at', page }
  const requests = useMyManualExemptionRequests(requestParams)
  const create = useCreateAManualExemptionDraft()
  const requiredLabel = (label: string) => t('manualExemptions.requiredField', { label })
  const draftForm = useForm<EvidenceFormValues>({
    resolver: zodResolver(newDraftSchema),
    mode: 'onBlur',
    defaultValues: {
      targetId: '',
      basis: 'english',
      englishTest: 'IELTS',
      score: '',
      previousProgramId: '',
      note: '',
    },
  })

  const refresh = () =>
    queryClient.invalidateQueries({ queryKey: getMyManualExemptionRequestsQueryKey() })

  const saveDraft = async (values: EvidenceFormValues) => {
    await create.mutateAsync({
      data: {
        target_course_id: Number(values.targetId),
        evidence: evidencePayload(values),
        student_note: values.note || undefined,
      },
    })
    draftForm.reset()
    await refresh()
  }

  const rows: StudentExemptionRequestRow[] = requests.data?.data ?? []
  const lastPage = Math.max(1, requests.data?.meta?.last_page ?? 1)
  useEffect(() => {
    if (!requests.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, requests.isPending])
  const requestViewState =
    requests.isError || targets.isError
      ? 'error'
      : requests.isPending || targets.isPending
        ? 'loading'
        : page > lastPage
          ? 'loading'
          : rows.length === 0
            ? 'empty'
            : 'content'

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" component="h1">
          {t('manualExemptions.title')}
        </Typography>
        <Typography color="text.secondary">{t('manualExemptions.intro')}</Typography>
      </Box>

      <Paper
        variant="outlined"
        sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
      >
        <Typography variant="h6" component="h2" gutterBottom>
          {t('manualExemptions.newDraft')}
        </Typography>
        <Stack component="form" spacing={2} noValidate onSubmit={draftForm.handleSubmit(saveDraft)}>
          <FormControl fullWidth error={Boolean(draftForm.formState.errors.targetId)}>
            <InputLabel id="exemption-target-label">
              {requiredLabel(t('manualExemptions.target'))}
            </InputLabel>
            <Controller
              control={draftForm.control}
              name="targetId"
              render={({ field }) => (
                <Select
                  {...field}
                  required
                  labelId="exemption-target-label"
                  label={requiredLabel(t('manualExemptions.target'))}
                  inputProps={{ 'aria-describedby': 'exemption-target-help' }}
                >
                  {(targets.data?.data ?? []).map((target) => (
                    <MenuItem
                      key={target.target_course_id}
                      value={String(target.target_course_id)}
                      disabled={target.already_recognized}
                    >
                      <Trans
                        t={t}
                        i18nKey="manualExemptions.targetOption"
                        values={{
                          code: target.target_course_code,
                          role: t(`manualExemptions.roles.${target.preparatory_requirement_role}`),
                        }}
                        components={{ code: <bdi dir="ltr" /> }}
                      />
                    </MenuItem>
                  ))}
                </Select>
              )}
            />
            <FormHelperText id="exemption-target-help">
              <FormErrorText
                message={
                  draftForm.formState.errors.targetId ? t('manualExemptions.required') : undefined
                }
              />
            </FormHelperText>
          </FormControl>
          <EvidenceFields form={draftForm} idPrefix="new" />
          <Button
            type="submit"
            variant="contained"
            disabled={!draftForm.formState.isValid || create.isPending}
          >
            {t('manualExemptions.saveDraft')}
          </Button>
          {create.isError && <Alert severity="error">{t('manualExemptions.saveError')}</Alert>}
        </Stack>
      </Paper>

      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography variant="h5" component="h2">
          {t('manualExemptions.myRequests')}
        </Typography>
        <Button
          startIcon={<DownloadIcon />}
          onClick={() =>
            void exportMyManualExemptions(requestParams).then((blob) =>
              downloadBlob(blob, 'exemption-requests.xlsx'),
            )
          }
        >
          {t('manualExemptions.export')}
        </Button>
      </Stack>

      {requestViewState === 'loading' && (
        <Stack spacing={2} aria-label={t('manualExemptions.loading')}>
          <Skeleton variant="rounded" height={180} />
          <Skeleton variant="rounded" height={180} />
        </Stack>
      )}
      {requestViewState === 'error' && (
        <Alert
          severity="error"
          action={
            <Button
              color="inherit"
              onClick={() => {
                void requests.refetch()
                void targets.refetch()
              }}
            >
              {t('manualExemptions.retry')}
            </Button>
          }
        >
          {t('manualExemptions.loadError')}
        </Alert>
      )}
      {requestViewState === 'empty' && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1}>
          <WorkspacePremiumOutlinedIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary" sx={{ textAlign: 'center' }}>
            {t('manualExemptions.empty')}
          </Typography>
        </Stack>
      )}
      {requestViewState === 'content' &&
        rows.map((row) => <StudentRequestCard key={row.id} row={row} onChanged={refresh} />)}
      {!requests.isPending && !requests.isError && lastPage > 1 && (
        <Pagination
          page={page}
          count={lastPage}
          onChange={(_, nextPage) => setPage(nextPage)}
          sx={{ alignSelf: 'center' }}
        />
      )}
    </Stack>
  )
}

function StudentRequestCard({
  row,
  onChanged,
}: {
  row: StudentExemptionRequestRow
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const revise = useReviseAnEditableDraftOrReturnedRequest()
  const submit = useSubmitOrResubmitAnExemptionRequest()
  const abandon = useAbandonADraftRequest()
  const editForm = useForm<EvidenceFormValues>({
    resolver: zodResolver(evidenceSchema),
    mode: 'onBlur',
    defaultValues: {
      basis:
        row.evidence?.previous_degree === 'MSc'
          ? 'ejust_msc'
          : row.evidence?.previous_degree === 'BSc'
            ? 'ejust_bsc'
            : 'english',
      englishTest: row.evidence?.english_test === 'TOEFL iBT' ? 'TOEFL iBT' : 'IELTS',
      score: String(row.evidence?.english_score ?? ''),
      previousProgramId: String(row.evidence?.previous_ejust_program_id ?? ''),
      note: row.student_note ?? '',
    },
  })
  const [confirmAbandon, setConfirmAbandon] = useState(false)
  const submitActionId = useRetryStableActionId()
  const id = row.id ?? 0
  const revision = row.revision ?? 1
  const actionPending = revise.isPending || submit.isPending || abandon.isPending
  const actionError = revise.isError || submit.isError || abandon.isError
  const latestReturnRevision = Math.max(
    0,
    ...(row.transitions ?? [])
      .filter((transition) => transition.action?.startsWith('returned_by_'))
      .map((transition) => transition.revision ?? 0),
  )
  const canSubmit = row.status === 'Draft' || latestReturnRevision < revision
  const saveChanges = async (values: EvidenceFormValues) => {
    await revise.mutateAsync({
      exemptionRequestId: id,
      data: {
        expected_revision: revision,
        evidence: evidencePayload(values),
        student_note: values.note,
      },
    })
    await onChanged()
  }

  return (
    <Paper
      variant="outlined"
      sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
    >
      <Stack spacing={2}>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ justifyContent: 'space-between', gap: 1 }}
        >
          <Box>
            <Typography variant="h6" component="h3" dir="ltr">
              {row.target_course_code}
            </Typography>
            <Typography color="text.secondary">
              <Trans
                t={t}
                i18nKey="manualExemptions.requestMeta"
                values={{
                  role: t(`manualExemptions.roles.${row.preparatory_requirement_role}`),
                  revision: t('manualExemptions.revision', { count: revision }),
                }}
                components={{ number: <bdi dir="ltr" /> }}
              />
            </Typography>
          </Box>
          <ExemptionStatusChip status={row.status} />
        </Stack>
        {row.editable && (
          <Stack
            component="form"
            spacing={2}
            noValidate
            onSubmit={editForm.handleSubmit(saveChanges)}
          >
            <EvidenceFields form={editForm} idPrefix={`edit-${id}`} />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              <Button type="submit" disabled={actionPending || !editForm.formState.isValid}>
                {t('manualExemptions.saveChanges')}
              </Button>
              <Button
                variant="contained"
                startIcon={
                  <SendIcon
                    data-testid={`submit-icon-${id}`}
                    sx={(theme) => ({
                      transform: theme.direction === 'rtl' ? 'scaleX(-1)' : 'none',
                    })}
                  />
                }
                disabled={actionPending || !canSubmit}
                onClick={() => {
                  const key = `submit:${id}:${revision}`
                  void submit
                    .mutateAsync({
                      exemptionRequestId: id,
                      data: {
                        expected_revision: revision,
                        action_id: submitActionId.forAction(key),
                      },
                    })
                    .then(async () => {
                      submitActionId.complete(key)
                      await onChanged()
                    })
                    .catch(() => undefined)
                }}
              >
                {row.status === 'Returned'
                  ? t('manualExemptions.resubmit')
                  : t('manualExemptions.submit')}
              </Button>
              {row.status === 'Draft' && (
                <Button
                  color="error"
                  variant="contained"
                  startIcon={<DeleteOutlineIcon />}
                  disabled={actionPending}
                  onClick={() => setConfirmAbandon(true)}
                >
                  {t('manualExemptions.abandon')}
                </Button>
              )}
            </Stack>
            {!canSubmit && (
              <Typography variant="body2" color="text.secondary">
                {t('manualExemptions.saveBeforeResubmit')}
              </Typography>
            )}
            {actionError && <Alert severity="error">{t('manualExemptions.actionError')}</Alert>}
            {row.status === 'Draft' && (
              <Dialog
                open={confirmAbandon}
                onClose={() => setConfirmAbandon(false)}
                aria-labelledby={`abandon-title-${id}`}
              >
                <DialogTitle id={`abandon-title-${id}`}>
                  {t('manualExemptions.abandon')}
                </DialogTitle>
                <DialogContent>
                  <DialogContentText>
                    <Trans
                      t={t}
                      i18nKey="manualExemptions.abandonConfirm"
                      values={{ course: row.target_course_code }}
                      components={{ code: <bdi dir="ltr" /> }}
                    />
                  </DialogContentText>
                </DialogContent>
                <DialogActions>
                  <Button onClick={() => setConfirmAbandon(false)}>
                    {t('manualExemptions.cancel')}
                  </Button>
                  <Button
                    color="error"
                    variant="contained"
                    startIcon={<DeleteOutlineIcon />}
                    onClick={() => {
                      setConfirmAbandon(false)
                      void abandon
                        .mutateAsync({
                          exemptionRequestId: id,
                          data: { expected_revision: revision },
                        })
                        .then(onChanged)
                    }}
                  >
                    {t('manualExemptions.confirmAbandon')}
                  </Button>
                </DialogActions>
              </Dialog>
            )}
          </Stack>
        )}
        {!row.editable && (
          <Stack spacing={1}>
            <ExemptionEvidenceSummary evidence={row.evidence} />
            {row.student_note && <Typography>{row.student_note}</Typography>}
          </Stack>
        )}
        <Divider />
        <ExemptionTransitionHistory transitions={row.transitions} />
      </Stack>
    </Paper>
  )
}

function EvidenceFields({
  form,
  idPrefix,
}: {
  form: UseFormReturn<EvidenceFormValues>
  idPrefix: string
}) {
  const { t } = useTranslation('enrollment')
  const requiredLabel = (label: string) => t('manualExemptions.requiredField', { label })
  const basis = form.watch('basis')
  const englishTest = form.watch('englishTest')
  const scoreError = form.formState.errors.score?.message

  return (
    <>
      <FormControl fullWidth>
        <InputLabel id={`${idPrefix}-basis-label`}>
          {requiredLabel(t('manualExemptions.evidenceBasis'))}
        </InputLabel>
        <Controller
          control={form.control}
          name="basis"
          render={({ field }) => (
            <Select
              {...field}
              required
              labelId={`${idPrefix}-basis-label`}
              label={requiredLabel(t('manualExemptions.evidenceBasis'))}
            >
              <MenuItem value="english">{t('manualExemptions.bases.english')}</MenuItem>
              <MenuItem value="ejust_bsc">{t('manualExemptions.bases.ejustBsc')}</MenuItem>
              <MenuItem value="ejust_msc">{t('manualExemptions.bases.ejustMsc')}</MenuItem>
            </Select>
          )}
        />
      </FormControl>
      {basis === 'english' && (
        <>
          <FormControl fullWidth>
            <InputLabel id={`${idPrefix}-english-test-label`}>
              {requiredLabel(t('manualExemptions.englishTest'))}
            </InputLabel>
            <Controller
              control={form.control}
              name="englishTest"
              render={({ field }) => (
                <Select
                  {...field}
                  required
                  labelId={`${idPrefix}-english-test-label`}
                  label={requiredLabel(t('manualExemptions.englishTest'))}
                >
                  <MenuItem value="IELTS">IELTS</MenuItem>
                  <MenuItem value="TOEFL iBT">TOEFL iBT</MenuItem>
                </Select>
              )}
            />
          </FormControl>
          <TextField
            {...form.register('score')}
            required
            label={requiredLabel(t('manualExemptions.englishScore'))}
            type="number"
            error={Boolean(scoreError)}
            helperText={
              <FormErrorText
                message={
                  scoreError
                    ? t(
                        scoreError === 'invalid_range'
                          ? 'manualExemptions.invalidEnglishScore'
                          : 'manualExemptions.required',
                      )
                    : undefined
                }
              />
            }
            slotProps={{
              htmlInput: {
                dir: 'ltr',
                min: 0,
                max: englishTest === 'IELTS' ? 9 : 120,
                step: englishTest === 'IELTS' ? 0.5 : 1,
              },
            }}
          />
        </>
      )}
      {basis === 'ejust_msc' && (
        <Controller
          control={form.control}
          name="previousProgramId"
          render={({ field, fieldState }) => (
            <PreviousProgramPicker
              value={field.value}
              onChange={field.onChange}
              onBlur={field.onBlur}
              error={fieldState.error?.message}
            />
          )}
        />
      )}
      <TextField
        {...form.register('note')}
        label={t('manualExemptions.studentNote')}
        multiline
        minRows={2}
        error={Boolean(form.formState.errors.note)}
        helperText={
          <FormErrorText
            message={form.formState.errors.note ? t('manualExemptions.tooLong') : undefined}
          />
        }
      />
    </>
  )
}

function PreviousProgramPicker({
  value,
  onChange,
  onBlur,
  error,
}: {
  value: string
  onChange: (value: string) => void
  onBlur: () => void
  error?: string
}) {
  const { t, i18n } = useTranslation('enrollment')
  const programs = useListPrograms()
  const options = (programs.data?.data ?? []).filter((program) => program.degree_level === 'MSc')
  const selected = options.find((program) => String(program.id) === value) ?? null
  const programLabel = (program: ListPrograms200DataItem) => {
    const name = pickLocaleName(program, i18n.language, program.code ?? '')

    return program.is_active === false
      ? t('manualExemptions.inactiveProgram', { program: name })
      : name
  }
  const label = t('manualExemptions.requiredField', {
    label: t('manualExemptions.previousProgramId'),
  })

  return (
    <Stack spacing={1}>
      <Autocomplete<ListPrograms200DataItem>
        options={options}
        value={selected}
        getOptionLabel={programLabel}
        isOptionEqualToValue={(option, candidate) => option.id === candidate.id}
        onChange={(_, program) => onChange(program?.id ? String(program.id) : '')}
        onBlur={onBlur}
        loading={programs.isPending}
        loadingText={t('manualExemptions.programsLoading')}
        noOptionsText={t('manualExemptions.noMscPrograms')}
        renderInput={(params) => (
          <TextField
            {...params}
            required
            label={label}
            error={Boolean(error)}
            helperText={
              <FormErrorText message={error ? t('manualExemptions.required') : undefined} />
            }
          />
        )}
      />
      {programs.isError && (
        <Alert
          severity="error"
          action={
            <Button color="inherit" onClick={() => void programs.refetch()}>
              {t('manualExemptions.retry')}
            </Button>
          }
        >
          {t('manualExemptions.programsLoadError')}
        </Alert>
      )}
    </Stack>
  )
}
