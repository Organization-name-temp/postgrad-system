<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Http\Concerns\CountsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\MintsEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\ReadsExistingEnrollments;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesCartLines;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;

final class CapacityOverrideFinalizer
{
    use CountsEnrollments;
    use MintsEnrollments;
    use ReadsExistingEnrollments;
    use ResolvesCartLines;
    use ResolvesOfferingRefs;

    public function __construct(
        private readonly StudentDirectory $students,
        private readonly AcademicCalendar $calendar,
        private readonly CourseCatalog $courses,
        private readonly Directory $directory,
        private readonly EligibilityResolver $eligibility,
        private readonly RegistrationPolicy $policy,
        private readonly RequiredRetakeValidator $required,
    ) {}

    /** Returns true only when the unchanged batch was atomically approved and minted. */
    public function finalizeIfReady(RegistrationRequest $request): bool
    {
        if ($request->status !== RegistrationStatus::AwaitingCapacityOverride) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.request_not_awaiting_capacity')]);
        }
        $request->load('lines.offering.meetings');
        $approved = $request->lines->filter(fn (RegistrationRequestLine $line): bool => $line->decision === LineDecision::Approved)->values();
        if ($approved->isEmpty()) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.batch_changed')]);
        }
        $student = $this->students->findByStudentId((int) $request->student_id);
        $semester = $this->calendar->find((int) $request->semester_id);
        if ($student === null || $semester === null) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.batch_changed')]);
        }
        if ($student->lifecycleStatus !== StudentLifecycleStatus::Active) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.student_inactive')]);
        }

        [$cartLines, $missing] = $this->resolve($approved, $student);
        if ($missing !== []) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.batch_changed')]);
        }

        $fullLineIds = [];
        foreach ($cartLines as $lineId => $cartLine) {
            if ($cartLine->seatsRemaining !== null && $cartLine->seatsRemaining < 1) {
                $fullLineIds[$lineId] = $cartLine;
                $requirement = CapacityOverrideRequirement::query()->firstOrCreate(
                    ['registration_request_id' => $request->id, 'registration_request_line_id' => $lineId],
                    ['course_offering_id' => $cartLine->offeringId, 'course_id' => $cartLine->courseId, 'department_id' => $this->courses->find($cartLine->courseId)?->departmentId, 'state' => 'Pending'],
                );
                if ($requirement->state !== 'Approved') {
                    return false;
                }
            }
        }
        CapacityOverrideRequirement::query()->where('registration_request_id', $request->id)->whereNotIn('registration_request_line_id', array_keys($fullLineIds))->where('state', 'Pending')->update(['state' => 'NoLongerRequired']);

        $lines = array_values($cartLines);
        $existing = $this->existingEnrollments($student->studentId, $semester->id);
        $validator = new CartValidator(CreditHoursValidator::fromConfig($this->policy->ceiling($student->studentId)));
        $violations = array_values(array_filter(
            $validator->validate($lines, $this->exceptionsFor($student, $semester, $lines, $this->policy), $existing),
            fn (CartViolation $violation): bool => $violation->code !== CartViolation::OFFERING_FULL,
        ));
        $violations = [...$violations, ...$this->required->validate($student->studentId, $semester->id, $lines, $this->policy->ceiling($student->studentId))];
        [$mintPlan, $retakeViolations] = $this->buildMintPlan($cartLines, $existing);
        $violations = [...$violations, ...$retakeViolations];
        if ($violations !== []) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->code][] = $violation->message;
            }
            throw ValidationException::withMessages($errors);
        }
        if (count($mintPlan) !== $approved->count()) {
            throw ValidationException::withMessages(['lines' => __('credit_decisions.registration.batch_changed')]);
        }

        $this->mintEnrollments($student->studentId, $mintPlan);
        $request->update(['status' => RegistrationStatus::Approved, 'decided_at' => now()]);

        return true;
    }

    /**
     * @param  Collection<int, RegistrationRequestLine>  $lines
     * @return array{0: array<int, CartLine>, 1: list<int>}
     */
    private function resolve(Collection $lines, StudentSummary $student): array
    {
        $offerings = $lines->pluck('offering')->filter()->keyBy('id');
        $this->attachOfferingRefs($offerings, $this->courses, $this->directory);
        $this->attachEnrollmentCounts($offerings);
        $classifications = $this->eligibility->classify($student, $offerings);
        $resolved = [];
        $missing = [];
        foreach ($lines as $line) {
            $offering = $offerings->get($line->course_offering_id);
            $course = $offering?->getAttribute('resolved_course');
            $classification = $classifications[$line->course_offering_id] ?? null;
            if (! $offering instanceof CourseOffering || ! $course instanceof CourseSummary || $classification === null) {
                $missing[] = (int) $line->id;

                continue;
            }
            $resolved[(int) $line->id] = new CartLine((int) $offering->id, (int) $offering->course_id, $course->code, $line->mode, $course->totalCh, $classification, $offering->seatsRemaining(), $this->meetingSlots($offering));
        }

        return [$resolved, $missing];
    }
}
