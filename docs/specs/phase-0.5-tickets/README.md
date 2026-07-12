# Phase 0.5 — Design System & Theming · Tickets

Tracer-bullet vertical slices broken out of [../phase-0.5-design-system.md](../phase-0.5-design-system.md)
(via `/to-tickets`). Each is agent-grabbable (`ready-for-agent`) and sized for one fresh context.

**These are staged locally** because `gh` isn't installed yet. Run
[publish-issues.ps1](./publish-issues.ps1) from the repo root to push them to GitHub as issues in
dependency order. The GitHub issues become the source of truth once created.

## Dependency order

```
01 ──┬── 02 ──┐
     ├── 03 ──┼──► 06 ──► 07
     ├── 04 ──────────────►┘
     └── 05            (independent of 07)
```

| # | Ticket | Blocked by |
|---|--------|------------|
| 01 | Token-driven theme foundation 🎯 | — |
| 02 | Self-hosted bilingual fonts | 01 |
| 03 | App-shell chrome (app bar + role-aware side nav) | 01 |
| 04 | StatusChip presentational component | 01 |
| 05 | Contrast gate (util + token-contrast test) | 01 |
| 06 | Re-skin screens + no-raw-hex ESLint gate | 01, 02, 03 |
| 07 | /style gallery + hybrid visual smoke + axe | 01, 02, 03, 04, 06 |

**Frontier:** 01 → then 02/03/04/05 in parallel → 06 → 07. Work one ticket at a time with
`/implement`, clearing context between tickets.
