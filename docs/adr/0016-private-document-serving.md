# Private documents are served through the authenticated client; API auth failures always return JSON

**Status:** accepted

Phase 2 emitted each uploaded document's `download_url` as an absolute API URL and opened
it with a **bare browser navigation** (`window.open` / `<a href>`). That navigation bypasses
the SPA's authenticated Axios layer, so it reaches the `auth:sanctum` route unauthenticated
and non-JSON, and Laravel's default handler tries to redirect to a `login` route this
API-only backend does not have — the observed **`Route [login] not defined.`** Phase 2.5
fixes it two ways: (1) the SPA **fetches the document through its authenticated client and
opens the bytes as an in-memory blob**, so the owner-or-reviewer authorization is always
enforced and it works no matter how the SPA and API origins are deployed; and (2) the API's
unauthenticated handler **always renders `401` JSON**, never a redirect.

## Considered options

- **Authenticated fetch → blob (chosen).** Origin-independent, keeps per-document
  owner/reviewer authorization intact, and sets a clean pattern for all future private-file
  serving. Cost: the whole file loads into memory before display — acceptable for these
  small scans.
- **Relative same-origin URL (rejected).** A plain click would carry the session cookie via
  the dev proxy, but silently breaks again the moment the SPA and API are served from
  different origins.
- **Short-lived signed URL (rejected).** Works as a bare click anywhere, but is a
  *capability* URL — anyone holding it can fetch the document while valid — a poor fit for
  national IDs and transcripts.

## Consequences

- The `401`-JSON fix is **app-wide**: every auth-guarded route now fails cleanly (`401`)
  instead of throwing on the missing `login` route — closing a latent landmine across the
  whole API, not just document downloads.
- All private-file downloads (Phase 3+ transcripts, official documents) follow the
  fetch-as-blob pattern rather than linking directly.
