import { afterEach, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import '../../shared/i18n/config'
import i18n from '../../shared/i18n/config'
import { CreditHourSummaryPage } from './CreditHourSummaryPage'
import {
  useMyCurrentAcademicStandingAndObservationHistory,
  useMyTargetAwareCreditHourSummary,
  useMyDerivedGraduationReadiness,
  useGetApiV1TerminationCasesMine,
  useGetApiV1RegistrationRemediationObligations,
} from '../../shared/api/generated/endpoints'

vi.mock('../../shared/api/generated/endpoints')
const mockedQuery = vi.mocked(useMyTargetAwareCreditHourSummary)
const mockedStanding = vi.mocked(useMyCurrentAcademicStandingAndObservationHistory)
const mockedReadiness = vi.mocked(useMyDerivedGraduationReadiness)
const mockedTermination = vi.mocked(useGetApiV1TerminationCasesMine)
const mockedRemediation = vi.mocked(useGetApiV1RegistrationRemediationObligations)
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

afterEach(async () => {
  await i18n.changeLanguage('en')
  vi.clearAllMocks()
})

it('distinguishes raw facts from capped Satisfied and Remaining credit', () => {
  mockedRemediation.mockReturnValue({ data: { data: [] }, isPending: false, isError: false } as ReturnType<typeof useGetApiV1RegistrationRemediationObligations>)
  mockedTermination.mockReturnValue({
    data: { data: null },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useGetApiV1TerminationCasesMine>)
  mockedReadiness.mockReturnValue({
    data: {
      data: {
        ready: false,
        remaining_credit_hours: 20,
        cgpa: 3.2,
        approaching_graduation: false,
        blockers: ['RemainingRequirements'],
      },
    },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyDerivedGraduationReadiness>)
  mockedStanding.mockReturnValue({
    data: {
      data: {
        standing: 'AcademicWarning',
        explanation: 'effective_grade_below_c_plus',
        observations: [],
      },
    },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyCurrentAcademicStandingAndObservationHistory>)
  mockedQuery.mockReturnValue({
    data: {
      data: {
        student_id: 1,
        curriculum_version_id: 2,
        categories: [
          {
            category: 'Preparatory',
            baseline_required_ch: 6,
            adjusted_required_ch: 5,
            attended_ch: 2,
            earned_ch: 1,
            transferred_ch: 0,
            waived_ch: 0,
            exempted_ch: 1,
            satisfied_ch: 0,
            remaining_ch: 5,
          },
        ],
        total: {
          category: 'Total',
          baseline_required_ch: 21,
          adjusted_required_ch: 20,
          attended_ch: 2,
          earned_ch: 1,
          transferred_ch: 0,
          waived_ch: 0,
          exempted_ch: 1,
          satisfied_ch: 0,
          remaining_ch: 20,
        },
      },
    },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyTargetAwareCreditHourSummary>)
  render(
    <ThemeProvider theme={theme}>
      <CreditHourSummaryPage />
    </ThemeProvider>,
  )

  expect(screen.getByRole('heading', { name: 'Credit-Hour Summary' })).toBeInTheDocument()
  expect(screen.getByRole('columnheader', { name: 'Earned' })).toBeInTheDocument()
  expect(screen.getByRole('columnheader', { name: 'Satisfied' })).toBeInTheDocument()
  expect(screen.getByRole('columnheader', { name: 'Remaining' })).toBeInTheDocument()
  expect(screen.getByRole('rowheader', { name: 'Total' })).toBeInTheDocument()
})

it('renders the same accounting labels in Arabic RTL', async () => {
  mockedRemediation.mockReturnValue({ data: { data: [] }, isPending: false, isError: false } as ReturnType<typeof useGetApiV1RegistrationRemediationObligations>)
  mockedTermination.mockReturnValue({
    data: { data: null },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useGetApiV1TerminationCasesMine>)
  await i18n.changeLanguage('ar')
  mockedStanding.mockReturnValue({
    data: { data: { standing: 'GoodStanding', explanation: 'requirements_met', observations: [] } },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyCurrentAcademicStandingAndObservationHistory>)
  mockedReadiness.mockReturnValue({
    data: {
      data: {
        ready: true,
        remaining_credit_hours: 0,
        cgpa: 3.5,
        approaching_graduation: false,
        blockers: [],
      },
    },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyDerivedGraduationReadiness>)
  mockedQuery.mockReturnValue({
    data: { data: { categories: [], total: { category: 'Total' } } },
    isPending: false,
    isError: false,
  } as ReturnType<typeof useMyTargetAwareCreditHourSummary>)
  render(
    <ThemeProvider theme={theme}>
      <CreditHourSummaryPage />
    </ThemeProvider>,
  )

  expect(document.documentElement).toHaveAttribute('dir', 'rtl')
  expect(screen.getByRole('heading', { name: 'ملخص الساعات المعتمدة' })).toBeInTheDocument()
  expect(screen.getByRole('columnheader', { name: 'مستوفى' })).toBeInTheDocument()
})
