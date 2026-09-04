<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentRequirement;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\IdentificationType;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\AdmittedStudent;
use Modules\Identity\Public\Contracts\StudentEnrollment;
use Modules\Identity\Public\StudentIdentity;
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
 * @property ?IdentificationType $id_type
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
 * @property ?int $previous_ejust_program_id
 * @property ?DegreeLevel $applying_for_degree
 * @property ?EnglishTest $english_test
 * @property ?string $english_score
 * @property ?bool $native_arabic_speaker
 * @property ?int $primary_program_id
 * @property ?int $alternative_program_id
 * @property ?string $conditions
 * @property ?int $waitlist_position
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
        'id_type',
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
        'previous_ejust_program_id',
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
            'id_type' => IdentificationType::class,
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
            'previous_ejust_program_id' => 'integer',
            'native_arabic_speaker' => 'boolean',
            'primary_program_id' => 'integer',
            'alternative_program_id' => 'integer',
            'waitlist_position' => 'integer',
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
     * The interview booked for this application (ticket 09), if any — one per
     * application. Created when Student Affairs schedules the applicant into a slot;
     * absent until then.
     *
     * @return HasOne<Interview, $this>
     */
    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
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
     * Whether Step 1 may still be *submitted* — from a fresh Draft, or a returned
     * application being pushed back for review. This is the one-time commit that
     * issues email verification, so it locks the moment the application is submitted.
     * Distinct from {@see isSnapshotEditable()}, which governs *editing* the captured
     * fields and stays open longer (through email verification).
     */
    public function canSubmitStep1(): bool
    {
        return in_array($this->status, [
            ApplicationStatus::Draft,
            ApplicationStatus::ReturnedForRevision,
        ], true);
    }

    /**
     * Whether the applicant may still edit the Step-1 snapshot. Open throughout the
     * window the application is in the applicant's hands — the same window in which
     * documents and program selection stay editable ({@see areDocumentsEditable()},
     * {@see isProgramSelectable()}) — so an applicant on the documents step can go
     * Back and fix a personal-info field before finalizing (Phase 2.5 B3). It locks
     * the moment the application enters Student Affairs review (UnderSAReview onward).
     */
    public function isSnapshotEditable(): bool
    {
        return $this->isInApplicantHands();
    }

    /**
     * Whether the applicant may still add or remove documents (ticket 05). Editable
     * throughout the applicant-hands window — through submission and email verification,
     * and again whenever the application is returned for revision — the same window the
     * Step-1 snapshot and program selection use; all three lock the moment it enters
     * review (UnderSAReview onward).
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
     * finalize hands it to Student Affairs. The Step-1 snapshot, documents, and program
     * selection are all editable in exactly this window ({@see isSnapshotEditable()},
     * {@see areDocumentsEditable()}, {@see isProgramSelectable()}); submitting Step 1 is
     * the narrower one-time action ({@see canSubmitStep1()}).
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
     * Reject the application with a reason, ending it at
     * {@see ApplicationStatus::Rejected} (terminal). Every reviewer gate that can
     * refuse an application funnels through here — the states the transition map allows
     * into `Rejected`: Student Affairs at screening (`UnderSAReview`, ticket 07), the
     * Dean after review (`UnderDeanReview`, ticket 08), and the Dean's final decision or
     * a resolution of a held offer (`InterviewCompleted`/`ConditionallyAccepted`/`Waitlisted`,
     * ticket 10). Any other status is a 409. The actor is the rejecting reviewer.
     */
    public function reject(int $actorId, string $note): void
    {
        $this->transitionTo(ApplicationStatus::Rejected, actorId: $actorId, note: $note);
    }

    /**
     * The Dean approves the application for interview:
     * {@see ApplicationStatus::UnderDeanReview} → {@see ApplicationStatus::InterviewScheduled}
     * (ticket 08). The status moves now; the concrete slot booking follows in
     * ticket 09. Legal only from `UnderDeanReview` (else 409). The actor is the Dean;
     * an optional `$note` records any comment on the timeline.
     */
    public function approveForInterview(int $actorId, ?string $note = null): void
    {
        $this->transitionTo(ApplicationStatus::InterviewScheduled, actorId: $actorId, note: $note);
    }

    /**
     * Whether the application is at the point where Student Affairs may book a concrete
     * interview slot for it — the Dean has approved it for interview
     * ({@see ApplicationStatus::InterviewScheduled}) but it has not yet moved on. This
     * gates scheduling (ticket 09): booking is not itself a status transition (the
     * status is already `interview_scheduled`), so the guard is a plain state check,
     * surfaced by the controller as a 409 when it fails.
     */
    public function isAwaitingInterviewScheduling(): bool
    {
        return $this->status === ApplicationStatus::InterviewScheduled;
    }

    /**
     * Student Affairs marks the interview complete once it has happened:
     * {@see ApplicationStatus::InterviewScheduled} → {@see ApplicationStatus::InterviewCompleted}
     * (ticket 09), from where the Dean records the final decision (ticket 10). The
     * matching {@see Interview::markCompleted()} stamp is performed together in one
     * transaction by the controller. Legal only from `interview_scheduled` (else 409);
     * the actor is the completing Student Affairs staffer.
     */
    public function completeInterview(int $actorId): void
    {
        $this->transitionTo(ApplicationStatus::InterviewCompleted, actorId: $actorId);
    }

    /**
     * The Dean accepts the applicant — the terminal `Accepted` transition (ticket 10)
     * and the phase's climax. Legal from `InterviewCompleted` (the direct decision) and
     * from `ConditionallyAccepted`/`Waitlisted` (a later resolution to Accepted) — any
     * other status is a 409.
     *
     * The status change and the acceptance mint run in **one transaction** here
     * (ADR-0013): the guarded transition, then {@see StudentEnrollment::admit()} across
     * the Identity\Public seam — so an `Accepted` application and its Student (University
     * ID, R01→R02, advisor, audit entry) commit together, and no Student can exist
     * without an acceptance. The caller resolves the advisor and Program code into
     * `$command` (data Admissions holds but Identity must not read from Catalog) and
     * dispatches the `AdmissionAccepted` side-effect event only once this returns. The
     * actor is the deciding Dean.
     */
    public function accept(int $actorId, StudentEnrollment $enrollment, AdmitStudent $command): AdmittedStudent
    {
        return DB::transaction(function () use ($actorId, $enrollment, $command): AdmittedStudent {
            $this->transitionTo(ApplicationStatus::Accepted, actorId: $actorId);

            return $enrollment->admit($command);
        });
    }

    /**
     * The frozen identity snapshot to carry onto the Student anchor at acceptance
     * (ADR-0013). Assembled from this application's own Step-1 fields — mapping an
     * Application's personal info to a {@see StudentIdentity} is the Application's own
     * knowledge, so the decision controller does not reach across these columns itself.
     * Only meaningful once the snapshot is complete (finalize enforces that before an
     * application can reach a decision).
     */
    public function identitySnapshot(): StudentIdentity
    {
        return new StudentIdentity(
            nameEn: (string) $this->name_en,
            nameAr: (string) $this->name_ar,
            nationalId: (string) $this->national_id,
            dateOfBirth: $this->date_of_birth?->format('Y-m-d') ?? '',
            nationality: (string) $this->nationality,
            gender: $this->gender->value,
        );
    }

    /**
     * The Dean makes a conditional offer: `InterviewCompleted` →
     * {@see ApplicationStatus::ConditionallyAccepted}, recording the free-text
     * `$conditions` both on the application (for later resolution) and on the timeline
     * (the applicant sees the terms). Held short of admission — no Student is minted —
     * until the Dean resolves it to Accepted or Rejected. Legal only from
     * `InterviewCompleted` (else 409); the actor is the Dean.
     */
    public function acceptConditionally(int $actorId, string $conditions): void
    {
        $this->conditions = $conditions;
        $this->transitionTo(ApplicationStatus::ConditionallyAccepted, actorId: $actorId, note: $conditions);
    }

    /**
     * The Dean waitlists the applicant: `InterviewCompleted` →
     * {@see ApplicationStatus::Waitlisted}, recording their `$position`. Held — no
     * Student is minted — until the Dean resolves it to Accepted (a spot opened) or
     * Rejected (the cycle closed). Legal only from `InterviewCompleted` (else 409); the
     * actor is the Dean.
     */
    public function waitlist(int $actorId, int $position): void
    {
        $this->waitlist_position = $position;
        $this->transitionTo(ApplicationStatus::Waitlisted, actorId: $actorId);
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
