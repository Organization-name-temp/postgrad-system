# 07 — Application status machine + Student Affairs screening + status timeline

**What to build:** Student Affairs works a queue of finalized applications — approving one (which auto-forwards to the Dean), returning it for revision (which loops back to the applicant with instructions), or rejecting it — each with a note. Every transition is recorded in an append-only, applicant-visible timeline, illegal transitions are refused, and the applicant can watch their application's progress. This is the workflow backbone the later stages build on.

**Blocked by:** 06 — Program selection + Catalog read facade + qualification + finalize; 02 — Notifications module + polled bell

**Status:** ready-for-agent

- [ ] The full `ApplicationStatus` transition map is enforced (hand-rolled enum + static allowed-transitions map); an illegal transition → **409**. Each named action appends an `application_status_transitions` row (from/to/actor/note/occurred_at) — **append-only**, never edited; distinct from the Identity Audit Log.
- [ ] Student Affairs (holding `application.screen`) sees a queue of `UnderSAReview` applications with **derived age** (business unimportant here — just days since entering review) and can open detail + documents.
- [ ] **Approve** auto-advances through the transient `SAApproved` to `UnderDeanReview` in one action; **return for revision** → `ReturnedForRevision` with instructions (applicant edits + resubmits → `UnderSAReview`); **reject** → `Rejected` with reason.
- [ ] Return-for-revision and reject emit a Notification to the applicant (ticket 02 module).
- [ ] The applicant sees a **status timeline** (read-only over the transition log) + a progress indicator; a colocated `statusToChip` map drives `<StatusChip>` across the statuses shown.
- [ ] Pest feature tests: legal + illegal transitions, screen RBAC, the return→resubmit loop, notification rows, timeline retrievable via API. Vitest: `statusToChip` map + timeline component. All gates green.
