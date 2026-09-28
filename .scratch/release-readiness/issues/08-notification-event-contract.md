# 08 — Define required user-facing notification events

**What to build:** Each User receives the agreed in-app notifications for actions requiring their attention, under an explicit event list rather than an ambiguous “all system events” claim.

**Blocked by:** None — can start immediately.

**Status:** needs-info

- [ ] Reconcile BR-71 and FR-NTF-001 against the existing Notification types, owner workflows and recipient-scope tests; distinguish Notifications from the Audit Log and domain events.
- [ ] Record the required event/recipient/payload matrix, including review decisions, required corrections, adverse Standing/recovery and live reassignment behaviour. Identify concrete omissions without adding notifications for every CRUD action.
- [ ] Create owner-sized implementation slices for agreed omissions, with once-only delivery and recipient authorization evidence; preserve typed bilingual payloads and the polled bell.
- [ ] Record any accepted exclusions in requirements; real email, SMS, websockets and scheduling remain deferred.
