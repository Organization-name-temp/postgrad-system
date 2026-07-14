<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentRequirement;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * A prospective student's Application to a single Admission Cycle (CONTEXT.md).
 * Created in the {@see ApplicationStatus::Draft} state by self-registration
 * (ticket 03), then filled with the per-cycle Step-1 personal-info snapshot
 * through the wizard (ticket 04) and carried through the rest of the 13-state
 * workflow by later tickets.
 *
 * The applicant (owner) is Identity's `User`, referenced here by a real FK — both
 * modules share the database — but never by an Eloquent relation, since the User
 * model is Identity\Internal (the seam is code-level, ADR-0002). Program selection
 * (ticket 06) is stored as an opaque Catalog id with no FK, per ADR-0012.
 *
 * @property int $id
 * @property int $user_id
 * @property int $admission_cycle_id
 * @property ApplicationStatus $status
 * @property ?string $name_en
 * @property ?string $name_ar
 * @property ?string $national_id
 * @property ?string $phone
 * @property ?Carbon $date_of_birth
 * @property ?string $nationality
 * @property ?Gender $gender
 * @property ?string $address_country
 * @property ?string $address_city
 * @property ?string $address_street
 * @property ?string $previous_university
 * @property ?string $graduation_certificate_name
 * @property ?Carbon $graduation_date
 * @property ?PreviousDegree $previous_degree
 * @property ?string $previous_major
 * @property ?string $graduation_gpa
 * @property ?string $graduation_cgpa
 * @property ?GpaScale $gpa_scale
 * @property ?bool $is_ejust_graduate
 * @property ?DegreeLevel $applying_for_degree
 * @property ?EnglishTest $english_test
 * @property ?string $english_score
 * @property ?bool $native_arabic_speaker
 * @property ?int $primary_program_id
 * @property ?int $alternative_program_id
 * @property ?Carbon $submitted_at
 * @property ?Carbon $finalized_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    /**
     * The identity columns (set once at registration) plus the Step-1 snapshot
     * fields the wizard saves. `status` and `submitted_at` are deliberately absent:
     * status is guarded (see below) and moved only by the transition methods, and
     * `submitted_at` is stamped by {@see self::submit()} — neither is mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'admission_cycle_id',
        'name_en',
        'name_ar',
        'national_id',
        'phone',
        'date_of_birth',
        'nationality',
        'gender',
        'address_country',
        'address_city',
        'address_street',
        'previous_university',
        'graduation_certificate_name',
        'graduation_date',
        'previous_degree',
        'previous_major',
        'graduation_gpa',
        'graduation_cgpa',
        'gpa_scale',
        'is_ejust_graduate',
        'applying_for_degree',
        'english_test',
        'english_score',
        'native_arabic_speaker',
        // Program selection (ticket 06): opaque Catalog Program refs, validated to
        // exist and pass the qualification rule by the controller before assignment.
        'primary_program_id',
        'alternative_program_id',
    ];

    /**
     * A fresh Application is a Draft. `status` is deliberately **not** fillable:
     * it is set here (in-memory, so a just-created model reports it without a
     * reload) and only ever moved by this model's guarded transition methods
     * (extended into the full transition machine by ticket 07) — never by mass
     * assignment.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ApplicationStatus::Draft->value,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'gender' => Gender::class,
            'previous_degree' => PreviousDegree::class,
            'gpa_scale' => GpaScale::class,
            'english_test' => EnglishTest::class,
            // The degree applied for is a Catalog\Public DegreeLevel now the seam is
            // open (ticket 06, ADR-0012) — so the finalize + qualification checks
            // compare it against a Program's level type-safely, not as a raw string.
            'applying_for_degree' => DegreeLevel::class,
            'date_of_birth' => 'date',
            'graduation_date' => 'date',
            'graduation_gpa' => 'decimal:2',
            'graduation_cgpa' => 'decimal:2',
            'english_score' => 'decimal:2',
            'is_ejust_graduate' => 'boolean',
            'native_arabic_speaker' => 'boolean',
            'primary_program_id' => 'integer',
            'alternative_program_id' => 'integer',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ApplicationFactory
    {
        return ApplicationFactory::new();
    }

    /** @return BelongsTo<AdmissionCycle, $this> */
    public function admissionCycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class);
    }

    /** @return HasMany<ApplicationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * The append-only status timeline (ticket 07), oldest-first. Each named action
     * appends one row; the API exposes it read-only as the applicant's status history.
     *
     * @return HasMany<ApplicationStatusTransition, $this>
     */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(ApplicationStatusTransition::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * Whether the applicant may still edit the Step-1 snapshot. Open while the
     * application is still a Draft, and again once Student Affairs returns it for
     * revision — the two states where the whole application is back in the
     * applicant's hands. It locks the moment they submit and stays locked through
     * review (widening the ticket-04 Draft-only rule for the return→resubmit loop).
     */
    public function isApplicantEditable(): bool
    {
        return in_array($this->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::ReturnedForRevision,
        ], true);
    }

    /**
     * Whether the applicant may still add or remove documents (ticket 05). Documents
     * stay editable a little longer than the Step-1 snapshot: through submission and
     * email verification, and again whenever the application is returned for revision
     * — but they lock the moment it enters review (UnderSAReview onward).
     */
    public function areDocumentsEditable(): bool
    {
        return $this->isInApplicantHands();
    }

    /**
     * Whether the applicant may still choose or change their Program(s) (ticket 06).
     * Program selection is editable in exactly the same window as documents — while
     * the application is still in the applicant's hands — and locks on finalize, when
     * it enters review.
     */
    public function isProgramSelectable(): bool
    {
        return $this->isInApplicantHands();
    }

    /**
     * The statuses in which the application still belongs to the applicant, before
     * finalize hands it to Student Affairs. Documents and program selection are both
     * editable in exactly this window; the Step-1 snapshot locks earlier (Draft only,
     * {@see self::isApplicantEditable()}).
     */
    private function isInApplicantHands(): bool
    {
        return in_array($this->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::Submitted,
            ApplicationStatus::EmailVerified,
            ApplicationStatus::ReturnedForRevision,
        ], true);
    }

    /**
     * Submit Step 1: the personal-info snapshot is complete (the controller has
     * re-validated it). Moves the Draft to {@see ApplicationStatus::Submitted},
     * awaiting email verification — or, when the applicant's email is *already*
     * verified, straight to {@see ApplicationStatus::EmailVerified} (the
     * pre-verified shortcut, e.g. a rejected applicant re-applying in a later
     * Cycle). Either way it stamps `submitted_at`. The applicant is the actor.
     */
    public function submit(bool $emailAlreadyVerified): void
    {
        $this->submitted_at = now();
        $this->transitionTo(
            $emailAlreadyVerified ? ApplicationStatus::EmailVerified : ApplicationStatus::Submitted,
            actorId: $this->user_id,
        );
    }

    /**
     * Record that the applicant has verified their email: {@see ApplicationStatus::Submitted}
     * → {@see ApplicationStatus::EmailVerified}. The applicant cannot reach the
     * documents step until this has happened. The applicant is the actor.
     */
    public function markEmailVerified(): void
    {
        $this->transitionTo(ApplicationStatus::EmailVerified, actorId: $this->user_id);
    }

    /**
     * Finalize a complete application into Student Affairs review:
     * {@see ApplicationStatus::EmailVerified} → {@see ApplicationStatus::UnderSAReview},
     * stamping `finalized_at`. Any other status is an illegal move (→ 409).
     *
     * Completeness — a primary Program plus every required and conditionally-required
     * document — is a *business* precondition the caller checks first (a missing
     * prerequisite is a 422, not a state conflict); see
     * {@see self::missingRequiredDocumentTypes()} and {@see self::hasPrimaryProgram()}.
     * This method only performs the guarded transition once those hold. The applicant
     * is the actor.
     */
    public function finalize(): void
    {
        $this->finalized_at = now();
        $this->transitionTo(ApplicationStatus::UnderSAReview, actorId: $this->user_id);
    }

    /**
     * Resubmit a returned application: {@see ApplicationStatus::ReturnedForRevision}
     * → {@see ApplicationStatus::UnderSAReview}, back into the Student Affairs queue
     * after the applicant has addressed the reviewer's instructions (FR-ADM-005.3).
     * The applicant is the actor; any other status is a 409.
     */
    public function resubmitForReview(): void
    {
        $this->transitionTo(ApplicationStatus::UnderSAReview, actorId: $this->user_id);
    }

    /**
     * Student Affairs approves the application for Dean review. Approving auto-advances
     * through the transient {@see ApplicationStatus::SAApproved} straight to
     * {@see ApplicationStatus::UnderDeanReview} in one action (two timeline rows: the
     * SA approval carries the reviewer's `$note`; the forward is automatic and has no
     * actor). Legal only from {@see ApplicationStatus::UnderSAReview} (else 409).
     */
    public function approveForDeanReview(int $actorId, ?string $note = null): void
    {
        $this->transitionTo(ApplicationStatus::SAApproved, actorId: $actorId, note: $note);
        $this->transitionTo(ApplicationStatus::UnderDeanReview);
    }

    /**
     * Student Affairs returns the application to the applicant for revision, with
     * instructions: {@see ApplicationStatus::UnderSAReview} →
     * {@see ApplicationStatus::ReturnedForRevision}. The `$note` is the reviewer's
     * instructions, recorded on the timeline and surfaced to the applicant. Legal
     * only from `UnderSAReview` (else 409).
     */
    public function returnForRevision(int $actorId, string $note): void
    {
        $this->transitionTo(ApplicationStatus::ReturnedForRevision, actorId: $actorId, note: $note);
    }

    /**
     * Student Affairs rejects the application at screening, with a reason:
     * {@see ApplicationStatus::UnderSAReview} → {@see ApplicationStatus::Rejected}
     * (terminal). Legal only from `UnderSAReview` (else 409).
     */
    public function reject(int $actorId, string $note): void
    {
        $this->transitionTo(ApplicationStatus::Rejected, actorId: $actorId, note: $note);
    }

    /**
     * When the application last entered the given status — the `occurred_at` of the
     * most recent transition into it — or null if it has never done so. Reads the
     * loaded `statusTransitions` relation (ordered oldest-first), so the caller
     * should eager-load it. Used to derive the Student Affairs queue's aging (days
     * since entering review) on demand, never stored (spec §SLAs).
     */
    public function enteredStatusAt(ApplicationStatus $status): ?Carbon
    {
        return $this->statusTransitions
            ->where('to_status', $status)
            ->last()?->occurred_at;
    }

    /** Whether a primary Program has been chosen — the first finalize prerequisite. */
    public function hasPrimaryProgram(): bool
    {
        return $this->primary_program_id !== null;
    }

    /**
     * The DocumentTypes this application must hold at least one of before it can be
     * finalized: the always-{@see DocumentRequirement::Required} set, plus the
     * conditionally-required documents this particular application actually owes —
     * the English-proficiency certificate once a test (other than None) is reported,
     * and the MSc thesis when applying for a PhD (BR-66/67). The static
     * requirement/size/format rules live on {@see DocumentType}; which *conditionals*
     * apply is resolved here, against this application's own snapshot.
     *
     * @return list<DocumentType>
     */
    public function requiredDocumentTypesForFinalize(): array
    {
        $types = array_values(array_filter(
            DocumentType::cases(),
            static fn (DocumentType $type): bool => $type->requirement() === DocumentRequirement::Required,
        ));

        if ($this->english_test !== null && $this->english_test !== EnglishTest::None) {
            $types[] = DocumentType::EnglishCertificate;
        }

        if ($this->applying_for_degree === DegreeLevel::PhD) {
            $types[] = DocumentType::MscThesis;
        }

        return $types;
    }

    /**
     * The required DocumentTypes still missing an upload — empty when the document
     * side of finalize is satisfied. Reads the loaded `documents` relation, so the
     * caller should have it available (the wizard endpoints eager-load it).
     *
     * @return list<DocumentType>
     */
    public function missingRequiredDocumentTypes(): array
    {
        $uploaded = $this->documents->pluck('document_type')->all();

        return array_values(array_filter(
            $this->requiredDocumentTypesForFinalize(),
            static fn (DocumentType $type): bool => ! in_array($type, $uploaded, true),
        ));
    }

    /**
     * Perform one guarded status change and record it on the append-only timeline.
     * The move must be legal per the static allowed-transitions map on
     * {@see ApplicationStatus::allowedTransitions()}; anything else is refused with a
     * 409 (a conflict with the Application's current state, distinct from input
     * validation). On success the new status is persisted (alongside any attributes
     * the caller set first, e.g. `submitted_at`/`finalized_at`) and one
     * {@see ApplicationStatusTransition} row is appended (from/to/actor/note/occurred_at).
     *
     * The `$actorId` is the user who caused the move — the applicant for self-service
     * moves, the reviewer for screening actions, or null for an automatic step (the
     * SAApproved → UnderDeanReview forward). `$note` carries a reviewer's instructions
     * or a decision reason.
     */
    private function transitionTo(ApplicationStatus $to, ?int $actorId = null, ?string $note = null): void
    {
        $from = $this->status;

        if (! $from->canTransitionTo($to)) {
            throw new ConflictHttpException(
                __('admissions.transition.illegal', ['from' => $from->value, 'to' => $to->value]),
            );
        }

        $this->status = $to;
        $this->save();

        $this->statusTransitions()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => $actorId,
            'note' => $note,
            'occurred_at' => now(),
        ]);
    }
}
