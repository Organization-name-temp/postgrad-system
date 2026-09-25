<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RemediationState;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RemediationObligation;

final class RequiredRetakeValidator
{
    public function __construct(private readonly ?CourseCatalog $courses = null, private readonly ?AcademicCalendar $calendar = null) {}

    /**
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    public function validate(int $studentId, int $semesterId, array $lines, int $ceiling): array
    {
        $mustRetakes = MustRetakeObligation::query()->where('student_id', $studentId)->where('state', 'Active')->orderBy('id')->get();
        $remediation = RemediationObligation::query()->where('student_id', $studentId)->where('state', RemediationState::Active)
            ->orderBy('id')->get();
        $courses = $this->courses ?? app(CourseCatalog::class);
        $calendar = $this->calendar ?? app(AcademicCalendar::class);
        $obligations = $remediation->map(fn (RemediationObligation $item): array => [
            'course_id' => (int) $item->course_id,
            'credit_hours' => $courses->find((int) $item->course_id)->totalCh ?? 0,
            'priority' => [0, $item->effective_deadline_at?->getTimestamp() ?? PHP_INT_MAX, $item->created_at->getTimestamp(), $courses->find((int) $item->course_id)->code, $item->id],
        ])->concat($mustRetakes->map(fn ($item): array => [
            'course_id' => (int) $item->course_id,
            'credit_hours' => (int) $item->credit_hours,
            'priority' => $this->mustRetakePriority($item, $courses, $calendar),
        ]))->sort(fn (array $a, array $b): int => $a['priority'] <=> $b['priority'])->unique('course_id')->values();
        if ($obligations->isEmpty()) {
            return [];
        }

        $offerings = CourseOffering::query()->with('meetings')
            ->where('semester_id', $semesterId)
            ->whereIn('course_id', $obligations->pluck('course_id'))
            ->orderBy('id')->get()->groupBy('course_id');
        $existing = Enrollment::query()->active()->with('offering')->where('student_id', $studentId)
            ->whereHas('offering', fn ($query) => $query->where('semester_id', $semesterId))->get();
        $addressed = array_fill_keys($existing->filter(fn (Enrollment $item): bool => $item->course_status !== CourseStatus::Audit)
            ->map(fn (Enrollment $item): int => (int) $item->offering->course_id)->all(), true);
        foreach ($lines as $line) {
            if ($line->mode !== RegistrationMode::Audit) {
                $addressed[$line->courseId] = true;
            }
        }

        $remaining = max(0, $ceiling - $existing->sum('credit_hours'));
        $required = [];
        $selectedMeetings = [];
        foreach ($obligations as $obligation) {
            $choices = $offerings->get($obligation['course_id'], collect());
            foreach ($choices as $offering) {
                $hours = $obligation['credit_hours'];
                $meetings = $offering->meetings->map(fn ($meeting): MeetingTime => MeetingTime::fromClock($meeting->weekday->value, $meeting->starts_at, $meeting->ends_at))->all();
                if ($hours <= $remaining && ! $this->clashes($meetings, $selectedMeetings)) {
                    $required[] = $obligation['course_id'];
                    $remaining -= $hours;
                    $selectedMeetings = [...$selectedMeetings, ...$meetings];
                    break;
                }
            }
        }

        $missing = array_values(array_filter($required, fn (int $courseId): bool => ! isset($addressed[$courseId])));
        if ($missing === []) {
            return [];
        }

        return [new CartViolation(
            CartViolation::MUST_RETAKE_REQUIRED,
            __('credit_decisions.registration.required_obligation'),
            $lines === [] ? [] : array_map(fn (CartLine $line): int => $line->offeringId, $lines),
        )];
    }

    /** @return array{int, int, int, string, int} */
    private function mustRetakePriority(MustRetakeObligation $item, CourseCatalog $courses, AcademicCalendar $calendar): array
    {
        $origin = Enrollment::query()->with('offering')->find($item->origin_enrollment_id);
        $semester = $origin?->offering === null ? null : $calendar->find((int) $origin->offering->semester_id);
        $originTime = $semester?->startsAt->getTimestamp() ?? $item->created_at->getTimestamp();
        $checkpoint = $semester?->gradeSubmissionDeadlineAt->getTimestamp() ?? $originTime;

        return [
            $item->category === 'Preparatory' ? 0 : 1,
            $item->category === 'Preparatory' ? $checkpoint : $originTime,
            $originTime,
            $courses->find((int) $item->course_id)->code,
            $item->id,
        ];
    }

    /**
     * @param  list<MeetingTime>  $left
     * @param  list<MeetingTime>  $right
     */
    private function clashes(array $left, array $right): bool
    {
        foreach ($left as $a) {
            foreach ($right as $b) {
                if ($a->overlaps($b)) {
                    return true;
                }
            }
        }

        return false;
    }
}
