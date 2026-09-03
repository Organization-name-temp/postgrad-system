# Export is a sibling route that renders display strings, authorized by its list's own gate and always audited

**Status:** accepted — records an explicit exception to [ADR-0024](0024-permission-composed-dashboard-cards.md)'s typed-values-only rule

Once every list is filterable ([ADR-0025](0025-support-layer-query-contract.md)), the next thing
anyone wants is the filtered result as a spreadsheet. Three questions follow that are each easy to
answer badly.

## Where it hangs

**`GET /{resource}/export` sits beside `GET /{resource}` and takes the identical query string**,
parsed once by the same `Support` code, so the file and the grid above it cannot disagree about
what was filtered. The obvious alternative — `?format=xlsx` on the list route itself — was rejected
because a route returning JSON *or* a binary body puts two `@response 200` shapes on one operation,
generating a TypeScript union the Orval client cannot read. This project has already been bitten by
exactly that; it is a known trap, not a hypothetical one.

Export is **uncapped and streamed**, which is what makes ADR-0025's 100-row `per_page` cap
reasonable: "give me everything" has a correct answer, and it is this route rather than an abusive
page size.

## What it produces

**A true `.xlsx`, written by `openspout/openspout`** — MIT, a streaming writer at constant memory,
and a small enough API to wrap in one `Support` class. The decisive argument against CSV is
domain-specific rather than aesthetic: **National ID is a 14-digit number**, and Excel renders a
14-digit CSV value as `2.9001E+13` and destroys it on save. Nothing in the file can prevent that,
because CSV carries no type. An `.xlsx` cell written as a string stays a string. University IDs
(`2026-CSE-0001`) and Course codes have the same exposure.

`maatwebsite/excel` was rejected as the heavier route to the same place: it pulls all of
PhpSpreadsheet for features this build does not use (imports beyond the one catalog case, styling,
queued exports), at materially higher memory.

## What is in it, and in what language

**A fixed, documented canonical column set per resource — richer than the grid — server-rendered in
the acting user's locale**, using the `Accept-Language` middleware and backend translation files
that have existed since Phase 0. Arabic exports set the sheet direction to RTL.

**This is a deliberate exception to ADR-0024**, which requires that payloads carry "typed domain
values only — never pre-rendered display strings — because the SPA renders bilingually from the
data". That rule is correct everywhere it applies, and it does not apply here for one reason: **a
spreadsheet has no renderer.** There is no client downstream of an `.xlsx` file to turn
`under_sa_review` into "Under SA Review" or "قيد مراجعة شؤون الطلاب". The export endpoint is the
only place in this API where the server renders text, and it is the only place with no client.

The column set is **canonical, not the grid's current columns**. The client is never the authority
on what an export contains — which is also what keeps the column chooser honest: choosing columns
changes the screen and never the file, so a shared export cannot silently omit a column its
recipient expected.

## What authorizes it

**An export is authorized by exactly the `can:` gate and row-level scope of the list it mirrors**,
and adds no new Permission. It reveals nothing the user could not already read on screen, so
inventing an export Permission would edit a matrix [ADR-0008](0008-rbac-enums-static-matrix.md)
calls university bylaws in order to restrict something already permitted. This is the **Central
Grade Entry** reasoning applied a second time: the plain reading of the §3.2 matrix rather than a
new power.

**What is new is that the data leaves.** So every export writes an **Audit Log** entry recording
the actor, the resource, the filter criteria applied, and the row count. That is the fact worth
retaining: not that someone read a queue, but that a spreadsheet of applicants' names, National IDs,
dates of birth, addresses and GPAs now exists outside the system. It also extends the Audit Log
from authentication events toward the "sensitive-data CRUD later" [CONTEXT.md](../../CONTEXT.md)
anticipated — the first non-auth category it carries.

Export is therefore a **List capability**, not a separate Report capability. Every filterable
collection receives a sibling export unless its endpoint ticket records a specific data-minimization
reason for exclusion and proves that no route exists. Reports export through drill-through to the
corresponding filtered List.

The SPA requests the sibling route through the authenticated Axios client, receives an in-memory
blob, and initiates the browser download from that blob. A bare link is forbidden because it bypasses
the configured client behavior and the private-download pattern in ADR-0016.

## Consequences

- **The Audit Log becomes the record of PII egress**, and the Audit Log Report
  ([ADR-0026](0026-reports-owned-by-modules.md)) is where it is read. Exports of the Audit Log are
  themselves audited; that recursion is intended and terminates, because the entry is written after
  the query is authorized.
- **Adding an export is a column map plus one audit call.** The route, query parsing, streaming and
  authorization all come from shared code; a module supplies only what its columns are and how each
  renders.
- **The canonical column set is versioned by review**, not by the client. Widening it is a decision
  someone makes deliberately, which is the point.
- One `.xlsx` is read back end-to-end with openspout's reader in the test suite — headers, a typed
  National ID cell, and RTL on the Arabic sheet — and the remaining resources assert their row
  generators only, so the cost stays linear in resources rather than quadratic.
- Because export is synchronous, a very large filtered set holds a request open. Accepted:
  NFR-PRF-008 is an informal target under [ADR-0005](0005-localhost-learning-build.md), and there is
  no queue worker in this build to hand it to.
