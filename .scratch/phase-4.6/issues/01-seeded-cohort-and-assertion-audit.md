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

**Status:** ready-for-human

- [x] A new deterministic seeder layer adds ~150–250 Students from a fixed seed, spread across
      Programs, degree levels, enrollment terms and the five existing Semesters
- [x] Generated Students carry a realistic spread of Enrollments, Course Statuses, finalized
      Grades and attendance marks — enough that a GPA distribution has a visible shape and some
      Students fall below 3.0
- [x] Every existing named persona is byte-for-byte unchanged; `r02@`, `r03@` and `multi@` keep
      the exact footprints Phase 4.5 gave them
- [x] Seeding stays **idempotent** and every window stays a **relative offset from seed time**
- [x] Generated accounts live inside the reserved namespace and cannot collide with Playwright's
      own data
- [x] Every Pest assertion depending on an absolute count, a sole record or an exhaustive list is
      re-expressed against a named persona or a relative fact
- [x] Every Playwright assertion of the same kind is re-expressed the same way; `dashboard.spec.ts`
      still asserts Card presence *and* absence for all seven accounts
- [x] `composer gate` and `npm run gate` are green on a fresh seed
- [x] The seed's wall-clock cost is measured and recorded in the ticket's close-out comment, since
      every gate run and Playwright run now pays it

## Phase 7 prerequisite disposition

This ticket is in the authorized executable closure for Phase 7; its implementation
is on `phase-7-academic-reports-bilingual`. Existing capabilities were verified
before extending them. See [the Phase 7 tracker](../../phase-7/tracker.md) for
focused tests, runtime evidence and the fixed review baseline, and
[the consolidated review](../../phase-7/review.md) for resolved Standards/Spec
findings. Both complete package gates passed: backend 1,334 tests / 8,458 assertions;
frontend 684 unit tests and 179 serial browser checks. The additional CI timing
regression and two affected workflows passed (3/3, 12.4m); see tracker for the
180-test discovery versus 179-test full-gate distinction. Any by-hand acceptance remains pending. Other Phase 4.6 tickets are
outside this goal and are not marked complete by this disposition.

The named-persona preservation criterion applies to this generated cohort layer.
The later approved Phase 7 worked-case allowance adds three advisees/open Cases
to r03 and departmental review work to r06 while retaining original persona and
Student records. It reuses MSC-CE/two existing Courses and isolates its Instructor.
No global unchanged-work-population parity against the starting baseline is claimed.
