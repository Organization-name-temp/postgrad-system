# 06 — Submit, correct, and verify payment evidence

**What to build:** Let Students submit private payment evidence and let Student Affairs verify it or return it for correction without discarding request approval or prior evidence.

**Blocked by:** 05 — Submit and review official Transcript Requests.

**Status:** ready-for-agent

- [ ] The owning Student submits a payment reference and receipt from Awaiting Payment to Payment Under Review; upload alone never satisfies payment or enables issuance.
- [ ] Student Affairs verifies one EGP payment exactly matching the captured fee, recording amount, verifier, and time, and transitions to Ready for Issuance.
- [ ] Student Affairs returns unverifiable/mismatched evidence with a mandatory reason to Payment Correction Required. Request approval remains valid and the Student may resubmit to Payment Under Review.
- [ ] Preserve every prior evidence submission and verification decision; no replacement deletes old files or rewrites history. Wrong-state or unauthorized submissions and decisions fail without partial changes.
- [ ] No installments, combined receipts, overpayment balances, or online payment integration. Cancellation refunds will use the exact verified amount.
- [ ] Receipt upload validation/storage and authenticated fetch-as-blob reuse established private-document conventions. Owner/Student Affairs/admin scope applies to reference, evidence, and processing history; Advisors, other Students, and public access cannot fetch them.
- [ ] Student submission/correction and staff verification UI expose only permitted actions, with bilingual reasons/messages, attributed transitions, audit events, and once-only correction/verification notifications.
- [ ] Serialize payment mutations on the request and revalidate status, providing the boundary later cancellation and issuance will use; duplicate verification does not record multiple successful payments or notifications.
- [ ] PostgreSQL HTTP tests cover exact/mismatched amounts, evidence history, wrong states, repeat verification, receipt access and JSON auth errors, Terminated Students, audit, and notifications; focused frontend tests cover correction and staff actions.
- [ ] Queue/export updates obey inherited scope/allowlist rules, generated API artifacts remain synchronized, and affected gates pass; report any gate not run with its concrete reason.
