# 10 — Issue official copies with public QR verification

**What to build:** Let Student Affairs certify exactly the reviewed bilingual record, let authorized users view its immutable issuance history, and let recipients inspect that official copy through its public QR link.

**Blocked by:** 09 — Review consistent official Transcript previews.

**Status:** complete

- [x] Issue only from an approved, verified-paid Ready for Issuance request with a valid current reviewed preview; no direct issuance bypass and no new copy from reopening, printing, or switching language.
- [x] Atomically commit at most one issuance per request with unique reference, issuer/time, both-language identity and academic content, authoritative GPA/CH figures, and an unguessable per-issuance verification token.
- [x] The committed content equals the reviewed coherent snapshot; refuse changed academic/identity content and require re-review. A real competing-transaction test proves the guarantee across owners rather than testing only a digest helper.
- [x] Serialize issuance against cancellation: issuance-first refuses cancellation; cancellation-submission-first blocks issuance. Repeated/concurrent issuance cannot create multiple copies or duplicate delivery/audit/notification effects.
- [x] Digital issuance transitions directly to Completed when the copy becomes available and releases the open-request limit without requiring opening acknowledgment. Collection issuance enters Ready for Collection and remains open for ticket 11.
- [x] Authenticated issuance history/detail is available to the owning Student, live Advisor, Student Affairs, and existing administrators; reassignment transfers Advisor authority. Preserve Terminated Students' services and mixed-Role unions.
- [x] EN/LTR and AR/RTL official screens/browser printing show frozen content, reference/date/issuer, seal/watermark, and QR; issuer attribution is not a cryptographic digital signature and no server PDF integration is introduced.
- [x] Public verification works without login outside the authenticated shell and exposes the complete valid issued snapshot, explicitly including National ID/passport and credit-decision source details, in both languages.
- [x] The public link grants access only to its issuance: no sequential-reference/Student-ID enumeration, current-record browsing, other copies, receipts, private attachment identifiers/URLs, financial history, reviewer notes, or termination-case evidence.
- [x] Public reads have no workflow effects or per-scan notifications. Links do not automatically expire after termination, graduation, account changes, or newer issuances.
- [x] Later Grades, resolved Incompletes, Catalog/identity labels, decisions, fee setup, or lifecycle changes do not rewrite existing snapshots; new issuance never automatically revokes an earlier copy.
- [x] Independently filterable history collections obey scoped Support pagination/export rules; never include bearer tokens in queue exports or unnecessary audit content. Audit issuance and notify digital availability or collection readiness once.
- [x] HTTP/frontend tests cover immutable history, scopes, public disclosure/absence of private data, stale issuance, cancellation races, retries, ongoing-Semester issuance, and print/language behavior; generated artifacts and affected gates pass, with reasons for any gate not run.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
