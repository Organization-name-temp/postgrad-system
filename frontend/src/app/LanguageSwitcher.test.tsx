import { ThemeProvider } from '@mui/material/styles'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, it, expect, vi } from 'vitest'
import i18n from '../shared/i18n/config'
import { createAppTheme } from './theme/createAppTheme'
import { LanguageSwitcher } from './LanguageSwitcher'

const auth = vi.hoisted(() => ({ authenticated: false, persist: vi.fn() }))
vi.mock('../features/auth/useAuth', () => ({
  useAuth: () => ({ isAuthenticated: auth.authenticated, persistLocale: auth.persist }),
}))
beforeEach(async () => {
  auth.persist.mockClear()
  await i18n.changeLanguage('en')
})
it.each([false, true])(
  'switches language and persists only for authenticated=%s',
  async (authenticated) => {
    auth.authenticated = authenticated
    const user = userEvent.setup()
    render(
      <ThemeProvider theme={createAppTheme('en')}>
        <LanguageSwitcher />
      </ThemeProvider>,
    )
    await user.click(screen.getByRole('button', { name: 'العربية' }))
    expect(i18n.language).toBe('ar')
    expect(document.documentElement).toHaveAttribute('dir', 'rtl')
    if (authenticated) expect(auth.persist).toHaveBeenCalledWith('ar')
    else expect(auth.persist).not.toHaveBeenCalled()
  },
)
