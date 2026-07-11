import { http } from '../../shared/api/http'

// Sanctum SPA flow: before the first state-changing request (login), prime the
// XSRF-TOKEN cookie. Axios then echoes it back as the X-XSRF-TOKEN header on
// subsequent same-origin requests automatically, satisfying CSRF protection.
export function fetchCsrfCookie(): Promise<unknown> {
  return http.get('/sanctum/csrf-cookie')
}
