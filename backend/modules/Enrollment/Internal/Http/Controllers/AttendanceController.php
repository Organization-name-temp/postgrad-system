<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Http\Concerns\ComputesAttendance;
use Modules\Enrollment\Internal\Http\Concerns\NotifiesRegistration;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Support\AttendanceCalculator;
use Modules\Enrollment\Internal\Support\SessionSchedule;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;
use Modules\Identity\Public\UserSummary;
use Modules\Notifications\Public\Contracts\Notifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Enrollment
 *
 * The **attendance register** (ADR-0019, ticket 08, a deliberate SRS extension) — the
 * Instructor-facing surface, authorised **by assignment**, exactly as Phase 2 authorised
 * interviewers: only an Offering's assigned Instructor (R04) may read/generate its sessions,
 * cancel them, and mark attendance; anyone else is forbidden. No new permission is added and
 * the §3.2 matrix is untouched, so every route is `auth:sanctum` and the controller enforces
 * the assignment itself. (ADR-0015's administrative override — R09/R10 reading unscoped and
 * writing with an Audit Log row — is hardened across every Phase-3 scope guard in ticket 10.)
 *
 * Each Offering's weekly meeting times generate {@see ClassSession} rows across its Semester
 * (the pure {@see SessionSchedule} seam, materialised idempotently the first time the register
 * is opened). The Instructor cancels sessions that did not happen (a cancelled session counts
 * in no denominator — the holiday answer) and records a binary present/absent mark per active
 * Enrollment. A Student's attendance percentage is always **derived**, never stored
 * ({@see ComputesAttendance}); the single consequence is the **audit-at-risk** flag — an Audit
 * Enrollment whose percentage first crosses below the threshold raises a chip and one
 * `attendance.audit_at_risk` notification, fired on the downward crossing, never on every mark.
 */
class AttendanceController
{
    use ComputesAttendance;
    use NotifiesRegistration;
    use ResolvesOfferingRefs;

    /**
     * The signed-in Instructor's offerings ("My Offerings").
     *
     * Every Course Offering the acting user teaches, across all Semesters — the Phase-4
     * grading link, born here. Each carries its Semester, resolved Course, weekly meeting
     * times, and its active roster size, so the instructor sees their teaching load at a
     * glance. A user who teaches nothing gets an empty list (no permission gate — teaching
     * is the scope). Requires only authentication.
     *
     * @response 200 {"data": [{"id": 1, "student_count": 12, "semester": {"id": 1, "term": "Spring", "year": 2026, "name_en": "Spring 2026", "name_ar": "ربيع 2026"}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}]}
     */
    public function myOfferings(
        Request $request,
        CourseCatalog $courses,
        Directory $directory,
        AcademicCalendar $calendar,
    ): JsonResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();

        /** @var Collection<int, CourseOffering> $offerings */
        $offerings = CourseOffering::query()
            ->where('instructor_id', $userId)
            ->with('meetings')
            ->orderByDesc('semester_id')
            ->orderBy('id')
            ->get();

        $this->attachOfferingRefs($offerings, $courses, $directory);
        $rosterCounts = $this->rosterCounts($offerings->map(fn (CourseOffering $o): int => (int) $o->getKey())->all());
        $semesters = $this->semestersFor($offerings, $calendar);

        $rows = $offerings->map(fn (CourseOffering $offering): array => [
            'id' => (int) $offering->getKey(),
            'student_count' => $rosterCounts[$offering->getKey()] ?? 0,
            'semester' => $semesters[$offering->semester_id] ?? null,
            'course' => $this->courseArray($offering),
            'meetings' => $this->meetingArrays($offering),
        ])->all();

