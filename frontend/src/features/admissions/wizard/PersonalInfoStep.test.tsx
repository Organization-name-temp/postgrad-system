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
    const user = userEvent.setup()
    renderStep()

    // Focus the first required field and tab away without filling it.
    const nameEn = await screen.findByLabelText(/full name \(english\)/i)
    await user.click(nameEn)
    await user.tab()

    // The error appears at the field on blur — not only after a bulk submit.
    expect(await screen.findByText(/this field is required/i)).toBeInTheDocument()
  })

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
