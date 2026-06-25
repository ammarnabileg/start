-- Seed role_permissions for system roles (no tenant_id = system-wide templates)
-- company_owner: all permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'company_owner' AND r.tenant_id IS NULL;

-- hr_director: all except settings.manage
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr_director' AND r.tenant_id IS NULL
  AND p.slug NOT IN ('settings.manage');

-- hr_manager: jobs, candidates, interviews, pipeline, offers, talent pool, reports, ai, users.view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'hr_manager' AND r.tenant_id IS NULL
  AND p.slug IN (
    'jobs.view','jobs.create','jobs.edit','jobs.archive',
    'candidates.view','candidates.create','candidates.edit',
    'interviews.view','interviews.manage','interviews.evaluate',
    'applications.view','applications.manage','applications.stage_change',
    'offers.view','offers.create','offers.send','offers.manage',
    'talent_pool.view','talent_pool.manage',
    'pipeline.view','pipeline.manage',
    'human_interviews.schedule',
    'reports.view','ai.use','ai.analytics',
    'users.view','roles.view',
    'settings.view','avatars.manage','career_page.view'
  );

-- recruiter
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'recruiter' AND r.tenant_id IS NULL
  AND p.slug IN (
    'jobs.view','candidates.view','candidates.create','candidates.edit',
    'interviews.view','interviews.manage','interviews.evaluate',
    'applications.view','applications.manage','applications.stage_change',
    'pipeline.view','pipeline.manage',
    'human_interviews.schedule',
    'talent_pool.view','offers.view',
    'reports.view','ai.use'
  );

-- technical_interviewer
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'technical_interviewer' AND r.tenant_id IS NULL
  AND p.slug IN (
    'candidates.view','interviews.view','interviews.evaluate',
    'applications.view','pipeline.view','ai.use'
  );

-- department_manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'department_manager' AND r.tenant_id IS NULL
  AND p.slug IN (
    'candidates.view','interviews.view','interviews.evaluate',
    'applications.view','pipeline.view','reports.view'
  );

-- operations_manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'operations_manager' AND r.tenant_id IS NULL
  AND p.slug IN (
    'candidates.view','pipeline.view','reports.view','applications.view',
    'offers.view','talent_pool.view','ai.analytics'
  );

-- executive_reviewer / viewer: read-only
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug IN ('executive_reviewer','viewer') AND r.tenant_id IS NULL
  AND p.slug IN ('candidates.view','applications.view','pipeline.view','reports.view','offers.view');
