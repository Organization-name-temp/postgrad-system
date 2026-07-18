import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ApplyPage } from './ApplyPage'

// The personal-info step now renders two country pickers (nationality + phone), each
// building a localized list of ~250 entries on mount — cheap in a browser, but slow
// enough in jsdom under full-suite load to blow the 5s default. Give these end-to-end
// wizard tests more headroom.
vi.setConfig({ testTimeout: 15000 })

const LINK = 'http://localhost/api/v1/applications/1/verify-email?expires=1&signature=abc'

// A complete, client-valid Step-1 snapshot. The personal-info step now validates on
// the client (RHF + Zod, ADR-0014) before it will submit, so the wizard-flow tests
// seed a filled form — an empty draft is deliberately blocked at the client now, and
// is covered by PersonalInfoStep's own on-blur test instead.
const SNAPSHOT = {
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

// A tiny in-memory server behind the shared Axios mutator, so the wizard drives
// the REAL generated hooks: the status → step derivation, the submit → verify →
// documents progression, and inline 422 handling are observed, not stubbed.
const srv = vi.hoisted(() => ({
  status: 'draft' as string,
  submitShouldFail: false,
  // The applicant's snapshot the in-memory server holds, so a PATCH edit is
  // observable on the next read (the wizard refetches after saving).
  name_en: undefined as string | undefined,
}))

// Every call the wizard makes through the shared mutator, so the PATCH re-save on
// back-navigation is asserted end-to-end rather than stubbed at the hook.
const calls = vi.hoisted(() => ({
  list: [] as Array<{ method?: string; url?: string; data?: unknown }>,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    // Following the verification link marks the email verified server-side.
    get: () => {
      srv.status = 'email_verified'
      return Promise.resolve({ data: {} })
    },
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    calls.list.push({ method: config.method, url, data: config.data })
    if (url.endsWith('/application/submit')) {
      if (srv.submitShouldFail) {
        return Promise.reject({
          isAxiosError: true,
          response: {
            status: 422,
            data: {
              message: 'The full name (Arabic) field is required.',
              errors: { name_ar: ['The full name (Arabic) field is required.'] },
            },
          },
        })
      }
      srv.status = 'submitted'
      return Promise.resolve({
        data: { id: 1, status: 'submitted', admission_cycle_id: 1, name_en: 'Sara' },
        verification_url: LINK,
      })
    }
    if (url.endsWith('/application/resend-verification')) {
      return Promise.resolve({
        data: { id: 1, status: 'submitted', admission_cycle_id: 1 },
        verification_url: LINK,
      })
    }
    if (url.endsWith('/application')) {
      // PATCH persists the edited field so a later read reflects it.
      if (config.method === 'PATCH') {
        const body = (config.data ?? {}) as { name_en?: string }
        if (body.name_en !== undefined) srv.name_en = body.name_en
      }
      return Promise.resolve({
        data: {
          ...SNAPSHOT,
          id: 1,
          status: srv.status,
          admission_cycle_id: 1,
          name_en: srv.name_en ?? SNAPSHOT.name_en,
        },
      })
    }
    return Promise.resolve({})
  },
}))

