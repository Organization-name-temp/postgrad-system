# 09 — Review consistent official Transcript previews

**What to build:** Let Student Affairs review the complete official Transcript content for an approved paid request, with a review contract that detects changed content before certification.

**Blocked by:** 03 — Show credit decisions and Credit-Hour Summary; 06 — Submit, correct, and verify payment evidence; 07 — Request and decide cancellation.

**Status:** complete

- [x] Student Affairs can preview a Ready for Issuance request only while approval and verified payment remain valid and no cancellation is pending; preserve administrator override and service scopes.
- [x] Preview includes the full approved header, Enrollments/statuses/Attempts, effective credit decisions, owner-provided GPA/CH summaries, and both language versions; no financial evidence or review/termination notes appear.
- [x] Required admission-time identity, including Identification Type, must be resolved. Missing identity blocks official readiness with an actionable bilingual message rather than guessed data.
- [x] Establish a reviewed-content contract bound to the request and complete content, so issuance can refuse a stale or unreviewed copy; changes in either language, academic facts, labels, identity, or summaries invalidate the review.
- [x] Compose a coherent view across contributing owner Public contracts and authoritative calculations. Document the transactional strategy the issuance slice must share; a request lock or hash over inconsistent independent reads is insufficient.
- [x] Expose preview and review-readiness through documented API responses and a bilingual staff screen with clear updated-content/re-review behavior; preview does not create an issuance or prematurely complete delivery.
- [x] HTTP tests demonstrate coherent composition and invalidation after Grade correction, Incomplete resolution, credit-decision change, identity/Catalog label change, or lifecycle change, including controlled competing owner transactions where necessary.
- [x] Keep academic rules in owners, snapshot accounting distinct from live read models, and reviewed metadata distinct from sensitive audit payloads.
- [x] Focused frontend tests cover refreshed preview and blocked readiness; API artifacts, boundaries, and affected gates pass, with concrete reasons for any gate not run.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
