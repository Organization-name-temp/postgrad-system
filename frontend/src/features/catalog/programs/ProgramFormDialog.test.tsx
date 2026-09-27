import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { ProgramFormDialog } from './ProgramFormDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/departments/207')
      return Promise.resolve({
        data: {
          id: 207,
          code: 'EXISTING',
          name_en: 'Existing Department',
          name_ar: 'القسم الحالي',
        },
      })
    if (config.url === '/api/v1/departments') {
      const searched = config.params?.q === 'احمد'
      return Promise.resolve({
        data: [
          {
            id: searched ? 309 : 1,
            code: searched ? 'MATCH' : 'FIRST',
            name_en: searched ? 'Matched Department' : 'First Department',
            name_ar: searched ? 'قسم أحمد' : 'القسم الأول',
          },
        ],
        meta: { total: searched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({ data: { id: 8 } })
  },
}))
beforeEach(() => {
  api.calls = []
})
it.each(['en', 'ar'] as const)(
  'hydrates and searches Program Department ownership beyond the first page in %s',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <ProgramFormDialog
            open
            onClose={() => undefined}
            program={{
              id: 8,
              department_id: 207,
              code: 'EXISTING',
              name_en: 'Existing Program',
              name_ar: 'برنامج',
              degree_level: 'MSc',
              prep_required_ch: 6,
              core_required_ch: 6,
              elective_required_ch: 9,
            }}
          />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const picker = await screen.findByRole('combobox', {
      name: i18n.t('programs.department', { ns: 'catalog' }),
    })
    await vi.waitFor(() =>
      expect(picker).toHaveValue(locale === 'ar' ? 'القسم الحالي' : 'Existing Department'),
    )
    const save = screen.getByRole('button', { name: i18n.t('programs.save', { ns: 'catalog' }) })
    expect(save).toBeEnabled()
    fireEvent.change(picker, { target: { value: 'احمد' } })
    expect(picker).toHaveValue('احمد')
    expect(save).toBeDisabled()
    fireEvent.click(
      await screen.findByRole('option', {
        name: locale === 'ar' ? 'قسم أحمد' : 'Matched Department',
      }),
    )
    fireEvent.click(save)
    await vi.waitFor(() =>
      expect(api.calls.find((call) => call.method === 'PATCH')?.data).toMatchObject({
        department_id: 309,
      }),
    )
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
  },
)
