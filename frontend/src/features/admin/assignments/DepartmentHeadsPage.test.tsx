import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { DepartmentHeadsPage } from './DepartmentHeadsPage'

interface Assignment {
  id: number
  user_id: number
  department_id: number | null
  university_wide: boolean
  head_name: string
  head_email: string
}

// A tiny in-memory server behind the shared Axios mutator, so the page drives the REAL
// generated hooks (assignments list + departments list + assign/unassign mutations):
// the department-name join, the no-Head warning and the assign → POST body are
// observed, not stubbed.
const srv = vi.hoisted(() => ({
  assignments: [] as Assignment[],
  deleted: [] as number[],
  posted: undefined as unknown,
  departmentsFail: false,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()

    if (url.endsWith('/admin/department-head-assignments') && method === 'get') {
      return Promise.resolve({ data: srv.assignments })
    }
    if (url.endsWith('/admin/department-head-assignments') && method === 'post') {
      srv.posted = config.data
      return Promise.resolve({ data: { id: 99, ...(config.data as object) } })
    }
    if (url.includes('/admin/department-head-assignments/') && method === 'delete') {
      const id = Number(url.split('/').pop())
      srv.deleted.push(id)
      srv.assignments = srv.assignments.filter((a) => a.id !== id)
      return Promise.resolve({})
    }
    if (url.endsWith('/admin/department-heads')) {
      return Promise.resolve({ data: [{ id: 6, name: 'Dr. Head', email: 'head@ejust.test' }] })
    }
    if (url.endsWith('/departments')) {
      if (srv.departmentsFail) {
        return Promise.reject(new Error('departments unavailable'))
      }
      return Promise.resolve({
        data: [
          { id: 3, code: 'CE', name_en: 'Computer Engineering', name_ar: 'هندسة الحاسبات' },
          { id: 4, code: 'EE', name_en: 'Electrical Engineering', name_ar: 'الهندسة الكهربائية' },
        ],
      })
    }
    return Promise.resolve({ data: [] })
  },
}))

function departmentAssignment(overrides: Partial<Assignment> = {}): Assignment {
  return {
    id: 1,
    user_id: 6,
    department_id: 3,
    university_wide: false,
    head_name: 'Dr. Head',
    head_email: 'head@ejust.test',
    ...overrides,
  }
}

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <DepartmentHeadsPage />
    </QueryClientProvider>,
  )
}

/** Every scope named in the "no Department Head" warning. */
async function uncoveredScopeNames(): Promise<string[]> {
  const alert = await screen.findByRole('alert')
  return within(alert)
    .getAllByRole('listitem')
    .map((item) => item.textContent ?? '')
}

