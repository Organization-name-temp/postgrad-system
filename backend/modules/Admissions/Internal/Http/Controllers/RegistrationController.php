<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Public\Contracts\ApplicantRegistrar;

/**
 * @group Admissions
 *
 * Public self-registration — the first unauthenticated door into the system
 * (ticket 03). Registering IS starting an application: in one transaction it
 * mints a User with the Applicant role (R01, via Identity's {@see ApplicantRegistrar}
 * facade — Admissions never touches the User model) and a Draft Application in an
 * open Admission Cycle, then logs the applicant in via Sanctum so they land
 * straight in their wizard. There is no orphan-account state.
 */
class RegistrationController
{
    /**
     * Register and start an application.
     *
     * Open only while an Admission Cycle's window is open: refused when none is
     * open, auto-filed into the one open Cycle, or (when several are open) filed
     * into the chosen one. Rate-limited per client. On success the applicant is
     * authenticated and their Draft Application is returned. Requests without a
     * first-party SPA session are refused before any records are created.
     *
     * @unauthenticated
     *
     * @bodyParam name string required The applicant's full name. Example: Sara Applicant
     * @bodyParam email string required A unique email address; becomes the login. Example: sara@example.com
     * @bodyParam password string required At least 8 characters; must match the confirmation. Example: correct-horse
     * @bodyParam password_confirmation string required Repeat of the password. Example: correct-horse
     * @bodyParam admission_cycle_id integer The open Cycle to apply to. Required only when more than one Cycle is open; otherwise the single open Cycle is used. Example: 1
     *
     * @response 201 {"data": {"id": 1, "status": "draft", "admission_cycle_id": 1}}
     * @response 400 {"message": "A first-party SPA session is required."}
     * @response 409 scenario="admissions closed" {"message": "Admissions are currently closed."}
     * @response 422 scenario="validation" {"message": "The email has already been taken.", "errors": {"email": ["The email has already been taken."]}}
     */
    public function __invoke(Request $request, ApplicantRegistrar $registrar): JsonResponse
    {
        // The window gates the whole act. Checked first so a stranger arriving
        // after the intake closed gets a clear "closed" answer, not field errors.
        // 409: the request conflicts with the current server state (no open cycle)
        // — a business-state refusal, not input validation (422).
        $openCycles = AdmissionCycle::query()->currentlyOpen()->get();

        if ($openCycles->isEmpty()) {
            return response()->json(['message' => __('admissions.registration.closed')], 409);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        // With exactly one open Cycle the choice is implicit; with several, the
        // applicant must name which open intake they are applying to.
        if ($openCycles->count() > 1) {
            $rules['admission_cycle_id'] = ['required', 'integer', Rule::in($openCycles->pluck('id')->all())];
        }

        $validated = $request->validate($rules);

        $cycle = $openCycles->count() === 1
            ? $openCycles->first()
            : $openCycles->firstWhere('id', (int) $validated['admission_cycle_id']);

        // One atomic act: the User (R01) and their Application commit together. The
        // Application starts as a Draft by the model's default (status is guarded,
        // not mass-assignable).
        $application = DB::transaction(fn (): Application => Application::create([
            'user_id' => $registrar->register($validated['name'], $validated['email'], $validated['password']),
            'admission_cycle_id' => $cycle->id,
        ]));

        // Session established only after the transaction commits.
        Auth::loginUsingId($application->user_id);
        $request->session()->regenerate();

        return (new ApplicationResource($application))->response()->setStatusCode(201);
    }
}
