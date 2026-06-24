<?php
/**
 * Master list of all platform permissions grouped by module.
 * Used by the installer to seed the permissions table and by RBAC.
 */
return [
    'dashboard' => [
        'dashboard.view' => 'View Dashboard',
    ],
    'jobs' => [
        'jobs.view'    => 'View Jobs',
        'jobs.create'  => 'Create Jobs',
        'jobs.edit'    => 'Edit Jobs',
        'jobs.delete'  => 'Delete Jobs',
        'jobs.publish' => 'Publish Jobs',
    ],
    'candidates' => [
        'candidates.view'    => 'View Candidates',
        'candidates.create'  => 'Create Candidates',
        'candidates.edit'    => 'Edit Candidates',
        'candidates.delete'  => 'Delete Candidates',
        'candidates.compare' => 'Compare Candidates',
    ],
    'interviews' => [
        'interviews.view'    => 'View Interviews',
        'interviews.create'  => 'Create Interviews',
        'interviews.report'  => 'View Interview Reports',
    ],
    'pipeline' => [
        'pipeline.view'   => 'View Pipeline',
        'pipeline.manage' => 'Manage Pipeline',
    ],
    'offers' => [
        'offers.view'   => 'View Offers',
        'offers.create' => 'Create Offers',
        'offers.send'   => 'Send Offers',
    ],
    'talent_pool' => [
        'talent_pool.view'   => 'View Talent Pool',
        'talent_pool.manage' => 'Manage Talent Pool',
    ],
    'avatars' => [
        'avatars.view'   => 'View Avatars',
        'avatars.manage' => 'Manage Avatars',
    ],
    'users' => [
        'users.view'   => 'View Users',
        'users.manage' => 'Manage Users',
    ],
    'roles' => [
        'roles.view'   => 'View Roles',
        'roles.manage' => 'Manage Roles',
    ],
    'settings' => [
        'settings.view'   => 'View Settings',
        'settings.manage' => 'Manage Settings',
    ],
    'ai' => [
        'ai.use'       => 'Use AI Features',
        'ai.analytics' => 'View AI Analytics',
    ],
    'platform' => [
        'platform.admin'      => 'Platform Administration',
        'platform.companies'  => 'Manage Companies',
        'platform.terminal'   => 'Access Terminal',
        'platform.analytics'  => 'Platform Analytics',
    ],
];
