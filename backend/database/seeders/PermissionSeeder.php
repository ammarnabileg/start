<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'jobs.view', 'jobs.create', 'jobs.edit', 'jobs.delete', 'jobs.publish',
            'candidates.view', 'candidates.manage', 'candidates.export',
            'interviews.view', 'interviews.manage', 'interviews.evaluate',
            'offers.view', 'offers.create', 'offers.send',
            'talent-pool.view', 'talent-pool.manage',
            'users.view', 'users.manage',
            'settings.view', 'settings.manage',
            'analytics.view',
            'pipeline.manage',
            'human-interviews.schedule', 'human-interviews.evaluate',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }

        $roles = [
            'super_admin' => $permissions,
            'company_owner' => $permissions,
            'hr_director' => $permissions,
            'hr_manager' => array_filter($permissions, fn($p) => !in_array($p, ['settings.manage', 'users.manage'])),
            'recruiter' => ['jobs.view', 'candidates.view', 'candidates.manage', 'interviews.view', 'pipeline.manage', 'talent-pool.view'],
            'technical_interviewer' => ['candidates.view', 'interviews.view', 'interviews.evaluate', 'human-interviews.evaluate'],
            'department_manager' => ['jobs.view', 'candidates.view', 'interviews.view', 'human-interviews.schedule', 'human-interviews.evaluate'],
            'operations_manager' => ['jobs.view', 'candidates.view', 'interviews.view', 'analytics.view'],
            'executive_reviewer' => ['jobs.view', 'candidates.view', 'interviews.view', 'analytics.view'],
            'viewer' => ['jobs.view', 'candidates.view', 'interviews.view'],
            'candidate' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
