<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleSlug: string
{
    case SuperAdmin           = 'super_admin';
    case HrDirector           = 'hr_director';
    case HrManager            = 'hr_manager';
    case Recruiter            = 'recruiter';
    case TechnicalInterviewer = 'technical_interviewer';
    case DepartmentManager    = 'department_manager';
    case OperationsManager    = 'operations_manager';
    case ExecutiveReviewer    = 'executive_reviewer';
    case Viewer               = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin           => 'Super Admin',
            self::HrDirector           => 'HR Director',
            self::HrManager            => 'HR Manager',
            self::Recruiter            => 'Recruiter',
            self::TechnicalInterviewer => 'Technical Interviewer',
            self::DepartmentManager    => 'Department Manager',
            self::OperationsManager    => 'Operations Manager',
            self::ExecutiveReviewer    => 'Executive Reviewer',
            self::Viewer               => 'Interviewer Viewer',
        };
    }

    /** Roles whose data access is limited to their own department(s). */
    public function isDepartmentScoped(): bool
    {
        return in_array($this, [self::DepartmentManager, self::OperationsManager, self::Viewer], true);
    }
}
