<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleSlug;

/**
 * The permission catalog and the role→permission matrix (docs/14-rbac.md).
 * Single source of truth used by RolePermissionSeeder and the authorization gates.
 */
final class Permissions
{
    /** @var array<string, array{name:string, group:string}> */
    public const CATALOG = [
        'job.view'            => ['name' => 'View jobs', 'group' => 'jobs'],
        'job.create'          => ['name' => 'Create jobs', 'group' => 'jobs'],
        'job.update'          => ['name' => 'Update jobs', 'group' => 'jobs'],
        'job.delete'          => ['name' => 'Delete jobs', 'group' => 'jobs'],
        'invitation.create'   => ['name' => 'Create invitations', 'group' => 'invitations'],
        'invitation.cancel'   => ['name' => 'Cancel invitations', 'group' => 'invitations'],
        'candidate.view'      => ['name' => 'View candidates', 'group' => 'candidates'],
        'candidate.update'    => ['name' => 'Update candidates', 'group' => 'candidates'],
        'candidate.delete'    => ['name' => 'Delete candidates', 'group' => 'candidates'],
        'gdpr.erase'          => ['name' => 'Erase candidate data (GDPR)', 'group' => 'candidates'],
        'interview.view'      => ['name' => 'View interviews', 'group' => 'interviews'],
        'interview.monitor'   => ['name' => 'Monitor live interviews', 'group' => 'interviews'],
        'interview.move_stage' => ['name' => 'Move pipeline stage', 'group' => 'interviews'],
        'report.view'         => ['name' => 'View reports', 'group' => 'reports'],
        'report.export'       => ['name' => 'Export reports', 'group' => 'reports'],
        'template.manage'     => ['name' => 'Manage templates', 'group' => 'config'],
        'avatar.manage'       => ['name' => 'Manage avatars', 'group' => 'config'],
        'question.manage'     => ['name' => 'Manage questions', 'group' => 'config'],
        'pipeline.manage'     => ['name' => 'Manage pipelines', 'group' => 'config'],
        'user.manage'         => ['name' => 'Manage users', 'group' => 'users'],
        'settings.manage'     => ['name' => 'Manage settings', 'group' => 'settings'],
        'integration.manage'  => ['name' => 'Manage integrations', 'group' => 'settings'],
        'audit.view'          => ['name' => 'View audit log', 'group' => 'audit'],
    ];

    /** Role → permission slugs. '*' means all permissions. */
    public static function matrix(): array
    {
        $all = array_keys(self::CATALOG);

        return [
            RoleSlug::SuperAdmin->value => ['*'],
            RoleSlug::HrManager->value => array_values(array_diff($all, [
                'user.manage', 'settings.manage', 'integration.manage',
            ])),
            RoleSlug::Recruiter->value => [
                'job.view', 'job.create', 'job.update', 'invitation.create', 'invitation.cancel',
                'candidate.view', 'candidate.update', 'interview.view', 'interview.monitor',
                'interview.move_stage', 'report.view', 'report.export', 'question.manage',
            ],
            RoleSlug::DeptManager->value => [
                'job.view', 'candidate.view', 'interview.view', 'interview.move_stage',
                'report.view', 'report.export',
            ],
            RoleSlug::Viewer->value => [
                'job.view', 'candidate.view', 'interview.view', 'report.view',
            ],
        ];
    }
}
