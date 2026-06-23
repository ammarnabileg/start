<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleSlug;

/**
 * Granular permission catalog: uniform CRUD (view / create / update / delete) for every resource,
 * plus a few non-CRUD abilities. The Super Admin always has full control (enforced in
 * AppServiceProvider via Gate::before). Roles & their permissions are editable from the admin UI
 * (Roles & Permissions screen). See docs/14-rbac.md.
 */
final class Permissions
{
    /** Resource key => human label. Each gets view/create/update/delete permissions. */
    public const RESOURCES = [
        'jobs'        => 'Jobs',
        'departments' => 'Departments',
        'candidates'  => 'Candidates',
        'interviews'  => 'Interviews',
        'templates'   => 'Templates',
        'avatars'     => 'Avatars',
        'questions'   => 'Questions',
        'pipelines'   => 'Pipelines',
        'users'       => 'Users',
        'roles'       => 'Roles',
        'reports'     => 'Reports',
        'settings'    => 'Settings',
    ];

    /** CRUD action key => label. */
    public const ACTIONS = [
        'view'   => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ];

    /** Extra non-CRUD abilities: slug => [label, resource-group]. */
    public const EXTRA = [
        'invitations.create'    => ['Create invitations', 'jobs'],
        'interviews.monitor'    => ['Monitor live interviews', 'interviews'],
        'interviews.move_stage' => ['Move pipeline stage', 'interviews'],
        'reports.export'        => ['Export reports', 'reports'],
        'candidates.erase'      => ['Erase candidate data (GDPR)', 'candidates'],
        'integrations.manage'   => ['Manage integrations', 'settings'],
        'audit.view'            => ['View audit log', 'settings'],
    ];

    /** @return array<string, array{name:string, group:string}> full slug => meta catalog. */
    public static function catalog(): array
    {
        $catalog = [];

        foreach (self::RESOURCES as $resource => $label) {
            foreach (self::ACTIONS as $action => $actionLabel) {
                $catalog["{$resource}.{$action}"] = [
                    'name'  => "{$actionLabel} {$label}",
                    'group' => $resource,
                ];
            }
        }

        foreach (self::EXTRA as $slug => [$name, $group]) {
            $catalog[$slug] = ['name' => $name, 'group' => $group];
        }

        return $catalog;
    }

    /** @return list<string> every permission slug. */
    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    /** Role => permission slugs ('*' = all). Editable later from the admin UI. */
    public static function matrix(): array
    {
        $adminOnly = [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.view', 'roles.create', 'roles.update', 'roles.delete',
            'settings.create', 'settings.update', 'settings.delete', 'integrations.manage',
        ];

        return [
            RoleSlug::SuperAdmin->value => ['*'],

            // HR Manager: full hiring control, but not user/role/settings administration.
            RoleSlug::HrManager->value => array_values(array_diff(self::all(), $adminOnly)),

            RoleSlug::Recruiter->value => [
                'jobs.view', 'jobs.create', 'jobs.update', 'invitations.create',
                'candidates.view', 'candidates.update',
                'interviews.view', 'interviews.monitor', 'interviews.move_stage',
                'reports.view', 'reports.export',
                'templates.view', 'avatars.view', 'questions.view', 'questions.create',
                'pipelines.view', 'departments.view',
            ],

            RoleSlug::DeptManager->value => [
                'jobs.view', 'candidates.view', 'interviews.view', 'interviews.move_stage',
                'reports.view', 'reports.export', 'pipelines.view', 'departments.view',
            ],

            RoleSlug::Viewer->value => [
                'jobs.view', 'candidates.view', 'interviews.view', 'reports.view', 'pipelines.view',
            ],
        ];
    }
}
