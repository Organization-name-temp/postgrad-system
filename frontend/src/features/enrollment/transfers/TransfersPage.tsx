import { useEffect, useState } from 'react'
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutlineRounded'
import DownloadIcon from '@mui/icons-material/Download'
import SendIcon from '@mui/icons-material/Send'
import SwapHorizIcon from '@mui/icons-material/SwapHoriz'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Divider,
  Checkbox,
  FormControl,
  FormControlLabel,
  FormGroup,
  FormHelperText,
  FormLabel,
  Pagination,
  Paper,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { Controller, useForm, type UseFormReturn } from 'react-hook-form'
import { Trans, useTranslation } from 'react-i18next'
import {
  exportMyTransferRequests,
  getMyTransferRequestsQueryKey,
  type MyTransferRequests200DataItem,
  type MyTransferEvidence200DataItem,
  type MyTransferTargets200DataItem,
  useAbandonATransferDraft,
  useCreateATransferDraft,
  useMyTransferRequests,
  useMyTransferEvidence,
  useMyTransferTargets,
  useReviseAnEditableTransferRequest,
  useSubmitOrResubmitATransferRequest,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage } from '../../../shared/api/errors'
import { downloadBlob } from '../credit-decisions/downloadBlob'
import { useRetryStableActionId } from '../credit-decisions/useRetryStableActionId'
import {
  FormErrorText,
  newTransferSchema,
  transferEvidenceSchema,
  type TransferEvidenceValues,
} from './formSchema'
import { TransferEvidenceSummary } from './TransferEvidenceSummary'
import { TransferHistory } from './TransferHistory'
import { TransferStatusChip } from './TransferStatusChip'

const blankEvidence: TransferEvidenceValues = {
  targetId: '',
  institution: '',
  courseCode: '',
  courseName: '',
  sourceHours: '',
  printedGrade: '',
  documentIds: [],
  note: '',
}

function evidencePayload(values: TransferEvidenceValues) {
  return {
    external_institution: values.institution,
    external_course_code: values.courseCode,
    external_course_name: values.courseName,
    source_credit_hours: Number(values.sourceHours),
    external_grade: values.printedGrade,
    document_ids: values.documentIds,
    student_note: values.note || undefined,
  }
}

