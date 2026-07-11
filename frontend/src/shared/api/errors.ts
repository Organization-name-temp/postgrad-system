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
