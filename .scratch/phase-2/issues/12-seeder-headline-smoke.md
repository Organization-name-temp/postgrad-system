# 12 — E-JUST admissions seeder + headline multi-role Playwright smoke (capstone)

**What to build:** A realistic, deterministic seeder that populates admissions so every screen demonstrates immediately, and one comprehensive end-to-end smoke that drives a stranger all the way to admitted Student across every role. The phase capstone.

**Blocked by:** 10 — Final decision + acceptance mint + Student anchor; 11 — SLA + interview-reminder artisan commands

**Status:** ready-for-agent

- [ ] A **deterministic** seeder (runs after the Phase-1 catalog seeder): one **open Fall 2026** Admission Cycle (window spanning the current date) and optionally a closed past cycle; **3–4 Advisors**; **a Dean per Faculty** with seeded `faculty_dean_assignments`; a couple of interviewers; **one Application resting in each status** (Draft … Rejected), the `Accepted` one having produced a real `Student` (University ID, advisor, R02); open **interview slots**; and a small **sample document file** on the private disk referenced by seeded document rows.
- [ ] The seeder must **not** consume the demo applicant's email / national ID (the smoke registers its own fresh applicant).
- [ ] One **comprehensive multi-role Playwright smoke** against the real stack: a fresh applicant registers into the open cycle → completes the wizard (verification link on screen, upload a document, pick a qualifying program) → finalizes → **Student Affairs** approves → **Dean** approves for interview → SA schedules a slot → **interviewer** records an evaluation → SA completes → **Dean** accepts (advisor pre-filled) → the applicant re-signs-in as a **Student** with a visible University ID.
- [ ] `@axe-core` reports no serious/critical violations on the new screens; the smoke is green in CI with the HTML report / per-step screenshots uploaded as artifacts.
- [ ] Full `composer gate` + `npm run gate` green.
