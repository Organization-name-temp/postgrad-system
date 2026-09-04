import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '../../../shared/i18n/config'
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
  complete?: boolean
  missingRoles?: string[]
}

function setup({
  entries = [],
  canManage = true,
  complete = false,
  missingRoles = [],
}: SetupOptions = {}) {
  const addMutate = vi.fn()
  const assignRoleMutate = vi.fn()
  const removeMutate = vi.fn()

  mockedApi.useListAProgramsCurriculum.mockReturnValue({
    data: {
      data: entries,
      meta: {
        preparatory_requirements_complete: complete,
        missing_preparatory_requirement_roles: missingRoles,
      },
    },
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
  mockedApi.useAssignAPreparatoryRequirementRole.mockReturnValue({
    mutate: assignRoleMutate,
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof api.useAssignAPreparatoryRequirementRole>)
  mockedApi.getListAProgramsCurriculumQueryKey.mockReturnValue([
    '/api/v1/programs/1/curriculum',
  ] as unknown as ReturnType<typeof api.getListAProgramsCurriculumQueryKey>)

  render(
    <QueryClientProvider client={new QueryClient()}>
      <CurriculumPanel programId={1} canManage={canManage} />
    </QueryClientProvider>,
  )

  return { addMutate, assignRoleMutate, removeMutate, user: userEvent.setup() }
}

describe('CurriculumPanel', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    await i18n.changeLanguage('en')
  })

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

  it('assigns a semantic role when adding a Preparatory Course', async () => {
    const { addMutate, user } = setup()

    const section = screen.getByTestId('curriculum-section-Preparatory')
    await user.click(within(section).getByRole('combobox', { name: 'Add a course' }))
    await user.click(await screen.findByRole('option', { name: /PRE401/ }))
    await user.click(
      within(section).getByRole('combobox', { name: 'Preparatory Requirement Role' }),
    )
    await user.click(await screen.findByRole('option', { name: 'English Language' }))
    await user.click(within(section).getByRole('button', { name: 'Add' }))

    expect(addMutate).toHaveBeenCalledWith(
      {
        programId: 1,
        data: {
          course_id: 2,
          category: 'Preparatory',
          preparatory_requirement_role: 'EnglishLanguage',
        },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    )
  })

  it('shows assigned and missing Preparatory Requirement Roles for review', () => {
    const entry: ListAProgramsCurriculum200DataItem = {
      id: 10,
      program_id: 1,
      category: 'Preparatory',
      preparatory_requirement_role: 'EnglishLanguage',
      ordinal: 0,
      course: COURSES[1],
    }
    setup({
      entries: [entry],
      missingRoles: ['ArabicLanguage', 'JapaneseLanguage'],
    })

    expect(screen.getByText('English Language')).toBeInTheDocument()
    expect(
      screen.getByText('Missing roles: Arabic Language, Japanese Language.'),
    ).toBeInTheDocument()
  })

  it('corrects an existing Preparatory Requirement Role', async () => {
    const entry: ListAProgramsCurriculum200DataItem = {
      id: 10,
      program_id: 1,
      category: 'Preparatory',
      preparatory_requirement_role: 'EnglishLanguage',
      ordinal: 0,
      course: COURSES[1],
    }
    const { assignRoleMutate, user } = setup({ entries: [entry] })

    await user.click(screen.getByRole('combobox', { name: 'Role for PRE401' }))
    await user.click(await screen.findByRole('option', { name: 'Japanese Culture' }))

    expect(assignRoleMutate).toHaveBeenCalledWith(
      {
        programId: 1,
        curriculumEntryId: 10,
        data: { preparatory_requirement_role: 'JapaneseCulture' },
      },
      expect.objectContaining({ onSuccess: expect.any(Function) }),
    )
  })

  it('presents Preparatory Requirement Roles in Arabic under RTL', async () => {
    await i18n.changeLanguage('ar')
    setup({ missingRoles: ['ArabicLanguage', 'KeySkills'] })

    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    expect(
      screen.getByText('الأدوار الناقصة: اللغة العربية, المهارات الأساسية.'),
    ).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'دور المتطلب التمهيدي' })).toBeInTheDocument()
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
