import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { DepartmentFormDialog } from './DepartmentFormDialog'

const api = vi.hoisted(() => ({ calls: [] as AxiosRequestConfig[] }))
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } } },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.url === '/api/v1/faculties/207') {
      return Promise.resolve({
        data: { id: 207, code: 'MATCH', name_en: 'Matched Faculty', name_ar: 'كلية أحمد' },
      })
    }
    if (config.url === '/api/v1/faculties') {
      const matched = config.params?.q === 'احمد'
      return Promise.resolve({
        data: [
          {
            id: matched ? 207 : 1,
            code: matched ? 'MATCH' : 'FIRST',
            name_en: matched ? 'Matched Faculty' : 'First Faculty',
            name_ar: matched ? 'كلية أحمد' : 'الكلية الأولى',
          },
        ],
        meta: { total: matched ? 1 : 101, per_page: 100 },
      })
    }
    return Promise.resolve({ data: { id: 8 } })
  },
}))
beforeEach(() => {
  api.calls = []
})

it('resolves an existing owning Faculty outside the first collection page', async () => {
  await i18n.changeLanguage('en')
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <DepartmentFormDialog
          open
          onClose={() => undefined}
          department={{
            id: 8,
            faculty_id: 207,
            code: 'EXISTING',
            name_en: 'Existing',
            name_ar: 'قسم',
          }}
        />
      </QueryClientProvider>
    </ThemeProvider>,
  )
  await vi.waitFor(() =>
    expect(screen.getByRole('combobox', { name: /Faculty/ })).toHaveValue('Matched Faculty'),
  )
  expect(api.calls).toContainEqual(expect.objectContaining({ url: '/api/v1/faculties/207' }))
})

it.each(['en', 'ar'] as const)(
  'searches the owning Faculty beyond the first page and submits its ID in %s',
  async (locale) => {
    await i18n.changeLanguage(locale)
    render(
      <ThemeProvider theme={createAppTheme(locale)}>
        <QueryClientProvider
          client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
          <DepartmentFormDialog open onClose={() => undefined} />
        </QueryClientProvider>
      </ThemeProvider>,
    )
    const picker = await screen.findByRole('combobox', {
      name: i18n.t('departments.faculty', { ns: 'catalog' }),
    })
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(
      await screen.findByRole('option', {
        name: locale === 'ar' ? 'كلية أحمد' : 'Matched Faculty',
      }),
    )
    for (const [key, value] of [
      ['code', 'NEW-DEPT'],
      ['nameEn', 'New Department'],
      ['nameAr', 'قسم جديد'],
    ]) {
      fireEvent.change(
        screen.getByLabelText(i18n.t(`departments.${key}`, { ns: 'catalog' }), { exact: false }),
        { target: { value } },
      )
    }
    const save = screen.getByRole('button', { name: i18n.t('departments.save', { ns: 'catalog' }) })
    expect(save).toBeEnabled()
    fireEvent.change(picker, { target: { value: 'First' } })
    expect(picker).toHaveValue('First')
    expect(save).toBeDisabled()
    fireEvent.change(picker, { target: { value: 'احمد' } })
    fireEvent.click(
      await screen.findByRole('option', {
        name: locale === 'ar' ? 'كلية أحمد' : 'Matched Faculty',
      }),
    )
    fireEvent.click(save)
    await vi.waitFor(() =>
      expect(api.calls.find((call) => call.method === 'POST')?.data).toMatchObject({
        faculty_id: 207,
      }),
    )
    expect(api.calls.find((call) => call.params?.q === 'احمد')?.params).toMatchObject({
      q: 'احمد',
      per_page: 100,
    })
  },
)
