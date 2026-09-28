<?php

// Audit retest, intentionally separate from phpunit.xml's normal suites.
// Historical exploit-success output is retained in docs/security-audit/evidence.
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class SecurityAuditTestCase extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'prs_test') {
            throw new RuntimeException('Audit PoCs must only run against prs_test.');
        }
    }
}

uses(SecurityAuditTestCase::class);

beforeEach(fn () => Storage::fake('local'));

function auditRealUpload(string $filename, string $content): UploadedFile
{
    Storage::disk('local')->put('audit-input/'.$filename, $content);

    // Use the real MIME detector; Laravel fake files infer MIME from extension.
    return new UploadedFile(Storage::disk('local')->path('audit-input/'.$filename), $filename, null, null, true);
}

function auditOutsideFaculty(): array
{
    $inside = Program::factory()->create();
    $outside = Program::factory()->create();
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $inside->department->faculty_id]);
    $application = Application::factory()->underReview()->create(['primary_program_id' => $outside->id]);
    Sanctum::actingAs($dean);

    return [$dean, $application];
}

it('AUDIT-01 blocks outside-faculty PII timeline and private files', function () {
    [, $application] = auditOutsideFaculty();
    $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);
    Storage::disk('local')->put($document->stored_path, '%PDF-1.4 AUDIT_PRIVATE_DOCUMENT');

    $this->getJson('/api/v1/applications')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/applications/'.$application->id)->assertForbidden();
    $this->get('/api/v1/documents/'.$document->ulid)->assertForbidden();
    $this->getJson('/api/v1/applications/'.$application->id.'/timeline')->assertForbidden();
});

it('AUDIT-02 prevents outside-faculty rejection through screening', function () {
    [, $application] = auditOutsideFaculty();
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', [
        'action' => 'reject', 'note' => 'AUDIT: unauthorized cross-faculty rejection',
    ])->assertForbidden();
    $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'under_sa_review']);
});

it('AUDIT-03 rejects guesses after the account failure budget is exhausted', function () {
    $user = User::factory()->create();
    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'AUDIT-WRONG-'.$i], ['Origin' => 'http://localhost'])
            ->assertStatus($i < 5 ? 422 : 429);
    }
    expect(AuditLog::query()->where('action', 'login_failed')->count())->toBe(5);
});

it('DEFERRED-01 registration accepts a weak password and ignores injected roles', function () {
    AdmissionCycle::factory()->open()->create();
    $this->withHeader('Origin', 'http://localhost:5173')->postJson('/api/v1/register', [
        'name' => 'Audit Applicant', 'email' => 'audit-weak@example.test',
        'password' => 'aaaaaaaa', 'password_confirmation' => 'aaaaaaaa',
        'roles' => ['R10'], 'email_verified_at' => now()->toISOString(),
    ])->assertCreated();
    $user = User::query()->where('email', 'audit-weak@example.test')->firstOrFail();
    expect($user->hasRole(Role::Applicant))->toBeTrue();
    expect($user->hasRole(Role::SuperAdmin))->toBeFalse();
    expect($user->email_verified_at)->toBeNull();
});

it('AUDIT-04 refuses stateless registration before committing records', function () {
    AdmissionCycle::factory()->open()->create();
    $this->postJson('/api/v1/register', [
        'name' => 'Audit Stateless', 'email' => 'audit-stateless@example.test',
        'password' => 'Audit-Password-123!', 'password_confirmation' => 'Audit-Password-123!',
    ])->assertStatus(400);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('applications', 0);
});

it('AUDIT-05 refuses a stale screening handler after a terminal rejection', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::StudentAffairs);
    $application = Application::factory()->underReview()->create();
    // Two requests both bind the row before either writes it.
    $staleBinding = Application::findOrFail($application->id);
    Sanctum::actingAs($reviewer);
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', [
        'action' => 'reject', 'note' => 'AUDIT first decision',
    ])->assertOk()->assertJsonPath('data.status', 'rejected');
    $second = Request::create('/api/v1/applications/'.$application->id.'/screen', 'POST', ['action' => 'approve']);
    $second->setUserResolver(fn () => $reviewer);
    try {
        app()->call([app(ApplicationScreeningController::class), 'screen'], [
            'request' => $second, 'application' => $staleBinding,
        ]);
        $this->fail('The stale screening handler must fail.');
    } catch (ConflictHttpException $error) {
        expect($error->getStatusCode())->toBe(409);
    }
    expect($application->fresh()->status->value)->toBe('rejected');
    expect($application->statusTransitions()->where('to_status', 'rejected')->exists())->toBeTrue();
    expect($application->statusTransitions()->where('from_status', 'under_sa_review')->count())->toBe(1);
});

it('DEFERRED-02 applicant verifies email using a link returned to the session without a mailbox', function () {
    $application = Application::factory()->submitted()->create();
    $user = User::findOrFail($application->user_id);
    Sanctum::actingAs($user);
    $url = $this->postJson('/api/v1/application/resend-verification')->assertOk()->json('verification_url');
    $this->getJson($url)->assertOk()->assertJsonPath('data.status', 'email_verified');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('DEFERRED-03 PDF with an inert embedded script is stored and returned unchanged', function () {
    $application = Application::factory()->submitted()->create();
    Sanctum::actingAs(User::findOrFail($application->user_id));
    // Inert marker only: no malware and no script execution is attempted.
    $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /OpenAction << /S /JavaScript /JS (/* AUDIT_INERT_MARKER */) >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    $response = $this->post('/api/v1/application/documents', [
        'document_type' => 'cv', 'file' => auditRealUpload('audit.pdf', $bytes),
    ], ['Accept' => 'application/json'])->assertCreated();
    $this->get('/api/v1/documents/'.$response->json('data.id'))->assertOk()->assertStreamedContent($bytes);
});

it('CONTROL unrelated applicants cannot read or delete someone elses file or enter administration', function () {
    $victim = Application::factory()->underReview()->create();
    $document = ApplicationDocument::factory()->create(['application_id' => $victim->id]);
    $attacker = User::factory()->create();
    $attacker->assignRole(Role::Applicant);
    Application::factory()->create(['user_id' => $attacker->id]);
    Sanctum::actingAs($attacker);
    $this->getJson('/api/v1/applications/'.$victim->id)->assertForbidden();
    $this->get('/api/v1/documents/'.$document->ulid)->assertForbidden();
    $this->deleteJson('/api/v1/application/documents/'.$document->ulid)->assertNotFound();
    $this->getJson('/api/v1/admin/faculty-dean-assignments')->assertForbidden();
});

it('CONTROL tampered verification signatures fail', function () {
    $application = Application::factory()->submitted()->create();
    $this->getJson('/api/v1/applications/'.$application->id.'/verify-email?expires=9999999999&signature=invalid')
        ->assertForbidden();
    expect(User::findOrFail($application->user_id)->email_verified_at)->toBeNull();
});

it('CONTROL SQL-looking sort fields and HTML masquerading as a PDF are rejected', function () {
    auditOutsideFaculty();
    $this->getJson('/api/v1/applications?sort='.urlencode('id desc; SELECT pg_sleep(1)'))->assertUnprocessable();
    $application = Application::factory()->create();
    Sanctum::actingAs(User::findOrFail($application->user_id));
    $this->post('/api/v1/application/documents', [
        'document_type' => 'cv',
        'file' => auditRealUpload('looks-like.pdf', '<html><script>/* AUDIT_INERT */</script></html>'),
    ], ['Accept' => 'application/json'])->assertUnprocessable();
});
