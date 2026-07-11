<?php

namespace Modules\Identity\Public\Enums;

/**
 * The fixed permission set from SRS §3.2 — one case per matrix function. Like
 * {@see Role}, the set and its role assignments are university bylaws, not
 * runtime-editable (ADR-0008). Each case's {@see self::roles()} lists the roles
 * that hold it; System/Super Admin (R09/R10) hold every permission and are
 * applied in {@see self::forRoles()}, not repeated per case.
 */
enum Permission: string
{
    case SubmitApplication = 'application.submit';
    case UploadDocuments = 'documents.upload';
    case ViewApplicationStatus = 'application.view_status';
    case ScreenApplications = 'application.screen';
    case ScheduleInterview = 'interview.schedule';
    case DecideApplicants = 'applicant.decide';
    case RegisterCoursesSelf = 'courses.register_self';
    case RegisterCoursesForStudent = 'courses.register_for_student';
    case ApproveCourseRegistration = 'registration.approve';
    case ViewCourseCatalog = 'catalog.view';
    case ManagePrograms = 'catalog.manage';
    case SubmitExemption = 'exemption.submit';
    case ApproveExemption = 'exemption.approve';
    case SubmitTransferWaiver = 'transfer_waiver.submit';
    case ApproveTransferWaiver = 'transfer_waiver.approve';
    case EnterGrades = 'grades.enter';
    case FinalizeGrades = 'grades.finalize';
    case ViewOwnGrades = 'grades.view_own';
    case ViewStudentGrades = 'grades.view_student';
    case ViewUnofficialTranscript = 'transcript.view_unofficial';
    case IssueOfficialTranscript = 'transcript.issue_official';
    case ExportTranscript = 'transcript.export';
    case MonitorAcademicStanding = 'standing.monitor';
    case RecommendTermination = 'termination.recommend';
    case ApproveTermination = 'termination.approve';
    case ManageAcademicCalendar = 'calendar.manage';

    /**
     * The roles that hold this permission, transcribed from the SRS §3.2 matrix
     * (excluding the R09/R10 admin blanket — see {@see self::forRoles()}). The
     * matrix footnote qualifiers (own-only, unofficial-only) are row-level
     * scoping enforced in later phases, not separate permissions.
     *
     * @return list<Role>
     */
    public function roles(): array
    {
        return match ($this) {
            self::SubmitApplication => [Role::Applicant],
            self::UploadDocuments => [Role::Applicant, Role::Student, Role::StudentAffairs],
            self::ViewApplicationStatus => [Role::Applicant, Role::StudentAffairs, Role::Dean],
            self::ScreenApplications => [Role::StudentAffairs, Role::Dean],
            self::ScheduleInterview => [Role::StudentAffairs, Role::Dean],
            self::DecideApplicants => [Role::Dean],
            self::RegisterCoursesSelf => [Role::Student],
            self::RegisterCoursesForStudent => [Role::Advisor, Role::StudentAffairs],
            self::ApproveCourseRegistration => [Role::Advisor, Role::StudentAffairs],
            self::ViewCourseCatalog => [
                Role::Applicant, Role::Student, Role::Advisor, Role::Instructor,
                Role::StudentAffairs, Role::DepartmentHead, Role::Dean, Role::Council,
            ],
            self::ManagePrograms => [Role::DepartmentHead, Role::Dean],
            self::SubmitExemption => [Role::Student],
            self::ApproveExemption => [Role::Advisor, Role::DepartmentHead],
            self::SubmitTransferWaiver => [Role::Student],
            self::ApproveTransferWaiver => [Role::Advisor, Role::DepartmentHead, Role::Council],
            self::EnterGrades => [Role::Instructor, Role::StudentAffairs],
            self::FinalizeGrades => [Role::Instructor, Role::DepartmentHead],
            self::ViewOwnGrades => [Role::Student],
            self::ViewStudentGrades => [
                Role::Advisor, Role::Instructor, Role::StudentAffairs,
                Role::DepartmentHead, Role::Dean, Role::Council,
            ],
            self::ViewUnofficialTranscript => [Role::Student, Role::Advisor, Role::StudentAffairs],
            self::IssueOfficialTranscript => [Role::StudentAffairs],
            self::ExportTranscript => [Role::Student, Role::StudentAffairs, Role::Dean],
            self::MonitorAcademicStanding => [
                Role::Student, Role::Advisor, Role::StudentAffairs,
                Role::DepartmentHead, Role::Dean, Role::Council,
            ],
            self::RecommendTermination => [Role::Advisor, Role::DepartmentHead],
            self::ApproveTermination => [Role::Council],
            self::ManageAcademicCalendar => [Role::StudentAffairs, Role::Dean],
        };
    }

    /**
     * The effective permissions for a user holding the given roles: the union of
     * each role's permissions. System Administrator (R09) and Super Administrator
     * (R10) hold every permission.
     *
     * @param  list<Role>  $roles
     * @return list<Permission>
     */
    public static function forRoles(array $roles): array
    {
        $isAdmin = array_any(
            $roles,
            static fn (Role $role): bool => $role === Role::SystemAdmin || $role === Role::SuperAdmin,
        );

        if ($isAdmin) {
            return self::cases();
        }

        return array_values(array_filter(
            self::cases(),
            static fn (Permission $permission): bool => array_any(
                $permission->roles(),
                static fn (Role $granting): bool => in_array($granting, $roles, true),
            ),
        ));
    }
}
