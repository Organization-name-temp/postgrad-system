<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Http\Resources\Concerns\RendersProgramSummary;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;

/**
 * An Application in the { data: ... } envelope. Carries the Application's identity
 * (id/status/cycle) plus the Step-1 personal-info snapshot filled so far — nulls
 * where the applicant has not yet provided a field — so the SPA can render and
 * resume the wizard. The documents uploaded so far (ticket 05) and the selected
 * program(s) (ticket 06) are included; the status timeline is a separate read
 * (`GET /applications/{id}/timeline`, ticket 07), not embedded here.
 *
 * Program selection is surfaced two ways: the opaque `primary_program_id` /
 * `alternative_program_id` the applicant stores, and — resolved through the
 * `Catalog\Public` {@see ProgramCatalog} facade (ADR-0012) — a `primary_program` /
 * `alternative_program` summary object for the review and reviewer screens to
 * render without re-reading the catalog tree. A dropped reference resolves to null.
 *
 * The verification link is *not* part of this payload: it is returned only by the
 * submit and resend endpoints (which add `verification_url` alongside `data`), so
 * a plain read cannot mint fresh links and bypass the resend throttle.
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    use RendersProgramSummary;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        // Resolve the chosen program(s) in one facade read (empty ids ⇒ no read).
        $programIds = array_values(array_filter(
            [$application->primary_program_id, $application->alternative_program_id],
            static fn (?int $id): bool => $id !== null,
        ));
        $programs = $programIds === [] ? [] : app(ProgramCatalog::class)->findMany($programIds);

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
            'applying_for_degree' => $application->applying_for_degree?->value,
            'english_test' => $application->english_test?->value,
            'english_score' => $application->english_score,
            'native_arabic_speaker' => $application->native_arabic_speaker,
            'submitted_at' => $application->submitted_at?->toIso8601String(),

            // Program selection (ticket 06): the opaque refs plus resolved summaries.
            'primary_program_id' => $application->primary_program_id,
            'alternative_program_id' => $application->alternative_program_id,
            'primary_program' => $this->programSummaryArray($programs[$application->primary_program_id] ?? null),
            'alternative_program' => $this->programSummaryArray($programs[$application->alternative_program_id] ?? null),

            'finalized_at' => $application->finalized_at?->toIso8601String(),

            // Documents uploaded so far (ticket 05).
            'documents' => ApplicationDocumentResource::collection($application->documents),
        ];
    }
}
