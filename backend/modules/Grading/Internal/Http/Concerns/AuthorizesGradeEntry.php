<?php

namespace Modules\Grading\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Http\Controllers\GradeSheetController;
use Modules\Grading\Internal\Http\Controllers\IncompleteController;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Enums\Permission;

/**
 * Who may enter marks on which Grade Sheet, and which of those writes is audited (ADR-0023,
 * ticket 09). Two authorities, and the §3.2 matrix is used exactly as written — no new
 * permission, no new role mapping, and no ADR-0015 override:
 *
 * - **The Instructor's own Offering.** Authority is the Offering assignment, not the
 *   `grades.enter` permission the route already checked (the Phase-2 interviewer / Phase-3
 *   attendance precedent). Their own writes are **not** audited: attesting one's own grades is
 *   the ordinary case, exactly as an Instructor's own attendance register is.
 * - **Central data entry.** A user holding `grades.enter` but *not* `grades.finalize` — Student
 *   Affairs, and by §3.2 only Student Affairs — reaches **any** Offering's Sheet, because that
 *   pair of facts read literally means one thing: a records office keys in grades that arrive
 *   on paper, and can never sign them. Every such write to someone else's Sheet is audited
 *   ({@see self::auditCentralEntry()}), which is what keeps the Instructor accountable as the
 *   author of their own grades even when they did not type them.
 *
 * The permission-shaped test is what makes the two cases distinguishable at all: Instructor and
 * Department Head both hold `grades.finalize`, so only "enter but not finalize" isolates central
 * entry — and it isolates it *without* widening anything else. A Department Head holds no
 * `grades.enter`, so the route middleware refuses them before this trait is reached; an
 * administrator (R09/R10) holds both permissions and is therefore treated as an Instructor here,
 * scoped to Offerings they are assigned — the grading write-side ADR-0015 override is not part
 * of this ticket.
 *
 * Signing is untouched: `POST .../sign` sits behind `can:grades.finalize`, so a central-entry
 * user is refused there with a 403 by the route, never by a check here.
 *
 * Used by {@see GradeSheetController} for every Sheet action. {@see IncompleteController} keeps
 * its own Instructor-only guard: its outstanding list is the Instructor's own worklist by
 * construction, so central entry has no Incomplete id to reach a resolution round through, and
 * a central list of every outstanding Incomplete is not something this ticket asks for.
 */
trait AuthorizesGradeEntry
{
    /**
     * The Offering the acting user is working, or an abort: a 404 when the reference dangles, a
     * 403 when they are neither its assigned Instructor nor a central-entry user.
     */
    private function authorizeOffering(int $offeringId, Request $request, EnrollmentRecords $enrollments): OfferingSummary
    {
        $offering = $enrollments->findOffering($offeringId);
        abort_if($offering === null, 404, __('grading.offering.missing'));
        abort_if(
            $offering->instructorId !== $this->actingUserId($request)
                && ! $this->actingAsCentralEntry($request),
            403,
            __('grading.offering.not_yours'),
        );

        return $offering;
    }

    /**
     * The same authority, narrowed to the **assigned Instructor alone** — for the acts central
     * entry is not: opening a Correction round reaches into the finalized academic record rather
     * than keying a mark into a Draft one, so it stays with the Instructor whose grades they are
     * (ticket 09 widens entry, never the record).
     */
    private function authorizeOwnOffering(int $offeringId, Request $request, EnrollmentRecords $enrollments): OfferingSummary
    {
        $offering = $enrollments->findOffering($offeringId);
        abort_if($offering === null, 404, __('grading.offering.missing'));
        abort_if($offering->instructorId !== $this->actingUserId($request), 403, __('grading.offering.not_yours'));

        return $offering;
    }

    /**
     * Whether the acting user is entering marks centrally — holds `grades.enter` (which the route
     * has already established) but not `grades.finalize`. Read from the registered §3.2 gates
     * rather than from a role, so it stays true to the matrix if a role's permissions ever move.
     */
    private function actingAsCentralEntry(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $user->can(Permission::EnterGrades->value)
            && ! $user->can(Permission::FinalizeGrades->value);
    }

    /**
     * Attribute a write to a Sheet whose Instructor is someone else to the Audit Log — central
     * data entry, so the Instructor stays accountable for grades they did not type. A no-op when
     * the actor **is** the assigned Instructor: the Phase-3 precedent that the legitimately
     * scoped party's own action stays out of the audit trail.
     *
     * The `_centrally` action suffix, rather than the Phase-2/3 `_by_admin`, is deliberate: this
     * is Student Affairs exercising a permission the matrix grants them outright, not an
     * administrator overriding a scope guard.
     *
     * @param  array<string, mixed>  $metadata  Extra facts about the write, merged after the
     *                                          routing facts every entry carries.
     */
    private function auditCentralEntry(
        int $actorUserId,
        OfferingSummary $offering,
        AuditLogger $audit,
        string $action,
        int $gradeSheetId,
        array $metadata = [],
    ): void {
        if ($offering->instructorId === $actorUserId) {
            return;
        }

        $audit->record(
            action: $action,
            actorUserId: $actorUserId,
            metadata: [
                'course_offering_id' => $offering->id,
                'assigned_instructor_id' => $offering->instructorId,
                ...$metadata,
            ],
            subjectType: 'grade_sheet',
            subjectId: (string) $gradeSheetId,
        );
    }

    /** The acting user's id — the one place the guards and the audit read it from. */
    private function actingUserId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
