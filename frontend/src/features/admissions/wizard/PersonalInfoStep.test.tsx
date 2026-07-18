import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { PersonalInfoStep } from './PersonalInfoStep'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'

// The step never reaches the network in these tests — on-blur validation is entirely
// client-side (RHF + Zod, ADR-0014) — so the mutator is a no-op behind the real hooks.
const calls = vi.hoisted(() => ({ list: [] as string[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: { url?: string }) => {
    calls.list.push(config.url ?? '')
    return Promise.resolve({ data: {} })
  },
}))

// A complete, client-valid snapshot — used by the cross-field submit-gate tests, where
// a single bad score must be the only thing that can block the submit (Zod surfaces the
// object-level rules only once every field is individually valid).
const COMPLETE: MyApplication200Data = {
  id: 1,
  status: 'draft',
  admission_cycle_id: 1,
  name_en: 'Sara Applicant',
  name_ar: 'سارة المتقدمة',
  id_type: 'national_id',
  national_id: '29001010101010',
  phone: '+201000000000',
  date_of_birth: '1998-05-20',
  nationality: 'EG',
  gender: 'Female',
  address_country: 'Egypt',
  address_city: 'Alexandria',
  address_street: '15 Corniche Road',
  previous_university: 'Cairo University',
  graduation_certificate_name: 'BSc in Computer Science',
  graduation_date: '2020-07-01',
  previous_degree: 'BSc',
  previous_major: 'Computer Science',
  graduation_gpa: '3.50',
  graduation_cgpa: '3.45',
  gpa_scale: '4.0',
  is_ejust_graduate: false,
  applying_for_degree: 'MSc',
  english_test: 'IELTS',
  english_score: '7.50',
  native_arabic_speaker: true,
}

function renderStep(application: MyApplication200Data | undefined = undefined) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <PersonalInfoStep application={application} onSubmitted={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  calls.list = []
})

describe('PersonalInfoStep on-blur validation', () => {
  it('surfaces an inline error the moment focus leaves an invalid field', async () => {
    renderStep()

    // Focus the first required field and leave it without filling it.
    const nameEn = await screen.findByLabelText(/full name \(english\)/i)
    fireEvent.focus(nameEn)
    fireEvent.blur(nameEn)

    // The error appears at the field on blur — not only after a bulk submit.
    expect(await screen.findByText(/this field is required/i)).toBeInTheDocument()
  }, 15000)

  it('re-validates on change once a field has errored, clearing the message when fixed', async () => {
    renderStep()

    // Blur the field in place (no focus hop to a sibling, which would error it too).
    const nameEn = await screen.findByLabelText(/full name \(english\)/i)
    fireEvent.focus(nameEn)
    fireEvent.blur(nameEn)
    expect(await screen.findByText(/this field is required/i)).toBeInTheDocument()

    // A valid value re-validates on change and clears the inline error.
    fireEvent.change(nameEn, { target: { value: 'Sara Applicant' } })
    await waitFor(() =>
      expect(screen.queryByText(/this field is required/i)).not.toBeInTheDocument(),
    )
  })

  it('blocks an incomplete submit on the client, before any request is sent', async () => {
    const user = userEvent.setup()
    renderStep()

    await user.click(await screen.findByRole('button', { name: /save & continue/i }))

    // Client validation fires on submit and reports the empty required fields …
    expect(await screen.findAllByText(/this field is required/i)).not.toHaveLength(0)
    // … and nothing was submitted to the server.
    expect(calls.list.some((url) => url.includes('/application/submit'))).toBe(false)
  })
})

