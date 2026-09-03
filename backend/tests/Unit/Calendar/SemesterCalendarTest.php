<?php

use Illuminate\Support\Carbon;
use Modules\Calendar\Internal\Support\SemesterCalendar;

/*
| The pure Semester date logic (ticket 01): the cross-field ordering invariant the
| CRUD enforces on write, and the period answers ("registration open", "running",
| add/drop / late-add / withdrawal windows) every later Phase-3 ticket reads through
| the Calendar\Public facade. A pure value — no clock of its own, the caller passes
| `now` — so it is unit-tested directly rather than through an HTTP round-trip (the
| CreditHours / BusinessDays precedent, spec §Testing Decisions).
|
| A well-ordered reference calendar the cases perturb one field at a time:
|   reg opens  2026-08-01  →  reg closes 2026-09-05  →  starts 2026-09-01
|   add/drop end 2026-09-14  →  late-add end 2026-09-21  →  withdrawal 2026-11-30
|   ends 2026-12-20  →  grade submission 2027-01-10
*/

/**
 * @param  array<string, string>  $overrides
 */
function calendar(array $overrides = []): SemesterCalendar
{
    $dates = array_merge([
        'starts_at' => '2026-09-01 00:00',
        'ends_at' => '2026-12-20 00:00',
        'registration_opens_at' => '2026-08-01 00:00',
        'registration_closes_at' => '2026-09-05 00:00',
        'add_drop_ends_at' => '2026-09-14 00:00',
        'late_add_ends_at' => '2026-09-21 00:00',
        'withdrawal_deadline_at' => '2026-11-30 00:00',
        'grade_submission_deadline_at' => '2027-01-10 00:00',
    ], $overrides);

    return new SemesterCalendar(
        startsAt: Carbon::parse($dates['starts_at']),
        endsAt: Carbon::parse($dates['ends_at']),
        registrationOpensAt: Carbon::parse($dates['registration_opens_at']),
        registrationClosesAt: Carbon::parse($dates['registration_closes_at']),
        addDropEndsAt: Carbon::parse($dates['add_drop_ends_at']),
        lateAddEndsAt: Carbon::parse($dates['late_add_ends_at']),
        withdrawalDeadlineAt: Carbon::parse($dates['withdrawal_deadline_at']),
        gradeSubmissionDeadlineAt: Carbon::parse($dates['grade_submission_deadline_at']),
    );
}

it('accepts a well-ordered calendar with no violations', function () {
    expect(calendar()->orderingViolations())->toBe([]);
});

