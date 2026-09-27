# 12 — Revoke official issuances

**What to build:** Let Student Affairs explicitly withdraw an official copy's validity without erasing its certification history, and show recipients only its reference and revoked status afterward.

**Blocked by:** 10 — Issue official copies with public QR verification.

**Status:** complete

- [x] Existing official-issuance authority allows Student Affairs to append a revocation with mandatory reason, actor, and timestamp; preserve administrator override and refuse unauthorized mutations.
- [x] Keep the original academic snapshot immutable and readable by its owning Student, live Advisor, Student Affairs, and administrators, visibly marked Revoked; the reason remains non-public.
- [x] Public verification of a revoked token returns reference and Revoked status only, with no Student name, identifier, academic fields, attachments, revocation reason, or hidden embedded snapshot payload.
- [x] Both public and authenticated EN/AR screens render revocation correctly; invalid/unknown tokens do not reveal another issuance. Ensure the UI does not continue rendering stale full content after a revoked response.
- [x] Revocation is separate from request processing/delivery status, does not reopen a completed request, and does not create an automatic refund. New issuance alone still does not revoke an older one.
- [x] Serialize revocation and prevent repeated decisions from duplicating history, audit, or notifications. Notify the owning Student once and retain the reasoned internal action history.
- [x] PostgreSQL HTTP tests assert the full response-body disclosure boundary, preserved internal history, scopes/reassignment, repeated actions, no request/refund side effects, and both languages; focused frontend tests verify public content removal.
- [x] Generated artifacts, affected gates, and print implications pass; report any gate not run with its concrete reason.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
