# 13 — Global search fan-out

**What to build:** The app-bar search slot has been empty since Phase 0.5, when it was deferred
"to the phases that add their endpoints". The notification bell filled its slot in Phase 2; search
never did, and Phase 4.5 deferred it again. Tickets 06 and 07 have now given every module a
bilingual, normalised search — so the slot can finally be filled without a single new backend
endpoint.

A signed-in user types into the app bar and sees grouped results — Students, Courses, Programs,
Applications — and jumps straight to a record without first deciding which screen owns it. An
Arabic name typed without its hamza still finds the person.

**The shell fans out; it does not aggregate.** The app bar calls each *permitted* module's own list
endpoint with `?q=&per_page=5` and groups the results by type. Each request is **independently
authorized and independently failing**, exactly as Dashboard Cards are — so a user only ever sees
groups they could have reached by navigating, one group erroring leaves the others intact, and a
permission regression cannot leak a surface.

This is deliberately not a server-side `/search` endpoint. A cross-module search has no owning
module under ADR-0026, so it would have to live in `app/` — which would mean opening the `App`
layer's missing Admissions and Notifications edges, putting domain query logic where no route has
ever lived, and re-implementing per-type authorization that the list endpoints already enforce
correctly. The fan-out costs one extra round trip per group and buys all of that back. It is also
**why those deptrac edges stay recorded-not-fixed**: nothing in `app/` needs them.

Behavioural details that matter more than they look:

- **Which groups appear is derived from the acting user's own permissions**, the same way the side
  nav and the Dashboard already compose.
- **Requests are debounced and cancelled** on subsequent keystrokes, so typing does not queue a
  request per character.
- **The empty state distinguishes "no matches" from "you can search nothing"** — a user with no
  searchable surface should not see a search box that always returns nothing.
- **Keyboard reachable and screen-reader announced**, with results as a proper listbox rather than
  a div of links.

**Blocked by:** 06 — Admissions and Catalog lists on the contract; 07 — Remaining lists on the
contract.

**Status:** ready-for-agent

- [ ] The app-bar search slot is filled and reachable by keyboard
- [ ] Typing fans out to each permitted module's existing list endpoint with `q` and a small page
      size; **no new backend endpoint is added**
- [ ] Results are grouped by type with a link into each record's own surface
- [ ] Groups are chosen from the acting user's own permissions; a user never sees a group they
      cannot open
- [ ] One group failing renders that group's error and leaves the others intact
- [ ] Arabic search works unnormalised — `احمد` finds `أحمد` — through the ticket-02 normalisation
- [ ] Requests are debounced and in-flight requests cancelled on new input
- [ ] The empty state distinguishes no-matches from nothing-searchable
- [ ] Results are announced to screen readers as a listbox; axe passes on the app bar with results
      open, in light/dark × EN/AR
- [ ] No deptrac edge is added, and the `App` layer's missing edges stay untouched
- [ ] Vitest covers grouping, the permission filter, debounce/cancel and both empty states
- [ ] `npm run gate` green
