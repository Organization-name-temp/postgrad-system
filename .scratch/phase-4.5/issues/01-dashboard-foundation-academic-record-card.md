# 01 — Dashboard foundation + My Academic Record Card

**What to build:** A Student signs in and lands on a real dashboard. Above the fold is a
slim identity header — their name, their roles, and their University ID — and below it a
single Card showing their **CGPA and latest Semester GPA as numbers**, linking into My
Grades. The wall of raw permission chips that has stood on the landing page since Phase 0
is gone.

This is the phase's tracer bullet: it is the only ticket that cuts the entire path, and
every Card after it is a small fill. It establishes, in one place and once:

- the shared **`<DashboardCard>`** presentation component — title, count slot, rows slot,
  empty-state slot, error state, action link. It encodes the design *rules* and **no Card
  names**, exactly as `<StatusChip>` does. Every later Card inherits its empty state, so
  "nothing outstanding" reads as *"you're all caught up"* and never as a bare `0`.
- the landing page **moved out of the auth feature and into the app shell**. This is not
  cosmetic: `eslint-plugin-boundaries` forbids a feature importing another feature, so a
  page that composes Cards from many features cannot live in one. The route stays `/`.
- the **declarative permission-to-Card registry**, sitting beside the existing nav table and
  carrying, per Card, its key, its gate, and its component. It is *data, not logic* — there
  is deliberately no selection function, because the table is the specification.
- the **three gate kinds** the registry understands, all three exercised by the end of the
  phase: a Permission gate (the default), a **Role gate** for persona Cards, and the
  **admin branch**.
- the first Card endpoint, proving the API contract: read-only, in the module that owns the
  data, independently authorized, `{data, meta}` envelope, and **typed domain values only —
  never pre-rendered display strings**, because the SPA renders bilingually from the data
  the way Notifications already does.

**The Role gate is the subtle part, and it is not new.** My Academic Record hangs off the
Student anchor, and an administrator inherits `grades.view_own` under
[ADR-0008](../../../docs/adr/0008-rbac-enums-static-matrix.md) while holding **no Student
anchor at all** — so gating this Card on the Permission would render a broken card for
R09/R10. The frontend's shared access helper already documents this exact rule for the
persona nav items and has applied it since Phase 2 and Phase 3; this ticket applies the
existing convention to Cards rather than inventing a mechanism. Get this right here and
tickets 03 and 04 inherit it for free.

The **admin branch must ship in this ticket even though no admin Card exists yet**: a User
holding R09/R10 sees only Cards flagged as belonging to the admin set, which is empty until
ticket 07. An administrator therefore lands on a correct, deliberately empty dashboard
throughout tickets 03–06, rather than accumulating every other role's queue and having it
taken away later.

This ticket also lands the two vocabulary decisions it implements: the
[CONTEXT.md](../../../CONTEXT.md) entries for **Dashboard** and **Dashboard Card**, and
**ADR-0024**. Note the term is *Dashboard Card*, never *Panel* — `InterviewPanel`,
`CurriculumPanel`, `VersionHistoryPanel` and `CartPanel` already ship using "Panel" as a
generic word for a bordered page section, and adopting it as a domain term would put four
shipped components in violation of the ubiquitous language.

See [the spec](../spec.md) for the full Card inventory and the composition rule.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] A shared, presentational `<DashboardCard>` exists with title, count, rows, empty-state, error and action-link slots. It carries no Card names and no domain vocabulary, following the `<StatusChip>` precedent.
- [ ] The authenticated landing page lives in the app shell, still routed at `/`, and composes Cards from the registry. The old feature-owned page is deleted, not left orphaned.
- [ ] The permission-chip wall is removed. A slim identity header retains the user's name, roles, and — for a Student — the University ID block placed there by [ADR-0013](../../../docs/adr/0013-acceptance-handoff-student-anchor.md).
- [ ] The Card registry is a declarative table beside the existing nav table, expressing all three gate kinds: single Permission, Role, and admin-set membership. No selection function is introduced.
- [ ] A User holding R09 or R10 sees **only** admin-set Cards — an empty grid at this point — and never the union of everything they inherit.
- [ ] Grading exposes a read-only My Academic Record Card endpoint returning CGPA, latest Semester GPA and earned credit hours as typed domain values, reading the existing on-demand GPA/CGPA derivation rather than recomputing it (ADR-0020).
- [ ] A Student with no CGPA-counting graded Enrollment gets `null`, and the Card renders a dash — never `0.00`.
- [ ] The Card endpoint is authorized on the server independently of what the client chose to request; a user without the gate gets 403.
- [ ] Cards fetch and fail independently: one Card erroring renders that Card's error state and leaves the rest of the grid intact.
- [ ] Bilingual EN + AR for every new string, in its own feature namespace. No English-only placeholder copy.
- [ ] Pest feature tests cover the endpoint's payload for a seeded Student, the 403, and the null-CGPA case. Vitest + RTL covers `<DashboardCard>`'s states (populated, empty, error, with and without a link) and the landing page's registry filtering, asserting Card **absence** as well as presence — including a persona Card correctly withheld from an administrator who inherits its Permission.
- [ ] `openapi.yaml` and the Orval client regenerated and committed; both drift checks clean.
- [ ] [CONTEXT.md](../../../CONTEXT.md) gains **Dashboard** and **Dashboard Card**, including the Card-versus-Report distinction so Phase 7 does not re-litigate it.
- [ ] **ADR-0024** records the composition rule and, explicitly, why the code departs from SRS §2.3 and architecture.md's "each role sees a tailored dashboard".
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` and `npm run gate` both green; the landing page passes axe with no serious or critical violations.
