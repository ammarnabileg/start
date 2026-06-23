<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\RoleSlug;

/**
 * Granular permission catalog: uniform CRUD (view / create / update / delete) for every module,
 * plus special abilities. Editable from the Roles & Permissions admin UI. Super Admin always has
 * full control (Gate::before). See docs/25-permissions-matrix.md.
 */
final class Permissions
{
    /** Resource key => label. Each gets view/create/update/delete. */
    public const RESOURCES = [
        'jobs'            => 'Jobs',
        'applications'    => 'Applications',
        'candidates'      => 'Candidates',
        'ai_interviews'   => 'AI Interviews',
        'human_interviews' => 'Human Interviews',
        'evaluations'     => 'Evaluations',
        'offers'          => 'Offers',
        'departments'     => 'Departments',
        'pipelines'       => 'Pipelines',
        'templates'       => 'Interview Templates',
        'avatars'         => 'Avatars',
        'questions'       => 'Questions',
        'talent_pool'     => 'Talent Pool',
        'documents'       => 'Documents',
        'notes'           => 'Notes',
        'tags'            => 'Tags',
        'users'           => 'Users',
        'roles'           => 'Roles',
        'reports'         => 'Reports',
        'settings'        => 'Settings',
        'integrations'    => 'Integrations',
        'ai_config'       => 'AI Configuration',
    ];

    public const ACTIONS = [
        'view'   => 'View',
        'create' => 'Create',
        'update' => 'Edit',
        'delete' => 'Delete',
    ];

    /** Extra non-CRUD abilities: slug => [label, resource-group]. */
    public const EXTRA = [
        'invitations.create'          => ['Create AI interview invitations', 'ai_interviews'],
        'ai_interviews.monitor'       => ['Monitor live AI interviews', 'ai_interviews'],
        'interviews.schedule'         => ['Schedule human interviews', 'human_interviews'],
        'decisions.advance'           => ['Advance candidate', 'applications'],
        'decisions.reject'            => ['Reject candidate', 'applications'],
        'decisions.override_ai'       => ['Override AI decision', 'applications'],
        'decisions.approve'           => ['Final approval', 'applications'],
        'decisions.make_offer'        => ['Make / extend offer', 'offers'],
        'candidates.move_stage'       => ['Move on pipeline', 'candidates'],
        'candidates.access_financial' => ['Access financial info', 'candidates'],
        'candidates.access_sensitive' => ['Access sensitive PII', 'candidates'],
        'reports.export'              => ['Export reports', 'reports'],
        'data.export'                 => ['Bulk / API data export', 'reports'],
        'workflows.manage'            => ['Manage workflows & pipelines', 'pipelines'],
        'audit.view'                  => ['View audit logs', 'settings'],
    ];

