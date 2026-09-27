# 04 — Configure Transcript fees and payment instructions

**What to build:** Let Student Affairs configure the standard Transcript fee and bilingual payment instructions, and show Students whether official requests are available.

**Blocked by:** None — can start immediately.

**Status:** complete

- [x] Records owns one standard positive EGP fee and English/Arabic payment instructions; introduce only the module discovery, API, persistence, and feature wiring needed for this complete configuration slice.
- [x] Existing official-issuance authority governs configuration changes, preserving administrator override; other Roles cannot mutate setup and no new Role/Permission is added.
- [x] Validate positive monetary amounts without floating-point currency drift and require both language versions of instructions before service availability becomes true.
- [x] Student-facing service availability and applicable fee are readable before requesting; payment instructions themselves are not disclosed to Students before their request approval.
- [x] Before configuration is complete, explain that official requests are unavailable while unofficial Transcript viewing remains available; do not invent bank details or an institutional fee.
- [x] Student Affairs can edit configuration in a bilingual screen and each successful change is audited through Identity's audit writer. Later request snapshots must be possible without reading Records internals across a seam.
- [x] Fee configuration and financial recording remain Transcript-specific, with no Finance module, fee waivers, gateway, or production infrastructure.
- [x] HTTP and focused frontend tests cover permission scope, invalid amounts/instructions, setup availability, instructions disclosure, and audit behavior; generated contract/client and affected gates pass.
- [x] If another Records slice already introduced module/feature wiring, extend it rather than creating competing discovery or registries; report any gate not run with its concrete reason.

## Verified completion

Acceptance is verified by the ticket-specific coverage, complete gates and resolved
consolidated review recorded in [the Phase 6 evidence](../verification.md).
