# Consolidated Phase 6 review

One consolidated review, after implementation and full acceptance gates.
Base: `2e9c16db11213f97a78261750a8d1d76337148d5`.
Reviewed head: `e8d06ec0766b0e230286932ba3a10a113223d66e`.
Contract: `spec.md`, all 13 individual tickets, canonical CONTEXT, SRS §4.9 and
accepted ADR-0044–0050. Handwritten code and generated artifacts were inspected separately.
No per-ticket review or approval cycle was used.

## Standards

Four P2 documented-standard findings:

1. **Nullable identity contract.** `MeController.php` documents Identification Type
   solely as a string while unresolved legacy identity returns null. Generated OpenAPI,
   TypeScript and Zod omit nullable. This breaches AGENTS.md's synchronized API contract.
2. **RTL identifiers/dates.** Decision CH/dates and Transfer/Waiver codes/grades in
   `TranscriptDocument.tsx`, payment/refund/transition metadata in `RequestPage.tsx`,
   queue IDs/amounts and issuer dates lack the isolation/locale formatting required by
   design-system §11. Preserve Arabic prose and isolate scalar content.
3. **Domain statuses.** Request queue/detail/timeline and issuance-history statuses use
   plain text rather than shared `StatusChip`, contrary to design-system §10. Colocate
   the Records tone map; retain explanatory Alerts as appropriate.
4. **Empty states.** Empty request and issuance collections show no explanation,
   contrary to design-system §9's loading/empty/error contract. Add bilingual guidance.

No additional concrete ownership, authorization, receipt privacy, recovery-evidence or
certification-guard breach was reported. No heuristic smell warranted architecture churn.

## Spec

Two P2 findings:

1. **Localized exports.** All three Records XLSX exports have hard-coded English headers
   and descriptive status/delivery values. RTL direction alone does not implement the
   canonical Export definition, “rendered in the requester's language,” and the inherited
   Support collection/export contract in spec lines 266–270. Localize actual cell values
   while retaining identifiers, amounts, currency codes and references.
2. **Official watermark.** The boxed seal, attribution and QR omit the watermark required
   by BR-64, spec line 218 and ticket 10. Add a bilingual token-based document watermark;
   retain reference/status-only revoked public rendering and verify screen/print behavior.

No additional concrete workflow, immutable-snapshot, public-disclosure, financial-scope,
role-union, live-Advisor, Terminated-service, concurrency or deferred-feature violation
was reported.

## Resolution and re-verification

All six findings were verified against the actual source and approved requirements.
Fixes preserve business rules and disclosure:

- Nullable source response metadata regenerated OpenAPI, TypeScript and Zod; an HTTP contract
  regression checks unresolved identity against the generated nullable OpenAPI field.
- Records scalar rendering now isolates IDs, CH, grades, dates and references. Locale
  timestamps preserve the instant, explicitly show UTC and use Western digits.
- Request queue/detail/timeline and official history use the feature-owned shared-chip
  map. Successful empty queues/pickers have bilingual explanation and icon; queue errors
  include retry and loading uses skeletons.
- All Records XLSX headers and descriptive lifecycle/status/delivery cells are localized.
  The Arabic HTTP/XLSX regression failed before the change and passes afterward.
- Official academic documents contain a decorative bilingual SVG watermark using theme tokens,
  with an empty image alternative beside the readable heading.
  It is excluded from current unofficial and reference/status-only public revoked views.
  Existing seal/issuer/QR remain; no signing or server PDF infrastructure was added.

Focused export/recovery checks passed 6 tests / 58 assertions; focused Records UI checks
passed 17 tests, including reproduced red/green presentation regressions. Schema changes
required one legacy Dashboard fixture to represent Identification Type as explicit null.
The backend affected gate rerun passed 1,216 tests / 6,066 assertions and all static/drift
checks. The complete frontend gate rerun passed all 534 unit tests and 66 serial browser tests,
including the A4 watermark/print matrix. Additional A4 print and runner
contention failures were reproduced and narrowly resolved during verification; see
[verification.md](verification.md) for the final results.

Summary: Standards 4 findings, worst nullable owner API contract; Spec 2 findings, worst
requester-language Export contract. All six are resolved and complete affected gate evidence is green.
