# 03 — Export foundation + the screening queue exported

**What to build:** The Student Affairs officer who just narrowed the screening queue clicks Export
and gets an `.xlsx` containing **exactly the rows they were looking at** — every one of them, not
the page they were on. They open it in Excel and the 14-digit National ID is still fourteen digits.
An Arabic-language user gets the same file with Arabic headers and the sheet reading right to left.
A System Administrator can later see that the export happened, who ran it, over what, with which
filters, and how many rows left the system.

This establishes the export half of the platform, and every later export is a column map.

- **`GET /{resource}/export` sits beside `GET /{resource}`** and takes the **identical query
  string**, parsed by the same `Support` code, so the file and the grid cannot disagree about what
  was filtered. The alternative — `?format=xlsx` on the list route — is rejected: a route returning
  JSON *or* a binary body puts two `@response 200` shapes on one operation and generates a
  TypeScript union Orval cannot read.
- **Export is uncapped and streamed.** That is what makes ticket 02's 100-row page cap correct
  rather than merely tolerable: "give me everything" has a real answer, and it is this route.
- **True `.xlsx` via `openspout`**, wrapped in one `Support` writer taking a column definition and a
  row generator, streaming at constant memory. CSV is rejected on a domain ground: CSV carries no
  type, so Excel renders a 14-digit National ID in scientific notation and destroys it on save.
  Cells are written with explicit types so identifiers stay text.
- **A canonical column set per resource, server-rendered in the acting user's locale**, using the
  `Accept-Language` middleware and backend translation files that have existed since Phase 0;
  Arabic exports set the sheet to RTL. This is the **one recorded exception** to ADR-0024's
  typed-values-only rule, and the reason is narrow and worth stating in the code: a spreadsheet has
  no renderer. The column set is canonical and **not** the grid's columns — the client is never the
  authority on what an export contains.
- **Authorized by the mirrored list's own `can:` gate and row-level scope**, adding no Permission.
  It reveals nothing the user could not already read, so this is the plain reading of the §3.2
  matrix rather than a new power — the Central Grade Entry precedent.
- **Every export writes an Audit Log entry** carrying actor, resource, filter criteria and row
  count. That is the genuinely new fact: not that a queue was read, but that a spreadsheet of
  names, National IDs, dates of birth, addresses and GPAs now exists outside the system. It also
  extends the Audit Log from authentication events into the sensitive-data category CONTEXT.md
  anticipated.
- **`<DataToolbar>` gains the export control.** It derives `/export` plus the current query string,
  requests it through the authenticated Axios client, and downloads the returned in-memory blob.
  A bare link is forbidden because it bypasses the configured private API client.

**Blocked by:** 02 — Query contract foundation + the screening queue filtered end to end.

**Status:** ready-for-human

- [x] `GET /applications/export` accepts the identical query string as the list and returns a
      streamed `.xlsx`
- [x] The export contains every matching row, not one page, and ignores `per_page` entirely
- [x] A 14-digit National ID and a University ID (`2026-CSE-0001`) survive as text cells and are
      not coerced to numbers or dates
- [x] Headers and enum values render in the requester's locale; an Arabic request produces an RTL
      sheet
- [x] The column set is defined server-side per resource and is unaffected by anything the client
      sends
- [x] Each module supplies a separate actor-aware export-column projection, not the filter/sort
      allowlist; it may retain readable-but-unfilterable fields, while a user who may list rows
      cannot export a hidden National ID, CGPA, Mark, or equivalent field
- [x] The route is refused with 403 to a user who cannot open the list it mirrors, and its rows are
      scoped identically
- [x] Every successful export writes one Audit Log entry with actor, resource, filter criteria and
      row count
- [x] One export is verified **end to end** by reading the produced file back with openspout's
      reader — headers, a typed National-ID cell, and RTL on the Arabic sheet
- [x] The export control derives its request from the current query string, uses authenticated Axios,
      downloads an in-memory blob, and is not rendered as a bare export link
- [x] Scribe documents a single binary response; `openapi.yaml` and the Orval client are
      regenerated and committed
- [x] `composer gate` and `npm run gate` green

## Phase 7 prerequisite disposition

This ticket is in the authorized executable closure for Phase 7; its implementation
is on `phase-7-academic-reports-bilingual`. Existing capabilities were verified
before extending them. See [the Phase 7 tracker](../../phase-7/tracker.md) for
focused tests, runtime evidence and the fixed review baseline, and
[the consolidated review](../../phase-7/review.md) for resolved Standards/Spec
findings. Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend 684 unit tests and 179 serial browser checks. The additional CI timing
regression and two affected workflows passed (3/3, 12.4m); see tracker for the
180-test discovery versus 179-test full-gate distinction. Any by-hand acceptance remains pending. Other Phase 4.6 tickets are
outside this goal and are not marked complete by this disposition.
