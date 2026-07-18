import { isAxiosError } from 'axios'

interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
}

/**
 * Extracts the server's human-readable message from a failed API response.
 * The API uses Laravel's native `{message, errors}` error envelope; the message
 * is already localized by the server per the request's Accept-Language.
 */
export function apiErrorMessage(error: unknown): string | undefined {
  if (isAxiosError(error)) {
    const body = error.response?.data as ApiErrorBody | undefined
    return body?.message
  }
  return undefined
}

/**
 * Per-field validation errors from a 422 response, keyed by field name. Used to
 * render inline messages beside each input; the strings are already localized by
 * the server per Accept-Language, so a single-field first message reads in the
 * applicant's language. Returns an empty object for non-422 / non-Axios errors.
 */
export function apiFieldErrors(error: unknown): Record<string, string[]> {
  if (isAxiosError(error)) {
    const body = error.response?.data as ApiErrorBody | undefined
    return body?.errors ?? {}
  }
  return {}
}
