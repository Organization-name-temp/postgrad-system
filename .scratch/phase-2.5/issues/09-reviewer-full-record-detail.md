# 09 — Reviewer full-record detail

**What to build:** When Student Affairs or a Dean opens an application, they see the **complete** applicant record — identity, contact, address, full academic background, English proficiency, program, documents, and the status timeline — grouped into readable sections, instead of the current six-field summary. The API already returns the full payload; this is a display gap.

**Blocked by:** Phase 2 review gate merged (tickets 01–04). *(Workstream 2; no enhancement-ticket blocker — frontier once the gate merges.)*

**Status:** ready-for-agent

- [ ] The Student Affairs and Dean application-detail screens render every field of the application record, grouped into sections.
- [ ] Documents remain viewable (via the ticket-01 authenticated fetch) and the status timeline is still shown.
- [ ] Vitest covers that the detail screen renders the full field set. Gates green.
