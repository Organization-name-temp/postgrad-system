import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { PersonalInfoStep } from './PersonalInfoStep'

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

function renderStep() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <PersonalInfoStep application={undefined} onSubmitted={() => {}} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

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
