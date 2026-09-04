import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { AxiosRequestConfig } from 'axios'
import '../shared/i18n/config' // initialise i18next so useTranslation works
import { NotificationsBell } from './NotificationsBell'

interface Row {
  id: number
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

// A tiny in-memory server. `vi.hoisted` lets the vi.mock factory (hoisted above
// imports) reference it. The bell drives the REAL generated hooks against this
// through the one seam they all share — the Axios mutator — so the full
// query → mutation → invalidate → refetch loop runs, and "the badge clears on
// mark-read" is observed, not stubbed.
const srv = vi.hoisted(() => ({ unread: 0, rows: [] as Row[] }))

vi.mock('../shared/api/http', () => ({
  // i18n/config also imports `http` to set Accept-Language — provide a stub so
  // replacing the module doesn't drop that export.
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: (config: AxiosRequestConfig) => {
    const url = config.url ?? ''
    if (url.endsWith('/notifications/unread-count')) {
      return Promise.resolve({ data: { count: srv.unread } })
    }
    if (url.endsWith('/notifications/read-all')) {
      srv.unread = 0
      srv.rows = srv.rows.map((r) => ({ ...r, read_at: r.read_at ?? '2026-07-14T00:00:00+00:00' }))
      return Promise.resolve({})
    }
    const readOne = /\/notifications\/(\d+)\/read$/.exec(url)
    if (readOne) {
      const id = Number(readOne[1])
      srv.rows = srv.rows.map((r) => {
        if (r.id === id && !r.read_at) {
          srv.unread -= 1
          return { ...r, read_at: '2026-07-14T00:00:00+00:00' }
        }
        return r
      })
      return Promise.resolve({})
    }
    if (url.endsWith('/notifications')) {
      return Promise.resolve({ data: srv.rows, links: {}, meta: {} })
    }
    return Promise.resolve({})
  },
}))

function renderBell() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <NotificationsBell />
    </QueryClientProvider>,
  )
}

describe('NotificationsBell', () => {
  beforeEach(() => {
    srv.unread = 3
    srv.rows = [
      {
        id: 3,
        type: 'application.accepted',
        data: { university_id: '2026-CSE-0001' },
        read_at: null,
        created_at: '2026-07-14T12:00:00+00:00',
      },
      {
        id: 2,
        type: 'interview.scheduled',
        data: {},
        read_at: null,
        created_at: '2026-07-13T12:00:00+00:00',
      },
      {
        id: 1,
        type: 'application.returned',
        data: {},
        read_at: null,
        created_at: '2026-07-12T12:00:00+00:00',
      },
    ]
  })

  it('shows the unread count on the badge', async () => {
    renderBell()
    expect(await screen.findByText('3')).toBeInTheDocument()
  })

  it('renders notifications bilingually from their type and clears the count on mark-all-read', async () => {
    const user = userEvent.setup()
    renderBell()

    // The bell announces the unread count (badge + accessible name).
    const bell = screen.getByRole('button', { name: /open notifications/i })
    await waitFor(() => expect(bell).toHaveAccessibleName(/3 unread/i))
    expect(screen.getByText('3')).toBeInTheDocument()

    // Open the panel.
    await user.click(bell)

    // The acceptance message is rendered from the type + payload via i18n — the
    // server stored no text, and the University ID is interpolated from `data`.
    expect(await screen.findByText(/you have been accepted/i)).toBeInTheDocument()
    expect(screen.getByText(/2026-CSE-0001/)).toBeInTheDocument()

    // Mark all read → the mutation succeeds, both queries refetch, the count
    // clears: the bell no longer announces any unread notifications.
    await user.click(screen.getByRole('button', { name: /mark all as read/i }))
    await waitFor(() => expect(bell).toHaveAccessibleName('Open notifications'))
  })

  it('marks a single notification read, dropping the count by one', async () => {
    const user = userEvent.setup()
    renderBell()

    const bell = screen.getByRole('button', { name: /open notifications/i })
    await waitFor(() => expect(bell).toHaveAccessibleName(/3 unread/i))
    await user.click(bell)

    // Click one unread notification (the "mark as read" row for the acceptance).
    const rows = await screen.findAllByRole('button', { name: /mark as read/i })
    await user.click(rows[0])

    // Only that one is marked read → the count drops from 3 to 2.
    await waitFor(() => expect(bell).toHaveAccessibleName(/2 unread/i))
  })

  it('renders the automatic exemption notification from its typed payload', async () => {
    const user = userEvent.setup()
    srv.unread = 1
    srv.rows = [
      {
        id: 4,
        type: 'exemption.automatically_applied',
        data: { course_code: 'ENG-PREP-X' },
        read_at: null,
        created_at: '2026-09-04T10:00:00+00:00',
      },
    ]
    renderBell()

    await user.click(screen.getByRole('button', { name: /open notifications/i }))
    expect(
      await screen.findByText(/automatic preparatory exemption.*ENG-PREP-X/i),
    ).toBeInTheDocument()
  })
})
