# 11 — Audit Log Report

**What to build:** The Audit Log has been written since Phase 0 and **never read**. It has no list
endpoint at all; the only thing that touches it is the Administration Card, which shows the most
recent few entries. This ticket gives a System Administrator the ability to actually interrogate it:
filter by actor, event type and date range, search, page through the result, and export it.

This is the last of SRS §4.12's five buildable Reports, and the only one that also builds a new
surface rather than aggregating an existing one — which makes it the sharpest demonstration of the
query contract in the phase. It is also the surface where the contract's own rules are most visibly
load-bearing.

- **Owned by Identity**, which owns the Audit Log and needs no outbound edge to read its own data —
  the one Report of the six that requires nothing from another module.
- **Role-gated on the administrator pair**, not permission-gated. §4.12 lists SysAdmin and Super
  Admin, and no Permission distinguishes an administrator — they inherit every Permission under
  ADR-0008. This is the AdministrationCard precedent applied again, and it is why the gate is a
  Role: there is no Permission that could express it.
- **The log is append-only and stays that way.** This surface reads. There is no edit, no delete,
  no retention action, and no endpoint that could produce one.
- **Exporting the Audit Log is itself audited.** That recursion is intended and terminates, because
  the entry is written after the query is authorized — the export of the log becomes a row in the
  log, which is exactly the behaviour a reviewer would want.
- **Filters that matter:** actor, event type, date range, and free-text over the entry's subject.
  Actor filtering resolves user identities through Identity's own directory, so the filter values a
  reviewer picks from are names rather than opaque ids.
- **A distribution of events over time** gives the Report its chart — events per day or per type
  across the chosen range — with the usual "View as table" disclosure and drill-through into the
  filtered entries.

**Blocked by:** 03 — Export foundation; 08 — Analytics section + Application Pipeline +
drill-through.

**Status:** ready-for-agent

- [ ] A read-only, paginated, filterable Audit Log list endpoint exists — the system's first
      audit read surface
- [ ] Filters cover actor, event type and date range, with free-text search over the entry subject
- [ ] Actor filter values resolve to user names through Identity's directory rather than raw ids
- [ ] The endpoint and the Report are **role-gated** on the administrator pair; every other role,
      including holders of every Permission, receives 403
- [ ] No write, edit, delete or retention endpoint is added; the log stays append-only
- [ ] The Report charts event volume over the chosen range, with a "View as table" disclosure and
      drill-through into the filtered entries
- [ ] The list exports to `.xlsx` with a canonical column set
- [ ] Exporting the Audit Log writes its own Audit Log entry, recorded after authorization
- [ ] Pest covers the authorized payload, the 403 for a non-administrator holding every Permission,
      the filters, and the export's self-audit
- [ ] `openapi.yaml` and the Orval client regenerated and committed
- [ ] `composer gate` and `npm run gate` green
