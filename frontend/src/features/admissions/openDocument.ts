import { customInstance } from '../../shared/api/http'

/**
 * Open a private application document in a new tab.
 *
 * The document is fetched through the authenticated API client (the shared axios
 * instance carries the Sanctum session cookie + XHR headers) as a blob and shown via
 * an object URL — never linked to directly. A bare browser navigation to the download
 * URL carries no auth context and non-JSON headers, so it is refused (and previously
 * hit the missing `login` route). Fetching keeps the owner/reviewer authorization
 * intact and works regardless of how the SPA and API origins are deployed (ADR-0016).
 *
 * Only the URL's *path* is requested (same-origin, through the client), not the
 * absolute form the server emitted.
 */
export async function openDocument(downloadUrl: string): Promise<void> {
  const path = new URL(downloadUrl, window.location.origin).pathname
  const blob = await customInstance<Blob>({ url: path, method: 'GET', responseType: 'blob' })

  const objectUrl = URL.createObjectURL(blob)
  window.open(objectUrl, '_blank', 'noopener,noreferrer')
  // Release the object URL once the new tab has had time to load it.
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
}
