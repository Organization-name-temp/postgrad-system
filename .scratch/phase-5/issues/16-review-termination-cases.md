# 16 — Review Termination Cases through Advisor and Department Head

**What to build:** Carry an actionable Termination Case through the two required pre-Council review stages with live Advisor ownership, scoped Department Head authority, immutable assessments, and evidence revalidation before every action.

**Blocked by:** 15 — Detect termination evidence and reconcile Cases.

**Status:** ready-for-agent

- [ ] A newly opened Case starts awaiting Advisor assessment and sends the normal Student/current-Advisor notification once.
- [ ] Only the Student's currently assigned Advisor may record the assessment and recommendation, using the existing recommendation Permission for this transition only.
- [ ] While assessment is pending, Advisor reassignment removes the Case from the former Advisor's queue and authority and places it with the new Advisor.
- [ ] A completed assessment remains immutable, attributed, and valid after reassignment; the Case remains at the Department Head stage instead of restarting.
- [ ] Only the Department Head scoped to the Student's bound Program Department may recommend termination to Council or dismiss the Case.
- [ ] The Department Head cannot bypass Advisor assessment, and the Advisor cannot perform the departmental or Council transition.
- [ ] Every action recomputes and revalidates authoritative trigger evidence first; cleared or changed evidence performs Trigger Cleared or Evidence Changed behavior instead of applying a stale review.
- [ ] Department Head dismissal records the reviewed fingerprint and reason, suppresses exactly that fingerprint, removes the readiness block, and preserves all prior assessments.
- [ ] Advisor and Department Head queues use live scope, pagination, readable filters, audited exports where required, and clear action ownership.
- [ ] Subsequent Advisor notifications resolve the current assignment even when the preserved assessment belongs to a former Advisor.
- [ ] Student, Advisor, and Department Head screens show evidence, attributed history, reasons, and current stage in EN/AR and LTR/RTL.
- [ ] HTTP feature and component tests cover Role/Permission/scope combinations, reassignment before and after assessment, stale actions, dismissal, evidence changes during review, notifications, and generated contracts.