export function TransfersPage() {
  const { t } = useTranslation('enrollment')
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const params = { sort: '-updated_at', page }
  const requests = useMyTransferRequests(params)
  const targets = useMyTransferTargets()
  const evidenceDocuments = useMyTransferEvidence()
  const create = useCreateATransferDraft()
  const form = useForm<TransferEvidenceValues>({
    resolver: zodResolver(newTransferSchema),
    mode: 'onBlur',
    defaultValues: blankEvidence,
  })
  const rows = requests.data?.data ?? []
  const lastPage = Math.max(1, requests.data?.meta?.last_page ?? 1)

  useEffect(() => {
    if (!requests.isPending && page > lastPage) setPage(lastPage)
  }, [lastPage, page, requests.isPending])

  const refresh = () => queryClient.invalidateQueries({ queryKey: getMyTransferRequestsQueryKey() })
  const saveDraft = async (values: TransferEvidenceValues) => {
    await create.mutateAsync({
      data: { target_course_id: Number(values.targetId), ...evidencePayload(values) },
    })
    form.reset(blankEvidence)
    await refresh()
  }
  const state =
    requests.isError || targets.isError || evidenceDocuments.isError
      ? 'error'
      : requests.isPending || targets.isPending || evidenceDocuments.isPending
        ? 'loading'
        : rows.length === 0
          ? 'empty'
          : 'content'

  return (
    <Stack spacing={3}>
      <Box>
        <Typography variant="h4" component="h1">
          {t('transfers.title')}
        </Typography>
        <Typography color="text.secondary">{t('transfers.intro')}</Typography>
      </Box>

      <Paper
        variant="outlined"
        sx={(theme) => ({ p: { xs: 2, sm: 3 }, borderRadius: theme.shape.radiusLg })}
      >
        <Typography variant="h6" component="h2" gutterBottom>
          {t('transfers.newDraft')}
        </Typography>
        <Stack component="form" spacing={2} noValidate onSubmit={form.handleSubmit(saveDraft)}>
          <Controller
            control={form.control}
            name="targetId"
            render={({ field, fieldState }) => (
              <TargetPicker
                targets={targets.data?.data ?? []}
                value={field.value ?? ''}
                onChange={field.onChange}
                onBlur={field.onBlur}
                error={fieldState.error?.message}
              />
            )}
          />
          <TransferFields
            form={form}
            prefix="new-transfer"
            documents={evidenceDocuments.data?.data ?? []}
          />
          <Button
            type="submit"
            variant="contained"
            disabled={!form.formState.isValid || create.isPending}
          >
            {t('transfers.saveDraft')}
          </Button>
          {create.isError && <Alert severity="error">{t('transfers.saveError')}</Alert>}
        </Stack>
      </Paper>

      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
        <Typography variant="h5" component="h2">
          {t('transfers.myRequests')}
        </Typography>
        <Button
          startIcon={<DownloadIcon />}
          onClick={() =>
            void exportMyTransferRequests(params).then((blob) =>
              downloadBlob(blob, 'transfer-requests.xlsx'),
            )
          }
        >
          {t('transfers.export')}
        </Button>
      </Stack>

      {state === 'loading' && (
        <Stack spacing={2} aria-label={t('transfers.loading')}>
          <Skeleton variant="rounded" sx={(theme) => ({ height: theme.spacing(27.5) })} />
          <Skeleton variant="rounded" sx={(theme) => ({ height: theme.spacing(27.5) })} />
        </Stack>
      )}
      {state === 'error' && (
        <Alert
          severity="error"
          action={
            <Button
              color="inherit"
              onClick={() => {
                void requests.refetch()
                void targets.refetch()
                void evidenceDocuments.refetch()
              }}
            >
              {t('transfers.retry')}
            </Button>
          }
        >
          {t('transfers.loadError')}
        </Alert>
      )}
      {state === 'empty' && (
        <Stack sx={{ alignItems: 'center', py: 4 }} spacing={1}>
          <SwapHorizIcon color="disabled" fontSize="large" />
          <Typography color="text.secondary">{t('transfers.empty')}</Typography>
        </Stack>
      )}
      {state === 'content' &&
        rows.map((row) => (
          <TransferRequestCard
            key={row.id}
            row={row}
            documents={evidenceDocuments.data?.data ?? []}
            onChanged={refresh}
          />
        ))}
      {!requests.isPending && !requests.isError && lastPage > 1 && (
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

function TransferRequestCard({
  row,
  documents,
  onChanged,
}: {
  row: MyTransferRequests200DataItem
  documents: MyTransferEvidence200DataItem[]
  onChanged: () => Promise<unknown>
}) {
  const { t } = useTranslation('enrollment')
  const [confirmAbandon, setConfirmAbandon] = useState(false)
  const revise = useReviseAnEditableTransferRequest()
  const submit = useSubmitOrResubmitATransferRequest()
  const abandon = useAbandonATransferDraft()
  const actionId = useRetryStableActionId()
  const id = row.id ?? 0
  const revision = row.revision ?? 1
  const form = useForm<TransferEvidenceValues>({
    resolver: zodResolver(transferEvidenceSchema),
    mode: 'onBlur',
    defaultValues: {
      ...blankEvidence,
      institution: row.external_institution ?? '',
      courseCode: row.external_course_code ?? '',
      courseName: row.external_course_name ?? '',
      sourceHours: row.source_credit_hours ?? '',
      printedGrade: row.external_grade ?? '',
      documentIds: row.document_ids?.map(String) ?? [],
      note: row.student_note ?? '',
    },
  })
  const pending = revise.isPending || submit.isPending || abandon.isPending
  const actionError = revise.error ?? submit.error ?? abandon.error
  const lastReturnRevision = Math.max(
    0,
    ...(row.transitions ?? [])
      .filter((item) => item.action?.startsWith('returned_by_'))
      .map((item) => item.revision ?? 0),
  )
  const canSubmit = row.status === 'Draft' || lastReturnRevision < revision

  const save = async (values: TransferEvidenceValues) => {
    await revise.mutateAsync({
      transferRequestId: id,
      data: { expected_revision: revision, ...evidencePayload(values) },
    })
    await onChanged()
  }
  const submitRequest = () => {
    const key = `transfer-submit:${id}:${revision}`
    void submit
      .mutateAsync({
        transferRequestId: id,
        data: { expected_revision: revision, action_id: actionId.forAction(key) },
      })
      .then(async () => {
        actionId.complete(key)
        await onChanged()
      })
      .catch(() => undefined)
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
              {t('transfers.requestMeta', {
                category: t(`transfers.categories.${row.target_category}`),
                count: revision,
              })}
            </Typography>
          </Box>
          <TransferStatusChip status={row.status} />
        </Stack>
        {row.editable ? (
          <Stack component="form" spacing={2} noValidate onSubmit={form.handleSubmit(save)}>
            <TransferFields form={form} prefix={`transfer-${id}`} documents={documents} />
            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
              <Button type="submit" disabled={pending || !form.formState.isValid}>
                {t('transfers.saveChanges')}
              </Button>
              <Button
                variant="outlined"
                startIcon={
                  <SendIcon
                    sx={(theme) => ({
                      transform: theme.direction === 'rtl' ? 'scaleX(-1)' : 'none',
                    })}
                  />
                }
                disabled={pending || !canSubmit}
                onClick={submitRequest}
              >
                {row.status === 'Returned' ? t('transfers.resubmit') : t('transfers.submit')}
              </Button>
              {row.status === 'Draft' && (
                <Button
                  color="error"
                  variant="outlined"
                  startIcon={<DeleteOutlineIcon />}
                  disabled={pending}
                  onClick={() => setConfirmAbandon(true)}
                >
                  {t('transfers.abandon')}
                </Button>
              )}
            </Stack>
            {!canSubmit && (
              <Typography variant="body2" color="text.secondary">
                {t('transfers.saveBeforeResubmit')}
              </Typography>
            )}
            {Boolean(actionError) && (
              <Alert severity="error">
                {apiErrorMessage(actionError) ?? t('transfers.actionError')}
              </Alert>
            )}
          </Stack>
        ) : (
          <TransferEvidenceSummary evidence={row} />
        )}
        {row.student_note && !row.editable && <Typography>{row.student_note}</Typography>}
        {row.decision && (
          <Alert severity="success">
            <Trans
              t={t}
              i18nKey="transfers.decision"
              values={{
                grade: row.decision.normalized_grade,
                count: row.decision.target_credit_hours,
              }}
              components={{ grade: <bdi dir="ltr" />, hours: <bdi dir="ltr" /> }}
            />
          </Alert>
        )}
        <Divider />
        <TransferHistory transitions={row.transitions} />
      </Stack>
      <Dialog
        open={confirmAbandon}
        onClose={() => setConfirmAbandon(false)}
        aria-labelledby={`transfer-abandon-${id}`}
      >
        <DialogTitle id={`transfer-abandon-${id}`}>{t('transfers.abandon')}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            <Trans
              t={t}
              i18nKey="transfers.abandonConfirm"
              values={{ course: row.target_course_code }}
              components={{ code: <bdi dir="ltr" /> }}
            />
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmAbandon(false)}>{t('transfers.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            startIcon={<DeleteOutlineIcon />}
            onClick={() => {
              setConfirmAbandon(false)
              void abandon
                .mutateAsync({ transferRequestId: id, data: { expected_revision: revision } })
                .then(onChanged)
            }}
          >
            {t('transfers.confirmAbandon')}
          </Button>
        </DialogActions>
      </Dialog>
    </Paper>
  )
}

function TargetPicker({
  targets,
  value,
  onChange,
  onBlur,
  error,
}: {
  targets: MyTransferTargets200DataItem[]
  value: string
  onChange: (value: string) => void
  onBlur: () => void
  error?: string
}) {
  const { t } = useTranslation('enrollment')
  const selected = targets.find((target) => String(target.target_course_id) === value) ?? null
  return (
    <Autocomplete
      options={targets}
      value={selected}
      getOptionDisabled={(target) => target.already_transferred === true}
      getOptionLabel={(target) =>
        `${target.target_course_code ?? ''} · ${t(`transfers.categories.${target.target_category}`)} · ${t('transfers.targetHoursPlain', { count: target.target_credit_hours })}`
      }
      renderOption={({ key, ...props }, target) => (
        <li key={key} {...props}>
          <bdi dir="ltr">{target.target_course_code}</bdi>
          <Box component="span" sx={{ mx: 1 }}>
            ·
          </Box>
          {t(`transfers.categories.${target.target_category}`)}
          <Box component="span" sx={{ mx: 1 }}>
            ·
          </Box>
          <bdi dir="ltr">
            {t('transfers.targetHoursPlain', { count: target.target_credit_hours })}
          </bdi>
        </li>
      )}
      isOptionEqualToValue={(option, candidate) =>
        option.target_course_id === candidate.target_course_id
      }
      onChange={(_, target) =>
        onChange(target?.target_course_id ? String(target.target_course_id) : '')
      }
      onBlur={onBlur}
      renderInput={(params) => (
        <TextField
          {...params}
          required
          label={t('transfers.target')}
          error={Boolean(error)}
          helperText={<FormErrorText message={error ? t('transfers.required') : undefined} />}
        />
      )}
    />
  )
}

function TransferFields({
  form,
  prefix,
  documents,
}: {
  form: UseFormReturn<TransferEvidenceValues>
  prefix: string
  documents: MyTransferEvidence200DataItem[]
}) {
  const { t } = useTranslation('enrollment')
  const input = (
    name: keyof TransferEvidenceValues,
    label: string,
    options: { multiline?: boolean; dir?: 'ltr' } = {},
  ) => {
    const error = form.formState.errors[name]?.message
    return (
      <TextField
        {...form.register(name)}
        id={`${prefix}-${name}`}
        required={name !== 'note'}
        label={label}
        multiline={options.multiline}
        minRows={options.multiline ? 2 : undefined}
        error={Boolean(error)}
        helperText={
          <FormErrorText message={error ? t(`transfers.validation.${error}`) : undefined} />
        }
        slotProps={{ htmlInput: options.dir ? { dir: options.dir } : undefined }}
      />
    )
  }
  return (
    <>
      {input('institution', t('transfers.institution'))}
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        {input('courseCode', t('transfers.courseCode'), { dir: 'ltr' })}
        {input('courseName', t('transfers.courseName'))}
      </Stack>
      <Stack direction={{ xs: 'column', md: 'row' }} spacing={2}>
        {input('sourceHours', t('transfers.sourceHours'), { dir: 'ltr' })}
        {input('printedGrade', t('transfers.printedGrade'))}
      </Stack>
      <Controller
        control={form.control}
        name="documentIds"
        render={({ field, fieldState }) => (
          <FormControl required error={Boolean(fieldState.error)}>
            <FormLabel>{t('transfers.documentIds')}</FormLabel>
            <FormGroup>
              {documents.map((document) => {
                const documentId = document.id ?? ''
                return (
                  <FormControlLabel
                    key={documentId}
                    disabled={!documentId}
                    control={
                      <Checkbox
                        checked={field.value.includes(documentId)}
                        onBlur={field.onBlur}
                        onChange={(_, checked) =>
                          field.onChange(
                            checked
                              ? [...field.value, documentId]
                              : field.value.filter((id) => id !== documentId),
                          )
                        }
                      />
                    }
                    label={
                      <span>
                        <bdi dir="ltr">{document.original_filename}</bdi> ·{' '}
                        {t(`transfers.documentTypes.${document.type}`)}
                      </span>
                    }
                  />
                )
              })}
            </FormGroup>
            <FormHelperText>
              {fieldState.error ? (
                <FormErrorText message={t('transfers.validation.required')} />
              ) : (
                t('transfers.documentIdsHelp')
              )}
            </FormHelperText>
          </FormControl>
        )}
      />
      {input('note', t('transfers.studentNote'), { multiline: true })}
    </>
  )
}
