import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ApplyPage } from './ApplyPage'

const LINK = 'http://localhost/api/v1/applications/1/verify-email?expires=1&signature=abc'

// A tiny in-memory server behind the shared Axios mutator, so the wizard drives
// the REAL generated hooks: the status → step derivation, the submit → verify →
// documents progression, and inline 422 handling are observed, not stubbed.
const srv = vi.hoisted(() => ({
  status: 'draft' as string,
  submitShouldFail: false,
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
      return Promise.resolve({ data: { id: 1, status: srv.status, admission_cycle_id: 1 } })
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
})
