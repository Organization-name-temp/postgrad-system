import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { AssignmentsPage } from './AssignmentsPage'

interface Assignment {
  id: number
  user_id: number
  faculty_id: number
  dean_name: string
  dean_email: string
}

// A tiny in-memory server behind the shared Axios mutator, so the page drives the
// REAL generated hooks (assignments list + faculties list + assign/unassign
// mutations): the faculty-name join and the unassign → refetch loop are observed,
// not stubbed.
const srv = vi.hoisted(() => ({
  assignments: [] as Assignment[],
  deleted: [] as number[],
  posted: undefined as unknown,
}))

vi.mock('../../../shared/api/http', () => ({
  http: {
    defaults: { headers: { common: {} as Record<string, string> } },
    get: () => Promise.resolve({}),
  },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    const method = (config.method ?? 'get').toLowerCase()

    if (url.endsWith('/admin/faculty-dean-assignments') && method === 'get') {
      return Promise.resolve({ data: srv.assignments })
    }
    if (url.endsWith('/admin/faculty-dean-assignments') && method === 'post') {
      srv.posted = config.data
      return Promise.resolve({ data: { id: 99, ...(config.data as object) } })
    }
    if (url.includes('/admin/faculty-dean-assignments/') && method === 'delete') {
      const id = Number(url.split('/').pop())
      srv.deleted.push(id)
      srv.assignments = srv.assignments.filter((a) => a.id !== id)
      return Promise.resolve({})
    }
    if (url.endsWith('/admin/deans')) {
      return Promise.resolve({ data: [{ id: 7, name: 'Dr. Dean', email: 'dean@ejust.test' }] })
    }
    if (url.endsWith('/faculties')) {
      return Promise.resolve({
        data: [{ id: 3, name_en: 'Faculty of Engineering', name_ar: 'كلية الهندسة' }],
      })
    }
    return Promise.resolve({ data: [] })
  },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <AssignmentsPage />
    </QueryClientProvider>,
  )
}

describe('AssignmentsPage', () => {
  beforeEach(() => {
    srv.assignments = []
    srv.deleted = []
    srv.posted = undefined
  })

  it('shows an empty state when there are no assignments', async () => {
    renderPage()
    expect(await screen.findByText(/no dean.*faculty assignments yet/i)).toBeInTheDocument()
  })

  it('lists each assignment with the dean and the resolved faculty name', async () => {
    srv.assignments = [
      { id: 1, user_id: 7, faculty_id: 3, dean_name: 'Dr. Dean', dean_email: 'dean@ejust.test' },
    ]
    renderPage()

    const row = (await screen.findByText('Dr. Dean')).closest('tr') as HTMLElement
    // The opaque faculty_id (3) is joined to its name from the faculties list.
    expect(within(row).getByText('Faculty of Engineering')).toBeInTheDocument()
  })

  it('unassigns a Dean only after confirming in the dialog (destructive-action guard)', async () => {
    srv.assignments = [
      { id: 1, user_id: 7, faculty_id: 3, dean_name: 'Dr. Dean', dean_email: 'dean@ejust.test' },
    ]
    const user = userEvent.setup({ delay: null })
    renderPage()

    // The row action opens a confirmation dialog — it does not delete immediately.
    await user.click(await screen.findByRole('button', { name: /unassign dr\. dean/i }))
    expect(srv.deleted).not.toContain(1)

    // Confirming in the dialog fires the DELETE.
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: /^unassign$/i }))
    expect(srv.deleted).toContain(1)
  })

  it('does not unassign when the confirmation is cancelled', async () => {
    srv.assignments = [
      { id: 1, user_id: 7, faculty_id: 3, dean_name: 'Dr. Dean', dean_email: 'dean@ejust.test' },
    ]
    const user = userEvent.setup({ delay: null })
    renderPage()

    await user.click(await screen.findByRole('button', { name: /unassign dr\. dean/i }))
    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: /cancel/i }))

    expect(srv.deleted).not.toContain(1)
  })
})
