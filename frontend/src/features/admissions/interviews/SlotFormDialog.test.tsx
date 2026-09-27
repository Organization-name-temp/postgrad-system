import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { SlotFormDialog } from './SlotFormDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[], searchable: false }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (api.searchable && config.url === '/api/v1/interview-slots/interviewers') {
      const searched = config.params?.q === 'احمد' || config.params?.id === '207'
      return Promise.resolve({
        data: [
          {
            id: searched ? 207 : 1,
            name: searched ? 'أحمد مدرس' : 'First instructor',
            email: 'instructor@example.test',
          },
        ],
        meta: { total: searched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({ data: [], meta: { total: 0, per_page: 100 } })
  },
}))
beforeEach(() => {
  api.calls = []
  api.searchable = false
})
it('requests a bounded eligible interviewer reference when the slot form opens', async () => {
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <SlotFormDialog open onClose={() => undefined} />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await screen.findByRole('dialog', { name: 'New slot' })
  await vi.waitFor(() =>
    expect(
      api.calls.find((call) => call.url === '/api/v1/interview-slots/interviewers')?.params,
    ).toMatchObject({ per_page: 100 }),
  )
})

it.each(['en', 'ar'] as const)(
  'finds an eligible interviewer beyond the first reference page in %s',
  async (locale) => {
    api.searchable = true
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <SlotFormDialog open onClose={() => undefined} />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const picker = await screen.findByRole('combobox', {
      name: new RegExp(i18n.t('interviews.slots.interviewer', { ns: 'admissions' })),
    })
    fireEvent.change(
      screen.getByLabelText(
        new RegExp(i18n.t('interviews.slots.scheduledAt', { ns: 'admissions' })),
      ),
      { target: { value: '2026-10-01T10:00' } },
    )
    fireEvent.change(
      screen.getByLabelText(new RegExp(i18n.t('interviews.slots.location', { ns: 'admissions' }))),
      { target: { value: 'Room' } },
    )
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(await screen.findByRole('option', { name: /أحمد مدرس/ }))
    expect(picker).toHaveValue('أحمد مدرس')
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
    const save = screen.getByRole('button', {
      name: i18n.t('interviews.save', { ns: 'admissions' }),
    })
    expect(save).toBeEnabled()
    fireEvent.change(picker, { target: { value: 'First' } })
    expect(save).toBeDisabled()
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(await screen.findByRole('option', { name: /أحمد مدرس/ }))
    fireEvent.click(save)
    await vi.waitFor(() =>
      expect(api.calls.find((call) => call.method === 'POST')?.data).toMatchObject({
        interviewer_id: 207,
      }),
    )
  },
)

it('hydrates the assigned interviewer by owner ID when editing beyond the first page', async () => {
  api.searchable = true
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <SlotFormDialog
          open
          onClose={() => undefined}
          slot={{
            id: 9,
            interviewer_id: 207,
            scheduled_at: '2026-10-01T10:00:00Z',
            duration_minutes: 30,
            mode: 'in_person',
            location: 'Room',
          }}
        />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await vi.waitFor(() =>
    expect(screen.getByRole('combobox', { name: /Interviewer/ })).toHaveValue('أحمد مدرس'),
  )
  expect(api.calls.find((call) => call.params?.id === '207')?.params).toMatchObject({
    id: '207',
    per_page: 100,
  })
})
