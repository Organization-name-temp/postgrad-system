# Client-side validation mirrors authoritative server rules (React Hook Form + Zod)

**Status:** accepted

Phase 2 shipped the applicant forms with no client-side validation — plain `useState`,
`noValidate`, errors surfaced only as server `422`s after submit. Phase 2.5 hardens this:
forms use **React Hook Form** with a **hand-authored Zod schema per form**, validating
**on blur**, so an applicant learns what is wrong at the field, the moment they leave it.
The server's validation rules remain the **single authoritative gate** — the client Zod
schema is a deliberate, duplicated *mirror* for UX only, never the authority.

## Considered options

- **RHF + hand-authored Zod, server authoritative (chosen).** The rich rules Phase 2.5
  introduces — formats (14-digit National ID, E.164 phone) and cross-field checks
  (GPA ≤ scale ceiling, English score within the selected test's range) — are expressed
  clearly on both sides. The duplication is accepted; drift is caught by the server tests,
  which are the source of truth.
- **Derive client rules from the OpenAPI-generated Zod (rejected).** Orval already emits a
  Zod module from the committed `openapi.yaml`, but Scribe cannot express these format /
  cross-field rules, so the generated schema would still need hand refinements — maintaining
  both anyway, behind a false sense of a single source.
- **Server-only, just surface errors better on blur (rejected).** Avoids a form library,
  but re-implements rule logic ad hoc per field, yielding inconsistent, brittle validation.

## Consequences

- New SPA form stack: **React Hook Form** + **Zod** (Zod already present transitively via
  Orval) — adopted for the whole app, not just admissions.
- Validation logic lives in two languages (PHP rules + TS Zod) by design. If the two ever
  diverge, the failure mode is a redundant server `422`, never accepting bad data.
- Sets the pattern every future form (Phase 3+) follows.
