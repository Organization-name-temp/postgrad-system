import { useState } from 'react'
import type { FormEvent } from 'react'
import {
  Alert,
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
  PREVIOUS_DEGREES,
} from './fieldOptions'

interface PersonalInfoStepProps {
  application: MyApplication200Data | undefined
  /** Called after a successful submit with the issued verification link (null when the applicant was already verified). */
  onSubmitted: (verificationUrl: string | null) => void
}

/** The editable form state — every field a string (or boolean for the two flags). */
interface FormState {
  name_en: string
  name_ar: string
  national_id: string
  phone: string
  date_of_birth: string
  nationality: string
  gender: string
  address_country: string
  address_city: string
  address_street: string
  previous_university: string
  graduation_certificate_name: string
  graduation_date: string
  previous_degree: string
  previous_major: string
  graduation_gpa: string
  graduation_cgpa: string
  gpa_scale: string
  is_ejust_graduate: boolean
  applying_for_degree: string
  english_test: string
  english_score: string
  native_arabic_speaker: boolean
}

function seed(application: MyApplication200Data | undefined): FormState {
  return {
    name_en: application?.name_en ?? '',
    name_ar: application?.name_ar ?? '',
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
function toSubmitBody(form: FormState): SubmitStep1Body {
  return {
    name_en: form.name_en,
    name_ar: form.name_ar,
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
function toPartialBody(form: FormState): SaveStep1partialBody {
  const body: SaveStep1partialBody = {
    is_ejust_graduate: form.is_ejust_graduate,
    native_arabic_speaker: form.native_arabic_speaker,
  }
  const text: Array<keyof SaveStep1partialBody & keyof FormState> = [
    'name_en',
    'name_ar',
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
 * Step 1 of the applicant wizard: the per-cycle personal-info snapshot (SRS
 * §4.2). "Save & continue" submits the complete snapshot — the server validates
 * it, moves the Application to `submitted`, and returns the email-verification
 * link; "Save & finish later" persists a partial draft so the applicant can
 * resume. Server-side validation failures are shown inline beside each field
 * (already localized by the API), so errors read in the applicant's language.
 */
export function PersonalInfoStep({ application, onSubmitted }: PersonalInfoStepProps) {
  const { t } = useTranslation('admissions')
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [form, setForm] = useState<FormState>(() => seed(application))
  const submit = useSubmitStep1()
  const save = useSaveStep1partial()

  const fieldErrors = apiFieldErrors(submit.error ?? save.error)
  const generalError = submit.isError
    ? (apiErrorMessage(submit.error) ?? t('personalInfo.submitError'))
    : undefined
  const busy = submit.isPending || save.isPending

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((current) => ({ ...current, [key]: value }))

  const refresh = () => queryClient.invalidateQueries({ queryKey: getMyApplicationQueryKey() })

  const handleContinue = (event: FormEvent) => {
    event.preventDefault()
    submit.mutate(
      { data: toSubmitBody(form) },
      {
        onSuccess: async (result) => {
          await refresh()
          onSubmitted(result.verification_url ?? null)
        },
      },
    )
  }

  const handleSaveLater = () => {
    save.mutate(
      { data: toPartialBody(form) },
      {
        onSuccess: async () => {
          await refresh()
          navigate('/', { replace: true })
        },
      },
    )
  }

  /** A text/number field wired to form state, with the server's inline error for it. */
  const text = (name: keyof FormState, opts: { type?: string; required?: boolean } = {}) => (
    <TextField
      label={t(`personalInfo.fields.${name}`)}
      value={form[name] as string}
      onChange={(event) => set(name, event.target.value as FormState[typeof name])}
      type={opts.type ?? 'text'}
      required={opts.required}
      fullWidth
      error={Boolean(fieldErrors[name])}
      helperText={fieldErrors[name]?.[0]}
      slotProps={opts.type === 'date' ? { inputLabel: { shrink: true } } : undefined}
    />
  )

  const select = (name: keyof FormState, options: readonly string[], labelPrefix?: string) => (
    <TextField
      select
      label={t(`personalInfo.fields.${name}`)}
      value={form[name] as string}
      onChange={(event) => set(name, event.target.value as FormState[typeof name])}
      required
      fullWidth
      error={Boolean(fieldErrors[name])}
      helperText={fieldErrors[name]?.[0]}
    >
      {options.map((option) => (
        <MenuItem key={option} value={option}>
          {labelPrefix ? t(`${labelPrefix}.${option}`) : option}
        </MenuItem>
      ))}
    </TextField>
  )

  const scoreDisabled = form.english_test === ENGLISH_TEST_NONE

  return (
    <Box component="form" onSubmit={handleContinue} noValidate>
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
        {text('national_id', { required: true })}
        {text('phone', { required: true })}
        {text('date_of_birth', { type: 'date', required: true })}
        {text('nationality', { required: true })}
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
        <FormControlLabel
          control={
            <Checkbox
              checked={form.is_ejust_graduate}
              onChange={(event) => set('is_ejust_graduate', event.target.checked)}
            />
          }
          label={t('personalInfo.fields.is_ejust_graduate')}
        />

        <Divider textAlign="left">{t('personalInfo.sections.english')}</Divider>
        {select('english_test', ENGLISH_TESTS)}
        <TextField
          label={t('personalInfo.fields.english_score')}
          value={scoreDisabled ? '' : form.english_score}
          onChange={(event) => set('english_score', event.target.value)}
          type="number"
          fullWidth
          disabled={scoreDisabled}
          helperText={fieldErrors.english_score?.[0] ?? t('personalInfo.scoreHelp')}
          error={Boolean(fieldErrors.english_score)}
        />
        <FormControlLabel
          control={
            <Checkbox
              checked={form.native_arabic_speaker}
              onChange={(event) => set('native_arabic_speaker', event.target.checked)}
            />
          }
          label={t('personalInfo.fields.native_arabic_speaker')}
        />
      </Stack>

      <Stack direction="row" spacing={2} sx={{ mt: 3 }}>
        <Button type="submit" variant="contained" disabled={busy}>
          {submit.isPending ? t('personalInfo.saving') : t('personalInfo.saveContinue')}
        </Button>
        <Button type="button" variant="outlined" onClick={handleSaveLater} disabled={busy}>
          {t('personalInfo.saveLater')}
        </Button>
      </Stack>
    </Box>
  )
}
