# 05 — React Hook Form + Zod on-blur validation foundation

**What to build:** The applicant wizard validates each field the moment focus leaves it, showing a clear inline message, rather than only surfacing a bulk `422` after submit. This migrates the personal-info step from `useState`/`noValidate` to React Hook Form + a Zod schema that mirrors the *current* server rules (no rule changes yet) — establishing the validation pattern for the whole SPA. Per [ADR-0014](../../../docs/adr/0014-client-validation-mirrors-server-rules.md).

**Blocked by:** Phase 2 review gate merged (tickets 01–04). *(Workstream 2: first ticket on the `phase-2.5` branch off `main`; establishes the form stack tickets 06–08 build on.)*

**Status:** ready-for-agent

- [ ] The personal-info step uses React Hook Form with a Zod resolver; fields validate on blur, and re-validate on change once a field has errored.
- [ ] Each field shows its own inline error; the server rules stay the authoritative gate and still surface inline on `422`.
- [ ] Existing behaviour preserved: per-step save / resume still works.
- [ ] React Hook Form + Zod added to the SPA. Vitest covers on-blur error surfacing for a representative field. Gates green.
