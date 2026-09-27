<?php

namespace Modules\Records\Internal\Support;

use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;

final readonly class RecordsAccess
{
    public function __construct(private Directory $directory) {}

    public function staff(int $userId): bool
    {
        return $this->directory->isAdministrator($userId) || $this->directory->hasRole($userId, Role::StudentAffairs);
    }

    public function reads(int $userId, StudentSummary $student): bool
    {
        return $this->staff($userId)
            || ($student->userId === $userId && $this->directory->hasRole($userId, Role::Student))
            || ($student->advisorId === $userId && $this->directory->hasRole($userId, Role::Advisor));
    }
}
