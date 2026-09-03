# 14 — Column chooser

**What to build:** The registrar grids are wide — an Applications queue carries identity, Program,
degree level, status, dates and a derived age, and no single user wants all of it at once. This
ticket lets a user choose which columns a grid shows, carried in the URL beside the filters so the
choice is shareable and survives a reload.

**The chosen columns affect the screen and never the file.** ADR-0027 fixed the export column set
as canonical and server-defined precisely so that the client is never the authority on what an
export contains — a colleague opening a shared file finds the columns they expect regardless of
what the sender had on screen. So the chooser and the export are deliberately decoupled, and that
is a feature rather than an oversight worth "fixing" later.

Details:

- **One shared control in `shared/ui`**, encoding the rules and **no domain names**, alongside
  `<FilterBar>` and `<DataToolbar>`.
- **Each grid declares its own available and default columns.** The chooser renders what it is
  given; it knows nothing about Applications or Courses.
- **Column choice is URL state**, so it round-trips exactly as filters, sort and page already do.
- **Some columns cannot be hidden.** The identifying column of a row is what makes the row
  actionable; a grid of dates with no name is not a useful thing to be able to produce.
- **Hiding a column does not stop it being filterable or sortable.** Visibility is presentation;
  the allowlist is authorization. Conflating them would either weaken the security rule or make the
  chooser silently change results.

**Blocked by:** 02 — Query contract foundation; 03 — Export foundation.

**Status:** ready-for-agent

- [ ] A column chooser lives in `shared/ui` and contains no domain names
- [ ] Each grid declares its available columns and its defaults; the chooser renders only what it
      is handed
- [ ] Column selection round-trips through the URL alongside filter, sort and page state
- [ ] A grid's identifying column cannot be hidden
- [ ] Hiding a column does not change which fields are filterable or sortable, and does not change
      the result set
- [ ] The export is **unaffected** by the chooser and still emits its canonical column set —
      asserted by a test, since this is the invariant most likely to be "helpfully" broken later
- [ ] The chooser is keyboard reachable and screen-reader labelled; axe passes with it open in
      light/dark × EN/AR
- [ ] Vitest covers URL round-trip, the undismissable identifying column, and export independence
- [ ] `npm run gate` green
