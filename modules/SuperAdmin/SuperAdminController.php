<?php
declare(strict_types=1);

class SuperAdminController
{
    public static function dashboard(Request $r): void
    {
        $db = Database::getInstance();
        $stats = [
            'total_tenants'      => $db->fetchColumn("SELECT COUNT(*) FROM tenants"),
            'active_tenants'     => $db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status = 'active'"),
            'total_users'        => $db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_super_admin = 0"),
            'total_jobs'         => $db->fetchColumn("SELECT COUNT(*) FROM jobs"),
            'total_applications' => $db->fetchColumn("SELECT COUNT(*) FROM applications"),
            'total_interviews'   => $db->fetchColumn("SELECT COUNT(*) FROM ai_interviews"),
            'total_hired'        => $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE status = 'hired'"),
        ];

        $recentTenants = $db->fetchAll(
            "SELECT t.*, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM users WHERE tenant_id = t.id) AS user_count,
                (SELECT COUNT(*) FROM jobs WHERE tenant_id = t.id) AS job_count,
                (SELECT COUNT(*) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.tenant_id = t.id) AS interview_count
             FROM tenants t LEFT JOIN users u ON u.id = t.owner_id
             ORDER BY t.created_at DESC LIMIT 10"
        );

        $tokenUsage = $db->fetchAll(
            "SELECT DATE(created_at) AS date, SUM(total_tokens) AS tokens, COUNT(*) AS requests
             FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at) ORDER BY date"
        );