describe('PersonalInfoStep structured inputs (ticket 06)', () => {
  it('validates the identifier against the chosen ID type, and refreshes on a type switch', async () => {
    const user = userEvent.setup()
    renderStep()

    // Default type is National ID: a 6-char alphanumeric is not 14 digits → error.
    const idNumber = await screen.findByLabelText(/identification number/i)
    fireEvent.focus(idNumber)
    fireEvent.change(idNumber, { target: { value: 'ABC123' } })
    fireEvent.blur(idNumber)
    expect(await screen.findByText(/national id is exactly 14 digits/i)).toBeInTheDocument()

    // Switching to Passport re-checks the identifier: "ABC123" is a valid passport.
    await user.click(screen.getByRole('combobox', { name: /identification type/i }))
    await user.click(await screen.findByRole('option', { name: 'Passport' }))
    await waitFor(() =>
      expect(screen.queryByText(/national id is exactly 14 digits/i)).not.toBeInTheDocument(),
    )
  }, 15000)

  it('validates the phone as an E.164 number for the selected country', async () => {
    renderStep()

    // Country defaults to Egypt (+20); a too-short national number is invalid. The
    // national-number field is labelled "Phone" (the required marker adds an asterisk).
    const phone = await screen.findByLabelText(/phone/i)
    fireEvent.focus(phone)
    fireEvent.change(phone, { target: { value: '123' } })
    fireEvent.blur(phone)
    expect(await screen.findByText(/valid phone number/i)).toBeInTheDocument()

    // A full Egyptian mobile number clears the error (stored E.164 = +201000000000).
    fireEvent.change(phone, { target: { value: '1000000000' } })
    await waitFor(() => expect(screen.queryByText(/valid phone number/i)).not.toBeInTheDocument())
  }, 15000)

  it('offers nationality as a searchable country list, not free text', async () => {
    const user = userEvent.setup()
    renderStep()

    const nationality = await screen.findByRole('combobox', { name: /nationality/i })
    await user.click(nationality)
    await user.type(nationality, 'Egyp')
    // The list is filtered to matching countries; picking one is how a value is set.
    expect(await screen.findByRole('option', { name: /egypt/i })).toBeInTheDocument()
  }, 15000)
})

describe('PersonalInfoStep academic scores (ticket 07)', () => {
  it('keeps GPA/CGPA disabled until a scale is chosen, then enables them', async () => {
    const user = userEvent.setup()
    renderStep()

    // Before a scale is picked, both value fields are locked and nudge the applicant
    // to choose the scale first (scale-first gating).
    const gpa = await screen.findByLabelText(/graduation gpa/i)
    const cgpa = screen.getByLabelText(/graduation cgpa/i)
    expect(gpa).toBeDisabled()
    expect(cgpa).toBeDisabled()
    expect(screen.getAllByText(/choose your gpa scale first/i).length).toBeGreaterThan(0)

    // Choosing a scale unlocks them and switches the hint to that scale's ceiling.
    await user.click(screen.getByRole('combobox', { name: /gpa scale/i }))
    await user.click(await screen.findByRole('option', { name: '4.0' }))
    await waitFor(() => expect(screen.getByLabelText(/graduation gpa/i)).toBeEnabled())
    expect(screen.getByLabelText(/graduation cgpa/i)).toBeEnabled()
    expect(screen.getAllByText(/enter a value from 0 to 4/i).length).toBeGreaterThan(0)
  }, 15000)

  it('shows the selected test’s range as the score hint', async () => {
    const user = userEvent.setup()
    renderStep()

    // Picking IELTS surfaces its 0–9 band as a proactive hint on the score field.
    await user.click(screen.getByRole('combobox', { name: /english proficiency test/i }))
    await user.click(await screen.findByRole('option', { name: 'IELTS' }))
    expect(await screen.findByText(/valid range for this test: 0–9/i)).toBeInTheDocument()
  }, 15000)

  // The cross-field bounds (GPA vs its scale, score vs its test) are the client mirror
  // of the server rule; Zod surfaces them at submit, once the rest of the form is valid.
  // These seed a complete, valid snapshot so a single bad score is the only thing that
  // can block the submit — proving the rule, not the form's other fields.
  it('blocks the submit when a GPA exceeds the chosen scale’s ceiling', async () => {
    renderStep(COMPLETE)

    // 90 on a 4.0 scale is out of range.
    fireEvent.change(await screen.findByLabelText(/graduation gpa/i), { target: { value: '90' } })
    fireEvent.click(screen.getByRole('button', { name: /save & continue/i }))

    expect(await screen.findByText(/must be between 0 and 4/i)).toBeInTheDocument()
    expect(calls.list.some((url) => url.includes('/application/submit'))).toBe(false)
  }, 15000)

  it('blocks the submit when an english score is outside the selected test’s range', async () => {
    renderStep(COMPLETE)

    // The seeded test is IELTS (0–9); a 250 is out of range.
    fireEvent.change(await screen.findByLabelText(/english test score/i), {
      target: { value: '250' },
    })
    fireEvent.click(screen.getByRole('button', { name: /save & continue/i }))

    expect(await screen.findByText(/must be between 0 and 9/i)).toBeInTheDocument()
    expect(calls.list.some((url) => url.includes('/application/submit'))).toBe(false)
  }, 15000)
})
