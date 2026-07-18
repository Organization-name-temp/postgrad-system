# 11 — Navigation overhaul (persona gating + area grouping)

**What to build:** The navigation is correct per role and grouped for clarity. "My application" (and the `/apply` route) show only to actual Applicants (Role R01), not to admins who merely inherit the permission — so an admin's nav is the administration / oversight set, not every role's home screen. And nav items are grouped into labelled sections by area (Admissions / Catalog / Calendar / Administration), each shown only when the signed-in user has items under it. Per [ADR-0015](../../../docs/adr/0015-administrative-override-of-scope-guards.md).

**Blocked by:** Phase 2 review gate merged (tickets 01–04). *(Workstream 2; no enhancement-ticket blocker — frontier once the gate merges.)*

**Status:** ready-for-agent

- [ ] Persona items ("My application", "My interviews") gate on the actual Role; the `/apply` route is guarded to R01. Capability items stay permission-gated.
- [ ] An admin (R09/R10) no longer sees "My application"; an Applicant does; a reviewer sees their capability items.
- [ ] Nav items are grouped under area subheaders; empty sections do not render.
- [ ] Vitest covers persona gating (admin vs applicant) and grouped rendering. Gates green.
