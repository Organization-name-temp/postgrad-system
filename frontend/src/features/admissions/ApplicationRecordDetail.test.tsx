import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { ThemeProvider, createTheme } from '@mui/material/styles'
import '../../shared/i18n/config' // initialise i18next so useTranslation works
import { ApplicationRecordDetail } from './ApplicationRecordDetail'
import { formatDate } from '../../shared/datetime'
import type { ReviewAnApplication200Data } from '../../shared/api/generated/endpoints'

// The record only renders; openDocument (behind the shared mutator) fires on click, so
// the mutator just needs to exist for the import to resolve.
vi.mock('../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: () => Promise.resolve({ data: {} }),
}))

// The timeline chip reads CSS-variable palette entries, so render under a cssVariables
// theme (as the app always does).
const theme = createTheme({ cssVariables: true, colorSchemes: { light: true, dark: true } })

// A fully-populated record — every captured field carries a distinctive value so the
// full read-back can be asserted field-by-field, not by count.
const application: ReviewAnApplication200Data = {
  id: 1,
  status: 'under_sa_review',
  admission_cycle_id: 1,
  name_en: 'Sara Fahmy',
  name_ar: 'سارة فهمي',
  national_id: '29001011234567',
  id_type: 'national_id',
  phone: '+201000000000',
  date_of_birth: '1990-01-01',
  nationality: 'EG',
  gender: 'Female',
  address_country: 'Egypt',
  address_city: 'Alexandria',
  address_street: '15 El-Horreya Road',
  previous_university: 'Cairo University',
  graduation_certificate_name: 'BSc Computer Science',
  graduation_date: '2012-07-01',
  previous_degree: 'BSc',
  previous_major: 'Computer Science',
  graduation_gpa: '3.7',
  graduation_cgpa: '3.65',
  gpa_scale: '4.0',
  is_ejust_graduate: false,
  applying_for_degree: 'MSc',
  english_test: 'IELTS',
  english_score: '7.5',
  native_arabic_speaker: true,
  primary_program: { id: 10, code: 'MSC-CE', name_en: 'MSc in Computer Engineering' },
  alternative_program: { id: 11, code: 'MSC-EE', name_en: 'MSc in Electrical Engineering' },
  documents: [
    {
      id: 'doc-1',
      document_type: 'graduation_certificate',
      original_filename: 'degree.pdf',
      download_url: 'http://localhost/api/v1/documents/doc-1',
    },
    {
      id: 'doc-2',
      document_type: 'transcript',
      original_filename: 'transcript.pdf',
      download_url: 'http://localhost/api/v1/documents/doc-2',
    },
  ],
}

const timeline = {
  entries: [
    {
      id: 1,
      from_status: 'email_verified',
      to_status: 'under_sa_review',
      actor_user_id: 5,
      note: null,
      occurred_at: '2026-07-10T10:00:00+00:00',
    },
  ],
  isPending: false,
  isError: false,
}

function renderDetail() {
  render(
    <ThemeProvider theme={theme}>
      <ApplicationRecordDetail application={application} timeline={timeline} />
    </ThemeProvider>,
  )
}

describe('ApplicationRecordDetail', () => {
  it('groups the complete record into readable sections', () => {
    renderDetail()
    for (const heading of [
      'Identity',
      'Contact & address',
      'Academic background',
      'English proficiency',
      'Programs',
      'Documents',
    ]) {
      expect(screen.getByText(heading)).toBeInTheDocument()
    }
  })

  it('renders the full field set, not the old six-field summary', () => {
    renderDetail()
    // Identity + contact — including fields absent from the old summary.
    expect(screen.getByText('29001011234567')).toBeInTheDocument()
    expect(screen.getByText('+201000000000')).toBeInTheDocument()
    expect(screen.getByText('Egypt')).toBeInTheDocument()
    expect(screen.getByText('Alexandria')).toBeInTheDocument()
    expect(screen.getByText('15 El-Horreya Road')).toBeInTheDocument()
    // Academic background — the fuller record.
    expect(screen.getByText('BSc Computer Science')).toBeInTheDocument()
    expect(screen.getByText('Computer Science')).toBeInTheDocument()
    expect(screen.getByText('3.7')).toBeInTheDocument() // gpa
    expect(screen.getByText('3.65')).toBeInTheDocument() // cgpa (was missing)
    expect(screen.getByText('4.0')).toBeInTheDocument() // gpa_scale (was missing)
    // English proficiency.
    expect(screen.getByText('7.5')).toBeInTheDocument()
  })

  it('labels enum and boolean fields rather than echoing raw values', () => {
    renderDetail()
    expect(screen.getByText('National ID')).toBeInTheDocument() // id_type label
    expect(screen.getByText('Female')).toBeInTheDocument() // gender enum
    // is_ejust_graduate = false → "No"; native_arabic_speaker = true → "Yes".
    expect(screen.getByText('No')).toBeInTheDocument()
    expect(screen.getByText('Yes')).toBeInTheDocument()
  })

  it('formats dates for the locale instead of echoing the raw ISO string', () => {
    renderDetail()
    expect(screen.queryByText('1990-01-01')).not.toBeInTheDocument()
    expect(screen.getByText(formatDate('1990-01-01', 'en'))).toBeInTheDocument()
    expect(screen.getByText(formatDate('2012-07-01', 'en'))).toBeInTheDocument()
  })

  it('renders both program choices by their localized names', () => {
    renderDetail()
    expect(screen.getByText('MSc in Computer Engineering')).toBeInTheDocument()
    expect(screen.getByText('MSc in Electrical Engineering')).toBeInTheDocument()
  })

  it('lists each uploaded document as an openable link, with its type', () => {
    renderDetail()
    expect(screen.getByRole('button', { name: 'degree.pdf' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'transcript.pdf' })).toBeInTheDocument()
    // "Academic transcript" is the transcript document's type label (unique to the
    // documents list — unlike "Graduation certificate", which also labels a field).
    expect(screen.getByText('Academic transcript')).toBeInTheDocument()
  })

  it('shows the status timeline alongside the record', () => {
    renderDetail()
    expect(screen.getByTestId('status-timeline')).toBeInTheDocument()
    // The single transition entered above is listed.
    const list = screen.getByTestId('timeline-list')
    expect(within(list).getAllByRole('listitem')).toHaveLength(1)
  })
})
