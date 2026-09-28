# 05 — Specify retained sensitive-change audit coverage

**What to build:** Administrators can trace the retained sensitive changes through a documented Audit Log contract, with clear treatment of Catalog versions and owner workflow histories.

**Blocked by:** None — can start immediately.

**Status:** needs-info

- [ ] Inventory FR-SEC-002.10 and BR-80 against sensitive write routes: Calendar, Catalog, staff routing, admission decisions, academic and Records mutations. Name concrete missing events.
- [ ] Resolve which version/history records are sufficient evidence and which actions require a separate Identity Audit Log event; do not conflate the security log with Status Transitions.
- [ ] Define event facts without leaking private evidence, failure/rollback behaviour and actor attribution; seven-year production retention operations remain under the accepted scope decision.
- [ ] Produce owner-sized implementation tickets for agreed missing events, with transactional no-success-event-on-rollback tests and administrator read/export checks through Phase 4.6 issue 11.
- [ ] Record accepted exceptions in source documents; missing audit events must not be labelled complete by a passing existing suite.
