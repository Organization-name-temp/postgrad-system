# Release readiness and remaining requirements audit

**Status:** Audit delivered; implementation and human acceptance outstanding.
**Baseline:** main `44807f61223dbbebca8551c0f8844cb6fd24db78`.
**Authorization:** User approved the completion audit, source-status reconciliation and local tickets after Phase 7.

Deliver a traceable classification of implemented, missing, deferred and unresolved
requirements, correct stale publication claims, and maintain a dependency-aware local
backlog. This effort does not implement the backlog or promote the localhost build to
production. Accepted ADRs amend the production-labelled SRS.

Sources and evidence: [requirements audit](../../docs/release-requirements-audit.md),
[release checklist and CI](../../docs/release-readiness.md),
[Phase 7 prerequisite evidence](../phase-7/tracker.md).

## New issues

| Issue | Delivery | Blocked by |
| --- | --- | --- |
| [01](issues/01-role-assignment-policy.md) | Resolve Role assignment boundaries | None; needs-info |
| [02](issues/02-administrator-role-assignment.md) | Audited administrator Role assignment API/UI | 01 |
| [03](issues/03-program-admission-metadata.md) | Reconcile Program admission information gap | None; needs-info |
| [04](issues/04-verification-link-replay.md) | Reconcile verification link replay semantics | None; needs-info |
| [05](issues/05-sensitive-change-audit-coverage.md) | Specify complete sensitive-change Audit Log coverage | None; needs-info |
| [06](issues/06-localhost-runbook.md) | Reproducible setup, demo and manual-command guide | None |
| [07](issues/07-human-lifecycle-acceptance.md) | Human lifecycle, bilingual and print acceptance | None for current baseline; repeat affected steps after backlog changes |
| [08](issues/08-notification-event-contract.md) | Define required Notification events | None; needs-info |
| [09](issues/09-retake-clearance-semantics.md) | Reconcile supersession and trigger clearance | None; needs-info |
| [10](issues/10-release-closeout.md) | Exact-commit CI, accepted scope and release notes | 02–09 and Phase 4.6 closeout 17; accepted dispositions may replace implementation only when explicitly recorded |

The remaining approved Phase 4.6 issues are reused rather than duplicated. Their
current frontier and recommended owner-sized splits are in the release checklist.
A decision issue cannot silently authorize new domain rules; move it to ready-for-agent
only after its acceptance contract is resolved. Do not mark human acceptance from CI.
