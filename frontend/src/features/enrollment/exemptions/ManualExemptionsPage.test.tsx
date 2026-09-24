import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { getMyManualExemptionRequestsQueryKey } from '../../../shared/api/generated/endpoints'
import { ManualExemptionsPage } from './ManualExemptionsPage'

const theme = createAppTheme('en')

const api = vi.hoisted(() => ({
  requests: { data: [] as unknown[], meta: { total: 0 } },
  programs: [] as unknown[],
  posted: [] as AxiosRequestConfig[],
  failNextSubmit: false,
  failGets: false,
  pendingTargets: false,
  removeLastPageOnDelete: false,
  lastPageRemoved: false,
}))

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.posted.push(config)
    if (config.url?.endsWith('/targets') && api.pendingTargets) return new Promise(() => undefined)
    if (config.method === 'GET' && api.failGets) return Promise.reject(new Error('unavailable'))
    if (config.url?.endsWith('/submit') && api.failNextSubmit) {
      api.failNextSubmit = false
      return Promise.reject(new Error('response lost'))
    }
    if (config.url?.endsWith('/targets'))
      return Promise.resolve({
        data: [
          {
            target_course_id: 5,
            target_course_code: 'PRE401',
            preparatory_requirement_role: 'EnglishLanguage',
            credit_hours: 1,
            already_recognized: false,
          },
        ],
      })
    if (config.url?.endsWith('/programs'))
      return Promise.resolve({
        data: api.programs,
      })
    if (config.method === 'DELETE' && api.removeLastPageOnDelete) {
      api.lastPageRemoved = true
      return Promise.resolve({ data: { id: 8, status: 'Abandoned', revision: 1 } })
    }
    if (
      config.url?.endsWith('/exemption-requests') &&
      config.method === 'GET' &&
      api.removeLastPageOnDelete
    ) {
      const page = Number(config.params?.page ?? 1)
      if (page === 2) {
        return Promise.resolve({
          data: api.lastPageRemoved
            ? []
            : [
                {
                  id: 8,
                  target_course_code: 'PRE402',
                  preparatory_requirement_role: 'EnglishLanguage',
                  status: 'Draft',
                  revision: 1,
                  editable: true,
                  evidence: { english_test: 'IELTS', english_score: 7 },
                  transitions: [],
                },
              ],
          meta: {
            total: api.lastPageRemoved ? 1 : 2,
            current_page: 2,
            last_page: api.lastPageRemoved ? 1 : 2,
          },
        })
      }

      return Promise.resolve({
        data: [
          {
            id: 7,
            target_course_code: 'PRE401',
            preparatory_requirement_role: 'EnglishLanguage',
            status: 'Draft',
            revision: 1,
            editable: true,
            evidence: { english_test: 'IELTS', english_score: 7 },
            transitions: [],
          },
        ],
        meta: {
          total: api.lastPageRemoved ? 1 : 2,
          current_page: 1,
          last_page: api.lastPageRemoved ? 1 : 2,
        },
      })
    }
    if (config.url?.endsWith('/exemption-requests') && config.method === 'GET')
      return Promise.resolve(api.requests)
    return Promise.resolve({ data: { id: 1, status: 'Draft', revision: 1 } })
  },
}))

