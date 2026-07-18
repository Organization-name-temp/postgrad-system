# 02 — Fix false "upload every required document" block

**What to build:** An applicant who selected an English test, or is applying for a PhD, is no longer blocked at finalize by documents the UI presented as optional. Today the English certificate (when a test is selected) and the MSc thesis (for PhD applicants) are hard-required at finalize but shown with optional-looking warning chips, and the block message names nothing specific. When a condition is met the relevant document shows as **Required** and gates the Documents step, and if finalize is still blocked the error names exactly which documents (and whether the primary program) are missing.

**Blocked by:** None — can start immediately. *(Workstream 1. Root-cause + file references: [bugfixes.md](../bugfixes.md#b2).)*

**Status:** ready-for-agent

- [ ] Selecting an English test other than None makes the English-certificate document Required (chip + Documents-step gating); applying for a PhD makes the MSc thesis Required.
- [ ] The finalize error enumerates the specific missing document type(s), and separately reports a missing primary program, instead of one generic message.
- [ ] An application with all conditionally-required documents finalizes successfully.
- [ ] Tests: finalize with an English test selected but no certificate → 422 naming the English Certificate; a PhD applicant missing the thesis is named; a complete application finalizes. Gates green.
