<?php
Auth::requireSuper();
$db = Database::getInstance();

// GET /api/v1/super/stats
if ($method === 'GET' && $id === 'stats') {
    $recentSignups = $db->fetchAll(
        "SELECT id, name as company_name, slug, status, created_at,
                COALESCE((SELECT value FROM system_settings WHERE tenant_id=t.id AND `key`='plan' LIMIT 1),'basic') as plan
         FROM tenants t ORDER BY created_at DESC LIMIT 10"
    );
    $planRows = $db->fetchAll(
        "SELECT COALESCE((SELECT value FROM system_settings WHERE tenant_id=t.id AND `key`='plan' LIMIT 1),'basic') as plan,
                COUNT(*) as cnt FROM tenants t WHERE status='active' GROUP BY plan"
    );
    $planBreakdown = ['basic'=>0,'pro'=>0,'enterprise'=>0];
    foreach ($planRows as $r) { $planBreakdown[$r['plan']] = (int)$r['cnt']; }

    Response::success([
        'total_companies'      => (int)$db->fetchColumn("SELECT COUNT(*) FROM tenants"),
        'active_subscriptions' => (int)$db->fetchColumn("SELECT COUNT(*) FROM tenants WHERE status='active'"),
        'total_users'          => (int)$db->fetchColumn("SELECT COUNT(*) FROM users WHERE is_super_admin=0"),
        'total_ai_interviews'  => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_interviews"),
        'tokens_today'         => (int)$db->fetchColumn("SELECT COALESCE(SUM(total_tokens),0) FROM ai_usage_logs WHERE DATE(created_at)=CURDATE()"),
        'recent_signups'       => $recentSignups,
        'plan_breakdown'       => $planBreakdown,
        'system_health'        => [
            'db_status'          => 'ok',
            'storage_used'       => '—',
            'storage_available'  => '—',
            'queue_size'         => 0,
        ],
    ]);
}

// GET /api/v1/super/companies
if ($method === 'GET' && $id === 'companies' && !$sub) {
    $page   = max(1,(int)$req->get('page',1));
    $search = $req->get('search','');
    $where  = '1=1';
    $params = [];
    if ($search) { $where .= " AND (t.name LIKE ? OR t.slug LIKE ?)"; $params[] = "%{$search}%"; $params[] = "%{$search}%"; }

    $result = $db->paginate(
        "SELECT t.*, COUNT(DISTINCT u.id) as user_count, COUNT(DISTINCT j.id) as job_count
         FROM tenants t
         LEFT JOIN users u ON u.tenant_id=t.id AND u.is_super_admin=0
         LEFT JOIN jobs j ON j.tenant_id=t.id
         WHERE {$where} GROUP BY t.id ORDER BY t.created_at DESC",
        $params, $page, 20
    );
    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// POST /api/v1/super/companies
if ($method === 'POST' && $id === 'companies' && !$sub) {
    $name        = trim($req->input('name',''));
    $slug        = trim($req->input('slug',''));
    $ownerEmail  = trim($req->input('owner_email',''));
    $firstName   = trim($req->input('first_name',''));
    $lastName    = trim($req->input('last_name',''));

    if (!$name || !$slug || !$ownerEmail) Response::error('name, slug, and owner_email required');

    // Check slug uniqueness
    if ($db->fetchColumn("SELECT id FROM tenants WHERE slug=?", [$slug])) Response::error('Slug already taken');

    $tenantId = $db->insert('tenants', ['name'=>$name,'slug'=>$slug,'status'=>'active']);

    // Create owner user
    $tempPass = bin2hex(random_bytes(8));
    $userId   = $db->insert('users', [
        'tenant_id'     => $tenantId,
        'email'         => $ownerEmail,
        'first_name'    => $firstName ?: 'Owner',
        'last_name'     => $lastName ?: 'User',
        'password_hash' => password_hash($tempPass, PASSWORD_BCRYPT),
        'status'        => 'active',
        'is_super_admin'=> 0,
    ]);

    // Assign company_owner role (role id=2)
    $ownerRole = $db->fetch("SELECT id FROM roles WHERE slug='company_owner' LIMIT 1");
    if ($ownerRole) $db->insert('user_roles', ['user_id'=>$userId,'role_id'=>$ownerRole['id']]);

    Response::success(['tenant_id'=>$tenantId,'temp_password'=>$tempPass], 'Company created');
}

// PATCH /api/v1/super/companies/{id}/status
if ($method === 'PATCH' && $id === 'companies' && $sub && $sub2 === 'status') {
    $status = $req->input('status','');
    if (!in_array($status,['active','suspended'])) Response::error('Invalid status');
    $db->update('tenants', ['status'=>$status], ['id'=>(int)$sub]);
    Response::success(null, 'Status updated');
}

// GET /api/v1/super/users
if ($method === 'GET' && $id === 'users' && !$sub) {
    $page      = max(1,(int)$req->get('page',1));
    $q         = $req->get('q','');
    $companyId = $req->get('company_id','');
    $type      = $req->get('type','');
    $status    = $req->get('status','');
    $where     = "u.is_super_admin=0";
    $params    = [];
    if ($q)         { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)"; $params[] = "%{$q}%"; $params[] = "%{$q}%"; $params[] = "%{$q}%"; }
    if ($companyId) { $where .= " AND u.tenant_id=?"; $params[] = (int)$companyId; }
    if ($status)    { $where .= " AND u.status=?"; $params[] = $status; }
    if ($type === 'super_admin') { $where .= " AND u.is_super_admin=1"; }
    elseif ($type === 'hr')      { $where .= " AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug IN('company_owner','hr_manager','hr_staff'))"; }
    elseif ($type === 'candidate') { $where .= " AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug='candidate')"; }

    $result = $db->paginate(
        "SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.last_login_at,
                t.name as company_name,
                IF(u.is_super_admin=1,'super_admin',
                    IF(EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug='candidate'),'candidate','hr')
                ) as type
         FROM users u LEFT JOIN tenants t ON t.id=u.tenant_id
         WHERE {$where} ORDER BY u.created_at DESC",
        $params, $page, 20
    );
    Response::paginated($result['data'], $result['total'], $result['page'], $result['per_page']);
}

