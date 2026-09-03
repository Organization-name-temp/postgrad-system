# 02 — Seeder: data for the canonical role accounts

**What to build:** Signing in as any canonical `rNN@ejust.test` account shows a populated,
representative system rather than an empty one.

Today the canonical role accounts are **authorization fixtures**: they exist to prove the
§3.2 permission matrix, and several of them own no records at all. Every persona with a
real academic footprint lives under the named `advisor.*` / `student.*` seed namespace
instead. Specifically, `r02@` holds no Enrollments or Grades, `r03@` advises nobody, and
**`multi@` — the Advisor + Instructor account seeded in Phase 0 precisely to prove the
multi-role path — has neither advisees nor Offerings.**

That was invisible for four phases, because every screen was a list you navigate *into*.
It becomes glaring the moment the Dashboard is made of counts: an Instructor's dashboard
would render rich while an Advisor's renders zeros beside it, and the difference would read
as broken code rather than an empty account.

`multi@` is the important one. It is the **only** account that demonstrates the composition
rule this whole phase rests on — one page showing an advisee queue *and* an ungraded-sheets
Card, with no role switcher anywhere. Left empty, the phase ships its headline architectural
property with no way to show it.

This ticket is placed second, not last, deliberately: Cards built against empty accounts is
exactly how a Card that renders only zeros reaches a demo. It touches no dashboard code and
is independent of ticket 01 — the two run in parallel.

**Blocked by:** None — can start immediately, in parallel with ticket 01.

**Status:** ready-for-agent

- [ ] `r02@` is a Student with Enrollments across the seeded Semesters and finalized Grades, so a CGPA and at least one Semester GPA are computable for them.
- [ ] `r03@` is an Advisor holding advisees, with at least one registration request genuinely pending their approval.
- [ ] `multi@` holds **both** advisees with a pending request **and** Course Offerings with an unfinalized Grade Sheet, so its dashboard shows the union of two roles' work.
- [ ] The canonical accounts already carrying data — Instructor, Student Affairs, Department Head, Dean — keep it, and their existing relationships are not disturbed.
- [ ] Seeders stay **deterministic and idempotent**, and every window remains a relative offset from seed time so the seeded world never goes stale.
- [ ] The reserved persona namespace is still reserved: the Playwright suites' own data cannot collide with seeded records.
- [ ] `migrate:fresh --seed` runs clean from an empty database, and every existing Phase 0–4 Playwright smoke still passes against the extended seed — this ticket must not perturb a single existing assertion.
- [ ] No new migration, Permission, Role or module is introduced.
- [ ] `composer gate` green.