    /** @return array<string, array{name:string, group:string}> */
    public static function catalog(): array
    {
        $catalog = [];
        foreach (self::RESOURCES as $resource => $label) {
            foreach (self::ACTIONS as $action => $actionLabel) {
                $catalog["{$resource}.{$action}"] = ['name' => "{$actionLabel} {$label}", 'group' => $resource];
            }
        }
        foreach (self::EXTRA as $slug => [$name, $group]) {
            $catalog[$slug] = ['name' => $name, 'group' => $group];
        }
        return $catalog;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::catalog());
    }

    /** Build CRUD slugs for the given resources. */
    private static function crud(string ...$resources): array
    {
        $out = [];
        foreach ($resources as $r) {
            foreach (array_keys(self::ACTIONS) as $a) {
                $out[] = "{$r}.{$a}";
            }
        }
        return $out;
    }

    /** Build view-only slugs for the given resources. */
    private static function viewOnly(string ...$resources): array
    {
        return array_map(fn (string $r) => "{$r}.view", $resources);
    }

    private static function clean(array $slugs): array
    {
        return array_values(array_intersect(array_unique($slugs), self::all()));
    }

    /** Role => permission slugs ('*' = all). Default seed; editable later in the admin UI. */
    public static function matrix(): array
    {
        return [
            RoleSlug::SuperAdmin->value => ['*'],

            RoleSlug::HrDirector->value => array_values(array_diff(self::all(), ['roles.delete', 'settings.delete'])),

            RoleSlug::HrManager->value => self::clean(array_merge(
                self::crud('jobs', 'applications', 'candidates', 'ai_interviews', 'human_interviews',
                    'evaluations', 'offers', 'talent_pool', 'documents', 'notes', 'tags', 'questions'),
                self::viewOnly('templates', 'avatars', 'pipelines', 'departments', 'reports', 'ai_config', 'settings'),
                ['invitations.create', 'ai_interviews.monitor', 'interviews.schedule',
                 'decisions.advance', 'decisions.reject', 'decisions.override_ai', 'decisions.approve', 'decisions.make_offer',
                 'candidates.move_stage', 'candidates.access_financial', 'candidates.access_sensitive',
                 'reports.export', 'data.export', 'workflows.manage', 'audit.view'],
            )),

            RoleSlug::Recruiter->value => self::clean(array_merge(
                ['jobs.view', 'jobs.create', 'jobs.update'],
                ['applications.view', 'applications.create', 'applications.update'],
                ['candidates.view', 'candidates.create', 'candidates.update'],
                ['ai_interviews.view', 'human_interviews.view', 'human_interviews.create', 'human_interviews.update'],
                ['evaluations.view', 'offers.view'],
                ['talent_pool.view', 'talent_pool.create'],
                ['documents.view', 'documents.create', 'notes.view', 'notes.create', 'tags.view', 'tags.create'],
                self::viewOnly('templates', 'avatars', 'questions', 'pipelines', 'departments', 'reports'),
                ['invitations.create', 'ai_interviews.monitor', 'interviews.schedule',
                 'decisions.advance', 'decisions.reject', 'candidates.move_stage', 'reports.export'],
            )),

            RoleSlug::TechnicalInterviewer->value => self::clean([
                'candidates.view', 'ai_interviews.view', 'human_interviews.view',
                'evaluations.view', 'evaluations.create', 'evaluations.update',
                'documents.view', 'notes.view', 'notes.create', 'reports.view',
            ]),

            RoleSlug::DepartmentManager->value => self::clean([
                'jobs.view', 'applications.view', 'candidates.view',
                'ai_interviews.view', 'human_interviews.view', 'human_interviews.create',
                'evaluations.view', 'evaluations.create', 'documents.view', 'notes.view', 'notes.create',
                'pipelines.view', 'departments.view', 'reports.view', 'reports.export',
                'interviews.schedule', 'decisions.advance', 'decisions.reject', 'candidates.move_stage',
            ]),

            RoleSlug::OperationsManager->value => self::clean([
                'jobs.view', 'applications.view', 'candidates.view',
                'ai_interviews.view', 'human_interviews.view', 'human_interviews.create',
                'evaluations.view', 'evaluations.create', 'documents.view', 'notes.view', 'notes.create',
                'pipelines.view', 'departments.view', 'reports.view', 'reports.export',
                'interviews.schedule', 'decisions.advance', 'decisions.reject', 'candidates.move_stage',
            ]),

            RoleSlug::ExecutiveReviewer->value => self::clean([
                'jobs.view', 'applications.view', 'candidates.view',
                'candidates.access_financial', 'candidates.access_sensitive',
                'ai_interviews.view', 'human_interviews.view', 'evaluations.view',
                'offers.view', 'offers.create', 'offers.update', 'reports.view', 'reports.export', 'audit.view',
                'decisions.approve', 'decisions.reject', 'decisions.make_offer', 'decisions.override_ai',
                'candidates.move_stage', 'pipelines.view',
            ]),

            RoleSlug::Viewer->value => self::clean([
                'jobs.view', 'applications.view', 'candidates.view', 'ai_interviews.view',
                'human_interviews.view', 'evaluations.view', 'offers.view', 'reports.view', 'pipelines.view',
            ]),
        ];
    }
}
