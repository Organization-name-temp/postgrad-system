<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RegistrationRequest;

final readonly class CapacityOverrideWorkflow
{
    public function __construct(private CourseCatalog $courses) {}

    /**
     * @param  array<int, LineDecision>  $decisions
     * @param  list<CartLine>  $cartLines
     */
    public function startIfRequired(RegistrationRequest $request, array $decisions, array $cartLines, int $advisorId, ?string $note): bool
    {
        $full = array_values(array_filter($cartLines, fn (CartLine $line): bool => $line->seatsRemaining !== null && $line->seatsRemaining < 1));
        if ($full === []) {
            return false;
        }
        $active = MustRetakeObligation::query()->where('student_id', $request->student_id)->where('state', 'Active')
            ->whereIn('course_id', array_map(fn (CartLine $line): int => $line->courseId, $full))->pluck('course_id')->all();
        if (count(array_unique($active)) !== count(array_unique(array_map(fn (CartLine $line): int => $line->courseId, $full)))) {
            return false;
        }

        DB::transaction(function () use ($request, $decisions, $full, $advisorId, $note): void {
            foreach ($request->lines as $line) {
                $line->update(['decision' => $decisions[$line->id]]);
            }
            $request->update(['status' => RegistrationStatus::AwaitingCapacityOverride, 'advisor_note' => $note, 'decided_by' => $advisorId, 'decided_at' => now()]);
            foreach ($full as $cartLine) {
                $requestLine = $request->lines->firstWhere('course_offering_id', $cartLine->offeringId);
                $course = $this->courses->find($cartLine->courseId);
                CapacityOverrideRequirement::query()->firstOrCreate(
                    ['registration_request_id' => $request->id, 'registration_request_line_id' => $requestLine->id],
                    ['course_offering_id' => $cartLine->offeringId, 'course_id' => $cartLine->courseId, 'department_id' => $course?->departmentId, 'state' => 'Pending'],
                );
            }
        });

        return true;
    }
}
