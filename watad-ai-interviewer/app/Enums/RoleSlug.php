<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleSlug: string
{
    case SuperAdmin  = 'super_admin';
    case HrManager   = 'hr_manager';
    case Recruiter   = 'recruiter';
    case DeptManager = 'dept_manager';
    case Viewer      = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin  => 'Super Admin',
            self::HrManager   => 'HR Manager',
            self::Recruiter   => 'Recruiter',
            self::DeptManager => 'Department Manager',
            self::Viewer      => 'Interviewer Viewer',
        };
    }

    /** Roles whose data access is limited to their own department(s). */
    public function isDepartmentScoped(): bool
    {
        return in_array($this, [self::DeptManager, self::Viewer], true);
    }
}
