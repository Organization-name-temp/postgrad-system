# 11 — SLA + interview-reminder artisan commands

**What to build:** Two manually-run console commands that nudge humans without touching the workflow: one flags applications overdue for Student-Affairs or Dean review, the other reminds of upcoming interviews — both by emitting notifications only. This ticket introduces the console-command test seam.

**Blocked by:** 09 — Interview slots + scheduling + evaluation

**Status:** ready-for-agent

- [ ] `admissions:flag-overdue` computes each application's review age in **business days** (excluding the Egyptian Fri/Sat weekend, no holiday calendar), compares it to the config SLA, and emits an overdue Notification to the relevant reviewer for every application past its SLA. It **changes no application status**.
- [ ] `admissions:send-interview-reminders` emits reminder Notifications for interviews roughly 48h and 24h out. It **changes no status**.
- [ ] SLA thresholds are config-driven (`sa_screening_sla_days` / `dean_review_sla_days`, default 5); aging is derived on-demand, never stored.
- [ ] **Console-command feature tests (the one new seam):** invoke each command against seeded state and assert the exact Notification rows produced — and that no application status changed. The business-days helper is unit-tested directly (weekday counting across Fri/Sat).
- [ ] All gates green.