function renderPage(
  direction: 'ltr' | 'rtl' = 'ltr',
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } }),
) {
  return render(
    <ThemeProvider theme={direction === 'ltr' ? theme : createAppTheme('ar')}>
      <QueryClientProvider client={client}>
        <ManualExemptionsPage />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(() => {
  api.requests = { data: [], meta: { total: 0 } }
  api.programs = [
    {
      id: 12,
      code: 'MSC-CE',
      name_en: 'Computer Engineering MSc',
      name_ar: 'ماجستير هندسة الحاسبات',
      degree_level: 'MSc',
      is_active: true,
    },
  ]
  api.posted = []
  api.failNextSubmit = false
  api.failGets = false
  api.pendingTargets = false
  api.removeLastPageOnDelete = false
  api.lastPageRemoved = false
  URL.createObjectURL = vi.fn(() => 'blob:exemptions')
  URL.revokeObjectURL = vi.fn()
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
  void i18n.changeLanguage('en')
})

it('shows an initial-load error without also claiming the request list is empty', async () => {
  api.failGets = true
  renderPage()

  expect(await screen.findByText(/could not load exemption requests/i)).toBeInTheDocument()
  expect(screen.queryByText(/no manual exemption requests/i)).not.toBeInTheDocument()
})

it('renders one error state when requests fail while targets are still loading', async () => {
  api.failGets = true
  api.pendingTargets = true
  renderPage()

  expect(await screen.findByText(/could not load exemption requests/i)).toBeInTheDocument()
  expect(screen.queryByLabelText(/loading exemption requests/i)).not.toBeInTheDocument()
  expect(screen.queryByText(/no manual exemption requests/i)).not.toBeInTheDocument()
})

it('hides cached request cards when their refetch fails', async () => {
  api.requests = {
    data: [{ id: 7, target_course_code: 'PRE401', status: 'Draft', transitions: [] }],
    meta: { total: 1 },
  }
  api.failGets = true
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  client.setQueryData(
    getMyManualExemptionRequestsQueryKey({ sort: '-updated_at', page: 1 }),
    api.requests,
  )
  renderPage('ltr', client)

  expect(await screen.findByText(/could not load exemption requests/i)).toBeInTheDocument()
  expect(screen.queryByText('PRE401')).not.toBeInTheDocument()
})

it('shows a useful empty state and creates an editable Draft from structured evidence', async () => {
  renderPage()
  expect(await screen.findByText(/no manual exemption requests/i)).toBeInTheDocument()
  fireEvent.mouseDown(screen.getByLabelText(/preparatory requirement/i))
  fireEvent.click(await screen.findByText(/pre401/i))
  fireEvent.mouseDown(screen.getByLabelText(/english test/i))
  fireEvent.click(await screen.findByText(/toefl ibt/i))
  const score = screen.getByLabelText(/english score/i)
  fireEvent.change(score, { target: { value: '80' } })
  fireEvent.blur(score)
  fireEvent.change(screen.getByLabelText(/student note/i), {
    target: { value: 'Corrected report' },
  })
  const save = screen.getByRole('button', { name: /save draft/i })
  await waitFor(() => expect(save).toBeEnabled())
  fireEvent.click(save)

  await vi.waitFor(() =>
    expect(
      api.posted.some(
        (request) => request.method === 'POST' && request.url?.endsWith('/exemption-requests'),
      ),
    ).toBe(true),
  )
  expect(
    api.posted.find(
      (request) => request.method === 'POST' && request.url?.endsWith('/exemption-requests'),
    )?.data,
  ).toEqual(expect.objectContaining({ evidence: { english_test: 'TOEFL iBT', english_score: 80 } }))
}, 15_000)

it.each(['PendingAdvisor', 'PendingDepartmentHead', 'Approved', 'Rejected', 'Abandoned'])(
  'keeps submitted evidence visible to the Student while a %s request is locked',
  async (status) => {
    api.requests = {
      data: [
        {
          id: 7,
          target_course_code: 'PRE401',
          preparatory_requirement_role: 'EnglishLanguage',
          status,
          revision: 1,
          editable: false,
          evidence: { english_test: 'IELTS', english_score: 7 },
          student_note: 'Corrected score report',
          transitions: [],
        },
      ],
      meta: { total: 1 },
    }
    renderPage()

    expect(
      await screen.findByText(
        (_content, element) =>
          element?.tagName === 'P' && /IELTS.*7/.test(element.textContent ?? ''),
      ),
    ).toBeInTheDocument()
    expect(screen.getByText('Corrected score report')).toBeInTheDocument()
    expect(screen.getAllByLabelText(/english score/i)).toHaveLength(1)
  },
)

it('announces inline validation errors and describes the target selector', async () => {
  renderPage()
  await screen.findByText(/no manual exemption requests/i)

  expect(screen.getByLabelText(/preparatory requirement/i)).toHaveAttribute(
    'aria-describedby',
    'exemption-target-help',
  )
  const score = screen.getByLabelText(/english score/i)
  fireEvent.change(score, { target: { value: '9.5' } })
  expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  fireEvent.blur(score)
  expect(await screen.findByRole('alert')).toHaveTextContent(/selected test range/i)
})

it('keeps the English score numeric input LTR in Arabic', async () => {
  await i18n.changeLanguage('ar')
  renderPage('rtl')
  await screen.findByText(/لا توجد طلبات إعفاء يدوية/i)

  expect(screen.getByLabelText(/درجة اللغة الإنجليزية/i)).toHaveAttribute('dir', 'ltr')
})

it('localizes an overlength Student note without exposing a translation key', async () => {
  renderPage()
  await screen.findByText(/no manual exemption requests/i)

  const note = screen.getByLabelText(/student note/i)
  fireEvent.change(note, { target: { value: 'x'.repeat(2001) } })
  fireEvent.blur(note)
  expect(await screen.findByRole('alert')).toHaveTextContent('Use 2,000 characters or fewer.')

  await i18n.changeLanguage('ar')
  expect(await screen.findByRole('alert')).toHaveTextContent('استخدم 2000 حرف أو أقل.')
})

it('names the Draft and terminal consequence before abandonment', async () => {
  api.requests = {
    data: [
      {
        id: 7,
        target_course_code: 'PRE401',
        preparatory_requirement_role: 'EnglishLanguage',
        status: 'Draft',
        revision: 1,
        editable: true,
        evidence: { english_test: 'IELTS', english_score: 7 },
        transitions: [],
      },
    ],
    meta: { total: 1 },
  }
  renderPage()

  fireEvent.click(await screen.findByRole('button', { name: /abandon draft/i }))

  const dialog = screen.getByRole('dialog')
  expect(dialog).toHaveTextContent('PRE401')
  expect(dialog).toHaveTextContent(/becomes terminal and cannot be submitted/i)

  await i18n.changeLanguage('ar')
  expect(await screen.findByText(/ستصبح نهائية ولا يمكن إرسالها/i)).toBeInTheDocument()
  expect(dialog.querySelector('bdi[dir="ltr"]')).toHaveTextContent('PRE401')
})

it('returns to the last available page after abandoning the final Draft on a later page', async () => {
  api.removeLastPageOnDelete = true
  renderPage()

  fireEvent.click(await screen.findByRole('button', { name: /go to page 2/i }))
  expect(await screen.findByText('PRE402')).toBeInTheDocument()
  const falseEmpty = vi.fn()
  const observer = new MutationObserver(() => {
    if (screen.queryByText(/no manual exemption requests/i)) falseEmpty()
  })
  observer.observe(document.body, { childList: true, subtree: true })
  fireEvent.click(screen.getByRole('button', { name: /abandon draft/i }))
  fireEvent.click(
    within(screen.getByRole('dialog')).getByRole('button', { name: /abandon draft/i }),
  )

  expect(await screen.findByText('PRE401')).toBeInTheDocument()
  observer.disconnect()
  expect(falseEmpty).not.toHaveBeenCalled()
  expect(screen.queryByText(/no manual exemption requests/i)).not.toBeInTheDocument()
})

it('uses the named large-radius token for exemption panels', async () => {
  const { container } = renderPage()
  await screen.findByText(/no manual exemption requests/i)

  const panel = container.querySelector<HTMLElement>('.MuiPaper-outlined')
  expect(panel).not.toBeNull()
  expect(getComputedStyle(panel!).borderRadius).toBe('12px')
})

it('exports through the generated client with the list sort intact', async () => {
  renderPage()
  await screen.findByText(/no manual exemption requests/i)

  fireEvent.click(screen.getByRole('button', { name: /export xlsx/i }))

  await vi.waitFor(() =>
    expect(
      api.posted.some(
        (request) =>
          request.method === 'GET' &&
          request.url?.endsWith('/exemption-requests/export') &&
          request.params?.sort === '-updated_at' &&
          request.params?.page === 1,
      ),
    ).toBe(true),
  )
})

it('reuses the same action id when a Student retries a lost submit response', async () => {
  api.requests = {
    data: [
      {
        id: 7,
        target_course_code: 'PRE401',
        preparatory_requirement_role: 'EnglishLanguage',
        status: 'Draft',
        revision: 1,
        editable: true,
        evidence: { english_test: 'IELTS', english_score: 7 },
        transitions: [],
      },
    ],
    meta: { total: 1 },
  }
  api.failNextSubmit = true
  renderPage()

  const submit = await screen.findByRole('button', { name: /submit to advisor/i })
  fireEvent.click(submit)
  expect(await screen.findByText(/request changed/i)).toBeInTheDocument()
  fireEvent.click(submit)

  await vi.waitFor(() => {
    const calls = api.posted.filter(
      (request) => request.method === 'POST' && request.url?.endsWith('/submit'),
    )
    expect(calls).toHaveLength(2)
    expect(calls[0]?.data?.action_id).toBe(calls[1]?.data?.action_id)
  })
})

it('selects a previous E-JUST MSc Program by localized name instead of numeric input', async () => {
  renderPage()
  await screen.findByText(/no manual exemption requests/i)

  fireEvent.mouseDown(screen.getByLabelText(/evidence basis/i))
  fireEvent.click(await screen.findByText(/same-department e-just msc/i))
  const program = await screen.findByLabelText(/previous e-just program id/i)
  fireEvent.mouseDown(program)
  expect(await screen.findByText('Computer Engineering MSc')).toBeInTheDocument()
})

it('keeps an inactive historical E-JUST MSc Program selectable and identifies its state', async () => {
  api.programs = [
    {
      id: 13,
      code: 'MSC-HIST',
      name_en: 'Historical Engineering MSc',
      name_ar: 'ماجستير الهندسة التاريخي',
      degree_level: 'MSc',
      is_active: false,
    },
  ]
  renderPage()
  await screen.findByText(/no manual exemption requests/i)

  fireEvent.mouseDown(screen.getByLabelText(/evidence basis/i))
  fireEvent.click(await screen.findByText(/same-department e-just msc/i))
  const program = await screen.findByLabelText(/previous e-just program id/i)
  fireEvent.mouseDown(program)
  const historical = await screen.findByRole('option', {
    name: /historical engineering msc.*inactive/i,
  })
  fireEvent.click(historical)

  expect(program).toHaveValue('Historical Engineering MSc (Inactive)')
})

it('renders status text and append-only revision history in Arabic RTL', async () => {
  await i18n.changeLanguage('ar')
  api.requests = {
    data: [
      {
        id: 2,
        target_course_code: 'PRE401',
        preparatory_requirement_role: 'EnglishLanguage',
        status: 'Returned',
        revision: 2,
        editable: true,
        transitions: [
          {
            action: 'returned_by_advisor',
            reason: 'ارفع مستندًا واضحًا',
            revision: 1,
            current_revision: false,
          },
        ],
      },
    ],
    meta: { total: 1 },
  }
  renderPage('rtl')

  expect(await screen.findByText('مُعاد للتعديل')).toBeInTheDocument()
  expect(screen.getByText(/مراجعة سابقة/)).toBeInTheDocument()
  expect(document.documentElement.dir).toBe('rtl')
  expect(document.querySelector('bdi[dir="ltr"]')).not.toBeNull()
  expect(screen.getByTestId('submit-icon-2')).toHaveStyle({ transform: 'scaleX(-1)' })
})
