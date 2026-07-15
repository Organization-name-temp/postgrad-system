# 03 — Restore back-navigation to Personal info

**What to build:** From the Documents step an applicant can go **Back** to edit their personal information (permitted pre-finalize) and return, without losing entered document state. This is the minimal navigation fix; the polished clickable stepper is ticket 08.

**Blocked by:** None — can start immediately. *(Workstream 1; may share a PR with 04. Root-cause + file references: [bugfixes.md](../bugfixes.md#b3).)*

**Status:** ready-for-agent

- [ ] The Documents step has a Back action returning to the Personal-info step with saved values populated.
- [ ] Editing a personal-info field and continuing persists the change via the existing per-step save.
- [ ] Tests: Documents → Personal info → edit → continue persists the edit. Gates green.
