import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { InterviewPanel } from './InterviewPanel'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/application/interview/slots') {
      const searched = config.params?.q === 'احمد'
      return Promise.resolve({
        data: [
          {
            id: searched ? 207 : 1,
            is_open: true,
            scheduled_at: '2026-10-01T10:00:00Z',
            location: searched ? 'أحمد قاعة' : 'First room',
          },
        ],
        meta: { total: searched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({
      data: {
        id: 7,
        status: 'confirmed',
        can_reschedule: true,
        slot: {
          id: 11,
          mode: 'in_person',
          location: 'Original room',
          scheduled_at: '2026-10-02T10:00:00Z',
        },
      },
    })
  },
}))
it.each(['en', 'ar'] as const)(
  'searches the personal open-slot reference and submits an explicitly selected result beyond its first page in %s',
  async (locale) => {
    api.calls = []
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <InterviewPanel />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    fireEvent.click(
      await screen.findByRole('button', { name: locale === 'ar' ? 'إعادة الجدولة' : 'Reschedule' }),
    )
    const picker = await screen.findByRole('combobox', {
      name: locale === 'ar' ? 'موعد جديد' : 'New slot',
    })
    fireEvent.mouseDown(picker)
    fireEvent.click(await screen.findByRole('option', { name: /First room/ }))
    fireEvent.change(picker, { target: { value: 'احمد' } })
    expect(picker).toHaveValue('احمد')
    expect(
      screen.getByRole('button', {
        name: locale === 'ar' ? 'الانتقال إلى هذا الموعد' : 'Move to this slot',
      }),
    ).toBeDisabled()
    fireEvent.click(await screen.findByRole('option', { name: /أحمد قاعة/ }))
    fireEvent.click(
      screen.getByRole('button', {
        name: locale === 'ar' ? 'الانتقال إلى هذا الموعد' : 'Move to this slot',
      }),
    )
    await vi.waitFor(() =>
      expect(
        api.calls.find((call) => call.url === '/api/v1/application/interview/reschedule')?.data,
      ).toEqual({ interview_slot_id: 207 }),
    )
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
  },
)
