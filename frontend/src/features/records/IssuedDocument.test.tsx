import { act, render, screen } from '@testing-library/react'
import { afterEach, expect, it } from 'vitest'
import i18n from '../../shared/i18n/config'
import { IssuedDocument } from './IssuedDocument'

afterEach(async () => {
  await act(() => i18n.changeLanguage('en'))
})

it('shows frozen certification and QR in both languages and removes public content after revocation', async () => {
  const { rerender } = render(
    <IssuedDocument
      issuance={{
        reference: 'REF-ONE',
        status: 'Valid',
        issuer_name: 'Records Officer',
        issued_at: '2026-09-27',
        snapshot: {
          cgpa: null,
          identity: {
            name_en: 'Sara',
            name_ar: 'سارة',
            identification_number: 'AB123456',
            identification_type: 'passport',
            ready: true,
          },
        },
      }}
      verificationPath="/records/verify/unguessable"
    />,
  )
  expect(screen.getByText('OFFICIAL TRANSCRIPT')).toHaveAttribute('aria-hidden', 'true')
  expect(screen.getByText('AB123456')).toBeInTheDocument()
  expect(screen.getByRole('link', { name: 'Verify this official copy' })).toHaveAttribute(
    'href',
    expect.stringContaining('/records/verify/unguessable'),
  )
  expect(screen.getByTitle('QR verification link')).toBeInTheDocument()
  await act(() => i18n.changeLanguage('ar'))
  expect(screen.getByText('سارة')).toBeInTheDocument()
  expect(screen.getByText('سجل أكاديمي رسمي')).toHaveAttribute('aria-hidden', 'true')
  rerender(
    <IssuedDocument
      issuance={{ reference: 'REF-ONE', status: 'Revoked' }}
      verificationPath="/records/verify/unguessable"
      publicView
    />,
  )
  expect(screen.getByText('نسخة رسمية ملغاة الصلاحية')).toBeInTheDocument()
  expect(screen.getByText('REF-ONE')).toBeInTheDocument()
  expect(screen.queryByText('سجل أكاديمي رسمي')).not.toBeInTheDocument()
  expect(screen.queryByText('AB123456')).not.toBeInTheDocument()
  expect(screen.queryByText('سارة')).not.toBeInTheDocument()
  expect(screen.queryByText('Records Officer')).not.toBeInTheDocument()
})
