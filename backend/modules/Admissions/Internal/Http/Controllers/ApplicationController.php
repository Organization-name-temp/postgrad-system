<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\Gender;
use Modules\Admissions\Internal\Enums\GpaScale;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Http\Concerns\ResolvesCurrentApplication;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Verification\EmailVerificationLink;
use Modules\Identity\Public\Contracts\EmailVerifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * The applicant-owned Step-1 wizard (ticket 04). The signed-in applicant reads
 * their own Application, saves the personal-info snapshot per step ("save &
 * continue"/resume), and submits Step 1 — which issues the on-screen + logged
 * email-verification link. The snapshot is per-cycle (re-applying later captures
 * fresh data), not a shared profile. Endpoints act on *the current applicant's*
 * Application implicitly; there is no id in the path.
 */
class ApplicationController
{
    use ResolvesCurrentApplication;

    /**
     * My application.
     *
     * The signed-in applicant's current Application — its status and the whole
     * Step-1 snapshot (fields the applicant has not yet provided come back null),
     * so the SPA can render and resume the wizard at the right step.
     *
     * @response 200 {"data": {"id": 1, "status": "submitted", "admission_cycle_id": 1, "name_en": "Sara Applicant", "name_ar": "سارة المتقدمة", "national_id": "29001010101010", "phone": "+201000000000", "date_of_birth": "1998-05-20", "nationality": "Egyptian", "gender": "Female", "address_country": "Egypt", "address_city": "Alexandria", "address_street": "15 Corniche Road", "previous_university": "Cairo University", "graduation_certificate_name": "BSc in Computer Science", "graduation_date": "2020-07-01", "previous_degree": "BSc", "previous_major": "Computer Science", "graduation_gpa": "3.50", "graduation_cgpa": "3.45", "gpa_scale": "4.0", "is_ejust_graduate": false, "applying_for_degree": "MSc", "english_test": "IELTS", "english_score": "7.50", "native_arabic_speaker": true, "submitted_at": "2026-07-14T10:00:00+00:00", "documents": [{"id": "01ARZ3NDEKTSV4RRFFQ69G5FAV", "document_type": "graduation_certificate", "original_filename": "certificate.pdf", "mime": "application/pdf", "size_bytes": 24576, "uploaded_at": "2026-07-14T10:00:00+00:00", "download_url": "http://localhost/api/v1/documents/01ARZ3NDEKTSV4RRFFQ69G5FAV"}]}}
     * @response 401 {"message": "Unauthenticated."}
     * @response 404 {"message": "No application found."}
     */
    public function show(Request $request): ApplicationResource
    {
        return new ApplicationResource($this->currentApplication($request));
    }

    /**
     * Save Step 1 (partial).
     *
     * Persists whatever personal-info fields the applicant has entered so far,
     * keeping the Application a Draft so they can resume later. Each provided field
     * is validated (bilingually); missing fields are allowed. Editing is only
     * possible while the Application is still a Draft.
     *
     * @bodyParam name_en string Full name in English. Example: Sara Applicant
     * @bodyParam name_ar string Full name in Arabic. Example: سارة المتقدمة
     * @bodyParam national_id string National ID / passport; unique within the cycle. Example: 29001010101010
     * @bodyParam phone string Contact phone. Example: +201000000000
     * @bodyParam date_of_birth string Date of birth (Y-m-d), in the past. Example: 1998-05-20
     * @bodyParam nationality string Nationality. Example: Egyptian
     * @bodyParam gender string One of Male, Female. Example: Female
     * @bodyParam address_country string Country. Example: Egypt
     * @bodyParam address_city string City. Example: Alexandria
     * @bodyParam address_street string Street address. Example: 15 Corniche Road
     * @bodyParam previous_university string Previous university. Example: Cairo University
     * @bodyParam graduation_certificate_name string Name of the graduation certificate. Example: BSc in Computer Science
     * @bodyParam graduation_date string Graduation date (Y-m-d). Example: 2020-07-01
     * @bodyParam previous_degree string One of BSc, MSc, PhD. Example: BSc
     * @bodyParam previous_major string Previous major/specialization. Example: Computer Science
     * @bodyParam graduation_gpa number Graduation GPA. Example: 3.5
     * @bodyParam graduation_cgpa number Graduation CGPA. Example: 3.45
     * @bodyParam gpa_scale string One of 4.0, 4.3, 5.0, 6.0, 7.0, 10.0, 100. Example: 4.0
     * @bodyParam is_ejust_graduate boolean Whether the applicant graduated from E-JUST. Example: false
     * @bodyParam applying_for_degree string Degree applied for; one of MSc, PhD. Example: MSc
     * @bodyParam english_test string English test; one of IELTS, TOEFL iBT, TOEFL PBT, PTE, Cambridge C1, Cambridge C2, Cambridge B2, Duolingo, None. Example: IELTS
     * @bodyParam english_score number Score for the chosen test; required unless the test is None. Example: 7.5
     * @bodyParam native_arabic_speaker boolean Whether the applicant is a native Arabic speaker. Example: true
     *
     * @response 200 {"data": {"id": 1, "status": "draft", "admission_cycle_id": 1, "name_en": "Sara Applicant"}}
     * @response 409 scenario="not editable" {"message": "This application can no longer be edited."}
     * @response 422 scenario="validation" {"message": "The gender field must be a valid value.", "errors": {"gender": ["The gender field must be a valid value."]}}
     */
    public function update(Request $request): ApplicationResource
    {
        $application = $this->currentApplication($request);

        if (! $application->isApplicantEditable()) {
            throw new ConflictHttpException(__('admissions.application.not_editable'));
        }

        $application->update($request->validate($this->snapshotRules($application, required: false)));

        return new ApplicationResource($application);
    }

    /**
     * Submit Step 1.
     *
     * Validates the complete personal-info snapshot, persists it, and moves the
     * Draft forward: an unverified applicant to `submitted` with a fresh, signed
     * verification link returned in `verification_url` (and logged); an applicant
     * whose email is already verified straight to `email_verified` (the shortcut),
     * with `verification_url` null. Either way the applicant cannot reach the
     * documents step until their email is verified.
     *
     * @bodyParam name_en string required Full name in English. Example: Sara Applicant
     * @bodyParam name_ar string required Full name in Arabic. Example: سارة المتقدمة
     * @bodyParam national_id string required National ID / passport; unique within the cycle. Example: 29001010101010
     * @bodyParam phone string required Contact phone. Example: +201000000000
     * @bodyParam date_of_birth string required Date of birth (Y-m-d), in the past. Example: 1998-05-20
     * @bodyParam nationality string required Nationality. Example: Egyptian
     * @bodyParam gender string required One of Male, Female. Example: Female
     * @bodyParam address_country string required Country. Example: Egypt
     * @bodyParam address_city string required City. Example: Alexandria
     * @bodyParam address_street string required Street address. Example: 15 Corniche Road
     * @bodyParam previous_university string required Previous university. Example: Cairo University
     * @bodyParam graduation_certificate_name string required Name of the graduation certificate. Example: BSc in Computer Science
     * @bodyParam graduation_date string required Graduation date (Y-m-d). Example: 2020-07-01
     * @bodyParam previous_degree string required One of BSc, MSc, PhD. Example: BSc
     * @bodyParam previous_major string required Previous major/specialization. Example: Computer Science
     * @bodyParam graduation_gpa number required Graduation GPA. Example: 3.5
     * @bodyParam graduation_cgpa number required Graduation CGPA. Example: 3.45
     * @bodyParam gpa_scale string required One of 4.0, 4.3, 5.0, 6.0, 7.0, 10.0, 100. Example: 4.0
     * @bodyParam is_ejust_graduate boolean required Whether the applicant graduated from E-JUST. Example: false
     * @bodyParam applying_for_degree string required Degree applied for; one of MSc, PhD. Example: MSc
     * @bodyParam english_test string required English test; one of IELTS, TOEFL iBT, TOEFL PBT, PTE, Cambridge C1, Cambridge C2, Cambridge B2, Duolingo, None. Example: IELTS
     * @bodyParam english_score number Score for the chosen test; required unless the test is None. Example: 7.5
     * @bodyParam native_arabic_speaker boolean required Whether the applicant is a native Arabic speaker. Example: true
     *
     * @response 200 scenario="verification required" {"data": {"id": 1, "status": "submitted", "admission_cycle_id": 1, "name_en": "Sara Applicant"}, "verification_url": "http://localhost/api/v1/applications/1/verify-email?expires=1784000000&signature=abc123"}
     * @response 200 scenario="already verified" {"data": {"id": 1, "status": "email_verified", "admission_cycle_id": 1, "name_en": "Sara Applicant"}, "verification_url": null}
     * @response 409 scenario="already submitted" {"message": "Step 1 has already been submitted for this application."}
     * @response 422 scenario="incomplete" {"message": "The name ar field is required.", "errors": {"name_ar": ["The name ar field is required."]}}
     */
    public function submit(Request $request, EmailVerifier $verifier, EmailVerificationLink $link): JsonResponse
    {
        $application = $this->currentApplication($request);

        if (! $application->isApplicantEditable()) {
            throw new ConflictHttpException(__('admissions.application.already_submitted'));
        }

        $application->fill($request->validate($this->snapshotRules($application, required: true)));

        // Identity owns email verification; a User whose email is already verified
        // (e.g. re-applying in a later Cycle) skips straight to EmailVerified.
        $alreadyVerified = $verifier->isVerified((int) $application->user_id);
        $application->submit($alreadyVerified);

        $verificationUrl = $alreadyVerified ? null : $link->issue($application);

        return (new ApplicationResource($application))
            ->additional(['verification_url' => $verificationUrl])
            ->response();
    }

    /**
     * Validation rules for the Step-1 snapshot. On submit (`$required`) every field
     * is required; on partial save each is `sometimes` (validated only if present).
     * `national_id` is unique within the Application's Cycle (duplicate person in
     * one intake → 422, BR-03); `english_score` is required unless the test is None.
     * `applying_for_degree` is validated against MSc/PhD as a plain string — the
     * Catalog\Public seam that binds it to a DegreeLevel and cross-checks the chosen
     * Program opens in ticket 06 (ADR-0012).
     *
     * @return array<string, array<int, mixed>>
     */
    private function snapshotRules(Application $application, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name_en' => [$presence, 'string', 'min:2', 'max:100'],
            'name_ar' => [$presence, 'string', 'min:2', 'max:100'],
            'national_id' => [
                $presence, 'string', 'max:50',
                Rule::unique('applications', 'national_id')
                    ->where('admission_cycle_id', $application->admission_cycle_id)
                    ->ignore($application->getKey()),
            ],
            'phone' => [$presence, 'string', 'max:30'],
            'date_of_birth' => [$presence, 'date', 'before:today'],
            'nationality' => [$presence, 'string', 'max:100'],
            'gender' => [$presence, Rule::enum(Gender::class)],
            'address_country' => [$presence, 'string', 'max:100'],
            'address_city' => [$presence, 'string', 'max:100'],
            'address_street' => [$presence, 'string', 'max:255'],
            'previous_university' => [$presence, 'string', 'max:255'],
            'graduation_certificate_name' => [$presence, 'string', 'max:255'],
            'graduation_date' => [$presence, 'date', 'before_or_equal:today'],
            'previous_degree' => [$presence, Rule::enum(PreviousDegree::class)],
            'previous_major' => [$presence, 'string', 'max:200'],
            'graduation_gpa' => [$presence, 'numeric', 'min:0', 'max:100'],
            'graduation_cgpa' => [$presence, 'numeric', 'min:0', 'max:100'],
            'gpa_scale' => [$presence, Rule::enum(GpaScale::class)],
            'is_ejust_graduate' => [$presence, 'boolean'],
            'applying_for_degree' => [$presence, Rule::in(['MSc', 'PhD'])],
            'english_test' => [$presence, Rule::enum(EnglishTest::class)],
            // A score is required for any real test, forbidden semantics for None
            // (nullable so None may omit it). On partial save it is simply optional.
            'english_score' => $required
                ? ['required_unless:english_test,None', 'nullable', 'numeric', 'min:0', 'max:300']
                : ['sometimes', 'nullable', 'numeric', 'min:0', 'max:300'],
            'native_arabic_speaker' => [$presence, 'boolean'],
        ];
    }
}
