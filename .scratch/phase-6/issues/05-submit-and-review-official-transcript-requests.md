# 05 — Submit and review official Transcript Requests

**What to build:** Let a Student submit one official Transcript Request and let Student Affairs approve or reject it from a searchable queue, with captured pricing, delivery choice, and an attributed timeline.

**Blocked by:** 04 — Configure Transcript fees and payment instructions.

**Status:** ready-for-agent

- [ ] A Student submits for their own record only when fee setup is complete, choosing digital access or collection; persist Submitted with the applicable fee and both payment-instruction versions captured atomically.
- [ ] Enforce at most one open request per Student transactionally, not merely with a pre-submit read. Concurrent submissions cannot bypass the limit; design terminal outcomes and later delivery/refund stages into the invariant.
- [ ] Delivery method, captured fee, and instructions cannot be edited after submission. Changes to delivery require cancellation and a new request; rejected requests remain terminal without resubmission.
- [ ] Student Affairs alone approves to Awaiting Payment or rejects to Rejected, with a mandatory reason. Approval authorizes processing, not issuance; Rejected releases the open-request limit.
- [ ] Students see captured bilingual payment instructions only after approval; later configuration edits affect new requests only.
- [ ] Only the owning Student and Student Affairs read request history/service details, preserving administrator override; Advisors and other Roles do not gain service access from Transcript viewing authority.
- [ ] Terminated Students may submit and continue this historical document service. Termination leaves pending Records requests intact and never changes academic facts or restores rights.
- [ ] Deliver Student request/detail/history UI and Student Affairs review queues with state-appropriate actions, attributed timestamped transitions, audit entries, and once-only decision notifications carrying typed data.
- [ ] Independently filterable queues/history collections use Support pagination, deterministic sorting, EN/AR search and readable-field allowlists, and audited XLSX export siblings; aggregate timelines remain complete parent reads. Do not export future QR tokens.
- [ ] HTTP tests cover legal/illegal transitions, scope and mixed Roles, fee/instruction capture, concurrent submissions, terminal rejection, termination preservation, exports, audit, and notification behavior.
- [ ] Relevant frontend behavior, EN/AR/RTL, generated contract/client, boundaries, and affected gates pass; report any gate not run with its concrete reason.
