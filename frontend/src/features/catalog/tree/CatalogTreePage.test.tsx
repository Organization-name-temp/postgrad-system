import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ThemeProvider, createTheme } from '@mui/material'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { CatalogTreePage } from './CatalogTreePage'
import * as api from '../../../shared/api/generated/endpoints'
import type { TheCatalogBrowseTree200 } from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedApi = vi.mocked(api)

// One Faculty → Department → Program with a Preparatory (active) and a Program Core
// (inactive) course; Research Elective empty.
const TREE: TheCatalogBrowseTree200 = {
  data: {
    faculties: [
      {
        id: 1,
        code: 'FoE',
        name_en: 'Faculty of Engineering',
        name_ar: 'كلية الهندسة',
        is_active: true,
        departments: [
          {
            id: 1,
            code: 'CE',
            name_en: 'Computer Engineering',
            name_ar: 'هندسة الحاسوب',
            is_active: true,
            programs: [
              {
                id: 1,
                code: 'MSC-CE',
                name_en: 'MSc in Computer Engineering',
                name_ar: 'ماجستير في هندسة الحاسوب',
                degree_level: 'MSc',
                is_active: true,
                categories: [
                  {
                    category: 'Preparatory',
                    required_ch: 6,
                    courses: [
                      {
                        id: 10,
                        code: 'PRE401',
                        name_en: 'English for Academic Purposes',
                        name_ar: 'الإنجليزية للأغراض الأكاديمية',
                        level: 400,
                        is_active: true,
                      },
                    ],
                  },
                  {
                    category: 'ProgramCore',
                    required_ch: 6,
                    courses: [
                      {
                        id: 11,
                        code: 'DEP501',
                        name_en: 'Advanced Algorithms',
                        name_ar: 'خوارزميات متقدمة',
                        level: 500,
                        is_active: false,
                      },
                    ],
                  },
                  { category: 'ResearchElective', required_ch: 9, courses: [] },
                ],
              },
            ],
          },
        ],
      },
    ],
  },
}

function setup(tree: TheCatalogBrowseTree200 = TREE) {
  mockedApi.useTheCatalogBrowseTree.mockReturnValue({
    data: tree,
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof api.useTheCatalogBrowseTree>)

  // The status chips read design-token CSS variables, so the tree renders under a
  // cssVariables theme (as the app does) — otherwise theme.vars is undefined.
  render(
    <ThemeProvider theme={createTheme({ cssVariables: true })}>
      <QueryClientProvider client={new QueryClient()}>
        <MemoryRouter>
          <CatalogTreePage />
        </MemoryRouter>
      </QueryClientProvider>
    </ThemeProvider>,
  )

  // delay: null types synchronously — the tree re-filters on every keystroke, so
  // the default per-key delay makes this needlessly slow under a parallel run.
  return { user: userEvent.setup({ delay: null }) }
}

// Rendering @mui/x-tree-view in jsdom is heavy; under a parallel run it can brush
// past the 5s default, so this suite gets a roomier timeout (it passes in <1s of
// actual work).
describe('CatalogTreePage', { timeout: 15_000 }, () => {
  beforeEach(() => vi.clearAllMocks())

  it('prepends the University root and groups faculties under it', () => {
    setup()

    // The synthetic University root the SPA adds…
    expect(screen.getByText('Egypt-Japan University of Science and Technology')).toBeInTheDocument()
    // …with the Faculty grouping beneath it.
    expect(screen.getByText('Faculty of Engineering')).toBeInTheDocument()
  })

  it('filters to matching nodes and hides the rest as the query changes', async () => {
    const { user } = setup()
    const search = screen.getByRole('textbox')

    // Query the Preparatory course by code → it stays, the Core course is gone.
    await user.type(search, 'PRE401')
    expect(screen.getByText(/PRE401/)).toBeInTheDocument()
    expect(screen.queryByText(/DEP501/)).not.toBeInTheDocument()

    // Switch the query to text inside the other course → the reveal flips.
    await user.clear(search)
    await user.type(search, 'Advanced')
    expect(screen.getByText(/DEP501/)).toBeInTheDocument()
    expect(screen.queryByText(/PRE401/)).not.toBeInTheDocument()
  })

  it('shows a no-results message when nothing matches', async () => {
    const { user } = setup()

    await user.type(screen.getByRole('textbox'), 'nonexistent-xyz')

    expect(screen.getByText(/No programs or courses match/)).toBeInTheDocument()
  })

  it('marks each course with an accessible status chip (label, not colour alone)', async () => {
    const { user } = setup()
    const search = screen.getByRole('textbox')

    // The active Preparatory course reads "Active"…
    await user.type(search, 'PRE401')
    expect(screen.getByText('Active')).toBeInTheDocument()

    // …the retired Core course reads "Inactive".
    await user.clear(search)
    await user.type(search, 'DEP501')
    expect(screen.getByText('Inactive')).toBeInTheDocument()
  })
})
