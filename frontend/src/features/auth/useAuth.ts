import { useQueryClient } from '@tanstack/react-query'
import {
  useCurrentUser,
  useLogIn,
  useLogOut,
  useRegisterAndStartAnApplication,
  useUpdateCurrentUser,
  getCurrentUserQueryKey,
} from '../../shared/api/generated/endpoints'
import type {
  CurrentUser200Data,
  RegisterAndStartAnApplicationBody,
} from '../../shared/api/generated/endpoints'
import { usePermissions } from '../../shared/access/usePermissions'
import { fetchCsrfCookie } from './csrf'

export interface UseAuth {
  /** The signed-in user, or undefined when unauthenticated / still loading. */
  user: CurrentUser200Data | undefined
  /** True while the initial `/me` probe is in flight (guard shows a spinner). */
  isPending: boolean
  /** The server is the sole authority; this mirrors `/me` for UX routing only. */
  isAuthenticated: boolean
  /** Effective permission keys from `/me` (SRS §3.2), mirrored for UX gating. */
  permissions: string[]
  /** Whether the signed-in user holds a given permission key. */
  hasPermission: (permission: string) => boolean
  isLoggingIn: boolean
  loginError: unknown
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  isRegistering: boolean
  registerError: unknown
  /**
   * Self-register and start an application. Like login it establishes a Sanctum
   * session server-side, so it primes CSRF then re-probes `/me` on success.
   */
  register: (body: RegisterAndStartAnApplicationBody) => Promise<void>
  /** Persist the UI language to the signed-in user's `preferred_locale`. */
  persistLocale: (locale: string) => Promise<void>
}

/**
 * The SPA's authentication surface. `/me` (via TanStack Query) is the single
 * source of truth for who is signed in; login/logout mutate the Sanctum session
 * and then invalidate that query so every consumer re-derives its state.
 */
export function useAuth(): UseAuth {
  const queryClient = useQueryClient()
  const me = useCurrentUser()
  const loginMutation = useLogIn()
  const logoutMutation = useLogOut()
  const registerMutation = useRegisterAndStartAnApplication()
  const updateLocaleMutation = useUpdateCurrentUser()

  const login = async (email: string, password: string): Promise<void> => {
    await fetchCsrfCookie()
    await loginMutation.mutateAsync({ data: { email, password } })
    await queryClient.invalidateQueries({ queryKey: getCurrentUserQueryKey() })
  }

  const register = async (body: RegisterAndStartAnApplicationBody): Promise<void> => {
    await fetchCsrfCookie()
    await registerMutation.mutateAsync({ data: body })
    await queryClient.invalidateQueries({ queryKey: getCurrentUserQueryKey() })
  }

  const logout = async (): Promise<void> => {
    await logoutMutation.mutateAsync()
    queryClient.setQueryData(getCurrentUserQueryKey(), undefined)
    await queryClient.invalidateQueries({ queryKey: getCurrentUserQueryKey() })
  }

  const isAuthenticated = !me.isError && Boolean(me.data?.data)

  const persistLocale = async (locale: string): Promise<void> => {
    if (!isAuthenticated) {
      return
    }
    await updateLocaleMutation.mutateAsync({ data: { preferred_locale: locale } })
    await queryClient.invalidateQueries({ queryKey: getCurrentUserQueryKey() })
  }

  // Permission reading is cross-cutting, so it lives in shared/access; useAuth
  // delegates rather than keeping its own copy (one source of the rule).
  const { permissions, hasPermission } = usePermissions()

  return {
    user: me.data?.data,
    isPending: me.isPending,
    isAuthenticated,
    permissions,
    hasPermission,
    isLoggingIn: loginMutation.isPending,
    loginError: loginMutation.error,
    login,
    logout,
    isRegistering: registerMutation.isPending,
    registerError: registerMutation.error,
    register,
    persistLocale,
  }
}
