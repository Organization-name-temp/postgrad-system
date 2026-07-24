<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Identity\Public\Contracts\Directory;

/**
 * The one place the administrative override (ADR-0015) is decided for Enrollment — the
 * Phase-2.5 `IdentifiesAdministrator` pattern, applied here to the Phase-3 scope guards
 * that permissions don't cover: the advisor advisee-scope ({@see ScopesToAdvisees}) and
 * the attendance instructor-assignment check in `AttendanceController`.
 *
 * A System (R09) or Super (R10) Administrator holds every Permission (ADR-0008), which the
 * route gates already honour; this adds the *scope* bypass on top. Whether the signed-in
 * user is an administrator is Identity's to say, so it is read through the Identity\Public
 * `Directory` facade rather than inspected here — keeping the definition of "administrator"
 * in one module and the bypass consistent at every guard.
 */
trait IdentifiesAdministrator
{
    /** Whether the signed-in user may exercise the administrative override (R09/R10). */
    protected function actingAsAdministrator(Request $request, Directory $directory): bool
    {
        return $directory->isAdministrator((int) $request->user()?->getAuthIdentifier());
    }
}
