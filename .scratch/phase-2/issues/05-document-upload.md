# 05 — Document upload

**What to build:** The applicant uploads their certificates and documents. Each document type enforces its own format, size, and cardinality rules; files land on the **private** local disk under an opaque identifier and are served only to the owner or a reviewer; the applicant can preview, replace, and remove documents while the application is still editable.

**Blocked by:** 04 — Personal info (Step 1) + per-step save/resume + email verification

**Status:** ready-for-agent

- [ ] A `DocumentType` enum carries each type's own rules (requirement required/conditional/optional, max size, allowed formats, max count) — e.g. graduation certificate/transcript/national-ID/photo required, IELTS-or-TOEFL cert + MSc thesis conditional, CV optional, up to 2 recommendation letters, up to 5 additional certificates. Uploads are validated against the enum (bad type/size/over-count → 422).
- [ ] Files are stored on the **private `local` disk** (never `public`); no malware scan / encryption-at-rest (ADR-0005). Each document has a **ULID** public identifier plus recorded original filename, mime, and size.
- [ ] A Sanctum-authed **streaming download route** serves a document only to the owning Applicant or a holder of `application.screen`; anyone else → 403. The ULID (not a row id or filename) appears in the URL.
- [ ] Documents can be previewed / replaced / removed while the Application is applicant-editable (Draft / Submitted / EmailVerified / ReturnedForRevision); locked once `UnderSAReview`.
- [ ] Frontend documents step with per-type upload widgets, progress, and replace/remove.
- [ ] Pest feature tests: valid + invalid uploads, cardinality caps, authorized vs unauthorized download, removal, edit-lock after finalize. All gates green.
