import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { DocumentsStep } from './DocumentsStep'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'

// Record the calls the generated hooks make through the shared mutator, so the
// upload/remove wiring is observed end-to-end rather than stubbed at the hook.
const calls = vi.hoisted(() => ({ list: [] as Array<{ method?: string; url?: string }> }))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: AxiosRequestConfig) => {
    calls.list.push({ method: config.method, url: config.url })
    if (config.method === 'POST') {
      return Promise.resolve({
        data: {
          id: 'NEWULID',
          document_type: 'transcript',
          original_filename: 'new.pdf',
          mime: 'application/pdf',
          size_bytes: 100,
          uploaded_at: '2026-07-14T10:00:00Z',
          download_url: 'http://localhost/api/v1/documents/NEWULID',
        },
      })
    }
    return Promise.resolve({})
  },
}))

const application: MyApplication200Data = {
  id: 1,
  status: 'email_verified',
  admission_cycle_id: 1,
  documents: [
    {
      id: 'ULID1',
      document_type: 'graduation_certificate',
      original_filename: 'grad.pdf',
      mime: 'application/pdf',
      size_bytes: 2048,
      uploaded_at: '2026-07-14T10:00:00Z',
      download_url: 'http://localhost/api/v1/documents/ULID1',
    },
  ],
}

function renderStep() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <DocumentsStep application={application} />
    </QueryClientProvider>,
  )
}

describe('DocumentsStep', () => {
  beforeEach(() => {
    calls.list = []
  })

  it('renders a widget for every document type with its requirement', () => {
    renderStep()

    // Config-driven rendering: the required core documents, a conditional one, and an optional one.
    expect(screen.getByText('Graduation certificate')).toBeInTheDocument()
    expect(screen.getByText('Academic transcript')).toBeInTheDocument()
    expect(screen.getByText('English proficiency certificate')).toBeInTheDocument()
    expect(screen.getByText('Recommendation letters')).toBeInTheDocument()

    expect(screen.getAllByText('Required').length).toBeGreaterThanOrEqual(4)
    expect(screen.getAllByText('If applicable').length).toBeGreaterThanOrEqual(2)
    expect(screen.getAllByText('Optional').length).toBeGreaterThanOrEqual(3)
  })

  it('shows an already-uploaded file and hides the upload button for a full single-slot type', () => {
    renderStep()

    // The graduation certificate is uploaded (cap 1, so no further upload for it)…
    expect(screen.getByText('grad.pdf')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Remove' })).toBeInTheDocument()

    // …while the empty transcript slot still offers an upload.
    expect(screen.getAllByText('Upload').length).toBeGreaterThanOrEqual(1)
  })

  it('previews an uploaded document in a new tab', async () => {
    const openSpy = vi.spyOn(window, 'open').mockReturnValue(null)
    const user = userEvent.setup()
    renderStep()

    await user.click(screen.getByRole('button', { name: 'Preview' }))

    expect(openSpy).toHaveBeenCalledWith(
      'http://localhost/api/v1/documents/ULID1',
      '_blank',
      expect.stringContaining('noopener'),
    )
    openSpy.mockRestore()
  })

  it('removes an uploaded document via the delete endpoint', async () => {
    const user = userEvent.setup()
    renderStep()

    await user.click(screen.getByRole('button', { name: 'Remove' }))

    await waitFor(() =>
      expect(calls.list).toContainEqual({
        method: 'DELETE',
        url: '/api/v1/application/documents/ULID1',
      }),
    )
  })

  it('uploads a chosen file to the documents endpoint', async () => {
    const { container } = renderStep()

    // The first file input belongs to the transcript slot (graduation certificate is full).
    const input = container.querySelector('input[type="file"]') as HTMLInputElement
    const file = new File(['data'], 'transcript.pdf', { type: 'application/pdf' })
    fireEvent.change(input, { target: { files: [file] } })

    await waitFor(() =>
      expect(calls.list).toContainEqual({
        method: 'POST',
        url: '/api/v1/application/documents',
      }),
    )
  })
})
