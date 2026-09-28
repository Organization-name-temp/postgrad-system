# 06 — Bilingual polish: Records and public verification

**What to build:** Students, Student Affairs and Transcript recipients use existing Records services and public verification in either language with legible screens and browser-printed documents, preserving certified content and the established disclosure contract.

**Blocked by:** None — can start immediately.

**Status:** ready-for-human

- [x] Audit current Transcripts, requests/queues, fee/payment/correction, cancellation/refund, preview/issuance, collection, issuance history/revocation and public valid/revoked/invalid verification states in EN/LTR and AR/RTL with light/dark themes.
- [x] Record coverage and concrete defect dispositions. Fix untranslated messages/statuses, missing keys, clipping, incorrect direction and mixed-script layout through existing translation and theme mechanisms.
- [x] Maintain backend EN/AR message pairs and both frontend dictionaries. Runtime bilingual instructions and certified snapshot content are preserved; presentation fixes never rewrite an issued academic snapshot.
- [x] Student identifiers, Course codes, amounts, dates and document references remain readable in RTL. Do not mirror logos or QR codes. Check narrow layouts, long labels, request dialogs, evidence controls and accessible focus/labels.
- [x] Verify EN/AR browser printing for current and official Transcripts, including portrait page fit, readable tables, watermark/seal and QR placement. Preserve existing print-only styles and valid versus revoked presentation.
- [x] Public valid verification still shows the full issued snapshot under the accepted bearer-link contract. Revoked access still returns only reference and revoked status; no financial evidence, request history or review data is added to public or Advisor access.
- [x] Add focused regressions only for identified defects; reuse Records browser/print and service HTTP prior art. Test relevant dynamic language and public state interactions without duplicating the complete request state machine.
- [x] Run relevant tests and affected package checks; report any gate not run and why. Server PDF, cryptographic signatures, payment integrations, disclosure-policy changes and new delivery workflows remain out of scope.

## Comments

The Records/public-QR disclosure and historical immutability decisions are already accepted. This ticket improves bilingual presentation within those decisions.

## Executable disposition

Implemented on `phase-7-academic-reports-bilingual` against fixed baseline
`b3a3ae7b71538c36230985270de2b73bc2f92088`. Focused red/green evidence,
owner/API/UI verification and prerequisite dispositions are recorded in
[the Phase 7 tracker](../tracker.md). The single consolidated
[Standards/Spec review](../review.md) has all five actionable findings resolved.
Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend API/static checks, 121 files / 684 unit tests and all 179 serial browser
checks. The new CI transition regression and complete affected Enrollment/Records
journeys passed together (3/3, 12.4m, exit 0), providing evidence for all 180
current distinct browser tests. Required human demo/Arabic/print acceptance is
pending. Latest GitHub run is pre-fix and failed two browser contrast assertions;
`c1dbe1c` fixes them locally, with remote verification awaiting publication under
the user's no-push instruction. No phase acceptance, push or merge is claimed.