it('rejects registration opening on or after the semester starts', function () {
    $violations = calendar(['registration_opens_at' => '2026-09-02 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('registration_opens_at');
});

it('rejects registration opening on or after it closes', function () {
    $violations = calendar([
        'registration_opens_at' => '2026-09-06 00:00', // after closes; still before starts fails too
        'starts_at' => '2026-09-10 00:00',
    ])->orderingViolations();

    expect($violations)->toHaveKey('registration_opens_at');
});

it('rejects registration closing after the add/drop deadline', function () {
    $violations = calendar(['registration_closes_at' => '2026-09-15 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('registration_closes_at');
});

it('rejects add/drop ending on or after late-add ends', function () {
    $violations = calendar(['add_drop_ends_at' => '2026-09-21 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('add_drop_ends_at');
});

it('rejects late-add ending on or after the withdrawal deadline', function () {
    $violations = calendar(['late_add_ends_at' => '2026-11-30 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('late_add_ends_at');
});

it('rejects a withdrawal deadline after the semester ends', function () {
    $violations = calendar(['withdrawal_deadline_at' => '2026-12-21 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('withdrawal_deadline_at');
});

it('accepts a withdrawal deadline exactly on the semester end (inclusive bound)', function () {
    expect(calendar(['withdrawal_deadline_at' => '2026-12-20 00:00'])->orderingViolations())->toBe([]);
});

it('rejects a grade-submission deadline before the semester ends', function () {
    // Phase 4 ticket 09: the one new date joins the same invariant — grades are submitted for a
    // term that has finished, so the deadline can never precede the end.
    $violations = calendar(['grade_submission_deadline_at' => '2026-12-19 00:00'])->orderingViolations();

    expect($violations)->toHaveKey('grade_submission_deadline_at');
});

it('accepts a grade-submission deadline exactly on the semester end (inclusive bound)', function () {
    expect(calendar(['grade_submission_deadline_at' => '2026-12-20 00:00'])->orderingViolations())->toBe([]);
});

it('rejects a semester that ends on or before it starts', function () {
    $violations = calendar([
        'ends_at' => '2026-09-01 00:00',
        'withdrawal_deadline_at' => '2026-08-31 00:00',
    ])->orderingViolations();

    expect($violations)->toHaveKey('ends_at');
});

it('derives registration-open only inside the registration window', function () {
    $cal = calendar();

    expect($cal->isRegistrationOpen(Carbon::parse('2026-07-31 23:00')))->toBeFalse()
        ->and($cal->isRegistrationOpen(Carbon::parse('2026-08-15 00:00')))->toBeTrue()
        ->and($cal->isRegistrationOpen(Carbon::parse('2026-08-01 00:00')))->toBeTrue()  // inclusive open
        ->and($cal->isRegistrationOpen(Carbon::parse('2026-09-05 00:00')))->toBeTrue()  // inclusive close
        ->and($cal->isRegistrationOpen(Carbon::parse('2026-09-06 00:00')))->toBeFalse();
});

it('derives running between the semester start and end inclusive', function () {
    $cal = calendar();

    expect($cal->isRunning(Carbon::parse('2026-08-31 23:00')))->toBeFalse()
        ->and($cal->isRunning(Carbon::parse('2026-10-01 00:00')))->toBeTrue()
        ->and($cal->isRunning(Carbon::parse('2026-12-20 00:00')))->toBeTrue()  // inclusive end
        ->and($cal->isRunning(Carbon::parse('2026-12-21 00:00')))->toBeFalse();
});

it('derives the free add/drop period from semester start to the add/drop deadline', function () {
    $cal = calendar();

    expect($cal->isInAddDropPeriod(Carbon::parse('2026-08-31 23:00')))->toBeFalse()  // before start
        ->and($cal->isInAddDropPeriod(Carbon::parse('2026-09-01 00:00')))->toBeTrue()   // start (inclusive)
        ->and($cal->isInAddDropPeriod(Carbon::parse('2026-09-14 00:00')))->toBeTrue()   // deadline (inclusive)
        ->and($cal->isInAddDropPeriod(Carbon::parse('2026-09-15 00:00')))->toBeFalse();
});

it('derives the late-add period strictly after add/drop up to the late-add deadline', function () {
    $cal = calendar();

    expect($cal->isInLateAddPeriod(Carbon::parse('2026-09-14 00:00')))->toBeFalse()  // add/drop deadline is not late-add
        ->and($cal->isInLateAddPeriod(Carbon::parse('2026-09-18 00:00')))->toBeTrue()
        ->and($cal->isInLateAddPeriod(Carbon::parse('2026-09-21 00:00')))->toBeTrue()   // deadline (inclusive)
        ->and($cal->isInLateAddPeriod(Carbon::parse('2026-09-22 00:00')))->toBeFalse();
});

it('derives the withdrawal period strictly after late-add up to the withdrawal deadline', function () {
    $cal = calendar();

    expect($cal->isInWithdrawalPeriod(Carbon::parse('2026-09-21 00:00')))->toBeFalse()  // late-add deadline is not withdrawal
        ->and($cal->isInWithdrawalPeriod(Carbon::parse('2026-10-15 00:00')))->toBeTrue()
        ->and($cal->isInWithdrawalPeriod(Carbon::parse('2026-11-30 00:00')))->toBeTrue()   // deadline (inclusive)
        ->and($cal->isInWithdrawalPeriod(Carbon::parse('2026-12-01 00:00')))->toBeFalse();
});

it('reports having ended only strictly after the semester end', function () {
    $cal = calendar();

    expect($cal->hasEnded(Carbon::parse('2026-12-20 00:00')))->toBeFalse()
        ->and($cal->hasEnded(Carbon::parse('2026-12-21 00:00')))->toBeTrue();
});

it('reports the grade-submission deadline passed only strictly after it', function () {
    $cal = calendar();

    expect($cal->hasGradeSubmissionDeadlinePassed(Carbon::parse('2027-01-09 00:00')))->toBeFalse()
        ->and($cal->hasGradeSubmissionDeadlinePassed(Carbon::parse('2027-01-10 00:00')))->toBeFalse()  // the day itself is in time
        ->and($cal->hasGradeSubmissionDeadlinePassed(Carbon::parse('2027-01-11 00:00')))->toBeTrue();
});

it('keeps the grade-submission deadline out of the period vocabulary', function () {
    // The deadline is not a registration period: a term past its withdrawal deadline reads
    // `closed` whether or not grades are still due (ticket 09 flags lateness, gates nothing).
    expect(calendar()->currentPeriod(Carbon::parse('2027-01-05 00:00')))->toBe('closed');
});

it('names the current period, change windows taking precedence over the registration overlap', function () {
    $cal = calendar();

    expect($cal->currentPeriod(Carbon::parse('2026-07-15 00:00')))->toBe('upcoming')      // before reg opens
        ->and($cal->currentPeriod(Carbon::parse('2026-08-15 00:00')))->toBe('registration') // reg open, before start
        ->and($cal->currentPeriod(Carbon::parse('2026-09-03 00:00')))->toBe('add_drop')     // reg still open but term started
        ->and($cal->currentPeriod(Carbon::parse('2026-09-10 00:00')))->toBe('add_drop')     // add/drop only
        ->and($cal->currentPeriod(Carbon::parse('2026-09-18 00:00')))->toBe('late_add')     // late-add
        ->and($cal->currentPeriod(Carbon::parse('2026-10-15 00:00')))->toBe('withdrawal')   // withdrawal
        ->and($cal->currentPeriod(Carbon::parse('2026-12-10 00:00')))->toBe('closed')       // past withdrawal, still running
        ->and($cal->currentPeriod(Carbon::parse('2026-12-21 00:00')))->toBe('closed');      // ended
});

it('gives the current period its own closing date, matching whichever window is live', function () {
    // The deadline a Student or an administrator is working against (phase 4.5). It follows
    // `currentPeriod`'s precedence, so during the registration/add-drop overlap it is the add/drop
    // end — the live window's — and never the registration close the other answer has passed over.
    $cal = calendar();
    $endsAt = fn (string $now): ?string => $cal->currentPeriodEndsAt(Carbon::parse($now))?->toDateString();

    expect($endsAt('2026-08-15 00:00'))->toBe('2026-09-05')  // registration open → its close
        ->and($endsAt('2026-09-03 00:00'))->toBe('2026-09-14') // overlap → the live add/drop end
        ->and($endsAt('2026-09-18 00:00'))->toBe('2026-09-21') // late-add → its end
        ->and($endsAt('2026-10-15 00:00'))->toBe('2026-11-30'); // withdrawal → the deadline
});

it('has no closing date for a period that is not an open window', function () {
    // `upcoming` has not opened yet and `closed` has nothing left to close, so inventing a date for
    // either would be a fiction the Card would then display.
    $cal = calendar();

    expect($cal->currentPeriodEndsAt(Carbon::parse('2026-07-15 00:00')))->toBeNull()  // upcoming
        ->and($cal->currentPeriodEndsAt(Carbon::parse('2026-12-10 00:00')))->toBeNull() // closed, running
        ->and($cal->currentPeriodEndsAt(Carbon::parse('2026-12-21 00:00')))->toBeNull(); // ended
});

it('lists every open window when registration overlaps add-drop', function () {
    $cal = calendar();

    expect($cal->openWindows(Carbon::parse('2026-09-03 00:00')))
        ->toBe(['registration', 'add_drop']);
});

it('finds the nearest future deadline and names its source field', function () {
    $cal = calendar();

    $next = $cal->nextDeadline(Carbon::parse('2026-09-03 00:00'));

    expect($next)->not->toBeNull()
        ->and($next['key'])->toBe('registration_closes_at')
        ->and($next['at']->toDateString())->toBe('2026-09-05');
});

it('has no next deadline after every semester date has passed', function () {
    expect(calendar()->nextDeadline(Carbon::parse('2027-01-11 00:00')))->toBeNull();
});
