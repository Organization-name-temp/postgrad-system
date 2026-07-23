<?php

use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartValidator;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\CreditHoursExceptions;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;
use Modules\Enrollment\Internal\Support\MeetingTime;

/**
 * The pure batch cart validator (spec §Registration workflow), unit-tested directly (the
 * MeetingTime/CreditHours precedent). It answers "may this cart be submitted?" — the caps,
 * duplicate, clash and capacity rules the submission enforces, with the CH window
 * delegated to {@see CreditHoursValidator}. Lines are hand-built value objects, so no
 * HTTP stack or database is involved.
 */
$validator = new CartValidator(new CreditHoursValidator(minCreditHours: 6, maxCreditHours: 12));

/**
 * A resolved cart line with sensible defaults — a 3-CH Core course, Regular, uncapped, one
 * Monday 09:00–11:00 slot — overridable per case. Offering/course ids default to a shared
 * counter-free unique value from the code so distinct courses do not collide by accident.
 *
 * @param  list<MeetingTime>  $meetings
 */
function cartLine(
    int $offeringId = 1,
    ?int $courseId = null,
    string $courseCode = 'DEP501',
    RegistrationMode $mode = RegistrationMode::Regular,
    int $totalCh = 3,
    EnrollmentClassification $classification = EnrollmentClassification::Core,
    ?int $seatsRemaining = null,
    ?array $meetings = null,
): CartLine {
    return new CartLine(
        offeringId: $offeringId,
        courseId: $courseId ?? $offeringId,
        courseCode: $courseCode,
        mode: $mode,
        totalCh: $totalCh,
        classification: $classification,
        seatsRemaining: $seatsRemaining,
        meetings: $meetings ?? [MeetingTime::fromClock(1, '09:00', '11:00')],
    );
}

/** The set of violation codes returned for a cart. */
function codes(array $violations): array
{
    return array_map(fn (CartViolation $violation): string => $violation->code, $violations);
}

it('accepts a well-formed cart with no violations', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1),
        cartLine(offeringId: 2, courseId: 2, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    expect($validator->validate($lines, CreditHoursExceptions::none()))->toBe([]);
});

it('reports a below-floor cart', function () use ($validator) {
    $violations = $validator->validate([cartLine(totalCh: 3)], CreditHoursExceptions::none());

    expect(codes($violations))->toContain(CartViolation::BELOW_MIN_CREDIT_HOURS);
});

it('does not report a below-floor cart in the first semester', function () use ($validator) {
    $violations = $validator->validate([cartLine(totalCh: 3)], new CreditHoursExceptions(firstSemester: true));

    expect(codes($violations))->not->toContain(CartViolation::BELOW_MIN_CREDIT_HOURS);
});

it('reports an above-ceiling cart', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, totalCh: 6, meetings: [MeetingTime::fromClock(1, '09:00', '11:00')]),
        cartLine(offeringId: 2, courseId: 2, totalCh: 9, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    expect(codes($validator->validate($lines, CreditHoursExceptions::none())))
        ->toContain(CartViolation::ABOVE_MAX_CREDIT_HOURS);
});

it('rejects the same course twice, including Regular plus Audit of it', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 7, mode: RegistrationMode::Regular),
        cartLine(offeringId: 2, courseId: 7, mode: RegistrationMode::Audit, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    $violations = $validator->validate($lines, CreditHoursExceptions::none());

    expect(codes($violations))->toContain(CartViolation::DUPLICATE_COURSE);
    $duplicate = collect($violations)->firstWhere('code', CartViolation::DUPLICATE_COURSE);
    expect($duplicate->offeringIds)->toEqualCanonicalizing([1, 2]);
});

it('caps external electives at one per cart', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, classification: EnrollmentClassification::ExternalElective, meetings: [MeetingTime::fromClock(1, '09:00', '11:00')]),
        cartLine(offeringId: 2, courseId: 2, classification: EnrollmentClassification::ExternalElective, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    expect(codes($validator->validate($lines, CreditHoursExceptions::none())))
        ->toContain(CartViolation::EXTERNAL_ELECTIVE_CAP);
});

it('allows a single external elective', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, classification: EnrollmentClassification::ExternalElective),
        cartLine(offeringId: 2, courseId: 2, classification: EnrollmentClassification::Core, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    expect(codes($validator->validate($lines, CreditHoursExceptions::none())))
        ->not->toContain(CartViolation::EXTERNAL_ELECTIVE_CAP);
});

it('caps research courses at one per cart', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, classification: EnrollmentClassification::ResearchCourse, meetings: [MeetingTime::fromClock(1, '09:00', '11:00')]),
        cartLine(offeringId: 2, courseId: 2, classification: EnrollmentClassification::ResearchCourse, meetings: [MeetingTime::fromClock(2, '09:00', '11:00')]),
    ];

    expect(codes($validator->validate($lines, CreditHoursExceptions::none())))
        ->toContain(CartViolation::RESEARCH_COURSE_CAP);
});

it('rejects a full offering but not an uncapped or seated one', function () use ($validator) {
    $full = $validator->validate([cartLine(seatsRemaining: 0)], CreditHoursExceptions::none());
    $seated = $validator->validate([cartLine(seatsRemaining: 5)], CreditHoursExceptions::none());
    $uncapped = $validator->validate([cartLine(seatsRemaining: null)], CreditHoursExceptions::none());

    expect(codes($full))->toContain(CartViolation::OFFERING_FULL)
        ->and(codes($seated))->not->toContain(CartViolation::OFFERING_FULL)
        ->and(codes($uncapped))->not->toContain(CartViolation::OFFERING_FULL);
});

it('rejects two offerings that clash in the weekly schedule', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, meetings: [MeetingTime::fromClock(1, '09:00', '11:00')]),
        cartLine(offeringId: 2, courseId: 2, totalCh: 3, meetings: [MeetingTime::fromClock(1, '10:00', '12:00')]),
    ];

    $violations = $validator->validate($lines, CreditHoursExceptions::none());

    expect(codes($violations))->toContain(CartViolation::TIME_CLASH);
    $clash = collect($violations)->firstWhere('code', CartViolation::TIME_CLASH);
    expect($clash->offeringIds)->toEqualCanonicalizing([1, 2]);
});

it('does not clash offerings that merely touch at an endpoint or sit on different days', function () use ($validator) {
    $lines = [
        cartLine(offeringId: 1, courseId: 1, meetings: [MeetingTime::fromClock(1, '09:00', '11:00')]),
        cartLine(offeringId: 2, courseId: 2, meetings: [MeetingTime::fromClock(1, '11:00', '13:00')]), // touches
    ];

    expect(codes($validator->validate($lines, CreditHoursExceptions::none())))
        ->not->toContain(CartViolation::TIME_CLASH);
});
