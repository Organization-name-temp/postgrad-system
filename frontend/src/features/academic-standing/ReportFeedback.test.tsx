import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider } from '@mui/material/styles'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import type { AxiosRequestConfig } from 'axios'
import { it, expect, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { GraduationReportPage } from './GraduationReportPage'
import { TerminationCaseListPage } from './TerminationCaseListPage'
import { TerminationReportPage } from './TerminationReportPage'

const state = vi.hoisted(() => ({ mode: 'pending' }))
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    if (config.url?.includes('/reports/')) {
      if (state.mode === 'pending') return new Promise(() => {})
      if (state.mode === 'error') return Promise.reject(new Error('Unavailable'))
      if (config.url.endsWith('/cases'))
        return Promise.resolve({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } })
      return Promise.resolve({
        data: { total: 0, case_count: 0, student_count: 0, groups: {}, blockers: {}, states: {} },
      })
    }
    return Promise.resolve({ data: [] })
  },
}))

for (const locale of ['en', 'ar'] as const) {
  for (const owner of ['graduation', 'termination'] as const) {
    it.each(['pending', 'error'])(`${owner} explains %s feedback in ${locale}`, async (mode) => {
      state.mode = mode
      await i18n.changeLanguage(locale)
      render(
        <ThemeProvider theme={createAppTheme(locale)}>
          <QueryClientProvider
            client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
          >
            <MemoryRouter>
              {owner === 'graduation' ? <GraduationReportPage /> : <TerminationReportPage />}
            </MemoryRouter>
          </QueryClientProvider>
        </ThemeProvider>,
      )
      const labels = {
        graduation: {
          pending: {
            en: 'Loading Graduation Readiness Report',
            ar: 'جارٍ تحميل تقرير جاهزية التخرج',
          },
          error: {
            en: 'The Graduation Readiness Report could not be loaded.',
            ar: 'تعذّر تحميل تقرير جاهزية التخرج.',
          },
        },
        termination: {
          pending: { en: 'Loading Termination Report', ar: 'جارٍ تحميل تقرير إنهاء القيد' },
          error: {
            en: 'The Termination Report could not be loaded.',
            ar: 'تعذّر تحميل تقرير إنهاء القيد.',
          },
        },
      }
      const message = labels[owner][mode as 'pending' | 'error'][locale]
      if (mode === 'pending')
        expect(await screen.findByRole('progressbar', { name: message })).toBeInTheDocument()
      else expect(await screen.findByText(message)).toBeInTheDocument()
    })
  }
}

for (const locale of ['en', 'ar'] as const) {
  it.each(['pending', 'error', 'empty'])(`Case List explains %s in ${locale}`, async (mode) => {
    state.mode = mode
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <MemoryRouter>
            <TerminationCaseListPage />
          </MemoryRouter>
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const messages = {
      pending: {
        en: 'Loading matching Termination Cases',
        ar: 'جارٍ تحميل حالات إنهاء القيد المطابقة',
      },
      error: {
        en: 'The matching Termination Cases could not be loaded.',
        ar: 'تعذّر تحميل حالات إنهاء القيد المطابقة.',
      },
      empty: {
        en: 'No Termination Cases match these criteria within your authorized scope.',
        ar: 'لا توجد حالات إنهاء قيد مطابقة لهذه المعايير ضمن نطاقك المسموح.',
      },
    }
    const message = messages[mode as keyof typeof messages][locale]
    if (mode === 'pending')
      expect(await screen.findByRole('progressbar', { name: message })).toBeInTheDocument()
    else expect(await screen.findByText(message)).toBeInTheDocument()
  })
}
