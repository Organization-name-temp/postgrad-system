<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\PreviousDegree;
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
 * @property ?string $applying_for_degree
 * @property ?EnglishTest $english_test
 * @property ?string $english_score
 * @property ?bool $native_arabic_speaker
 * @property ?Carbon $submitted_at
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
            'date_of_birth' => 'date',
            'graduation_date' => 'date',
            'graduation_gpa' => 'decimal:2',
            'graduation_cgpa' => 'decimal:2',
            'english_score' => 'decimal:2',
            'is_ejust_graduate' => 'boolean',
            'native_arabic_speaker' => 'boolean',
            'submitted_at' => 'datetime',
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
     * Whether the applicant may still edit the snapshot. Editing is open while the
     * Application has not yet left the applicant's hands — Draft here in ticket 04,
     * widened to the other applicant-editable states (ReturnedForRevision, …) by
     * the workflow tickets.
     */
    public function isApplicantEditable(): bool
    {
        return $this->status === ApplicationStatus::Draft;
    }

    /**
     * Whether the applicant may still add or remove documents (ticket 05). Documents
     * stay editable a little longer than the Step-1 snapshot: through submission and
     * email verification, and again whenever the application is returned for revision
     * — but they lock the moment it enters review (UnderSAReview onward).
     */
    public function areDocumentsEditable(): bool
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
     * Cycle). Either way it stamps `submitted_at`.
     */
    public function submit(bool $emailAlreadyVerified): void
    {
        $this->transitionTo(
            $emailAlreadyVerified ? ApplicationStatus::EmailVerified : ApplicationStatus::Submitted,
            [ApplicationStatus::Draft],
        );
        $this->submitted_at = now();
        $this->save();
    }

    /**
     * Record that the applicant has verified their email: {@see ApplicationStatus::Submitted}
     * → {@see ApplicationStatus::EmailVerified}. The applicant cannot reach the
     * documents step until this has happened.
     */
    public function markEmailVerified(): void
    {
        $this->transitionTo(ApplicationStatus::EmailVerified, [ApplicationStatus::Submitted]);
        $this->save();
    }

    /**
     * Guard a status change: the current status must be one of `$allowedFrom`,
     * otherwise the move is illegal and refused with a 409 (a conflict with the
     * Application's current state, distinct from input validation). Ticket 07
     * generalises this into a static allowed-transitions map over every status and
     * layers the append-only transition log on top; ticket 04 guards only the two
     * early moves it owns.
     *
     * @param  list<ApplicationStatus>  $allowedFrom
     */
    private function transitionTo(ApplicationStatus $to, array $allowedFrom): void
    {
        if (! in_array($this->status, $allowedFrom, true)) {
            throw new ConflictHttpException(
                "Cannot move an application from {$this->status->value} to {$to->value}.",
            );
        }

        $this->status = $to;
    }
}
