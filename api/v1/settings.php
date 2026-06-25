<?php
Auth::requireHR();
$db  = Database::getInstance();
$tid = Auth::tenantId();

// GET /api/v1/settings
if ($method === 'GET' && !$id) {
    $rows     = $db->fetchAll("SELECT `key`, value FROM system_settings WHERE tenant_id=?", [$tid]);
    $settings = [];
    foreach ($rows as $r) { $settings[$r['key']] = $r['value']; }
    Response::success($settings);
}

// POST /api/v1/settings
if ($method === 'POST' && !$id) {
    Auth::requirePermission('settings.manage');
    $data = $req->all();

    // Test OpenAI key if provided
    if (!empty($data['openai_api_key'])) {
        $testAi = new OpenAIService($data['openai_api_key']);
        if (!$testAi->hasKey()) Response::error('Invalid OpenAI API key');
    }

    foreach ($data as $key => $value) {
        if (str_starts_with($key, '_')) continue; // skip internal fields
        $existing = $db->fetchColumn(
            "SELECT id FROM system_settings WHERE tenant_id=? AND `key`=?",
            [$tid, $key]
        );
        if ($existing) {
            $db->query("UPDATE system_settings SET value=? WHERE tenant_id=? AND `key`=?", [$value, $tid, $key]);
        } else {
            $db->insert('system_settings', ['tenant_id' => $tid, 'key' => $key, 'value' => $value]);
        }
    }

    Response::success(null, 'Settings saved');
}

Response::notFound();