        renderView('super-admin/dashboard', compact('stats','recentTenants','tokenUsage'), 'app');
    }

    public static function companies(Request $r): void
    {
        $db = Database::getInstance();
        $search = $r->get('q', '');
        $status = $r->get('status', 'all');

        $sql = "SELECT t.*, u.first_name, u.last_name, u.email,
                (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND is_super_admin = 0) AS user_count,
                (SELECT COUNT(*) FROM jobs WHERE tenant_id = t.id) AS job_count,
                (SELECT COUNT(*) FROM applications WHERE tenant_id = t.id) AS app_count,
                tas.openai_api_key IS NOT NULL AND tas.openai_api_key != '' AS has_openai
                FROM tenants t
                LEFT JOIN users u ON u.id = t.owner_id
                LEFT JOIN tenant_ai_settings tas ON tas.tenant_id = t.id
                WHERE 1=1";
        $params = [];
        if ($search) { $sql .= " AND (t.name LIKE ? OR t.slug LIKE ? OR u.email LIKE ?)"; $params = array_fill(0, 3, "%$search%"); }
        if ($status !== 'all') { $sql .= " AND t.status = ?"; $params[] = $status; }
        $sql .= " ORDER BY t.created_at DESC";

        $companies = $db->fetchAll($sql, $params);
        renderView('super-admin/companies', compact('companies','search','status'), 'app');
    }

    public static function showCompany(Request $r, int $id): void
    {
        $db = Database::getInstance();
        $company = $db->fetch("SELECT t.*, u.first_name, u.last_name, u.email FROM tenants t LEFT JOIN users u ON u.id = t.owner_id WHERE t.id = ?", [$id]);
        if (!$company) { http_response_code(404); renderView('errors/404',[],'app'); return; }

        $aiSettings = $db->fetch("SELECT * FROM tenant_ai_settings WHERE tenant_id = ?", [$id]) ?: [];
        $users      = $db->fetchAll("SELECT u.*, GROUP_CONCAT(r.name SEPARATOR ', ') AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.tenant_id = ? GROUP BY u.id ORDER BY u.created_at DESC", [$id]);
        $jobs       = $db->fetchAll("SELECT * FROM jobs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 20", [$id]);
        $usageStats = $db->fetchAll("SELECT DATE(created_at) AS date, SUM(total_tokens) AS tokens, COUNT(*) AS requests FROM ai_usage_logs WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date", [$id]);
        $subscription = $db->fetch("SELECT * FROM tenant_subscriptions WHERE tenant_id = ?", [$id]) ?: [];

        renderView('super-admin/companies', compact('company','aiSettings','users','jobs','usageStats','subscription'), 'app');
    }

    public static function createCompany(Request $r): void
    {
        $db   = Database::getInstance();
        $data = $r->all();
        $v = Validator::make($data, [
            'name'       => 'required|max:255',
            'slug'       => 'required|max:100',
            'admin_email'=> 'required|email',
            'admin_fname'=> 'required',
            'admin_pass' => 'required|min:8',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $db->beginTransaction();
        try {
            // Check slug unique
            if ($db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE slug = ?", [$data['slug']])) {
                throw new RuntimeException('Company slug already taken.');
            }

            $tenantId = $db->insert('tenants', [
                'name'       => $data['name'],
                'slug'       => $data['slug'],
                'status'     => 'active',
                'plan'       => $data['plan'] ?? 'starter',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // Check email not taken
            if ($db->fetchColumn("SELECT COUNT(*) FROM users WHERE email = ?", [$data['admin_email']])) {
                throw new RuntimeException('Email already registered.');
            }

            $userId = $db->insert('users', [
                'tenant_id'    => $tenantId,
                'first_name'   => $data['admin_fname'],
                'last_name'    => $data['admin_lname'] ?? '',
                'email'        => strtolower($data['admin_email']),
                'password_hash'=> password_hash($data['admin_pass'], PASSWORD_BCRYPT),
                'status'       => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $db->update('tenants', ['owner_id' => $userId], ['id' => $tenantId]);

            $hrDirRole = $db->fetch("SELECT id FROM roles WHERE slug = 'hr_director'");
            if ($hrDirRole) {
                $db->insert('user_roles', ['user_id'=>$userId,'role_id'=>$hrDirRole['id'],'created_at'=>date('Y-m-d H:i:s')]);
            }

            $db->insert('tenant_ai_settings', ['tenant_id'=>$tenantId,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $db->insert('tenant_subscriptions', [
                'tenant_id'=>$tenantId,'plan'=>$data['plan']??'starter','status'=>'active',
                'max_jobs'=>5,'max_users'=>3,'max_ai_interviews_per_month'=>100,
                'current_period_start'=>date('Y-m-d'),'current_period_end'=>date('Y-m-d',strtotime('+30 days')),
                'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
            ]);
            $db->insert('career_page_settings', ['tenant_id'=>$tenantId,'title'=>$data['name'].' Careers','is_public'=>1,'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
            $db->insert('avatars', ['tenant_id'=>$tenantId,'name'=>'Alex','gender'=>'neutral','language'=>'both','style'=>'formal','personality_prompt'=>'Professional AI interviewer.','is_default'=>1,'status'=>'active','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);

            $db->commit();
            Audit::log('company.created', 'tenant', $tenantId, null, ['name'=>$data['name']]);
            Response::success(['tenant_id'=>$tenantId,'user_id'=>$userId], 'Company created successfully.');
        } catch (Throwable $e) {
            $db->rollback();
            Response::error($e->getMessage(), 422);
        }
    }

    public static function suspendCompany(Request $r, int $id): void
    {
        $db = Database::getInstance();
        $company = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$id]);
        if (!$company) { Response::error('Not found', 404); return; }
        $newStatus = $company['status'] === 'active' ? 'suspended' : 'active';
        $db->update('tenants', ['status'=>$newStatus,'updated_at'=>date('Y-m-d H:i:s')], ['id'=>$id]);
        Audit::log('company.status_changed', 'tenant', $id, ['status'=>$company['status']], ['status'=>$newStatus]);
        Response::success(['status'=>$newStatus], "Company {$newStatus}.");
    }

    public static function users(Request $r): void
    {
        $db = Database::getInstance();
        $search = $r->get('q', '');
        $sql = "SELECT u.*, t.name AS tenant_name, GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
                FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.is_super_admin = 1" . ($search ? " AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)" : "") . "
                GROUP BY u.id ORDER BY u.created_at DESC";
        $params = $search ? array_fill(0, 3, "%$search%") : [];
        $users = $db->fetchAll($sql, $params);
        renderView('super-admin/users', compact('users','search'), 'app');
    }

    public static function createUser(Request $r): void
    {
        $db = Database::getInstance();
        $data = $r->all();
        $v = Validator::make($data, ['first_name'=>'required','email'=>'required|email','password'=>'required|min:8']);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }
        if ($db->fetchColumn("SELECT COUNT(*) FROM users WHERE email = ?", [strtolower($data['email'])])) {
            Response::error('Email already registered.', 422); return;
        }
        $userId = $db->insert('users', [
            'first_name'=>$data['first_name'],'last_name'=>$data['last_name']??'',
            'email'=>strtolower($data['email']),'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),
            'is_super_admin'=>1,'status'=>'active','email_verified_at'=>date('Y-m-d H:i:s'),
            'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s'),
        ]);
        $roleId = $db->fetchColumn("SELECT id FROM roles WHERE slug = 'super_admin'");
        if ($roleId) { $db->insert('user_roles', ['user_id'=>$userId,'role_id'=>(int)$roleId,'created_at'=>date('Y-m-d H:i:s')]); }
        Response::success(['user_id'=>$userId], 'Super admin created.');
    }

    public static function analytics(Request $r): void
    {
        $db = Database::getInstance();
        $period = $r->get('period', '30');
        $data = [
            'by_day' => $db->fetchAll("SELECT DATE(created_at) AS date, COUNT(*) AS applications FROM applications WHERE created_at >= DATE_SUB(NOW(),INTERVAL ? DAY) GROUP BY DATE(created_at)", [(int)$period]),
            'by_status' => $db->fetchAll("SELECT status, COUNT(*) AS count FROM applications GROUP BY status"),
            'by_tenant' => $db->fetchAll("SELECT t.name, COUNT(a.id) AS apps FROM applications a JOIN tenants t ON t.id = a.tenant_id GROUP BY t.id ORDER BY apps DESC LIMIT 10"),
            'hire_rate' => $db->fetchAll("SELECT t.name, COUNT(CASE WHEN a.status = 'hired' THEN 1 END) AS hired, COUNT(*) AS total FROM applications a JOIN tenants t ON t.id = a.tenant_id GROUP BY t.id"),
        ];
        renderView('super-admin/dashboard', array_merge(['view'=>'analytics'], $data), 'app');
    }

    public static function aiAnalytics(Request $r): void
    {
        $db = Database::getInstance();
        $stats = [
            'total_tokens' => $db->fetchColumn("SELECT SUM(total_tokens) FROM ai_usage_logs") ?: 0,
            'total_calls'  => $db->fetchColumn("SELECT COUNT(*) FROM ai_usage_logs") ?: 0,
            'by_tenant'    => $db->fetchAll("SELECT t.name, SUM(l.total_tokens) AS tokens, COUNT(l.id) AS calls FROM ai_usage_logs l JOIN tenants t ON t.id = l.tenant_id GROUP BY t.id ORDER BY tokens DESC LIMIT 20"),
            'by_feature'   => $db->fetchAll("SELECT feature, SUM(total_tokens) AS tokens, COUNT(*) AS calls FROM ai_usage_logs GROUP BY feature ORDER BY tokens DESC"),
            'by_day'       => $db->fetchAll("SELECT DATE(created_at) AS date, SUM(total_tokens) AS tokens FROM ai_usage_logs WHERE created_at >= DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at)"),
            'connected_tenants'=> $db->fetchColumn("SELECT COUNT(*) FROM tenant_ai_settings WHERE openai_api_key IS NOT NULL AND openai_api_key != ''"),
        ];
        renderView('super-admin/ai-analytics', compact('stats'), 'app');
    }

    public static function platformSettings(Request $r): void
    {
        $db = Database::getInstance();
        if ($r->isPost()) {
            $data = $r->all();
            foreach ($data as $key => $value) {
                if (str_starts_with($key, '_') || $key === 'action') continue;
                $existing = $db->fetch("SELECT id FROM system_settings WHERE `key` = ?", [$key]);
                if ($existing) {
                    $db->update('system_settings', ['value'=>$value,'updated_at'=>date('Y-m-d H:i:s')], ['id'=>$existing['id']]);
                } else {
                    $db->insert('system_settings', ['key'=>$key,'value'=>$value,'group_name'=>'general','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
                }
            }
            if ($r->isAjax()) { Response::success(null,'Settings saved.'); return; }
            setFlash('success','Platform settings saved.');
            Response::redirect('/super/settings');
        } else {
            $settings = [];
            foreach ($db->fetchAll("SELECT * FROM system_settings ORDER BY group_name, `key`") as $s) {
                $settings[$s['key']] = $s['value'];
            }
            renderView('super-admin/settings', compact('settings'), 'app');
        }
    }
}