        return response()->json(['data' => $rows]);
    }

    /**
     * An Offering's attendance register — sessions + roster + derived percentages.
     *
     * The assigned Instructor's register for one Offering: its Class Sessions (generated from
     * the meeting times across the Semester the first time this is opened — idempotent, so
     * re-opening never duplicates), and its roster of **active** Enrollments (withdrawn
     * students keep their history but leave the roster). Each roster row carries the student's
     * identity, their per-session marks, and their derived attendance percentage with the
     * audit-at-risk flag. Authorised by assignment — only this Offering's Instructor (else
     * 403).
     *
     * @urlParam offering_id integer required The ID of the offering. Example: 1
     *
     * @response 200 {"data": {"offering": {"id": 1, "semester": {"id": 1, "term": "Spring", "year": 2026, "name_en": "Spring 2026", "name_ar": "ربيع 2026"}, "course": {"id": 1, "code": "DEP501", "name_en": "Advanced Algorithms", "name_ar": "خوارزميات متقدمة", "level": 500, "total_ch": 3, "research_type": null, "is_active": true}, "instructor": {"id": 4, "name": "Dr. Instructor", "email": "instructor@ejust.test"}, "meetings": [{"weekday": 1, "starts_at": "09:00", "ends_at": "11:00"}]}, "sessions": [{"id": 10, "date": "2026-02-16", "weekday": 1, "starts_at": "09:00", "ends_at": "11:00", "cancelled": false}], "roster": [{"enrollment_id": 5, "course_status": "Audit", "student": {"student_id": 7, "university_id": "2025-MSC-CE-0002", "name": "Sara Student"}, "attendance": {"present": 8, "recorded": 10, "percentage": 80, "at_risk": false}, "marks": {"10": true}}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     */
    public function register(
        Request $request,
        CourseOffering $offering,
        CourseCatalog $courses,
        Directory $directory,
        AcademicCalendar $calendar,
        StudentDirectory $students,
    ): JsonResponse {
        $this->assertAssigned($offering, $request);

        $offering->load('meetings');
        $semester = $calendar->find((int) $offering->semester_id);
        abort_if($semester === null, 404, 'That semester no longer exists.');

        $this->ensureSessions($offering, $semester);

        $sessions = ClassSession::query()
            ->where('course_offering_id', $offering->getKey())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->get();

        /** @var Collection<int, Enrollment> $roster */
        $roster = Enrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->active()
            ->orderBy('id')
            ->get();

        $this->attachOfferingRefs(collect([$offering]), $courses, $directory);

        return response()->json([
            'data' => [
                'offering' => [
                    'id' => (int) $offering->getKey(),
                    'semester' => $this->semesterArray($semester),
                    'course' => $this->courseArray($offering),
                    'instructor' => $this->instructorArray($offering),
                    'meetings' => $this->meetingArrays($offering),
                ],
                'sessions' => $sessions->map(fn (ClassSession $s): array => $s->toRegisterArray())->all(),
                'roster' => $this->rosterRows($roster, $students, $directory),
            ],
        ]);
    }

    /**
     * Cancel or reinstate a Class Session.
     *
     * Toggles a session's cancellation (Eid, illness — also the holiday answer). A cancelled
     * session counts in no attendance denominator; reinstating it counts it again. Correctable
     * either way. Authorised by assignment — only the session's Offering's Instructor (else
     * 403).
     *
     * @urlParam class_session_id integer required The ID of the class session. Example: 10
     *
     * @bodyParam cancelled boolean required Whether the session is cancelled. Example: true
     *
     * @response 200 {"data": {"id": 10, "date": "2026-02-16", "weekday": 1, "starts_at": "09:00", "ends_at": "11:00", "cancelled": true}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     */
    public function updateSession(
        Request $request,
        ClassSession $classSession,
        StudentDirectory $students,
        CourseCatalog $courses,
        Notifier $notifier,
    ): JsonResponse {
        $offering = $this->offeringOf($classSession);
        $this->assertAssigned($offering, $request);

        $validated = $request->validate([
            'cancelled' => ['required', 'boolean'],
        ]);

        // Cancelling/reinstating changes the denominator for every Audit enrollment with a
        // mark on this session, so this is a register change too (ADR-0019: recompute around
        // each register change). Snapshot the audit roster's percentages, toggle, then notify
        // any that crossed below — cancelling a session a student was present at can push an
        // Audit enrollment under the threshold just as an absence mark can.
        $auditRoster = $this->activeAuditRoster($offering);
        $auditIds = $auditRoster->keys()->map(fn ($id): int => (int) $id)->all();
        $before = $this->attendanceTallies($auditIds);

        $classSession->update([
            'cancelled_at' => $validated['cancelled'] ? now() : null,
        ]);

        $this->notifyDownwardCrossings($auditIds, $before, $auditRoster, $offering, $students, $courses, $notifier);

        return response()->json(['data' => $classSession->toRegisterArray()]);
    }

    /**
     * Record (or correct) a session's attendance register.
     *
     * Saves a binary present/absent mark per active Enrollment for one Class Session; a save
     * upserts, so a correction overwrites. Only the Offering's active roster may be marked;
     * a cancelled session cannot be marked. Around the save the percentages are recomputed:
     * any **Audit** Enrollment whose percentage crosses below the threshold on this save
     * raises one `attendance.audit_at_risk` notification (fired once on the downward crossing,
     * never on every mark). Authorised by assignment — only this Offering's Instructor (else
     * 403).
     *
     * @urlParam class_session_id integer required The ID of the class session. Example: 10
     *
     * @bodyParam marks object[] required One entry per student to mark.
     * @bodyParam marks[].enrollment_id integer required An active Enrollment on this offering. Example: 5
     * @bodyParam marks[].present boolean required Whether the student was present. Example: true
     *
     * @response 200 {"data": {"session_id": 10, "attendance": [{"enrollment_id": 5, "present": 8, "recorded": 10, "percentage": 80, "at_risk": false}]}}
     * @response 403 scenario="not the assigned instructor" {"message": "You are not assigned to teach this offering."}
     * @response 409 scenario="session cancelled" {"message": "A cancelled session cannot be marked."}
     * @response 422 scenario="not on this roster" {"message": "One or more students are not on this offering's roster.", "errors": {"marks": ["One or more students are not on this offering's roster."]}}
     */
    public function mark(
        Request $request,
        ClassSession $classSession,
        StudentDirectory $students,
        CourseCatalog $courses,
        Notifier $notifier,
    ): JsonResponse {
        $offering = $this->offeringOf($classSession);
        $this->assertAssigned($offering, $request);

        if ($classSession->isCancelled()) {
            throw new ConflictHttpException('A cancelled session cannot be marked.');
        }

        $validated = $request->validate([
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.enrollment_id' => ['required', 'integer'],
            'marks.*.present' => ['required', 'boolean'],
        ]);

        // The submitted marks as `enrollment id => present`.
        $marks = $this->markMap($validated['marks']);
        $markedIds = array_keys($marks);

        // The active roster keyed by id — only these may be marked (a withdrawn student has
        // left the roster; a stranger's enrollment is not this offering's).
        $roster = Enrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->active()
            ->get()
            ->keyBy(fn (Enrollment $e): int => (int) $e->getKey());

        $offRoster = array_filter($markedIds, fn (int $id): bool => ! $roster->has($id));
        if ($offRoster !== []) {
            throw ValidationException::withMessages([
                'marks' => "One or more students are not on this offering's roster.",
            ]);
        }

        // The Audit enrollments in this save are the only ones that can go audit-at-risk;
        // snapshot their percentage before the save so we can detect a downward crossing.
        $auditIds = array_values(array_filter(
            $markedIds,
            fn (int $id): bool => $roster->get($id)?->course_status === CourseStatus::Audit,
        ));

        $before = $this->attendanceTallies($auditIds);

        DB::transaction(function () use ($marks, $classSession): void {
            foreach ($marks as $enrollmentId => $present) {
                AttendanceMark::updateOrCreate(
                    ['class_session_id' => $classSession->getKey(), 'enrollment_id' => $enrollmentId],
                    ['present' => $present],
                );
            }
        });

        // Side effect after the write: one notification per fresh downward crossing (ADR-0019).
        $this->notifyDownwardCrossings($auditIds, $before, $roster, $offering, $students, $courses, $notifier);

        return response()->json([
            'data' => [
                'session_id' => (int) $classSession->getKey(),
                'attendance' => $this->markedAttendance($markedIds, $roster),
            ],
        ]);
    }

    /**
     * The submitted marks reduced to `enrollment id => present`.
     *
     * @param  array<int, array{enrollment_id: int, present: bool}>  $marks
     * @return array<int, bool>
     */
    private function markMap(array $marks): array
    {
        $map = [];
        foreach ($marks as $mark) {
            $map[(int) $mark['enrollment_id']] = (bool) $mark['present'];
        }

        return $map;
    }

    /**
     * The acting user is this Offering's assigned Instructor, or a 403 — authorisation by
     * assignment (no permission gate; the Phase-2 interviewer precedent). ADR-0015's admin
     * override is layered on in ticket 10.
     */
    private function assertAssigned(CourseOffering $offering, Request $request): void
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        abort_if((int) $offering->instructor_id !== $userId, 403, 'You are not assigned to teach this offering.');
    }

    /** The session's Offering (with meetings), or a 404 when the reference dangles. */
    private function offeringOf(ClassSession $session): CourseOffering
    {
        $offering = $session->offering()->with('meetings')->first();
        abort_if($offering === null, 404, 'That course offering no longer exists.');

        return $offering;
    }

    /**
     * Materialise the Offering's Class Sessions idempotently from its meeting rows across the
     * Semester dates (the pure {@see SessionSchedule} seam). insertOrIgnore leans on the
     * (offering, date, start) unique key, so re-opening a register — or a later meeting edit —
     * never duplicates a session and never disturbs a cancellation.
     */
    private function ensureSessions(CourseOffering $offering, SemesterSummary $semester): void
    {
        $meetings = $offering->meetings->map(fn (OfferingMeeting $m): array => $m->toSlotArray())->all();
        $descriptors = SessionSchedule::generate($semester->startsAt, $semester->endsAt, $meetings);

        if ($descriptors === []) {
            return;
        }

        $now = now();
        $rows = array_map(fn (array $d): array => [
            'course_offering_id' => (int) $offering->getKey(),
            'session_date' => $d['date'],
            'starts_at' => $d['starts_at'],
            'ends_at' => $d['ends_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $descriptors);

        DB::table('class_sessions')->insertOrIgnore($rows);
    }

    /**
     * Build the roster rows: each active Enrollment's student identity, per-session marks, and
     * derived attendance. Resolves student names/University IDs through Identity\Public in one
     * batched read each (ADR-0012).
     *
     * @param  Collection<int, Enrollment>  $roster
     * @return list<array<string, mixed>>
     */
    private function rosterRows(Collection $roster, StudentDirectory $students, Directory $directory): array
    {
        if ($roster->isEmpty()) {
            return [];
        }

        $enrollmentIds = $roster->map(fn (Enrollment $e): int => (int) $e->getKey())->all();
        $tallies = $this->attendanceTallies($enrollmentIds);
        $marksByEnrollment = $this->marksByEnrollment($enrollmentIds);

        $studentSummaries = $students->findManyByStudentId(
            $roster->pluck('student_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );
        $users = $directory->findMany(array_values(array_map(
            fn (StudentSummary $s): int => $s->userId,
            $studentSummaries,
        )));

        return $roster->map(function (Enrollment $enrollment) use ($tallies, $marksByEnrollment, $studentSummaries, $users): array {
            $tally = $tallies[(int) $enrollment->getKey()] ?? ['present' => 0, 'recorded' => 0];
            $summary = $studentSummaries[(int) $enrollment->student_id] ?? null;

            // Name via the Identity directory; fall back to the University ID, then the anchor
            // id (the Student anchor carries no name, ADR-0012).
            $name = "student #{$enrollment->student_id}";
            if ($summary !== null) {
                $user = $users[$summary->userId] ?? null;
                $name = $user !== null ? $user->name : $summary->universityId;
            }

            return [
                'enrollment_id' => (int) $enrollment->getKey(),
                'course_status' => $enrollment->course_status->value,
                'student' => [
                    'student_id' => (int) $enrollment->student_id,
                    'university_id' => $summary?->universityId,
                    'name' => $name,
                ],
                'attendance' => $this->attendanceArray(
                    $tally['present'],
                    $tally['recorded'],
                    $enrollment->course_status === CourseStatus::Audit,
                ),
                'marks' => $marksByEnrollment[(int) $enrollment->getKey()] ?? (object) [],
            ];
        })->all();
    }

    /**
     * Per-enrollment map of `session id => present` over all of an Offering's marks, so the
     * register grid renders each cell. One batched read.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, array<int, bool>>
     */
    private function marksByEnrollment(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        $map = [];
        AttendanceMark::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get(['enrollment_id', 'class_session_id', 'present'])
            ->each(function (AttendanceMark $mark) use (&$map): void {
                $map[(int) $mark->enrollment_id][(int) $mark->class_session_id] = (bool) $mark->present;
            });

        return $map;
    }

    /**
     * The freshly-recomputed attendance for the marked enrollments — the mark response body,
     * flattened with the enrollment id.
     *
     * @param  list<int>  $enrollmentIds
     * @param  Collection<int, Enrollment>  $roster
     * @return list<array<string, mixed>>
     */
    private function markedAttendance(array $enrollmentIds, Collection $roster): array
    {
        $tallies = $this->attendanceTallies($enrollmentIds);

        return array_map(function (int $id) use ($tallies, $roster): array {
            $tally = $tallies[$id] ?? ['present' => 0, 'recorded' => 0];
            $enrollment = $roster->get($id);

            return array_merge(
                ['enrollment_id' => $id],
                $this->attendanceArray(
                    $tally['present'],
                    $tally['recorded'],
                    $enrollment?->course_status === CourseStatus::Audit,
                ),
            );
        }, $enrollmentIds);
    }

    /**
     * The active **Audit** roster of an Offering keyed by enrollment id — the set whose
     * percentage a mark or a session cancel/reinstate can push below the audit threshold.
     *
     * @return Collection<int, Enrollment>
     */
    private function activeAuditRoster(CourseOffering $offering): Collection
    {
        return Enrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->active()
            ->where('course_status', CourseStatus::Audit->value)
            ->get()
            ->keyBy(fn (Enrollment $e): int => (int) $e->getKey());
    }

    /**
     * Recompute the audit-at-risk crossing around a register change and notify each Audit
     * enrollment that crossed **below** on it — the single seam shared by marking and session
     * cancel/reinstate (ADR-0019: recompute around each register change, fire once on the
     * downward crossing). `$before` is the tally snapshot taken before the change; the
     * after-tally is read here.
     *
     * @param  list<int>  $auditEnrollmentIds
     * @param  array<int, array{present: int, recorded: int}>  $before
     * @param  Collection<int, Enrollment>  $roster  must contain the audit enrollment ids
     */
    private function notifyDownwardCrossings(
        array $auditEnrollmentIds,
        array $before,
        Collection $roster,
        CourseOffering $offering,
        StudentDirectory $students,
        CourseCatalog $courses,
        Notifier $notifier,
    ): void {
        if ($auditEnrollmentIds === []) {
            return;
        }

        $after = $this->attendanceTallies($auditEnrollmentIds);
        $crossed = array_values(array_filter($auditEnrollmentIds, fn (int $id): bool => AttendanceCalculator::crossedBelow(
            AttendanceCalculator::percentage($before[$id]['present'] ?? 0, $before[$id]['recorded'] ?? 0),
            AttendanceCalculator::percentage($after[$id]['present'] ?? 0, $after[$id]['recorded'] ?? 0),
            $this->auditThreshold(),
        )));

        $this->notifyCrossings($crossed, $roster, $offering, $students, $courses, $notifier, $after);
    }

    /**
     * Fire the audit-at-risk notification for each crossed Audit Enrollment — resolving the
     * owning Student's user id (the notification recipient) through Identity\Public and the
     * course code through the Catalog facade.
     *
     * @param  list<int>  $crossedEnrollmentIds
     * @param  Collection<int, Enrollment>  $roster
     * @param  array<int, array{present: int, recorded: int}>  $after
     */
    private function notifyCrossings(
        array $crossedEnrollmentIds,
        Collection $roster,
        CourseOffering $offering,
        StudentDirectory $students,
        CourseCatalog $courses,
        Notifier $notifier,
        array $after,
    ): void {
        if ($crossedEnrollmentIds === []) {
            return;
        }

        /** @var array<int, Enrollment> $crossed */
        $crossed = [];
        foreach ($crossedEnrollmentIds as $id) {
            $enrollment = $roster->get($id);
            if ($enrollment !== null) {
                $crossed[$id] = $enrollment;
            }
        }

        $summaries = $students->findManyByStudentId(array_values(array_unique(array_map(
            fn (Enrollment $e): int => (int) $e->student_id,
            $crossed,
        ))));

        $course = $courses->find((int) $offering->course_id);
        $courseCode = $course instanceof CourseSummary ? $course->code : "course #{$offering->course_id}";

        foreach ($crossed as $enrollmentId => $enrollment) {
            $summary = $summaries[(int) $enrollment->student_id] ?? null;
            if ($summary === null) {
                continue;
            }

            $tally = $after[$enrollmentId] ?? ['present' => 0, 'recorded' => 0];
            $percentage = (int) round(AttendanceCalculator::percentage($tally['present'], $tally['recorded']) ?? 0);

            $this->notifyAuditAtRisk($notifier, $summary->userId, $courseCode, $percentage);
        }
    }

    /**
     * Active-roster counts for a set of Offerings, keyed by offering id — one batched read
     * for the My Offerings list.
     *
     * @param  list<int>  $offeringIds
     * @return array<int, int>
     */
    private function rosterCounts(array $offeringIds): array
    {
        if ($offeringIds === []) {
            return [];
        }

        return DB::table('enrollments')
            ->select('course_offering_id', DB::raw('count(*) as aggregate'))
            ->whereIn('course_offering_id', $offeringIds)
            ->where('course_status', '!=', CourseStatus::Withdrawn->value)
            ->groupBy('course_offering_id')
            ->pluck('aggregate', 'course_offering_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * The compact Semester summaries for a set of Offerings, keyed by semester id — resolved
     * once per distinct Semester through the Calendar facade (ADR-0012/0018).
     *
     * @param  Collection<int, CourseOffering>  $offerings
     * @return array<int, array<string, mixed>>
     */
    private function semestersFor(Collection $offerings, AcademicCalendar $calendar): array
    {
        $semesters = [];
        foreach ($offerings->pluck('semester_id')->unique() as $semesterId) {
            $semester = $calendar->find((int) $semesterId);
            if ($semester !== null) {
                $semesters[(int) $semesterId] = $this->semesterArray($semester);
            }
        }

        return $semesters;
    }

    /**
     * A Semester's compact display shape (id + term + year + bilingual name) — enough for the
     * register/offerings headers; the full period dates live on the student registration
     * surfaces.
     *
     * @return array<string, mixed>
     */
    private function semesterArray(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
        ];
    }

    /**
     * The Offering's resolved Course as an array, or null when the reference dangles (read
     * through {@see ResolvesOfferingRefs}).
     *
     * @return array<string, mixed>|null
     */
    private function courseArray(CourseOffering $offering): ?array
    {
        $course = $offering->getAttribute('resolved_course');

        return $course instanceof CourseSummary ? $course->toArray() : null;
    }

    /**
     * The Offering's resolved Instructor as an array, or null when the reference dangles.
     *
     * @return array<string, mixed>|null
     */
    private function instructorArray(CourseOffering $offering): ?array
    {
        $instructor = $offering->getAttribute('resolved_instructor');

        return $instructor instanceof UserSummary ? $instructor->toArray() : null;
    }

    /**
     * The Offering's weekly meeting slots as arrays (the shared wire shape).
     *
     * @return list<array{weekday: int, starts_at: string, ends_at: string}>
     */
    private function meetingArrays(CourseOffering $offering): array
    {
        return $offering->meetings->map(fn (OfferingMeeting $m): array => $m->toSlotArray())->all();
    }
}