describe('DepartmentHeadsPage', () => {
  beforeEach(() => {
    srv.assignments = []
    srv.deleted = []
    srv.posted = undefined
    srv.departmentsFail = false
  })

  it('shows an empty state when there are no assignments', async () => {
    renderPage()
    expect(await screen.findByText(/no department head assignments yet/i)).toBeInTheDocument()
  })

  it('lists each assignment with the head and the resolved department name', async () => {
    srv.assignments = [departmentAssignment()]
    renderPage()

    const row = (await screen.findByRole('cell', { name: 'Dr. Head' })).closest('tr') as HTMLElement
    // The opaque department_id (3) is joined to its name from the departments list.
    expect(within(row).getByText('Computer Engineering')).toBeInTheDocument()
  })

  it('names the university-wide scope rather than leaving the department cell blank', async () => {
    srv.assignments = [departmentAssignment({ department_id: null, university_wide: true })]
    renderPage()

    const row = (await screen.findByRole('cell', { name: 'Dr. Head' })).closest('tr') as HTMLElement
    expect(within(row).getByText(/university-wide \(preparatory courses\)/i)).toBeInTheDocument()
  })

  it('warns about every department with no Head, and the uncovered university-wide scope', async () => {
    srv.assignments = [departmentAssignment()] // covers department 3 only
    renderPage()

    expect(await uncoveredScopeNames()).toEqual([
      'Electrical Engineering',
      'University-wide (Preparatory courses)',
    ])
  })

  it('does not treat the university-wide Head as covering a department', async () => {
    srv.assignments = [departmentAssignment({ department_id: null, university_wide: true })]
    renderPage()

    expect(await uncoveredScopeNames()).toEqual(['Computer Engineering', 'Electrical Engineering'])
  })

  it('drops the warning once every scope has a Head', async () => {
    srv.assignments = [
      departmentAssignment({ id: 1, department_id: 3 }),
      departmentAssignment({ id: 2, department_id: 4 }),
      departmentAssignment({ id: 3, department_id: null, university_wide: true }),
    ]
    renderPage()

    await screen.findByRole('cell', { name: 'Computer Engineering' })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('says coverage is unknown rather than claiming every department has a Head when the departments list fails', async () => {
    // The departments list is the warning's denominator. An empty one would silently
    // read as full coverage — the one wrong answer this screen must never give.
    srv.departmentsFail = true
    srv.assignments = [departmentAssignment({ department_id: null, university_wide: true })]
    renderPage()

    expect(await screen.findByText(/cannot tell you which ones have no head/i)).toBeInTheDocument()
    expect(screen.queryByText(/these have no department head/i)).not.toBeInTheDocument()
  })

  it('posts a null department when the university-wide scope is chosen', async () => {
    const user = userEvent.setup({ delay: null })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /assign a department head/i }))
    const dialog = await screen.findByRole('dialog')

    await user.click(within(dialog).getByRole('combobox', { name: /department head/i }))
    await user.click(await screen.findByRole('option', { name: 'Dr. Head' }))
    await user.click(within(dialog).getByRole('combobox', { name: /reviews/i }))
    await user.click(
      await screen.findByRole('option', { name: /university-wide \(preparatory courses\)/i }),
    )
    await user.click(within(dialog).getByRole('button', { name: /^assign$/i }))

    expect(srv.posted).toEqual({ user_id: 6, department_id: null })
  })

  it('posts the chosen department id when a department is chosen', async () => {
    const user = userEvent.setup({ delay: null })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /assign a department head/i }))
    const dialog = await screen.findByRole('dialog')

    await user.click(within(dialog).getByRole('combobox', { name: /department head/i }))
    await user.click(await screen.findByRole('option', { name: 'Dr. Head' }))
    await user.click(within(dialog).getByRole('combobox', { name: /reviews/i }))
    await user.click(await screen.findByRole('option', { name: 'Electrical Engineering' }))
    await user.click(within(dialog).getByRole('button', { name: /^assign$/i }))

    expect(srv.posted).toEqual({ user_id: 6, department_id: 4 })
  })

  it('cannot submit until both a head and a scope are chosen', async () => {
    const user = userEvent.setup({ delay: null })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /assign a department head/i }))
    const dialog = await screen.findByRole('dialog')
    expect(within(dialog).getByRole('button', { name: /^assign$/i })).toBeDisabled()

    await user.click(within(dialog).getByRole('combobox', { name: /department head/i }))
    await user.click(await screen.findByRole('option', { name: 'Dr. Head' }))
    expect(within(dialog).getByRole('button', { name: /^assign$/i })).toBeDisabled()
  })

  it('unassigns a Head only after confirming in the dialog (destructive-action guard)', async () => {
    srv.assignments = [departmentAssignment()]
    const user = userEvent.setup({ delay: null })
    renderPage()

    // The row action opens a confirmation dialog — it does not delete immediately.
    await user.click(
      await screen.findByRole('button', { name: /unassign dr\. head from computer engineering/i }),
    )
    expect(srv.deleted).not.toContain(1)

    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: /^unassign$/i }))
    expect(srv.deleted).toContain(1)
  })

  it('does not unassign when the confirmation is cancelled', async () => {
    srv.assignments = [departmentAssignment()]
    const user = userEvent.setup({ delay: null })
    renderPage()

    await user.click(
      await screen.findByRole('button', { name: /unassign dr\. head from computer engineering/i }),
    )
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: /cancel/i }))

    expect(srv.deleted).not.toContain(1)
  })
})
