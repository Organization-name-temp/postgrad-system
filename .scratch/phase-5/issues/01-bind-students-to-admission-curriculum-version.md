# 01 — Bind Students to their admission Curriculum Version

**What to build:** Make every accepted Student permanently use the Curriculum Version effective at admission, and expose that binding wherever the Student's academic identity is read, so later Catalog changes cannot silently alter their requirements.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] Admission resolves the latest Curriculum Version effective at or before the acceptance timestamp and stores its opaque identifier on the Student anchor in the same transaction as acceptance.
- [ ] Acceptance fails without partial state when the admitted Program has no usable Curriculum Version.
- [ ] Existing Students are bound deterministically; when Catalog history begins after admission, the earliest Version is used and the fallback is auditable rather than silent.
- [ ] Identity Public DTOs and the authenticated Student response expose the bound Curriculum Version identifier without exposing Catalog internals.
- [ ] Student-facing identity or academic-context UI shows which Curriculum Version governs the record using generated API types.
- [ ] Admission, backfill/fallback, authorization, OpenAPI, English/Arabic presentation, and PostgreSQL feature tests pass.
