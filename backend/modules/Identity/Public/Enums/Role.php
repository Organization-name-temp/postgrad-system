<?php

namespace Modules\Identity\Public\Enums;

/**
 * The ten fixed roles from SRS §3.1. The set is closed and lives in code; only
 * the *assignment* of roles to users is editable at runtime (ADR-0008).
 */
enum Role: string
{
    case Applicant = 'R01';
    case Student = 'R02';
    case Advisor = 'R03';
    case Instructor = 'R04';
    case StudentAffairs = 'R05';
    case DepartmentHead = 'R06';
    case Dean = 'R07';
    case Council = 'R08';
    case SystemAdmin = 'R09';
    case SuperAdmin = 'R10';

    /** Human-readable name as written in SRS §3.1. */
    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Applicant',
            self::Student => 'Student',
            self::Advisor => 'Academic Advisor',
            self::Instructor => 'Instructor',
            self::StudentAffairs => 'Student Affairs Staff',
            self::DepartmentHead => 'Department Head',
            self::Dean => 'Dean of Faculty',
            self::Council => 'Education & Academic Affairs Council',
            self::SystemAdmin => 'System Administrator',
            self::SuperAdmin => 'Super Administrator',
        };
    }
}
