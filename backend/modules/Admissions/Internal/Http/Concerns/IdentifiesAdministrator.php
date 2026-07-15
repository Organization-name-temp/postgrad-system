<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\Directory;

/**
 * The one place the administrative override (ADR-0015) is decided. A System (R09) or
 * Super (R10) Administrator holds every Permission (ADR-0008), which the route gates
 * already honour; this adds the *scope* bypass permissions don't cover — the Dean
 * faculty-scope ({@see ScopesToDeanFaculty}) and the interviewer row-level check in
 * `InterviewEvaluationController`.
 *
 * Whether the signed-in user is an administrator is Identity's to say, so it is read
 * through the Identity\Public `Directory` facade rather than inspected here — keeping
 * the definition of "administrator" in one module. Sharing this predicate is what makes
 * the bypass consistent at every guard (ADR-0015).
 */
trait IdentifiesAdministrator
{
    /** Whether the signed-in user may exercise the administrative override (R09/R10). */
    protected function actingAsAdministrator(Request $request, Directory $directory): bool
    {
        return $directory->isAdministrator((int) $request->user()?->getAuthIdentifier());
    }
}
