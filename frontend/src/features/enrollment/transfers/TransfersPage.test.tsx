import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { ThemeProvider } from '@mui/material/styles'
import type { AxiosRequestConfig } from 'axios'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../../app/theme/createAppTheme'
import i18n from '../../../shared/i18n/config'
import { TransfersPage } from './TransfersPage'

const api = vi.hoisted(() => ({
  calls: [] as AxiosRequestConfig[],
  requests: { data: [] as unknown[], meta: { total: 0, last_page: 1 } },
  failGets: false,
}))

const transcriptId = '01ARZ3NDEKTSV4RRFFQ69G5FAV'
const syllabusId = '01ARZ3NDEKTSV4RRFFQ69G5FAW'

vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} } }, get: vi.fn() },
  customInstance: (config: AxiosRequestConfig) => {
    api.calls.push(config)
    if (config.method === 'GET' && api.failGets) return Promise.reject(new Error('unavailable'))
    if (config.url?.endsWith('/evidence-documents'))
      return Promise.resolve({
        data: [
          { id: transcriptId, type: 'transcript', original_filename: 'transcript.pdf' },
          { id: syllabusId, type: 'additional_certificate', original_filename: 'syllabus.pdf' },
        ],
      })
    if (config.url?.endsWith('/targets'))
      return Promise.resolve({
        data: [
          {
            target_course_id: 5,
            target_course_code: 'CSE501',
            target_category: 'ProgramCore',
            target_credit_hours: 3,
            already_transferred: false,
          },
        ],
      })
    if (config.url?.endsWith('/transfer-requests') && config.method === 'GET')
      return Promise.resolve(api.requests)
    return Promise.resolve({ data: { id: 1, status: 'Draft', revision: 1 } })
  },
}))

function renderPage(locale: 'en' | 'ar' = 'en') {
  return render(
    <ThemeProvider theme={createAppTheme(locale)}>
      <QueryClientProvider
        client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
      >
        <TransfersPage />
      </QueryClientProvider>
    </ThemeProvider>,
  )
}

beforeEach(async () => {
  api.calls = []
  api.requests = { data: [], meta: { total: 0, last_page: 1 } }
  api.failGets = false
  URL.createObjectURL = vi.fn(() => 'blob:transfers')
  URL.revokeObjectURL = vi.fn()
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
  await i18n.changeLanguage('en')
})

it('creates a Draft with exact printed evidence and target/source hours kept distinct', async () => {
  renderPage()
  expect(await screen.findByText('No transfer requests yet.')).toBeInTheDocument()
  fireEvent.change(screen.getByLabelText(/target curriculum course/i), { target: { value: 'CSE' } })
  fireEvent.click(await screen.findByText(/CSE501/))
  const change = (id: string, value: string) => {
    const field = document.getElementById(`new-transfer-${id}`)
    expect(field).not.toBeNull()
    fireEvent.change(field as HTMLElement, { target: { value } })
    fireEvent.blur(field as HTMLElement)
  }
  change('institution', 'Université de Montréal')
  change('courseCode', 'IFT 6251')
  change('courseName', 'Machine Learning')
  change('sourceHours', '12.50')
  change('printedGrade', 'Très bien (B)')
  fireEvent.click(screen.getByLabelText(/transcript\.pdf/i))
  fireEvent.click(screen.getByLabelText(/syllabus\.pdf/i))
  for (const field of screen.getAllByRole('textbox')) fireEvent.blur(field)
  const save = screen.getByRole('button', { name: /save draft/i })
  await waitFor(() => expect(save).toBeEnabled())
  fireEvent.click(save)

  await vi.waitFor(() => expect(api.calls.some((call) => call.method === 'POST')).toBe(true))
  expect(api.calls.find((call) => call.method === 'POST')?.data).toEqual({
    target_course_id: 5,
    external_institution: 'Université de Montréal',
    external_course_code: 'IFT 6251',
    external_course_name: 'Machine Learning',
    source_credit_hours: 12.5,
    external_grade: 'Très bien (B)',
    document_ids: [transcriptId, syllabusId],
    student_note: undefined,
  })
})

it('renders immutable history and the final target-hour decision', async () => {
  api.requests = {
    data: [
      {
        id: 1,
        target_course_code: 'CSE501',
        target_category: 'ProgramCore',
        target_credit_hours: 3,
        external_institution: 'Kyoto University',
        external_course_code: 'CS501',
        external_course_name: 'Algorithms',
        source_credit_hours: '4.00',
        external_grade: 'Excellent',
        document_ids: [transcriptId],
        normalized_grade: 'A',
        status: 'Approved',
        revision: 2,
        editable: false,
        terminal: true,
        transitions: [
          {
            id: 1,
            action: 'verified_by_department_head',
            current_revision: false,
            revision: 1,
            actor_user_id: 8,
            created_at: '2026-09-01T10:00:00+00:00',
          },
          { id: 2, action: 'approved_by_council', current_revision: true, revision: 2 },
        ],
        decision: { normalized_grade: 'A', target_credit_hours: 3 },
      },
    ],
    meta: { total: 1, last_page: 1 },
  }
  renderPage()

  expect(
    await screen.findByText(
      (_content, element) =>
        element?.classList.contains('MuiAlert-message') === true &&
        /recognized as A using 3 target CH/i.test(element.textContent ?? ''),
    ),
  ).toBeInTheDocument()
  expect(screen.getByText(/previous revision.*review invalidated/i)).toBeInTheDocument()
  expect(
    screen.getByText(
      (_content, element) =>
        element?.tagName === 'SPAN' && /Actor 8/.test(element.textContent ?? ''),
    ),
  ).toBeInTheDocument()
  expect(
    screen.getByText(
      (_content, element) =>
        element?.tagName === 'P' &&
        /4.00.*printed grade.*Excellent/i.test(element.textContent ?? ''),
    ),
  ).toBeInTheDocument()
})

it('requires the Student to select owned admission evidence before saving', async () => {
  renderPage()
  const transcript = await screen.findByLabelText(/transcript\.pdf/i)
  fireEvent.click(transcript)
  fireEvent.click(transcript)
  fireEvent.blur(transcript)
  expect(await screen.findByRole('alert')).toHaveTextContent(/required/i)
})

it('localizes the Student surface and validation for Arabic RTL', async () => {
  await i18n.changeLanguage('ar')
  const { container } = renderPage('ar')
  expect(await screen.findByRole('heading', { name: 'طلبات تحويل الساعات' })).toBeInTheDocument()
  const institution = screen.getByLabelText(/المؤسسة الخارجية/)
  fireEvent.focus(institution)
  fireEvent.blur(institution)
  expect(await screen.findByRole('alert')).toHaveTextContent('هذا الحقل مطلوب')
  expect(container.querySelector('.MuiPaper-outlined')).not.toBeNull()
})

it('shows a single recoverable load error rather than a false empty state', async () => {
  api.failGets = true
  renderPage()
  expect(await screen.findByText(/could not load transfer requests/i)).toBeInTheDocument()
  expect(screen.queryByText('No transfer requests yet.')).not.toBeInTheDocument()
  expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument()
})
