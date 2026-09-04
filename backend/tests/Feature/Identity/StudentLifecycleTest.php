<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentLifecycleWriter;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;

uses(DatabaseMigrations::class);

it('changes lifecycle only through the transaction-scoped public writer', function () {
    $student = Student::factory()->create();
    $writer = app(StudentLifecycleWriter::class);

    expect(fn () => $writer->terminate((int) $student->id))
        ->toThrow(LogicException::class, 'active transaction');

    $summary = DB::transaction(fn () => $writer->terminate((int) $student->id));

    expect($summary?->studentId)->toBe($student->id)
        ->and($summary?->lifecycleStatus)->toBe(StudentLifecycleStatus::Terminated)
        ->and($student->fresh()->lifecycle_status)->toBe(StudentLifecycleStatus::Terminated);
});
