import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, within } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CurriculumPanel } from './CurriculumPanel'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/courses') {
      const searched = config.params?.q === 'احمد'
      return Promise.resolve({
        data: [
          {
            id: searched ? 309 : 1,
            code: searched ? 'MATCH' : 'FIRST',
            name_en: searched ? 'Matched Course' : 'First Course',
            name_ar: searched ? 'مقرر أحمد' : 'المقرر الأول',
            level: 500,
          },
        ],
        meta: { total: searched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({ data: [], meta: { preparatory_requirements_complete: true } })
  },
}))
beforeEach(() => {
  api.calls = []
})
it.each(['en', 'ar'] as const)(
  'finds and places an owner Course beyond the first reference page in %s',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <CurriculumPanel programId={8} canManage />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const core = within(await screen.findByTestId('curriculum-section-ProgramCore'))
    const picker = core.getByRole('combobox', {
      name: i18n.t('curriculum.addCourse', { ns: 'catalog' }),
    })
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(await screen.findByRole('option', { name: /MATCH/ }))
    const add = core.getByRole('button', { name: i18n.t('curriculum.add', { ns: 'catalog' }) })
    expect(add).toBeEnabled()
    fireEvent.change(picker, { target: { value: 'First' } })
    expect(picker).toHaveValue('First')
    expect(add).toBeDisabled()
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(await screen.findByRole('option', { name: /MATCH/ }))
    fireEvent.click(add)
    await vi.waitFor(() =>
      expect(api.calls.find((call) => call.method === 'POST')?.data).toMatchObject({
        course_id: 309,
        category: 'ProgramCore',
      }),
    )
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
  },
)
