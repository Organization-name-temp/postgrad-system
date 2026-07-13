import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { CurriculumPanel } from './CurriculumPanel'
import * as api from '../../../shared/api/generated/endpoints'
import type {
  ListAProgramsCurriculum200DataItem,
  ListCourses200DataItem,
} from '../../../shared/api/generated/endpoints'

vi.mock('../../../shared/api/generated/endpoints')
const mockedApi = vi.mocked(api)

const COURSES: ListCourses200DataItem[] = [
  {
    id: 1,
    code: 'DEP501',
    name_en: 'Advanced Algorithms',
    name_ar: 'خوارزميات',
    level: 500,
    is_active: true,
  },
  {
    id: 2,
    code: 'PRE401',
    name_en: 'English for Academic Purposes',
    name_ar: 'إنجليزي',
    level: 400,
    is_active: true,
  },
]

interface SetupOptions {
  entries?: ListAProgramsCurriculum200DataItem[]
  canManage?: boolean
}

function setup({ entries = [], canManage = true }: SetupOptions = {}) {
  const addMutate = vi.fn()
  const removeMutate = vi.fn()

  mockedApi.useListAProgramsCurriculum.mockReturnValue({
    data: { data: entries },
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof api.useListAProgramsCurriculum>)
  mockedApi.useListCourses.mockReturnValue({
    data: { data: COURSES },
  } as unknown as ReturnType<typeof api.useListCourses>)
  mockedApi.useAddACourseToAProgramsCurriculum.mockReturnValue({
    mutate: addMutate,
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof api.useAddACourseToAProgramsCurriculum>)
  mockedApi.useRemoveACurriculumEntryFromAProgram.mockReturnValue({
    mutate: removeMutate,
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof api.useRemoveACurriculumEntryFromAProgram>)
  mockedApi.getListAProgramsCurriculumQueryKey.mockReturnValue([
    '/api/v1/programs/1/curriculum',
  ] as unknown as ReturnType<typeof api.getListAProgramsCurriculumQueryKey>)

  render(
    <QueryClientProvider client={new QueryClient()}>
      <CurriculumPanel programId={1} canManage={canManage} />
    </QueryClientProvider>,
  )

  return { addMutate, removeMutate, user: userEvent.setup() }
}

describe('CurriculumPanel', () => {
  beforeEach(() => vi.clearAllMocks())

  it('adds the chosen course to the section it was picked in', async () => {
    const { addMutate, user } = setup()

    const section = screen.getByTestId('curriculum-section-ProgramCore')
    await user.click(within(section).getByRole('combobox'))
    await user.click(await screen.findByRole('option', { name: /DEP501/ }))
    await user.click(within(section).getByRole('button', { name: 'Add' }))

    expect(addMutate).toHaveBeenCalledWith(
      { programId: 1, data: { course_id: 1, category: 'ProgramCore' } },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    )
  })

  it('removes an existing entry', async () => {
    const entry: ListAProgramsCurriculum200DataItem = {
      id: 10,
      program_id: 1,
      category: 'ProgramCore',
      ordinal: 0,
      course: COURSES[0],
    }
    const { removeMutate, user } = setup({ entries: [entry] })

    const section = screen.getByTestId('curriculum-section-ProgramCore')
    await user.click(within(section).getByRole('button', { name: /Remove DEP501/ }))

    expect(removeMutate).toHaveBeenCalledWith(
      { programId: 1, curriculumEntryId: 10 },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    )
  })

  it('does not offer a course already placed in that category', async () => {
    const entry: ListAProgramsCurriculum200DataItem = {
      id: 10,
      program_id: 1,
      category: 'ProgramCore',
      ordinal: 0,
      course: COURSES[0], // DEP501
    }
    const { user } = setup({ entries: [entry] })

    const section = screen.getByTestId('curriculum-section-ProgramCore')
    await user.click(within(section).getByRole('combobox'))

    // PRE401 is still offered; the already-placed DEP501 is not.
    expect(await screen.findByRole('option', { name: /PRE401/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /DEP501/ })).not.toBeInTheDocument()
  })

  it('shows entries but hides add/remove controls without catalog.manage', () => {
    const entry: ListAProgramsCurriculum200DataItem = {
      id: 10,
      program_id: 1,
      category: 'ProgramCore',
      ordinal: 0,
      course: COURSES[0],
    }
    setup({ entries: [entry], canManage: false })

    // The placed course is visible to a viewer...
    expect(screen.getByText(/DEP501/)).toBeInTheDocument()
    // ...but no add picker and no remove control.
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Remove/ })).not.toBeInTheDocument()
  })
})
