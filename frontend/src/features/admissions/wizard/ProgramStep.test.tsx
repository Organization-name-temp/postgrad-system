import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ProgramStep } from './ProgramStep'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'

// A tiny in-memory server behind the shared mutator so the picker drives the REAL
// generated hooks: the catalog tree read, the selection PATCH, and its body are
// observed end-to-end rather than stubbed at the hook.
const calls = vi.hoisted(() => ({
  list: [] as Array<{ method?: string; url?: string; data?: unknown }>,
}))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: AxiosRequestConfig) => {
    calls.list.push({ method: config.method, url: config.url, data: config.data })
    if ((config.url ?? '').endsWith('/catalog/tree')) {
      return Promise.resolve({
        data: {
          faculties: [
            {
              id: 1,
              code: 'FoE',
              name_en: 'Faculty of Engineering',
              name_ar: 'كلية الهندسة',
              is_active: true,
              departments: [
                {
                  id: 1,
                  code: 'CE',
                  name_en: 'Computer Engineering',
                  name_ar: 'هندسة الحاسوب',
                  is_active: true,
                  programs: [
                    {
                      id: 10,
                      code: 'MSC-CE',
                      name_en: 'MSc in Computer Engineering',
                      name_ar: 'ماجستير هندسة الحاسوب',
                      degree_level: 'MSc',
                      is_active: true,
                      categories: [],
                    },
                  ],
                },
              ],
            },
          ],
        },
      })
    }
    return Promise.resolve({ data: { id: 1, status: 'email_verified' } })
  },
}))

const application: MyApplication200Data = { id: 1, status: 'email_verified', admission_cycle_id: 1 }

function renderStep(onContinue = vi.fn(), onBack = vi.fn()) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ProgramStep application={application} onBack={onBack} onContinue={onContinue} />
    </QueryClientProvider>,
  )
  return { onContinue, onBack }
}

describe('ProgramStep', () => {
  beforeEach(() => {
    calls.list = []
  })

  it('lists the offerable programs from the catalog tree', async () => {
    renderStep()
    expect(await screen.findByText(/MSc in Computer Engineering · MSc/)).toBeInTheDocument()
  })

  it('saves the chosen primary program and advances', async () => {
    const user = userEvent.setup()
    const { onContinue } = renderStep()

    await user.click(await screen.findByText('Primary'))
    await user.click(screen.getByRole('button', { name: /save & continue/i }))

    await waitFor(() =>
      expect(calls.list).toContainEqual(
        expect.objectContaining({ method: 'PATCH', url: '/api/v1/application/programs' }),
      ),
    )
    const patch = calls.list.find((c) => c.method === 'PATCH')
    expect(patch?.data).toMatchObject({ primary_program_id: 10 })
    expect(onContinue).toHaveBeenCalledOnce()
  })

  it('keeps Save disabled until a primary program is chosen', async () => {
    renderStep()
    await screen.findByText(/MSc in Computer Engineering/)
    expect(screen.getByRole('button', { name: /save & continue/i })).toBeDisabled()
  })
})
