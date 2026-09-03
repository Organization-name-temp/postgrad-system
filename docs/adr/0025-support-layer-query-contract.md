# A shared `Support` layer carries the query contract; every collection is paginated, and a field is filterable only if it is readable

**Status:** accepted

Until Phase 4.6 this codebase had **no shared backend code at all**, and that was a property
rather than an accident: [deptrac.yaml](../../backend/deptrac.yaml) grants no module access to
`App\`, every `Internal` is sealed, and everything crossing a seam is a DTO through a `Public`
facade ([ADR-0002](0002-modular-monolith-seams.md),
[ADR-0012](0012-cross-module-reads-by-id-and-dto.md)). Phase 4.6 breaks that property on purpose,
because it needs **one** filter/sort/paginate/search parser used by roughly twenty-five endpoints
across seven modules, and that parser is security-relevant (see the allowlist rule below). Seven
hand-copied versions of a security-relevant parser is the worst available outcome.

**Decision: a `Support\` tree under `backend/support/`, declared to deptrac as its own layer that
may depend on *nothing*, with `*_Internal → Support` granted for every module.** It is the backend
mirror of the frontend's `shared/ui`: it encodes the **query rules and no domain names**, exactly
as `<StatusChip>` encodes the design rules and no status names
([design-system.md](../design-system.md)) and `<DashboardCard>` encodes the Card rules and no Card
names ([ADR-0024](0024-permission-composed-dashboard-cards.md)).

**It is deliberately not a module.** ADR-0024 refused to create a `Dashboard` module because a
view "is a *view*, not a bounded context, and it owns no domain language"; a query parser owns no
domain language either, and the same objection would apply. `Support` is therefore a **layer
beneath** the modules rather than one beside them, and the property that makes that safe is
enforced rather than agreed: it has **zero outbound edges**. It cannot reference `App\`, it cannot
reference any module, and deptrac fails the build if it tries. A `Support` class that needs to know
what a Program is has been written in the wrong place.

## The contract

- **Flat query parameters**, not bracketed or nested:
  `?status=under_sa_review,sa_approved&cgpa_min=3&q=أحمد&sort=-cgpa,name&page=2&per_page=25`.
  Multi-value filters and multi-column sort are comma-separated; each descending sort field has a
  leading `-`. Flat names generate clean, readable types through Scribe → OpenAPI → Orval; bracket syntax
  generates `deepObject` parameters and awkward TypeScript for no gain here.
- **Every effective sort is total and deterministic.** After validated user-selected fields, the
  endpoint appends its stable unique key (normally `id`) unless already present. SQL and derived
  in-memory sorting use the identical sequence, so ties cannot duplicate or omit rows between pages.
- **Every collection endpoint is always paginated** — `{data, links, meta}`, unconditionally. A
  conditional envelope (plain array without `?page=`, paginated with it) would put two response
  shapes on one operation, which is precisely the failure this project already hit once: two
  `@response 200` scenarios of different shapes generate a TypeScript union the Orval client cannot
  read. Work surfaces default to 25 rows, reference and picker endpoints declare 100, and
  `per_page` is **hard-capped at 100**.
- **"Give me every row" is answered by `/export`**, never by a large `per_page`
  ([ADR-0027](0027-export-sibling-route-audited.md)). The cap is what makes export the correct tool
  rather than a convenience, and it is why the cap is acceptable.
- **Free-text `q`** is a case-insensitive substring match across a per-resource **declared field
  list** covering both the `_en` and `_ar` columns, with Arabic **normalised on both sides** — alef
  forms folded to `ا`, `ى` to `ي`, `ة` to `ه`, diacritics stripped — so a user typing `احمد` finds
  `أحمد`. Substring matching is chosen over Postgres full-text deliberately: there is no Arabic
  dictionary, so full-text buys Arabic no stemming, and its tokenizer cannot match `CSE5` against
  `CSE511` or a partial National ID, which is most of what staff actually type. Ranking and
  stemming are not offered. `pg_trgm` indexes are recorded as **the later optimization**, in the
  same sense ROADMAP §1 records Redis caching as one.

## Filterable if and only if readable

**A field is filterable and sortable only if the acting user is authorized to read it.** This is
not defensive tidiness; without it, filtering is an inference channel. An Instructor who may list
their own roster but not read CGPAs can send `?cgpa_min=3.5` and read `meta.total` — one binary
search per student recovers a CGPA the column never displayed. Filtering on a field is, for
disclosure purposes, **equivalent to reading it**.

So an endpoint's allowlist is a small function of the actor, not a static array, wherever a field
is permission-sensitive (CGPA, National ID, Mark, later Academic Standing). Non-sensitive fields
stay static. A known but actor-hidden field absent from the effective allowlist is **ignored**, never
honoured, so its presence cannot reveal the actor's access. An unknown field is rejected as a bad
contract request.

**Row-level scope and filters are different things and compose in one direction only.** Scope — the
Dean's Faculty, the Advisor's advisees, the Department Head's Department, the Instructor's
Offerings — is a **base query constraint the client never supplies**. Filters narrow *within* it
and can never widen it. A Dean filtering on another Faculty therefore gets an empty page rather
than a 422: an error would make the scope boundary probeable, and the empty page is the truthful
answer to "your applications in that Faculty".

## Considered options

- **A `Support` layer (chosen).** One parser, one allowlist mechanism, one Arabic normalisation,
  proven exhaustively once. Costs the codebase its zero-shared-backend-code property and adds a
  deptrac layer with seven inbound and zero outbound edges.
- **`spatie/laravel-query-builder` (rejected).** A vendor namespace is invisible to deptrac, so it
  would need no layer and no edge at all, and its allowlist API is battle-tested. Rejected because
  it imposes JSON:API syntax (`filter[status]`, `page[size]`) that clashes with this API's flat
  `{data, meta}` conventions, and because range filters, derived-value filters and the actor-aware
  allowlist all need custom filter classes — so the interesting and security-relevant code gets
  written by hand regardless, just inside someone else's abstraction.
- **A `Modules\Shared` module (rejected).** Would need seven new deptrac edges and would create a
  module owning no domain language — the exact objection ADR-0024 raised against a Dashboard
  module, and it would be inconsistent to accept it here having refused it there.
- **Per-module duplication, convention only (rejected).** Most faithful to the existing
  no-shared-code property and nothing new for deptrac to govern. Rejected because the duplicated
  code includes the allowlist rule above: seven copies means seven chances to omit the actor-aware
  check, and the failure is silent.
- **A `POST /search` DSL (rejected).** A boolean expression tree would be genuinely more
  expressive. Rejected because it is unbookmarkable — which the drill-through model depends on
  ([ADR-0026](0026-reports-owned-by-modules.md)) — hostile to Scribe and Orval typing, and far
  beyond what a screening queue needs.

## Consequences

- **The response envelope changes on every collection endpoint.** That is a breaking change
  touching ~25 Pest feature tests, the list components, `openapi.yaml` and the generated Orval
  client — and both `composer api:check` and `npm run api:check` are `git diff --exit-code` gates,
  so the regeneration is not optional.
- Every filter, search, sort, and pagination input is documented with `@queryParam`; model-bound
  routes retain explicit `@urlParam ... Example:` annotations so Scribe examples and the generated
  OpenAPI/Orval contract remain deterministic.
- **Testing is contract-once plus a shared dataset.** Operators, sorting, paging and Arabic
  normalisation are proven exhaustively as `Support` unit tests. A shared Pest dataset of
  `(endpoint, actor, expected scope)` then drives four assertions across every filterable endpoint —
  unknown fields rejected, scope composes with filter, actor-aware fields stay hidden, `meta` is
  correct — so adding an endpoint is one dataset row rather than a new test file.
- **`Support` must stay domain-free.** Its zero outbound edges are the enforcement. Anything
  needing a Program, a Semester or a Permission belongs in the module that owns it; `Support` is
  handed the allowlist, it never derives one.
- **A future `Support → Modules` or `Support → App` edge is a regression of this decision**, not a
  convenience — the same standing this project gave the absent `Enrollment_Internal →
  Grading_Public` edge in [ADR-0022](0022-grade-outcome-pushed-to-enrollment.md).
- Search performance rests on unindexed normalised expressions. That is accepted at this build's
  scale, and NFR-PRF-010 (search ≤ 1s) is an informal target here rather than a gate, per
  [ADR-0005](0005-localhost-learning-build.md).
