# Workstream 1 — Phase 2 gate-blocking bug fixes

Found during the manual review of Phase 2 (2026-07-15). These land on
**`phase-2-admissions`** *before* the review gate merges, so no known defect is merged.
Fix test-first (Pest / Vitest), then re-run `composer gate` + `npm run gate`.

Related enhancements (validation, RBAC, nav) are Phase 2.5 — see [spec.md](./spec.md).

> **Canonical tickets:** these four bugs are the tracker tickets [issues/01](./issues/01-fix-document-viewing.md)–[04](./issues/04-complete-review-step-readback.md) (B1→01, B2→02, B3→03, B4→04). This file is the **root-cause detail** behind them (with file references the tickets deliberately omit); the acceptance criteria of record live in the issue files.

---

## B1 — Applicant/reviewer document viewing throws "Route [login] not defined."

**Severity:** high (documents cannot be viewed at all in a split-origin deployment).

**Symptom:** clicking a document (applicant preview, or a reviewer's document link) opens a
page erroring `Route [login] not defined.`

**Root cause.** `download_url` is an absolute API URL opened by a bare browser navigation —
`window.open(...)` in [DocumentsStep.tsx:92-96](../../frontend/src/features/admissions/wizard/DocumentsStep.tsx#L92-L96) and `<a href>` in
[ApplicationReviewPage.tsx:172-176](../../frontend/src/features/admissions/screening/ApplicationReviewPage.tsx#L172-L176) and the Dean equivalent. That navigation bypasses the
authenticated Axios instance ([http.ts:6-12](../../frontend/src/shared/api/http.ts#L6-L12)), so it reaches `GET /api/v1/documents/{ulid}`
([routes.php:160-163](../../backend/modules/Admissions/Public/Http/routes.php#L160-L163)) unauthenticated and non-JSON; Laravel's default handler
redirects non-JSON guests to a `login` route that does not exist in this API-only backend.

**Fix** ([ADR-0016](../../docs/adr/0016-private-document-serving.md)):
1. **SPA fetches through the authenticated client → blob.** Add a small helper that GETs the
   document via `http` (responseType `blob`), then `URL.createObjectURL` → open in a new tab
   (revoke on cleanup). Use it in the applicant `preview()` and both reviewer document lists.
2. **API always returns 401 JSON on auth failure** (baseline, app-wide). Configure the
   unauthenticated handler (`bootstrap/app.php` `withExceptions` / `Authenticate` middleware)
   so guests get `401 {message}` and never a redirect to `login`.

**Acceptance / tests:**
- Backend: an unauthenticated `GET /api/v1/documents/{ulid}` returns **401 JSON** (not a
  redirect/500). Owner and reviewer still get the bytes; a non-owner non-reviewer gets 403.
- Frontend: the document viewer requests through the `http` client (assert the axios call,
  not a raw `window.open` of an absolute URL).

---

## B2 — "Upload every required document" blocks finalize after documents are uploaded

**Severity:** high (confusing dead-end for any applicant who selected an English test or is a
PhD applicant).

**Symptom:** the applicant uploaded documents but finalize fails with the generic
`Upload every required document before finalizing your application.`

**Root cause.** The finalize guard hard-requires **conditional** documents the UI presents as
optional: the **English certificate** whenever `english_test ≠ None`, and the **MSc thesis**
for PhD applicants ([Application.php:491-524](../../backend/modules/Admissions/Internal/Models/Application.php#L491-L524)). The Documents step shows those types with an
optional-looking *warning* chip ([DocumentsStep.tsx:38-43](../../frontend/src/features/admissions/wizard/DocumentsStep.tsx#L38-L43)), and the finalize error names nothing
specific (it can even co-report a missing primary program in the same `422`).

**Fix:**
1. **Live-promote conditional docs to Required in the UI.** When `english_test ≠ None`, the
   English-certificate row renders as **Required** and gates the Documents step's Continue;
   same for the MSc thesis when `applying_for_degree = PhD`. (Requirement computed from the
   Step-1 answers, matching `requiredDocumentTypesForFinalize()`.)
2. **Enumerate what's missing** in the finalize `422`: list the specific missing document
   type(s) and, separately, whether the primary program is missing — instead of one generic
   string.

**Acceptance / tests:**
- Backend: finalize with `english_test = IELTS` and no English certificate returns a `422`
  whose `documents` error names the **English Certificate**; a PhD applicant missing the MSc
  thesis is named; a complete application finalizes.
- Frontend: selecting a non-None English test flips the English-certificate row to Required
  and disables Continue until it is uploaded.

---

## B3 — Cannot return to Personal info from the Documents step (minimal fix)

**Severity:** medium.

**Symptom:** once on Documents, there is no way back to edit personal info; `DocumentsStep`
is passed only `onContinue`, no `onBack` ([ApplyPage.tsx:108-112](../../frontend/src/features/admissions/wizard/ApplyPage.tsx#L108-L112)).

**Fix (minimal — the polished clickable stepper is Phase 2.5):** add a **Back** action from
Documents to the Personal-info step. Editing the snapshot pre-finalize is already permitted
server-side (`PATCH /application` accepts edits while the Application is applicant-editable),
so this is a frontend wiring change: surface the personal-info step for editing and re-save
via the existing update mutation.

**Acceptance / tests:**
- Frontend: from Documents, Back returns to Personal info with the saved values populated;
  changing a field and continuing persists it (assert the `PATCH /application` call).

---

## B4 — Review step shows almost none of the captured data

**Severity:** medium (the applicant can't actually review before finalizing).

**Symptom:** the Review step renders only `name_en`, `national_id`, `applying_for_degree`,
program name(s), and a document **count** ([ReviewStep.tsx:89-124](../../frontend/src/features/admissions/wizard/ReviewStep.tsx#L89-L124)).

**Fix:** expand the Review step to a **complete read-back** — every captured personal-info
field grouped into sections (identity, contact & address, academic background, English
proficiency), the selected primary/alternative programs, and the **list** of uploaded
documents (filename + type), not just a count. (Per-section Edit jumps are Phase 2.5.)

**Acceptance / tests:**
- Frontend: the Review step renders every captured field and each uploaded document's
  filename + type; a finalized application's read-only status view is unaffected.

---

## Sequencing

B1 and B2 are the high-severity dead-ends → do first. B3/B4 are wizard-navigation/display and
can share a PR. All four are covered by tests before merge; the Phase 2 headline Playwright
smoke must still pass (it already exercises the upload → finalize → review path).
