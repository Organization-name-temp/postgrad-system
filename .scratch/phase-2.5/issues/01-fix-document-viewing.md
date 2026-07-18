# 01 — Fix document viewing (authenticated fetch + 401-JSON)

**What to build:** An applicant (and a reviewer) can open any document they are allowed to see. Today clicking a document opens a page that errors `Route [login] not defined.` — the download link is a bare browser navigation that never carries the session, so it reaches the auth guard unauthenticated and the API tries to redirect to a `login` route that does not exist in this API-only backend. Instead, the SPA fetches the document through its authenticated client and opens the returned bytes, and the API always answers auth failures with `401` JSON rather than a redirect. Per [ADR-0016](../../../docs/adr/0016-private-document-serving.md).

**Blocked by:** None — can start immediately. *(Workstream 1: lands on `phase-2-admissions` before the Phase 2 review gate. Root-cause + file references: [bugfixes.md](../bugfixes.md#b1).)*

**Status:** ready-for-agent

- [ ] The applicant document preview and both reviewer (Student Affairs + Dean) document links open the file via an authenticated request; the owner-or-reviewer authorization still holds (a non-owner, non-reviewer → 403).
- [ ] An unauthenticated request to a document — and to any auth-guarded API route — returns **401 JSON**, never a redirect to `login`; the `Route [login] not defined.` error is gone app-wide.
- [ ] Viewing works regardless of any SPA/API origin split (not reliant on a bare same-origin navigation carrying the cookie).
- [ ] Tests: unauthenticated document GET → 401 JSON; owner and reviewer succeed; stranger → 403. `composer gate` + `npm run gate` green.
