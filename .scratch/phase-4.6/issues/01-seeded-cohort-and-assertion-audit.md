# 01 — Seeded cohort + assertion audit

**What to build:** A reviewer runs `php artisan migrate:fresh --seed --force` and gets a world
big enough to have *shape*: a Program whose Students spread across the grading bands rather than
clustering on four of them, a screening queue that runs past one page, and an oversight list where
sorting by CGPA means something. Today the seeders are ~1,660 lines of handcrafted personas and
roughly a dozen Students — invisible while every screen is a list you navigate into, and glaring
the moment lists paginate and figures become distributions.

**Every named persona stays exactly as written.** `r01@`–`r10@`, `multi@`, and the whole
`@seed.ejust.test` cohort — applicants in each Admissions status, advisors, deans, interviewers,
Department Heads, the `student.*` cohort — are what the existing Playwright smokes assert against,
and moving any of them breaks four phases of tests for no gain. This ticket adds a **generated
layer on top**: roughly 150–250 additional Students distributed across Programs, enrollment terms,
Semesters, Course Statuses, grades and attendance, drawn from a **fixed faker seed** so the world
stays deterministic and idempotent, and living inside the reserved namespace so it can never
collide with a smoke's own data.

**This is a prefactor, and its position is the point.** It lands *before* the response envelope
changes because both jobs touch the same assertions. Grow the seed after migrating the endpoints
and every count-based Pest and Playwright assertion gets edited twice; grow it first and the
migration tickets inherit a world that already paginates.

**The audit is the real work, not the generation.** Any test asserting an absolute count, a first
row, an "only record" or an exhaustive list is now wrong. Each one gets re-expressed against the
named personas it actually cares about, or against a relative fact, so it stays meaningful as the
generated layer grows or shrinks later.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] A new deterministic seeder layer adds ~150–250 Students from a fixed seed, spread across
      Programs, degree levels, enrollment terms and the five existing Semesters
- [ ] Generated Students carry a realistic spread of Enrollments, Course Statuses, finalized
      Grades and attendance marks — enough that a GPA distribution has a visible shape and some
      Students fall below 3.0
- [ ] Every existing named persona is byte-for-byte unchanged; `r02@`, `r03@` and `multi@` keep
      the exact footprints Phase 4.5 gave them
- [ ] Seeding stays **idempotent** and every window stays a **relative offset from seed time**
- [ ] Generated accounts live inside the reserved namespace and cannot collide with Playwright's
      own data
- [ ] Every Pest assertion depending on an absolute count, a sole record or an exhaustive list is
      re-expressed against a named persona or a relative fact
- [ ] Every Playwright assertion of the same kind is re-expressed the same way; `dashboard.spec.ts`
      still asserts Card presence *and* absence for all seven accounts
- [ ] `composer gate` and `npm run gate` are green on a fresh seed
- [ ] The seed's wall-clock cost is measured and recorded in the ticket's close-out comment, since
      every gate run and Playwright run now pays it
