import { act, render, screen } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '../../shared/i18n/config'
import { TranscriptDocument } from './TranscriptDocument'

afterEach(async () => {
  await act(() => i18n.changeLanguage('en'))
})

it('shows null GPAs as dashes, preserves CH and Attempts, and switches academic labels to Arabic', async () => {
  render(
    <TranscriptDocument
      record={{
        identity: {
          name_en: 'Sara',
          name_ar: 'سارة',
          identification_number: 'AB123456',
          identification_type: 'passport',
          ready: true,
        },
        university_id: '2026-CE-0001',
        enrollment_term: 'Fall 2026',
        cgpa: null,
        semesters: [
          {
            semester: { id: 1, name_en: 'Fall 2026', name_ar: 'خريف 2026' },
            gpa: null,
            courses: [
              {
                enrollment_id: 1,
                course: { code: 'CE501', name_en: 'Algorithms', name_ar: 'الخوارزميات' },
                credit_hours: 2,
                classification: 'Core',
                result: 'InProgress',
                contributes_to_cgpa: false,
              },
            ],
          },
        ],
        credit_decisions: [
          {
            type: 'Exemption',
            approved_at: '2026-09-01T00:00:00Z',
            target_course_code: 'PRE501',
            reason_codes: ['english_proficiency'],
          },
        ],
      }}
    />,
  )
  expect(screen.getByText('Algorithms')).toBeInTheDocument()
  expect(screen.getByText('In Progress')).toBeInTheDocument()
  expect(screen.getAllByText('—')).toHaveLength(2)
  expect(screen.getByText('AB123456')).toHaveAttribute('dir', 'ltr')
  await act(() => i18n.changeLanguage('ar'))
  expect(screen.getByText('الخوارزميات')).toBeInTheDocument()
  expect(screen.getByText('قيد الدراسة')).toBeInTheDocument()
  expect(screen.getByText('خريف 2026')).toBeInTheDocument()
  expect(screen.getByText(/الخريف 2026/)).toBeInTheDocument()
  expect(screen.queryByText('english_proficiency')).not.toBeInTheDocument()
  expect(screen.queryByText('Algorithms')).not.toBeInTheDocument()
})

it('isolates external identifiers, credit hours, grades and approval dates in an Arabic record', () => {
  render(
    <TranscriptDocument
      language="ar"
      record={{
        cgpa: null,
        credit_decisions: [
          {
            type: 'Transfer',
            target_course_code: 'CE501',
            credit_hours: 3,
            approved_at: '2026-09-01T00:00:00Z',
            source_course_code: 'EXT501',
            original_grade: 'Distinction',
            normalized_grade: 'B',
          },
          {
            type: 'Waiver',
            target_course_code: 'CE502',
            prior_course_code: 'OLD502',
            prior_grade: 'A',
          },
        ],
      }}
    />,
  )
  for (const value of ['EXT501', 'Distinction', 'B', 'OLD502', 'A', '3']) {
    expect(screen.getByText(value)).toHaveAttribute('dir', 'ltr')
  }
  expect(screen.getByText(/2026/).closest('[dir="ltr"]')).not.toBeNull()
})
