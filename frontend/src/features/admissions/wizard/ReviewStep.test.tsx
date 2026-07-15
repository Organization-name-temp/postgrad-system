import { describe, expect, it, vi } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import '../../../shared/i18n/config' // initialise i18next so useTranslation works
import { ReviewStep } from './ReviewStep'
import { formatDate } from '../../../shared/datetime'
import type { MyApplication200Data } from '../../../shared/api/generated/endpoints'

// The read-back only renders; the finalize/resubmit hooks are never fired here, so
// the shared mutator just needs to exist for the generated hooks to import.
vi.mock('../../../shared/api/http', () => ({
  http: { defaults: { headers: { common: {} as Record<string, string> } } },
  customInstance: () => Promise.resolve({ data: {} }),
}))

// A fully-populated snapshot — every captured field carries a distinctive value so
// the read-back can be asserted field-by-field rather than by count.
const application: MyApplication200Data = {
  id: 1,
  status: 'email_verified',
  admission_cycle_id: 1,
  name_en: 'Sara Fahmy',
  name_ar: 'سارة فهمي',
  national_id: '29001011234567',
  phone: '+201000000000',
  date_of_birth: '1990-01-01',
  nationality: 'Egyptian',
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
  primary_program: {
    id: 10,
    code: 'MSC-CE',
    name_en: 'MSc in Computer Engineering',
    name_ar: 'ماجستير هندسة الحاسوب',
  },
  alternative_program: {
    id: 11,
    code: 'MSC-CS',
    name_en: 'MSc in Computer Science',
    name_ar: 'ماجستير علوم الحاسوب',
  },
  documents: [
    {
      id: 'doc-1',
      document_type: 'graduation_certificate',
      original_filename: 'degree.pdf',
    },
    {
      id: 'doc-2',
      document_type: 'transcript',
      original_filename: 'transcript.pdf',
    },
  ],
}

function renderReview() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <ReviewStep application={application} onBack={vi.fn()} />
    </QueryClientProvider>,
  )
}

describe('ReviewStep', () => {
  it('groups the complete captured record into sections', () => {
    renderReview()
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

  it('reads back every captured personal-info field, not a three-field summary', () => {
    renderReview()
    // Identity + contact
    expect(screen.getByText('Sara Fahmy')).toBeInTheDocument()
    expect(screen.getByText('سارة فهمي')).toBeInTheDocument()
    expect(screen.getByText('29001011234567')).toBeInTheDocument()
    expect(screen.getByText('+201000000000')).toBeInTheDocument()
    expect(screen.getByText('Egyptian')).toBeInTheDocument()
    expect(screen.getByText('Egypt')).toBeInTheDocument()
    expect(screen.getByText('Alexandria')).toBeInTheDocument()
    expect(screen.getByText('15 El-Horreya Road')).toBeInTheDocument()
    // Academic background
    expect(screen.getByText('Cairo University')).toBeInTheDocument()
    expect(screen.getByText('BSc Computer Science')).toBeInTheDocument()
    expect(screen.getByText('Computer Science')).toBeInTheDocument()
    expect(screen.getByText('3.7')).toBeInTheDocument()
    expect(screen.getByText('3.65')).toBeInTheDocument()
    expect(screen.getByText('4.0')).toBeInTheDocument() // gpa_scale
    // English proficiency
    expect(screen.getByText('IELTS')).toBeInTheDocument()
    expect(screen.getByText('7.5')).toBeInTheDocument()
  })

  it('labels enum and boolean fields rather than showing raw values', () => {
    renderReview()
    expect(screen.getByText('Female')).toBeInTheDocument() // gender enum
    expect(screen.getByText('MSc')).toBeInTheDocument() // applying_for_degree enum
    expect(screen.getByText('No')).toBeInTheDocument() // is_ejust_graduate = false
    expect(screen.getByText('Yes')).toBeInTheDocument() // native_arabic_speaker = true
  })

  it('formats dates for the locale instead of echoing the raw ISO string', () => {
    renderReview()
    // The raw ISO must not leak through; the locale-formatted form is shown.
    expect(screen.queryByText('1990-01-01')).not.toBeInTheDocument()
    expect(screen.getByText(formatDate('1990-01-01', 'en'))).toBeInTheDocument()
    expect(screen.getByText(formatDate('2012-07-01', 'en'))).toBeInTheDocument()
  })

  it('renders both program choices by their localized names', () => {
    renderReview()
    expect(screen.getByText('MSc in Computer Engineering')).toBeInTheDocument()
    expect(screen.getByText('MSc in Computer Science')).toBeInTheDocument()
  })

  it('lists each uploaded document by filename and type, not just a count', () => {
    renderReview()
    const documents = screen.getByRole('list')
    expect(within(documents).getByText('degree.pdf')).toBeInTheDocument()
    expect(within(documents).getByText('Graduation certificate')).toBeInTheDocument()
    expect(within(documents).getByText('transcript.pdf')).toBeInTheDocument()
    expect(within(documents).getByText('Academic transcript')).toBeInTheDocument()
  })
})
