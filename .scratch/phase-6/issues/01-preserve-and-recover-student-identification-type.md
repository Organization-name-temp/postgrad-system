# 01 — Preserve and recover Student Identification Type

**What to build:** Preserve whether a Student was admitted with a National ID or Passport, recover that information for existing Students, and make identity readiness available to authorized consumers without guessing from the identifier format.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] New admission acceptance carries Identification Type alongside the existing admission-time names and identification number into Identity's Student anchor, atomically with acceptance.
- [ ] Existing Students recover Identification Type from their accepted Applications through a narrow Admissions Public contract; a manually runnable, idempotent recovery path supports the localhost build.
- [ ] Missing or ambiguous authoritative evidence is reported as unresolved rather than inferred from number format; repeated recovery does not overwrite valid identity data or invent a correction workflow.
- [ ] Identity Public DTOs/contracts expose the admission-time identity and readiness needed by Records, without cross-module model or Internal imports.
- [ ] Authorized Student identity UI shows the Identification Type with the existing identifier and explains unresolved readiness in English and Arabic using generated API types; unrelated viewers gain no identity access.
- [ ] Student name, identifier, and type remain admission-time identity rather than following later archived Application edits.
- [ ] Test new acceptance for both types, recovery, repeated recovery, missing evidence, failed acceptance atomicity, and authorized identity reads through existing HTTP/Public seams.
- [ ] Relevant bilingual messages, OpenAPI/client artifacts, boundaries, and affected package gates pass; report any gate not run with its concrete reason.
