import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import '../shared/i18n/config'
import { UserMenu } from './UserMenu'
import { useAuth } from '../features/auth/useAuth'
import type { UseAuth } from '../features/auth/useAuth'

vi.mock('../features/auth/useAuth')
const mockedUseAuth = vi.mocked(useAuth)

function authState(overrides: Partial<UseAuth>): UseAuth {
  return {
    user: undefined,
    isPending: false,
    isAuthenticated: false,
    permissions: [],
    hasPermission: () => false,
    roles: [],
    hasRole: () => false,
    isAdmin: false,
    isLoggingIn: false,
    loginError: undefined,
    login: vi.fn(),
    logout: vi.fn(),
    isRegistering: false,
    registerError: undefined,
    register: vi.fn(),
    persistLocale: vi.fn(),
    ...overrides,
  }
}

describe('UserMenu', () => {
  beforeEach(() => vi.clearAllMocks())

  it('reveals the signed-in identity and logs out on demand', async () => {
    const logout = vi.fn().mockResolvedValue(undefined)
    mockedUseAuth.mockReturnValue(
      authState({ user: { id: 1, name: 'Dr. Dean', email: 'r07@ejust.test' }, logout }),
    )
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <UserMenu />
      </MemoryRouter>,
    )

    // Identity is behind the menu, not shown until opened.
    expect(screen.queryByText('r07@ejust.test')).not.toBeInTheDocument()

    await user.click(screen.getByRole('button'))
    expect(screen.getByText('Dr. Dean')).toBeInTheDocument()
    expect(screen.getByText('r07@ejust.test')).toBeInTheDocument()

    await user.click(screen.getByRole('menuitem', { name: /log out/i }))
    expect(logout).toHaveBeenCalledOnce()
  })
})
