<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\Application;

/**
 * An Application in the { data: ... } envelope. Carries the Application's identity
 * (id/status/cycle) plus the Step-1 personal-info snapshot filled so far — nulls
 * where the applicant has not yet provided a field — so the SPA can render and
 * resume the wizard. The documents uploaded so far are included (ticket 05);
 * program selection and the status timeline are added by later tickets.
 *
 * The verification link is *not* part of this payload: it is returned only by the
 * submit and resend endpoints (which add `verification_url` alongside `data`), so
 * a plain read cannot mint fresh links and bypass the resend throttle.
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        return [
            'id' => $application->getKey(),
            'status' => $application->status->value,
            'admission_cycle_id' => $application->admission_cycle_id,

            // Step-1 personal-info snapshot.
            'name_en' => $application->name_en,
            'name_ar' => $application->name_ar,
            'national_id' => $application->national_id,
            'phone' => $application->phone,
            'date_of_birth' => $application->date_of_birth?->format('Y-m-d'),
            'nationality' => $application->nationality,
            'gender' => $application->gender?->value,
            'address_country' => $application->address_country,
            'address_city' => $application->address_city,
            'address_street' => $application->address_street,
            'previous_university' => $application->previous_university,
            'graduation_certificate_name' => $application->graduation_certificate_name,
            'graduation_date' => $application->graduation_date?->format('Y-m-d'),
            'previous_degree' => $application->previous_degree?->value,
            'previous_major' => $application->previous_major,
            'graduation_gpa' => $application->graduation_gpa,
            'graduation_cgpa' => $application->graduation_cgpa,
            'gpa_scale' => $application->gpa_scale?->value,
            'is_ejust_graduate' => $application->is_ejust_graduate,
            'applying_for_degree' => $application->applying_for_degree,
            'english_test' => $application->english_test?->value,
            'english_score' => $application->english_score,
            'native_arabic_speaker' => $application->native_arabic_speaker,
            'submitted_at' => $application->submitted_at?->toIso8601String(),

            // Documents uploaded so far (ticket 05).
            'documents' => ApplicationDocumentResource::collection($application->documents),
        ];
    }
}
