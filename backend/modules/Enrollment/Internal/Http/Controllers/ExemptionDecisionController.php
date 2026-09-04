<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Enrollment\Internal\Http\Resources\ExemptionDecisionResource;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Support\AutomaticExemptionReadiness;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Credit Decisions */
class ExemptionDecisionController
{
    /**
     * My effective Exemptions.
     *
     * The acting Student's immutable automatic Preparatory Exemption decisions,
     * available before composing a Registration Request.
     *
     * @response 200 {"data": [{"id": 1, "student_id": 1, "curriculum_version_id": 12, "target_course_id": 8, "target_course_code": "PRE401", "preparatory_requirement_role": "EnglishLanguage", "credit_hours": 1, "source": "automatic", "reason_codes": ["english_proficiency"], "admission_application_id": 5, "approved_at": "2026-09-04T10:00:00+00:00"}]}
     * @response 403 {"message": "This action is unauthorized."}
     * @response 409 {"message": "Your admission exemptions are still being reconciled. Retry before composing registration."}
     */
    public function index(Request $request, StudentDirectory $students, AutomaticExemptionReadiness $readiness): AnonymousResourceCollection
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);
        $readiness->ensureReconciled($student);

        $decisions = ExemptionDecision::query()
            ->where('student_id', $student->studentId)
            ->orderBy('approved_at')
            ->orderBy('id')
            ->get();

        return ExemptionDecisionResource::collection($decisions);
    }
}
