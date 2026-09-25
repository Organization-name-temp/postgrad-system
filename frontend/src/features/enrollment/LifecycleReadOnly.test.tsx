import { render, screen } from '@testing-library/react'
import { Button, ThemeProvider } from '@mui/material'
import { beforeEach, expect, it, vi } from 'vitest'
import { createAppTheme } from '../../app/theme/createAppTheme'
import i18n from '../../shared/i18n/config'
import { useAuth, type UseAuth } from '../auth/useAuth'
import { LifecycleReadOnly } from './LifecycleReadOnly'

vi.mock('../auth/useAuth')
const mockedUseAuth = vi.mocked(useAuth)

beforeEach(async () => {
  await i18n.changeLanguage('en')
})

it('keeps history visible but disables mutation controls for a terminated student', () => {
  mockedUseAuth.mockReturnValue({
    user: { student: { lifecycle_status: 'Terminated' } },
  } as UseAuth)
  render(
    <ThemeProvider theme={createAppTheme('en')}>
      <LifecycleReadOnly>
        <p>Preserved request history</p>
        <Button>Submit change</Button>
      </LifecycleReadOnly>
    </ThemeProvider>,
  )

  expect(screen.getByText('Preserved request history')).toBeInTheDocument()
  expect(screen.getByText(/academic history remains available/i)).toBeInTheDocument()
  expect(screen.getByRole('button', { name: 'Submit change' })).toBeDisabled()
})
