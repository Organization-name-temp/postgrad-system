# 13 — Verify the complete Phase 6 journey

**What to build:** Make the entire approved Transcript service demonstrable across Student, Student Affairs, Advisor, and public-recipient views, and verify that integrated workflows uphold the Phase 6 contract.

**Blocked by:** 08 — Complete full manual refunds; 11 — Track collection from Student Affairs; 12 — Revoke official issuances.

**Status:** ready-for-agent

- [ ] Earlier tickets retain their own test-first behavior coverage and package verification; this ticket integrates the demo and catches cross-workflow gaps rather than deferring foundational tests or authorization to the end.
- [ ] One serial Playwright smoke covers request submission, approval, payment correction/verification, preview, digital issuance, unauthenticated QR reading, language switching, and explicit revocation with reference/status-only public response.
- [ ] Continue with separate sequential requests for cancellation/full refund and collection; release the one-open-request limit through real settlement/delivery rather than bypassing it with test-only mutations.
- [ ] Verify integrated identity recovery/readiness, Terminated Student services, live/former Advisor access, financial access restrictions, and retained history after owner changes.
- [ ] Confirm controlled PostgreSQL concurrency coverage exists and passes for submissions, coherent preview/issuance, owner-content changes, issuance versus cancellation, verification versus cancellation, duplicate issuance, and refund completion. Add missing integration coverage without duplicating already-proven implementation tests.
- [ ] Demonstrate EN/AR, RTL/LTR, theme/token behavior, relevant accessibility assertions, and browser print layouts for current, official, public, and revoked views. QR codes point to the real per-issuance route and do not expose service records.
- [ ] Demo fee/instruction setup uses only clearly labelled fictional learning data, not invented institutional bank instructions; no deferred PDF/signing/gateway/postal/email infrastructure is required.
- [ ] Review handwritten and generated diffs separately; committed OpenAPI/client artifacts, bilingual messages, module/feature boundaries, and document ownership/status references agree with implemented behavior.
- [ ] Backend and frontend affected gates, including the serial full-stack flow, pass against migrated/seeded PostgreSQL. Report any gate not run with its concrete reason, and retain actionable evidence of failures.
- [ ] Provide the complete headline demo and verification results for the repository's manual phase review; do not mark the phase complete merely because tickets exist or tests were not run.
