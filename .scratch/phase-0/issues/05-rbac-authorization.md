# 05 — RBAC authorization: permission matrix, gates, guarded widget

**What to build:** The system grants each user exactly the actions the SRS §3.2 matrix assigns their role(s), enforced on the API and mirrored in the UI. A signed-in user sees their roles and permissions, and a demo widget that appears only when they hold the required permission. This is **authorization**. See [ADR-0008](../../../docs/adr/0008-rbac-enums-static-matrix.md).

**Blocked by:** 04.

**Status:** ready-for-agent

- [ ] A `Permission` enum and a static `Permission → Role[]` matrix transcribed from SRS §3.2 live in `Identity/Public`; R09/R10 hold all permissions.
- [ ] Laravel Gates are registered from the matrix; a user's effective permissions are the union across their assigned roles.
- [ ] `GET /api/v1/me` includes the effective `permissions` array.
- [ ] A sample protected endpoint returns 200 for a role that holds the permission and 403 for one that does not.
- [ ] The SPA route guard keys off the permission keys from `/me`; a permission-gated demo widget renders only when the user holds the chosen permission.
- [ ] The dashboard displays the signed-in identity, held roles, and effective permissions.
- [ ] A Pest unit test asserts, for each Role, its exact Permission set from the matrix; feature tests cover the allow and the deny (403) paths.
- [ ] A Vitest + RTL test covers the route/permission guard: given a permission set, it renders the child vs. redirects.
