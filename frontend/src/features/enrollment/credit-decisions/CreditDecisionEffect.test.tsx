import { render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it } from 'vitest'
import i18n from '../../../shared/i18n/config'
import { CreditDecisionEffect } from './CreditDecisionEffect'

describe('CreditDecisionEffect', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('en')
  })

  it('distinguishes an effective decision from an attributed Revocation', () => {
    const { rerender } = render(
      <CreditDecisionEffect effective>Recognized for 3 CH</CreditDecisionEffect>,
    )

    expect(screen.getByText('Recognized for 3 CH')).toBeInTheDocument()
    expect(screen.queryByText('Decision revoked')).not.toBeInTheDocument()

    rerender(
      <CreditDecisionEffect
        effective={false}
        revocation={{
          reason: 'Corrected source transcript.',
          revoked_by_user_id: 9,
          created_at: '2026-09-24T10:00:00+00:00',
        }}
      >
        Recognized for 3 CH
      </CreditDecisionEffect>,
    )

    expect(screen.getByText('Decision revoked')).toBeInTheDocument()
    expect(screen.getByText('Corrected source transcript.')).toBeInTheDocument()
    expect(screen.getByText('9')).toHaveAttribute('dir', 'ltr')
    expect(screen.getByText(/24 Sept 2026/)).toHaveAttribute('dir', 'ltr')
    expect(screen.queryByText('Recognized for 3 CH')).not.toBeInTheDocument()
  })
})
