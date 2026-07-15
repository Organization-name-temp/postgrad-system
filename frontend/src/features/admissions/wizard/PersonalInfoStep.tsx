import { useMemo } from 'react'
import {
  Alert,
  Autocomplete,
  Box,
  Button,
  Checkbox,
  Divider,
  FormControlLabel,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import type { FieldPath, Resolver } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import {
  getMyApplicationQueryKey,
  useSaveStep1partial,
  useSubmitStep1,
} from '../../../shared/api/generated/endpoints'
import type {
  MyApplication200Data,
  SaveStep1partialBody,
  SubmitStep1Body,
} from '../../../shared/api/generated/endpoints'
import { apiErrorMessage, apiFieldErrors } from '../../../shared/api/errors'
import {
  APPLYING_DEGREES,
  ENGLISH_TESTS,
  ENGLISH_TEST_NONE,
  GENDERS,
  GPA_SCALES,
  ID_TYPES,
  ID_TYPE_NATIONAL,
  PREVIOUS_DEGREES,
} from './fieldOptions'
import { countryOptions } from './countries'
import { PhoneField } from './PhoneField'
import { buildPersonalInfoSchema, ID_FORMATS } from './personalInfoSchema'
import type { PersonalInfoValues } from './personalInfoSchema'

interface PersonalInfoStepProps {
  application: MyApplication200Data | undefined
  /** Called after a successful submit with the issued verification link (null when the applicant was already verified). Submit-flow only. */
  onSubmitted?: (verificationUrl: string | null) => void
  /**
   * Edit-flow (post-verification back-navigation): when provided, the step edits the
   * existing snapshot via the per-step save (PATCH) and returns here rather than
   * submitting Step 1 and issuing a verification link. Set by the wizard when the
   * applicant reaches this step by going Back from Documents (Phase 2.5 B3).
   */
  onBack?: () => void
}

/** Seed the form from the current snapshot — every field a string (empty where unset), the two flags booleans. */
function seed(application: MyApplication200Data | undefined): PersonalInfoValues {
  return {
    name_en: application?.name_en ?? '',
    name_ar: application?.name_ar ?? '',
    // Default a fresh form to National ID (the common case for local applicants);
    // a resumed snapshot keeps whatever was chosen.
    id_type: application?.id_type ?? ID_TYPE_NATIONAL,
    national_id: application?.national_id ?? '',
    phone: application?.phone ?? '',
    date_of_birth: application?.date_of_birth ?? '',
    nationality: application?.nationality ?? '',
    gender: application?.gender ?? '',
    address_country: application?.address_country ?? '',
    address_city: application?.address_city ?? '',
    address_street: application?.address_street ?? '',
    previous_university: application?.previous_university ?? '',
    graduation_certificate_name: application?.graduation_certificate_name ?? '',
    graduation_date: application?.graduation_date ?? '',
    previous_degree: application?.previous_degree ?? '',
    previous_major: application?.previous_major ?? '',
    graduation_gpa: application?.graduation_gpa != null ? String(application.graduation_gpa) : '',
    graduation_cgpa:
      application?.graduation_cgpa != null ? String(application.graduation_cgpa) : '',
    gpa_scale: application?.gpa_scale ?? '',
    is_ejust_graduate: application?.is_ejust_graduate ?? false,
    applying_for_degree: application?.applying_for_degree ?? '',
    english_test: application?.english_test ?? '',
    english_score: application?.english_score != null ? String(application.english_score) : '',
    native_arabic_speaker: application?.native_arabic_speaker ?? false,
  }
}

/** The full snapshot for submit — every field present; the score is dropped for the "None" test. */
function toSubmitBody(form: PersonalInfoValues): SubmitStep1Body {
  return {
    name_en: form.name_en,
    name_ar: form.name_ar,
    id_type: form.id_type,
    national_id: form.national_id,
    phone: form.phone,
    date_of_birth: form.date_of_birth,
    nationality: form.nationality,
    gender: form.gender,
    address_country: form.address_country,
    address_city: form.address_city,
    address_street: form.address_street,
    previous_university: form.previous_university,
    graduation_certificate_name: form.graduation_certificate_name,
    graduation_date: form.graduation_date,
    previous_degree: form.previous_degree,
    previous_major: form.previous_major,
    graduation_gpa: Number(form.graduation_gpa),
    graduation_cgpa: Number(form.graduation_cgpa),
    gpa_scale: form.gpa_scale,
    is_ejust_graduate: form.is_ejust_graduate,
    applying_for_degree: form.applying_for_degree,
    english_test: form.english_test,
    english_score:
      form.english_test === ENGLISH_TEST_NONE || form.english_score === ''
        ? undefined
        : Number(form.english_score),
    native_arabic_speaker: form.native_arabic_speaker,
  }
}

/** A partial save — only the fields the applicant has actually filled (empty strings omitted, so a Draft is not rejected for half-entered data). The two flags always carry a definite value. */
function toPartialBody(form: PersonalInfoValues): SaveStep1partialBody {
  const body: SaveStep1partialBody = {
    is_ejust_graduate: form.is_ejust_graduate,
    native_arabic_speaker: form.native_arabic_speaker,
  }
  const text: Array<keyof SaveStep1partialBody & keyof PersonalInfoValues> = [
    'name_en',
    'name_ar',
    'id_type',
    'national_id',
    'phone',
    'date_of_birth',
    'nationality',
    'gender',
    'address_country',
    'address_city',
    'address_street',
    'previous_university',
    'graduation_certificate_name',
    'graduation_date',
    'previous_degree',
    'previous_major',
    'gpa_scale',
    'applying_for_degree',
    'english_test',
  ]
  for (const key of text) {
    const value = form[key]
    if (typeof value === 'string' && value !== '') {
      body[key] = value as never
    }
  }
  if (form.graduation_gpa !== '') body.graduation_gpa = Number(form.graduation_gpa)
  if (form.graduation_cgpa !== '') body.graduation_cgpa = Number(form.graduation_cgpa)
  if (form.english_score !== '' && form.english_test !== ENGLISH_TEST_NONE) {
    body.english_score = Number(form.english_score)
  }
  return body
}

/**
 * Step 1 of the applicant wizard: the per-cycle personal-info snapshot (SRS §4.2),
 * on React Hook Form + a Zod schema that mirrors the server's `snapshotRules()`
 * (ADR-0014). Every field validates **on blur** (and re-validates on change once it
 * has errored), so the applicant learns what is wrong at the field rather than as a
 * bulk `422` after submit. The server stays the authoritative gate: its `422`s are
 * merged back onto the offending fields (uniqueness, and any rule the client mirror
 * has not caught). "Save & continue" submits the complete snapshot; "Save & finish
 * later" (and the edit-flow Back) persist a partial draft without gating on
 * completeness, so a half-filled form can still be resumed.
 */
export function PersonalInfoStep({ application, onSubmitted, onBack }: PersonalInfoStepProps) {
  const { t, i18n } = useTranslation('admissions')
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  // The nationality options, localized to the active language and sorted by label.
  const nationalities = useMemo(() => countryOptions(i18n.language), [i18n.language])

  const submit = useSubmitStep1()
  const save = useSaveStep1partial()

  // The schema carries the live translator, so its messages are localized; rebuilt
  // when the language changes. Typed to the all-string form state (the Zod enum
  // fields narrow the output, but the form seeds and edits them as plain strings).
  const schema = useMemo(() => buildPersonalInfoSchema(t), [t])
  const resolver = useMemo(() => zodResolver(schema) as Resolver<PersonalInfoValues>, [schema])

  const { control, handleSubmit, getValues, setValue, setError, trigger, watch } =
    useForm<PersonalInfoValues>({
      // `onTouched` is exactly ADR-0014's rule: validate a field on its first blur,
      // then re-validate it on every change once it has been touched/errored.
      resolver,
      mode: 'onTouched',
      reValidateMode: 'onChange',
      defaultValues: seed(application),
    })

  // Edit-flow: reached by going Back from a later step to correct a field. The
  // snapshot is already submitted, so the primary action re-saves it (PATCH) and
  // returns, rather than re-submitting Step 1.
  const editing = onBack !== undefined

  const generalError =
    submit.isError || save.isError
      ? (apiErrorMessage(submit.error ?? save.error) ?? t('personalInfo.submitError'))
      : undefined
  const busy = submit.isPending || save.isPending

  const refresh = () => queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })

  /** Merge the server's inline `422` field errors onto the form, so the authoritative
   *  gate still surfaces beside the offending field (uniqueness, and anything the client
   *  mirror does not catch). Editing the field re-validates and clears it. */
  const applyServerErrors = (error: unknown) => {
    for (const [field, messages] of Object.entries(apiFieldErrors(error))) {
      if (field in getValues() && messages?.[0]) {
        setError(field as FieldPath<PersonalInfoValues>, {
          type: 'server',
          message: messages[0],
        })
      }
    }
  }

  /** Persist the entered fields as a partial draft, then run `after` once the fresh
   *  snapshot has been reloaded — shared by "save & finish later" and the edit-flow
   *  Back-navigation save. Not gated on completeness (a draft may be half-filled). */
  const savePartial = (after: () => void) =>
    save.mutate(
      { data: toPartialBody(getValues()) },
      {
        onSuccess: async () => {
          await refresh()
          after()
        },
        onError: applyServerErrors,
      },
    )

  const handleSaveLater = () => savePartial(() => navigate('/', { replace: true }))

  // Submit-flow primary action: client validation runs first (handleSubmit), then the
  // full snapshot is submitted; a server 422 merges back onto the fields.
  const onValidSubmit = handleSubmit((values) => {
    submit.mutate(
      { data: toSubmitBody(values) },
      {
        onSuccess: async (result) => {
          await refresh()
          onSubmitted?.(result.verification_url ?? null)
        },
        onError: applyServerErrors,
      },
    )
  })

  const handlePrimary = () => {
    if (editing) {
      // Persist the correction, then return to the step the applicant came from.
      savePartial(() => onBack?.())
      return
    }
    void onValidSubmit()
  }

  /** A text/number field wired to the form, with its inline (client or server) error. */
  const text = (
    name: FieldPath<PersonalInfoValues>,
    opts: { type?: string; required?: boolean } = {},
  ) => (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <TextField
          {...field}
          value={(field.value as string) ?? ''}
          label={t(`personalInfo.fields.${name}`)}
          type={opts.type ?? 'text'}
          required={opts.required}
          fullWidth
          error={Boolean(fieldState.error)}
          helperText={fieldState.error?.message}
          slotProps={opts.type === 'date' ? { inputLabel: { shrink: true } } : undefined}
        />
      )}
    />
  )

  /** A required dropdown over a closed option list, with its inline error. */
  const select = (
    name: FieldPath<PersonalInfoValues>,
    options: readonly string[],
    labelPrefix?: string,
  ) => (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <TextField
          {...field}
          value={(field.value as string) ?? ''}
          select
          label={t(`personalInfo.fields.${name}`)}
          required
          fullWidth
          error={Boolean(fieldState.error)}
          helperText={fieldState.error?.message}
        >
          {options.map((option) => (
            <MenuItem key={option} value={option}>
              {labelPrefix ? t(`${labelPrefix}.${option}`) : option}
            </MenuItem>
          ))}
        </TextField>
      )}
    />
  )

  /** A boolean flag rendered as a checkbox. */
  const flag = (name: FieldPath<PersonalInfoValues>) => (
    <Controller
      control={control}
      name={name}
      render={({ field }) => (
        <FormControlLabel
          control={
            <Checkbox
              checked={Boolean(field.value)}
              onChange={(event) => field.onChange(event.target.checked)}
              onBlur={field.onBlur}
            />
          }
          label={t(`personalInfo.fields.${name}`)}
        />
      )}
    />
  )

  const scoreDisabled = watch('english_test') === ENGLISH_TEST_NONE

  // A proactive format hint for the identifier, driven by the chosen ID type (the same
  // message the validator would show), so the applicant knows the expected shape up front.
  const idType = watch('id_type')
  const identifierHint = ID_FORMATS[idType] ? t(ID_FORMATS[idType].messageKey) : undefined

  return (
    <Box
      component="form"
      onSubmit={(event) => {
        event.preventDefault()
        handlePrimary()
      }}
      noValidate
    >
      <Typography variant="h6" gutterBottom>
        {t('personalInfo.title')}
      </Typography>
      <Typography variant="body2" color="text.secondary" gutterBottom>
        {t('personalInfo.intro')}
      </Typography>

      {generalError && (
        <Alert severity="error" sx={{ my: 2 }}>
          {generalError}
        </Alert>
      )}

      <Stack spacing={2} sx={{ mt: 2 }}>
        <Divider textAlign="left">{t('personalInfo.sections.identity')}</Divider>
        {text('name_en', { required: true })}
        {text('name_ar', { required: true })}
        <Controller
          control={control}
          name="id_type"
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              value={(field.value as string) ?? ''}
              onChange={(event) => {
                field.onChange(event)
                // The identifier's format depends on this choice — re-check it so a
                // switch (e.g. National ID → Passport) refreshes any inline error now.
                void trigger('national_id')
              }}
              select
              label={t('personalInfo.fields.id_type')}
              required
              fullWidth
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message}
            >
              {ID_TYPES.map((option) => (
                <MenuItem key={option} value={option}>
                  {t(`personalInfo.idTypes.${option}`)}
                </MenuItem>
              ))}
            </TextField>
          )}
        />
        <Controller
          control={control}
          name="national_id"
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              value={(field.value as string) ?? ''}
              label={t('personalInfo.fields.national_id')}
              required
              fullWidth
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message ?? identifierHint}
            />
          )}
        />
        <Controller
          control={control}
          name="phone"
          render={({ field, fieldState }) => (
            <PhoneField
              value={(field.value as string) ?? ''}
              onChange={field.onChange}
              onBlur={field.onBlur}
              label={t('personalInfo.fields.phone')}
              countryLabelText={t('personalInfo.fields.phone_country')}
              lang={i18n.language}
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message ?? t('personalInfo.phoneHelp')}
            />
          )}
        />
        {text('date_of_birth', { type: 'date', required: true })}
        <Controller
          control={control}
          name="nationality"
          render={({ field, fieldState }) => (
            <Autocomplete
              options={nationalities}
              value={nationalities.find((option) => option.code === field.value) ?? null}
              getOptionLabel={(option) => option.label}
              isOptionEqualToValue={(option, value) => option.code === value.code}
              onChange={(_event, option) => field.onChange(option?.code ?? '')}
              onBlur={field.onBlur}
              fullWidth
              renderInput={(params) => (
                <TextField
                  {...params}
                  label={t('personalInfo.fields.nationality')}
                  required
                  error={Boolean(fieldState.error)}
                  helperText={fieldState.error?.message}
                />
              )}
            />
          )}
        />
        {select('gender', GENDERS, 'personalInfo.genders')}

        <Divider textAlign="left">{t('personalInfo.sections.address')}</Divider>
        {text('address_country', { required: true })}
        {text('address_city', { required: true })}
        {text('address_street', { required: true })}

        <Divider textAlign="left">{t('personalInfo.sections.academic')}</Divider>
        {text('previous_university', { required: true })}
        {text('graduation_certificate_name', { required: true })}
        {text('graduation_date', { type: 'date', required: true })}
        {select('previous_degree', PREVIOUS_DEGREES, 'personalInfo.degrees')}
        {text('previous_major', { required: true })}
        {text('graduation_gpa', { type: 'number', required: true })}
        {text('graduation_cgpa', { type: 'number', required: true })}
        {select('gpa_scale', GPA_SCALES)}
        {select('applying_for_degree', APPLYING_DEGREES, 'personalInfo.degrees')}
        {flag('is_ejust_graduate')}

        <Divider textAlign="left">{t('personalInfo.sections.english')}</Divider>
        <Controller
          control={control}
          name="english_test"
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              value={(field.value as string) ?? ''}
              onChange={(event) => {
                field.onChange(event)
                // No score for "None": clear it so a stale value can't be submitted.
                if (event.target.value === ENGLISH_TEST_NONE) {
                  setValue('english_score', '', { shouldValidate: false })
                }
              }}
              select
              label={t('personalInfo.fields.english_test')}
              required
              fullWidth
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message}
            >
              {ENGLISH_TESTS.map((option) => (
                <MenuItem key={option} value={option}>
                  {option}
                </MenuItem>
              ))}
            </TextField>
          )}
        />
        <Controller
          control={control}
          name="english_score"
          render={({ field, fieldState }) => (
            <TextField
              {...field}
              value={scoreDisabled ? '' : ((field.value as string) ?? '')}
              label={t('personalInfo.fields.english_score')}
              type="number"
              fullWidth
              disabled={scoreDisabled}
              error={Boolean(fieldState.error)}
              helperText={fieldState.error?.message ?? t('personalInfo.scoreHelp')}
            />
          )}
        />
        {flag('native_arabic_speaker')}
      </Stack>

      <Stack direction="row" spacing={2} sx={{ mt: 3 }}>
        <Button type="submit" variant="contained" disabled={busy}>
          {(editing ? save.isPending : submit.isPending)
            ? t('personalInfo.saving')
            : t('personalInfo.saveContinue')}
        </Button>
        {editing ? (
          <Button type="button" variant="outlined" onClick={onBack} disabled={busy}>
            {t('personalInfo.back')}
          </Button>
        ) : (
          <Button type="button" variant="outlined" onClick={handleSaveLater} disabled={busy}>
            {t('personalInfo.saveLater')}
          </Button>
        )}
      </Stack>
    </Box>
  )
}
