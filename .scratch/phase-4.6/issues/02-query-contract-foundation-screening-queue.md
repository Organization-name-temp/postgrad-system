# 02 — Query contract foundation + the screening queue filtered end to end

**What to build:** A Student Affairs officer opens the screening queue and, for the first time,
*works* it: narrows to one Admission Cycle and one Program, keeps only Applications waiting more
than a week, types `احمد` and finds `أحمد`, sorts by age, pages through the result, and pastes the
URL to a colleague who sees exactly the same view.

**This is the phase's tracer bullet.** It is the only ticket that cuts the whole path, and every
later list is a fill. It establishes, once and in one place:

- The **`Support` layer** — the project's first shared backend code. A new top-level namespace
  autoloaded beside `App\` and `Modules\`, declared to deptrac as its own layer with
  `*_Internal → Support` granted for every module and **zero outbound edges**. That last property
  is the whole safety argument and deptrac enforces it: a `Support` class that needs to know what a
  Program is has been written in the wrong place. It is deliberately **not** a module — it owns no
  domain language, which is the objection ADR-0024 raised against a Dashboard module.
- **Parsing and applying are separate, and this is load-bearing rather than stylistic.** The parser
  takes request input plus a declared allowlist and returns an **immutable query spec** — no
  database, no Eloquent, no framework request object beyond primitive extraction. A separate applier
  binds that spec to a builder. This is what lets the parser be tested exhaustively without a
  database, and it is the seam the unit tests attach to. If the parser touches Eloquent, the
  normalisation and operator tests quietly become slow feature tests.
- **The flat wire syntax** — `?status=a,b&age_days_min=7&q=…&sort=-age_days,name&page=2&per_page=25`.
  Comma-separated multi-values, `_min`/`_max` range suffixes, and comma-separated multi-column sort
  with a leading `-` on each descending field.
  Flat names generate readable types through Scribe → OpenAPI → Orval; bracket syntax would not.
- **The always-paginated envelope.** Never conditional — two `@response 200` shapes on one operation
  generate a TypeScript union the Orval client cannot read, and this project has hit that before.
  25 rows on work surfaces, 100 on reference endpoints, `per_page` **clamped** at 100 rather than
  rejected.
- **Bilingual free-text `q`** — case-insensitive substring across a per-resource declared field
  list spanning `_en` and `_ar`, with Arabic **normalised on both sides**: alef forms folded to a
  bare alef, `ى`→`ي`, `ة`→`ه`, combining diacritics stripped. Substring rather than full-text,
  because there is no Arabic dictionary and a tokenizer cannot match a code fragment or a partial
  National ID.
- **Filterable if and only if readable.** The allowlist is a function of the acting user wherever a
  field is permission-sensitive; a field absent from the effective allowlist is **ignored**, not
  errored. Without this, `?cgpa_min=` plus `meta.total` is an oracle for values never displayed.
- **Scope and filters compose one way.** Scope is a base constraint the controller applies and the
  client never supplies; filters narrow within it and can never widen it. An out-of-scope value
  gives an **empty page, not a 422**, so boundaries are not probeable.
- **`shared/ui` gains `<FilterBar>`, `<DataToolbar>` and pagination controls** — the query rules and
  **no domain names**, the `<StatusChip>`/`<DashboardCard>` discipline — plus a URL-state hook. They
  render into the plain MUI `<Table>` already in use; `@mui/x-data-grid` stays unadopted, because
  with everything server-side the grid is dumb.
- **The cross-module contract test** — one shared Pest dataset of `(endpoint, actor, expected
  scope)` driving four assertions, seeded here with the screening queue and grown by one row per
  endpoint thereafter. Deliberately the first backend test file that cuts across module boundaries,
  because the contract is one thing.

**Blocked by:** 01 — Seeded cohort + assertion audit.

**Status:** implemented prerequisite — final verification pending

- [x] `Support\` autoloads, has its own deptrac layer with every `*_Internal → Support` edge granted
      and **no outbound edge**; a deliberate violation fails `deptrac analyse`
- [x] The parser is a pure function of input + allowlist producing an immutable spec, unit-tested
      with **no database**
- [x] Unit tests cover multi-value parsing, `_min`/`_max` ranges, comma-separated multi-column sort
      with independent descending markers, effective allowlists, and rejection of
      unknown fields, `per_page` clamping, and every Arabic normalisation fold
- [x] Equal selected sort values fall back to the endpoint's stable unique key; adjacent-page tests
      prove ties cannot duplicate or omit rows
- [x] The screening queue filters on Application Status, Program, Admission Cycle and derived age
- [x] `q` searches the declared fields across `_en` and `_ar`; `احمد` finds `أحمد` and a partial
      National ID matches
- [x] The endpoint returns the paginated envelope with a correct total; `per_page=5000` is clamped
      to 100 rather than refused
- [x] A field outside the actor's effective allowlist is ignored, and a filter value outside the
      actor's scope returns an empty page rather than 422
- [x] `<FilterBar>`, `<DataToolbar>` and pagination live in `shared/ui` and contain no domain names
- [x] Filter, sort and page state round-trip through the URL; reload and back-button preserve the
      view, and a pasted URL reproduces it
- [x] The cross-module contract test exists with the screening queue as its first dataset row
- [x] `openapi.yaml` and the Orval client are regenerated **and committed**; both `api:check` gates
      pass
- [x] Every query parameter has an explicit Scribe `@queryParam`; every model-bound route retains
      its `@urlParam ... Example:` annotation so generated examples stay deterministic
- [x] Vitest covers `<FilterBar>` states and the URL-state round trip
- [ ] `composer gate` and `npm run gate` green

## Phase 7 prerequisite disposition

This ticket is in the authorized executable closure for Phase 7; its implementation
is on `phase-7-academic-reports-bilingual`. Existing capabilities were verified
before extending them. See [the Phase 7 tracker](../../phase-7/tracker.md) for
focused tests, runtime evidence and the fixed review baseline, and
[the consolidated review](../../phase-7/review.md) for resolved Standards/Spec
findings. Final backend gate is green; the complete frontend browser gate remains
active. Any by-hand acceptance remains pending. Other Phase 4.6 tickets are
outside this goal and are not marked complete by this disposition.
