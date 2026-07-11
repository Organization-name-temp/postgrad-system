# 04 — Login/logout with seeded role users + audit log

**What to build:** Any of the ten seeded roles can log in with email + password, land on an authenticated page, and log out; unauthenticated visitors are redirected to login; and every login, logout, and failed attempt is recorded in an append-only audit log. This ticket is **authentication** only — permission enforcement comes in 05.

**Blocked by:** 03.

**Status:** ready-for-agent

- [ ] `users`, `roles`, and a `users`↔`roles` pivot exist; a `Role` enum defines R01–R10.
- [ ] The seeder creates one user per role (`r01@ejust.test` … `r10@ejust.test`) with a shared dev password, plus one user holding multiple roles.
- [ ] After the CSRF cookie is fetched, `POST /api/v1/login` authenticates via Sanctum cookie session; `POST /api/v1/logout` ends the session.
- [ ] Wrong credentials return a clear error (401/422) and establish no session.
- [ ] `GET /api/v1/me` returns the authenticated user plus their roles, and returns 401 when unauthenticated.
- [ ] The SPA login page authenticates and lands on an authenticated page; logout returns to login; visiting a guarded route while unauthenticated redirects to login.
- [ ] An append-only `audit_log` (no update or delete path) records `login`, `logout`, and `login_failed` through the `AuditLogger` in `Identity/Public`; a failed login writes a row but triggers no lockout.
- [ ] Pest feature tests cover login success, logout, failed login, the `/me` shape, and that each auth event writes the expected audit row.