function renderApply() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/apply']}>
        <ApplyPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('ApplyPage', () => {
  beforeEach(() => {
    srv.status = 'draft'
    srv.submitShouldFail = false
    srv.name_en = undefined
    calls.list = []
  })

  it('opens a draft on the personal-info step', async () => {
    renderApply()
    expect(await screen.findByText('Personal information')).toBeInTheDocument()
    expect(screen.getByLabelText(/full name \(english\)/i)).toBeInTheDocument()
  })

  it('shows the server’s inline field error when a submit is rejected', async () => {
    srv.submitShouldFail = true
    const user = userEvent.setup({ delay: null })
    renderApply()

    await user.click(await screen.findByRole('button', { name: /save & continue/i }))

    // Shown both as the general alert and inline beside the field.
    const messages = await screen.findAllByText(/full name \(arabic\) field is required/i)
    expect(messages.length).toBeGreaterThanOrEqual(2)
    // Still on Step 1 — the submit did not advance the wizard.
    expect(screen.getByText('Personal information')).toBeInTheDocument()
  })

  it('submits Step 1, then verifies the email, walking through to the documents step', async () => {
    const user = userEvent.setup({ delay: null })
    renderApply()

    // Step 1 → submit advances to the email-verify step and shows the link.
    await user.click(await screen.findByRole('button', { name: /save & continue/i }))
    expect(await screen.findByText('Verify your email')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /verify-email/i })).toHaveAttribute('href', LINK)

    // Verifying follows the link and advances past verification.
    await user.click(screen.getByRole('button', { name: /^verify email$/i }))
    expect(await screen.findByText(/your email is verified/i)).toBeInTheDocument()
  })

  it('offers a resend when landing on the verify step without a link', async () => {
    srv.status = 'submitted'
    const user = userEvent.setup({ delay: null })
    renderApply()

    expect(await screen.findByText(/no verification link is showing/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /resend link/i }))
    expect(await screen.findByRole('link', { name: /verify-email/i })).toHaveAttribute('href', LINK)
  })

  it('goes Back from documents to personal info, edits a field, and persists it via PATCH', async () => {
    // A verified applicant resting on the documents step — the post-verify phase.
    srv.status = 'email_verified'
    const user = userEvent.setup({ delay: null })
    renderApply()

    // From documents, Back returns to the personal-info step for editing.
    await user.click(await screen.findByRole('button', { name: /^back$/i }))
    expect(await screen.findByText('Personal information')).toBeInTheDocument()

    // Correct a field and continue: the edit is saved through PATCH /application …
    // (fireEvent sets the value in one render, keeping the 24-field form snappy).
    fireEvent.change(screen.getByLabelText(/full name \(english\)/i), {
      target: { value: 'Corrected Name' },
    })
    await user.click(screen.getByRole('button', { name: /save & continue/i }))

    await waitFor(() =>
      expect(
        calls.list.some(
          (call) =>
            call.method === 'PATCH' &&
            call.url === '/api/v1/application' &&
            (call.data as { name_en?: string } | undefined)?.name_en === 'Corrected Name',
        ),
      ).toBe(true),
    )

    // … and the wizard returns to the documents step.
    expect(await screen.findByText(/choose the program you are applying to/i)).toBeInTheDocument()
  }, 15000)

  it('jumps between completed steps via the stepper, skipping the verified email step', async () => {
    // A verified applicant in the post-verify phase (resting on documents).
    srv.status = 'email_verified'
    const user = userEvent.setup({ delay: null })
    renderApply()

    // The reachable steps are clickable tabs; the already-verified email step is not.
    expect(await screen.findByRole('tab', { name: /personal info/i })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: /verify email/i })).not.toBeInTheDocument()

    // Clicking the personal-info step jumps straight to it.
    await user.click(screen.getByRole('tab', { name: /personal info/i }))
    expect(await screen.findByText('Personal information')).toBeInTheDocument()
  }, 15000)

  it('edits a section from the Review step and returns to Review, preserving values', async () => {
    srv.status = 'email_verified'
    const user = userEvent.setup({ delay: null })
    renderApply()

    // Jump to Review via the stepper (the steps are tabs).
    await user.click(await screen.findByRole('tab', { name: /review/i }))
    expect(await screen.findByText('Review & submit')).toBeInTheDocument()

    // Edit the first (identity) section → lands on the personal-info step, values intact.
    const editButtons = await screen.findAllByRole('button', { name: /^edit$/i })
    await user.click(editButtons[0])
    expect(await screen.findByText('Personal information')).toBeInTheDocument()
    expect(screen.getByLabelText(/full name \(english\)/i)).toHaveValue('Sara Applicant')

    // Leaving the edited step returns to Review (not the linear documents neighbour).
    await user.click(screen.getByRole('button', { name: /save & continue/i }))
    expect(await screen.findByText('Review & submit')).toBeInTheDocument()
  }, 15000)
})
