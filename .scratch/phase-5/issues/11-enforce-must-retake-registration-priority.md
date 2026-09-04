# 11 — Enforce Must-Retake registration priority

**What to build:** Require Students to address offered below-C+ Preparatory and Core Courses before unrelated for-credit study, while selecting a deterministic feasible set when every obligation cannot fit.

**Blocked by:** 07 — Revoke decisions and invalidate stale credit requests; 09 — Classify and observe Academic Standing.

**Status:** ready-for-agent

- [ ] A finalized or corrected below-C+ Preparatory/Core Grade idempotently creates a Must-Retake Obligation; a successful later Attempt or effective qualifying Exemption ends it without deleting history.
- [ ] The registration validator considers active obligations at initial submission, free add, late add, Advisor modification, and every final approval boundary.
- [ ] All offered obligations must be selected when feasible under current load ceiling and timetable.
- [ ] When they cannot all fit, Preparatory obligations are considered by earliest checkpoint, origin time, then Course code, followed by Core obligations by oldest failed Attempt then Course code.
- [ ] Greedy selection includes a Course only when one eligible Offering fits remaining load and timetable and never skips a feasible higher-priority Course to optimize Course count, CH, or capacity.
- [ ] The Student chooses any eligible non-conflicting Offering for each required Course; the Advisor may switch that Offering but cannot remove the Course.
- [ ] An unrelated Student-created timetable conflict must be removed or switched during an open modification window and does not make the obligation unavailable.
- [ ] When no obligation is offered, unrelated registration may proceed; after modification closes, no Enrollment is auto-dropped and an unselected obligation carries forward.
- [ ] Audit Enrollment neither satisfies nor clears an obligation, and normal load ceilings including Probation remain enforced.
- [ ] Eligible-course, cart, request, schedule, and Advisor views explain required Courses and permitted Offering choices in EN/AR without duplicating the validator.
- [ ] Ordering unit tests and every Enrollment mutation's PostgreSQL feature tests cover availability, feasibility, ties, successful remediation, corrected Exemption, authorization, notifications, and generated API/UI behavior.
