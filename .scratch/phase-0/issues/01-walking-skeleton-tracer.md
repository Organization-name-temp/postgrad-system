# 01 — Walking-skeleton tracer: monorepo, dev env, one end-to-end request

**What to build:** A developer can clone the repo, start Postgres, run the API and the SPA, and see a page that displays data fetched from the backend over `/api/v1` — proving the monorepo layout, both dev servers, the same-origin proxy, database connectivity, and the response conventions all work end-to-end. This is the tracer bullet the rest of Phase 0 is layered onto.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] `backend/` runs Laravel 12 on PHP 8.4; `frontend/` runs a React + TypeScript + Vite + MUI app; both live in the monorepo beside `docs/`.
- [ ] `docker compose up` starts PostgreSQL only, and the backend connects to it.
- [ ] The Vite dev server proxies `/api` and `/sanctum/csrf-cookie` to the backend, so the browser talks to a single origin.
- [ ] `GET /api/v1/health` returns 200 with a body in the `{ "data": { … } }` envelope.
- [ ] The SPA fetches `/api/v1/health` and renders its status on screen.
- [ ] Error responses follow `{ message, errors }` with correct status codes — an unknown `/api/v1` route returns a 404 in that shape.
- [ ] Pest, Vitest, and Playwright are installed and runnable.
- [ ] A Pest feature test asserts `GET /api/v1/health` returns the `{data}` envelope; a Playwright smoke loads the page and sees the rendered status.
