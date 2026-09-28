import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { CourseFormDialog } from './CourseFormDialog'

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
it.each([
  ['en', 'department'],
  ['ar', 'department'],
  ['en', 'university'],
  ['ar', 'university'],
] as const)(
  'hydrates and searches Course ownership beyond the first page in %s (%s)',
  async (locale, ownership) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <CourseFormDialog
            open
            onClose={() => undefined}
            course={{
              id: 8,
              department_id: 207,
              code: 'EXISTING',
              name_en: 'Existing Course',
              name_ar: 'مقرر',
              level: 500,
              lecture_ch: 3,
              tutorial_ch: 0,
              lab_ch: 0,
              total_ch: 3,
            }}
          />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const picker = await screen.findByRole('combobox', {
      name: i18n.t('courses.department', { ns: 'catalog' }),
    })
    await vi.waitFor(() =>
      expect(picker).toHaveValue(locale === 'ar' ? 'القسم الحالي' : 'Existing Department'),
    )
    const save = screen.getByRole('button', { name: i18n.t('courses.save', { ns: 'catalog' }) })
    expect(save).toBeEnabled()
    fireEvent.change(picker, { target: { value: 'احمد' } })
    expect(picker).toHaveValue('احمد')
    expect(save).toBeDisabled()
    fireEvent.click(
      await screen.findByRole('option', {
        name: locale === 'ar' ? 'قسم أحمد' : 'Matched Department',
      }),
    )
    if (ownership === 'university') {
      fireEvent.mouseDown(picker)
      fireEvent.click(
        await screen.findByRole('option', {
          name: i18n.t('courses.universityWide', { ns: 'catalog' }),
        }),
      )
      expect(picker).toHaveValue(i18n.t('courses.universityWide', { ns: 'catalog' }))
    }
    fireEvent.click(save)
    await vi.waitFor(() => expect(api.calls.find((call) => call.method === 'PATCH')).toBeDefined())
    const submitted = api.calls.find((call) => call.method === 'PATCH')?.data
    if (ownership === 'university') expect(submitted).not.toHaveProperty('department_id')
    else expect(submitted).toMatchObject({ department_id: 309 })
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
  },
)
