# 03 — OpenAPI → typed client pipeline

**What to build:** The frontend consumes a typed API client generated from the backend's OpenAPI spec, and the pipeline guarantees the committed client can never silently drift from the server. Sequenced before the auth work so the auth/RBAC slices consume generated hooks from the start.

**Blocked by:** 02.

**Status:** ready-for-agent

- [ ] Scribe generates an OpenAPI 3.0 `openapi.json` from the `/api/v1` routes.
- [ ] Orval generates a TanStack Query + Axios + Zod client from `openapi.json`.
- [ ] Both `openapi.json` and the generated client are committed to the repo.
- [ ] A single command regenerates both; running it on a clean checkout produces no diff.
- [ ] The backend gate fails when `openapi.json` is out of date; the frontend gate fails when the generated client differs from what is committed.
- [ ] The SPA's health request goes through the generated hook — no hand-written request/response types remain for it.
