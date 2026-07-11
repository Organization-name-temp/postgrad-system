<?php

use Modules\Identity\Public\Enums\Permission;
use Modules\Identity\Public\Enums\Role;

/**
 * An independent transcription of SRS §3.2 (permission keys per role), asserted
 * against the matrix in {@see Permission}. Kept separate on purpose: this is the
 * double-entry check that the code matrix matches the bylaws.
 */
$expected = [
    'R01' => ['application.submit', 'documents.upload', 'application.view_status', 'catalog.view'],
    'R02' => [
        'documents.upload', 'courses.register_self', 'catalog.view', 'exemption.submit',
        'transfer_waiver.submit', 'grades.view_own', 'transcript.view_unofficial',
        'transcript.export', 'standing.monitor',
    ],
    'R03' => [
        'courses.register_for_student', 'registration.approve', 'catalog.view',
        'exemption.approve', 'transfer_waiver.approve', 'grades.view_student',
        'transcript.view_unofficial', 'standing.monitor', 'termination.recommend',
    ],
    'R04' => ['catalog.view', 'grades.enter', 'grades.finalize', 'grades.view_student'],
    'R05' => [
        'documents.upload', 'application.view_status', 'application.screen', 'interview.schedule',
        'courses.register_for_student', 'registration.approve', 'catalog.view', 'grades.enter',
        'grades.view_student', 'transcript.view_unofficial', 'transcript.issue_official',
        'transcript.export', 'standing.monitor', 'calendar.manage',
    ],
    'R06' => [
        'catalog.view', 'catalog.manage', 'exemption.approve', 'transfer_waiver.approve',
        'grades.finalize', 'grades.view_student', 'standing.monitor', 'termination.recommend',
    ],
    'R07' => [
        'application.view_status', 'application.screen', 'interview.schedule', 'applicant.decide',
        'catalog.view', 'catalog.manage', 'grades.view_student', 'transcript.export',
        'standing.monitor', 'calendar.manage',
    ],
    'R08' => [
        'catalog.view', 'transfer_waiver.approve', 'grades.view_student',
        'standing.monitor', 'termination.approve',
    ],
];

$dataset = [];
foreach ($expected as $code => $keys) {
    $dataset[$code] = [$code, $keys];
}

it('grants each non-admin role exactly its SRS §3.2 permission set', function (string $code, array $keys) {
    $role = Role::from($code);

    $actual = array_map(
        fn (Permission $permission): string => $permission->value,
        Permission::forRoles([$role]),
    );

    sort($actual);
    sort($keys);

    expect($actual)->toBe($keys);
})->with($dataset);

it('grants System and Super Admin every permission', function (Role $role) {
    expect(Permission::forRoles([$role]))->toBe(Permission::cases());
})->with([
    'R09 System Admin' => Role::SystemAdmin,
    'R10 Super Admin' => Role::SuperAdmin,
]);

it('unions permissions across a multi-role user without duplication', function () {
    $permissions = Permission::forRoles([Role::Advisor, Role::Instructor]);

    // The union contains each role's permissions...
    expect($permissions)
        ->toContain(Permission::ApproveCourseRegistration) // Advisor only
        ->toContain(Permission::EnterGrades);              // Instructor only

    // ...a shared permission appears exactly once...
    $catalogViews = array_filter($permissions, fn (Permission $p) => $p === Permission::ViewCourseCatalog);
    expect($catalogViews)->toHaveCount(1);

    // ...and it does not leak a permission neither role holds.
    expect($permissions)->not->toContain(Permission::ApproveTermination);
});
