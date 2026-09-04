# RBAC as PHP enums + a static permission matrix

**Status:** accepted

E-JUST's role-based access control has an unusually **fixed** shape: the ten roles
(R01–R10) and the entire permission matrix (SRS §3.2) are set by university bylaws, not
by administrators at runtime. The only thing that changes at runtime is the **assignment**
of roles to users — and even that is restricted to System/Super Administrator (FR-SEC-002.4).
Users may hold more than one role.

We therefore model roles and permissions as **PHP 8.4 enums plus a static matrix in
code**: a `Role` enum (R01–R10), a `Permission` enum (one case per §3.2 function), and a
`Permission → Role[]` map transcribed from the matrix. Laravel `Gate`s are registered from
that map. Only role *assignment* touches the database — a `users`↔`roles` many-to-many
pivot. This ADR exists so a future reader does not "upgrade" to a database-driven
permission package expecting flexibility the domain does not want.

## Considered options

- **Enums + static matrix (chosen).** The bylaws matrix lives in one typed, diff-able,
  test-covered place; there is exactly one source of truth and it cannot drift into a
  half-edited state in the database. Enums fit the readonly/typed domain modeling the
  project already leans on ([ADR-0006](./0006-runtime-baseline-laravel-12-php-84.md)), and
  a changed permission is a reviewed code change, not a silent data edit. Cost: changing
  the matrix needs a deploy, not an admin screen — which is correct, because changing it
  *is* changing the bylaws.
- **`spatie/laravel-permission` (rejected).** Battle-tested, with ready middleware and
  gate helpers. But it models runtime-editable, database-stored roles and permissions —
  flexibility this domain explicitly does not have. The fixed matrix would end up
  duplicated as seed data, roles become DB rows instead of an exhaustive enum, and we
  inherit machinery (permission caching, teams) we never use.
- **Hybrid — spatie for storage, our enum matrix for rules (rejected).** Keeps the matrix
  in code while using spatie for assignment plumbing, but forces two overlapping mental
  models of the same concept for little gain over a plain pivot table.

## Consequences

- `Role`, `Permission`, and the matrix live in **`Identity/Public`**: `GET /api/v1/me`
  returns a user's effective permissions and both the API policies and the SPA guards
  consume them, so they legitimately cross the module boundary.
- The SPA mirrors permission keys for display only; the API is the sole authority and
  re-checks every request. The matrix is never shipped as authority to the client.
- A user's effective permissions are the **union** across their assigned roles; the
  multi-role path is exercised from Phase 0 by a seeded multi-role user.
- A narrowly routed workflow stage whose authority is inseparable from a fixed Role and
  ownership scope need not manufacture a new Permission. Phase 5's full Must-Retake
  capacity exception is gated directly on Department Head plus Course-department scope
  ([ADR-0042](0042-must-retake-courses-have-registration-priority.md)); it neither broadens
  `registration.approve` nor exposes the ordinary registration queue.
- If the project ever needs genuinely custom, per-user or per-tenant permissions, spatie
  (or a DB matrix) can be introduced then — callers depend on the `Permission` enum and
  the `me` contract, not on how the matrix is stored.
