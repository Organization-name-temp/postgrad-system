<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Identity\Public\StudentSummary;

final readonly class RegistrationPolicy
{
    public function ceiling(int $studentId): int
    {
        return (int) (DB::table('registration_policy_projections')->where('student_id', $studentId)->value('registration_ceiling_ch')
            ?? config('enrollment.max_credit_hours'));
    }

    /** @param list<CartLine> $lines */
    public function exceptions(StudentSummary $student, string $semesterName, array $lines): CreditHoursExceptions
    {
        $load = array_sum(array_map(fn (CartLine $line): int => $line->mode === RegistrationMode::Audit ? 0 : $line->totalCh, $lines));
        if ($load < 1 || $load > 5) {
            return new CreditHoursExceptions;
        }

        $basis = DB::table('registration_policy_projections')->where('student_id', $student->studentId)->first();
        if ($basis === null || $basis->remaining_exact_targets === null) {
            return new CreditHoursExceptions(firstSemester: $student->enrollmentTerm === $semesterName);
        }

        $selected = array_fill_keys(array_map(fn (CartLine $line): int => $line->courseId, array_filter($lines, fn (CartLine $line): bool => $line->mode !== RegistrationMode::Audit)), true);
        $contains = static fn (array $targets): bool => array_diff($targets, array_keys($selected)) === [];
        $selectedElective = array_sum(array_map(fn (CartLine $line): int => $line->classification === EnrollmentClassification::ResearchCourse || $line->classification === EnrollmentClassification::InternalElective || $line->classification === EnrollmentClassification::ExternalElective ? $line->totalCh : 0, array_filter($lines, fn (CartLine $line): bool => $line->mode !== RegistrationMode::Audit)));
        $remainingPreparatory = json_decode((string) $basis->remaining_preparatory_targets, true, flags: JSON_THROW_ON_ERROR);
        $remainingExact = json_decode((string) $basis->remaining_exact_targets, true, flags: JSON_THROW_ON_ERROR);

        return new CreditHoursExceptions(
            firstSemester: $student->enrollmentTerm === $semesterName && $remainingPreparatory !== [] && $contains($remainingPreparatory),
            approachingGraduationCompletion: $basis->total_remaining_ch >= 1 && $basis->total_remaining_ch <= 5 && $contains($remainingExact) && $selectedElective >= $basis->remaining_elective_ch,
        );
    }
}
