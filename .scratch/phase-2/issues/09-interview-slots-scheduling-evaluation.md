# 09 — Interview slots + scheduling + evaluation

**What to build:** Student Affairs sets up a pool of interview slots and schedules approved applicants into them; the applicant confirms or reschedules once; the assigned interviewer records an evaluation; and Student Affairs marks the interview complete so it can proceed to a decision. This is the minimal Calendar, built just-in-time.

**Blocked by:** 08 — Dean review + Dean↔Faculty assignment + admin assignment UI

**Status:** ready-for-agent

- [ ] `InterviewSlot` (scheduled_at, duration, `interviewer_id` = a faculty User, mode in-person/virtual, location or meeting link) created by Student Affairs under `interview.schedule`; capacity 1 (open or taken).
- [ ] Student Affairs schedules an `InterviewScheduled` applicant into an **open** slot, creating an `Interview` (one per Application) and notifying the applicant with the interview details.
- [ ] The applicant can **confirm**, or request **exactly one reschedule** into another open slot (a second reschedule is refused).
- [ ] Only the slot's **assigned interviewer** may record an evaluation score + notes (anyone else → 403).
- [ ] Student Affairs marks the interview **complete** → `InterviewCompleted`.
- [ ] Frontend: slot management, scheduling, applicant confirm/reschedule, interviewer evaluation entry.
- [ ] Pest feature tests: slot CRUD, scheduling + notification, one-reschedule cap, row-level evaluation authorization, completion transition. All gates green.
