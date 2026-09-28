import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider } from '@mui/material/styles'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, it, expect, vi } from 'vitest'
import i18n from '../../shared/i18n/config'
import { createAppTheme } from '../../app/theme/createAppTheme'
import { StandingReportPage } from './StandingReportPage'

const requests = vi.hoisted(() => [] as AxiosRequestConfig[])
const fixture = vi.hoisted(() => ({ mode: 'normal' }))
beforeEach(() => {
  fixture.mode = 'normal'
  requests.length = 0
})
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    requests.push(config)
    if (config.url?.endsWith('/faculties')) {
      if (config.params?.id && fixture.mode === 'pending') return new Promise(() => {})
      if (config.params?.id && fixture.mode === 'error')
        return Promise.reject(new Error('Reference lookup failed'))
      if (config.params?.id && fixture.mode === 'missing') return Promise.resolve({ data: [] })
      const selected = String(config.params?.id ?? '')
        .split(',')
        .filter(Boolean)
        .map(Number)
      const ids = selected.length ? selected : Array.from({ length: 100 }, (_, index) => index + 1)
      return Promise.resolve({
        data: ids
          .slice(0, 100)
          .map((id) => ({ id, name_en: `Faculty ${id}`, name_ar: `كلية ${id}` })),
      })
    }
    if (config.url?.endsWith('/reports/standing'))
      return Promise.resolve({ data: { total: 0, counts: {} } })
    return Promise.resolve({ data: [] })
  },
}))

it('retains every named bookmarked reference beyond the owner collection page cap', async () => {
  await i18n.changeLanguage('en')
  const ids = Array.from({ length: 101 }, (_, index) => index + 1).join(',')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <MemoryRouter initialEntries={[`/analytics/academic-standing?faculty_id=${ids}`]}>
          <StandingReportPage />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )
  expect(await screen.findByDisplayValue(/Faculty 101/)).toBeInTheDocument()
  expect(screen.getByLabelText('Report criteria')).toHaveTextContent('Faculty 101')
  const lookups = requests.filter(
    (request) => request.url?.endsWith('/faculties') && request.params?.id,
  )
  expect(lookups.length).toBeGreaterThan(1)
  expect(lookups.every((request) => String(request.params.id).split(',').length <= 100)).toBe(true)
})

for (const locale of ['en', 'ar'] as const) {
  it.each(['pending', 'error', 'missing'])(
    `distinguishes reference %s names in ${locale}`,
    async (mode) => {
      fixture.mode = mode
      await i18n.changeLanguage(locale)
      render(
        <ThemeProvider theme={createAppTheme(locale)}>
          <QueryClientProvider
            client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
          >
            <MemoryRouter initialEntries={['/analytics/academic-standing?faculty_id=101']}>
              <StandingReportPage />
            </MemoryRouter>
          </QueryClientProvider>
        </ThemeProvider>,
      )
      const labels = {
        pending: { en: 'Loading reference #101', ar: 'جارٍ تحميل اسم المرجع رقم 101' },
        error: {
          en: 'Reference name could not be loaded (#101)',
          ar: 'تعذّر تحميل اسم المرجع رقم 101',
        },
        missing: { en: 'Unavailable reference #101', ar: 'مرجع غير متاح رقم 101' },
      }
      expect(
        await screen.findByDisplayValue(labels[mode as keyof typeof labels][locale]),
      ).toBeInTheDocument()
    },
  )
}