// PATCH /api/v1/super/users/{id}/status  OR  POST /api/v1/super/users/{id}/suspend|activate
if ($id === 'users' && $sub && $sub2) {
    $newStatus = null;
    if ($method === 'PATCH' && $sub2 === 'status') {
        $newStatus = $req->input('status','');
        if (!in_array($newStatus,['active','suspended'])) Response::error('Invalid status');
    } elseif ($method === 'POST' && $sub2 === 'suspend')   { $newStatus = 'suspended'; }
    elseif  ($method === 'POST' && $sub2 === 'activate')  { $newStatus = 'active'; }
    if ($newStatus !== null) {
        $db->update('users', ['status'=>$newStatus], ['id'=>(int)$sub]);
        Response::success(null, 'User status updated');
    }
}

// GET /api/v1/super/ai-usage
if ($method === 'GET' && $id === 'ai-usage') {
    $from = $req->get('from', date('Y-m-d', strtotime('-30 days')));
    $to   = $req->get('to',   date('Y-m-d'));
    $byCompany = $db->fetchAll(
        "SELECT t.name as company_name, l.tenant_id,
                COALESCE((SELECT value FROM system_settings WHERE tenant_id=t.id AND `key`='plan' LIMIT 1),'basic') as plan,
                SUM(l.total_tokens) as tokens,
                SUM(l.total_tokens) * 0.000002 as cost,
                COUNT(DISTINCT l.job_id) as interviews
         FROM ai_usage_logs l JOIN tenants t ON t.id=l.tenant_id
         WHERE DATE(l.created_at) BETWEEN ? AND ?
         GROUP BY l.tenant_id, t.name ORDER BY tokens DESC",
        [$from, $to]
    );
    $totals = $db->fetch(
        "SELECT COALESCE(SUM(total_tokens),0) as tokens,
                COALESCE(SUM(total_tokens),0) * 0.000002 as cost,
                COUNT(DISTINCT job_id) as interviews
         FROM ai_usage_logs WHERE DATE(created_at) BETWEEN ? AND ?",
        [$from, $to]
    );
    Response::success(['totals' => $totals, 'by_company' => $byCompany]);
}

// GET /api/v1/super/settings
if ($method === 'GET' && $id === 'settings') {
    $rows = $db->fetchAll("SELECT `key`, value FROM system_settings WHERE tenant_id IS NULL");
    $s = [];
    foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
    Response::success($s);
}

// POST /api/v1/super/settings
if ($method === 'POST' && $id === 'settings' && !$sub) {
    $data = $req->all();
    foreach ($data as $key => $value) {
        if (str_starts_with($key,'_')) continue;
        $exists = $db->fetchColumn("SELECT id FROM system_settings WHERE tenant_id IS NULL AND `key`=?", [$key]);
        if ($exists) {
            $db->query("UPDATE system_settings SET value=? WHERE tenant_id IS NULL AND `key`=?", [$value, $key]);
        } else {
            $db->insert('system_settings', ['tenant_id'=>null,'key'=>$key,'value'=>$value]);
        }
    }
    Response::success(null, 'Settings saved');
}

Response::notFound();
