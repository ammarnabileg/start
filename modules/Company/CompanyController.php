<?php
declare(strict_types=1);

class CompanyController
{
    /**
     * GET  /settings — show company settings form.
     * POST /settings — save tenant settings.
     */
    public static function settings(Request $r): void
    {
        Auth::requireAuth();
        Auth::requirePermission('settings.manage');

        $db       = Database::getInstance();
        $tenantId = Auth::tenantId();

        if (!$tenantId) {
            Response::error('No tenant context.', 403);
        }

        $tenant = $db->fetch("SELECT * FROM tenants WHERE id = ?", [$tenantId]);

        if ($r->isPost()) {
            $data = $r->only([
                'name',
                'domain',
                'logo_url',
            ]);

            $v = Validator::make($data, [
                'name'       => 'required|min:2|max:255',
                'domain'     => 'nullable|url|max:255',
                'logo_url'   => 'nullable|url|max:2048',
            ]);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                $_SESSION['old']    = $data;
                Response::redirect('/settings');
            }

            $now = date('Y-m-d H:i:s');

            $db->update('tenants', [
                'name'       => trim((string)$data['name']),
                'domain'     => trim((string)($data['domain'] ?? '')) ?: null,
                'logo_url'   => trim((string)($data['logo_url'] ?? '')) ?: null,
                'updated_at' => $now,
            ], ['id' => $tenantId]);

            // Handle extra key/value settings
            $extraKeys = array_diff(array_keys($r->all()), ['name', 'domain', 'logo_url', '_method', '_token']);
            foreach ($extraKeys as $key) {
                $value = $r->input($key);
                if (is_string($key) && $key !== '') {
                    $existing = $db->fetch(
                        "SELECT id FROM tenant_settings WHERE tenant_id = ? AND `key` = ?",
                        [$tenantId, $key]
                    );
                    if ($existing) {
                        $db->update('tenant_settings',
                            ['value' => $value, 'updated_at' => $now],
                            ['tenant_id' => $tenantId, 'key' => $key]
                        );
                    } else {
                        $db->insert('tenant_settings', [
                            'tenant_id'  => $tenantId,
                            'key'        => $key,
                            'value'      => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            Auth::refreshUser();

            if ($r->isAjax()) {
                Response::json(['success' => true, 'message' => 'Settings saved.']);
            }

            $_SESSION['flash_success'] = 'Company settings saved.';
            Response::redirect('/settings');
        }

        // GET — load extra settings into a flat map
        $rawSettings = $db->fetchAll(
            "SELECT `key`, `value` FROM tenant_settings WHERE tenant_id = ?",
            [$tenantId]
        );
        $settingsMap = [];
        foreach ($rawSettings as $row) {
            $settingsMap[$row['key']] = $row['value'];
        }

        renderView('hr/settings/company', [
            'pageTitle'   => 'Company Settings',
            'tenant'      => $tenant,
            'settings'    => $settingsMap,
            'errors'      => $_SESSION['errors'] ?? [],
            'old'         => $_SESSION['old'] ?? [],
        ], 'app');

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * GET  /settings/ai — show AI settings form.
     * POST /settings/ai — validate and save OpenAI / HeyGen keys.
     */
    public static function aiSettings(Request $r): void
    {
        Auth::requireAuth();
        Auth::requirePermission('settings.manage');

        $db       = Database::getInstance();
        $tenantId = Auth::tenantId();

        if (!$tenantId) {
            Response::error('No tenant context.', 403);
        }

        $aiSettings = $db->fetch(
            "SELECT * FROM tenant_ai_settings WHERE tenant_id = ?",
            [$tenantId]
        ) ?: [];

        if ($r->isPost()) {
            $data = $r->only([
                'openai_api_key',
                'heygen_api_key',
                'openai_model',
                'enable_video_interviews',
                'enable_voice_interviews',
                'enable_text_interviews',
            ]);

            $v = Validator::make($data, [
                'openai_api_key'          => 'nullable|max:255',
                'heygen_api_key'          => 'nullable|max:255',
                'openai_model'            => 'nullable|max:50',
                'enable_video_interviews' => 'nullable|boolean',
                'enable_voice_interviews' => 'nullable|boolean',
                'enable_text_interviews'  => 'nullable|boolean',
            ]);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                Response::redirect('/settings/ai');
            }

            $openaiKey  = trim((string)($data['openai_api_key'] ?? ''));
            $heygenKey  = trim((string)($data['heygen_api_key'] ?? ''));
            $model      = trim((string)($data['openai_model'] ?? 'gpt-4o')) ?: 'gpt-4o';
            $now        = date('Y-m-d H:i:s');

            $keyErrors        = [];
            $openaiConnected  = $aiSettings['openai_connected_at'] ?? null;
            $heygenConnected  = $aiSettings['heygen_connected_at'] ?? null;

            // Validate OpenAI key if provided/changed
            if ($openaiKey && $openaiKey !== ($aiSettings['openai_api_key'] ?? '')) {
                if (!ApiKeyManager::validateOpenAI($openaiKey)) {
                    $keyErrors['openai_api_key'] = ['The OpenAI API key is invalid or unreachable.'];
                } else {
                    $openaiConnected = $now;
                }
            } elseif (!$openaiKey) {
                $openaiConnected = null;
            }

            // Validate HeyGen key if provided/changed
            if ($heygenKey && $heygenKey !== ($aiSettings['heygen_api_key'] ?? '')) {
                if (!ApiKeyManager::validateHeyGen($heygenKey)) {
                    $keyErrors['heygen_api_key'] = ['The HeyGen API key is invalid or unreachable.'];
                } else {
                    $heygenConnected = $now;
                }
            } elseif (!$heygenKey) {
                $heygenConnected = null;
            }

            if ($keyErrors) {
                if ($r->isAjax()) {
                    Response::error('One or more API keys are invalid.', 422, $keyErrors);
                }
                $_SESSION['errors'] = $keyErrors;
                Response::redirect('/settings/ai');
            }

            $saveData = [
                'openai_api_key'          => $openaiKey ?: null,
                'heygen_api_key'          => $heygenKey ?: null,
                'openai_model'            => $model,
                'enable_video_interviews' => (int)!empty($data['enable_video_interviews']),
                'enable_voice_interviews' => (int)!empty($data['enable_voice_interviews']),
                'enable_text_interviews'  => (int)!empty($data['enable_text_interviews']),
                'openai_connected_at'     => $openaiConnected,
                'heygen_connected_at'     => $heygenConnected,
                'updated_at'              => $now,
            ];

            if ($aiSettings) {
                $db->update('tenant_ai_settings', $saveData, ['tenant_id' => $tenantId]);
            } else {
                $db->insert('tenant_ai_settings', array_merge($saveData, [
                    'tenant_id'  => $tenantId,
                    'created_at' => $now,
                ]));
            }

            if ($r->isAjax()) {
                Response::json(['success' => true, 'message' => 'AI settings saved.']);
            }

            $_SESSION['flash_success'] = 'AI settings saved successfully.';
            Response::redirect('/settings/ai');
        }

        // GET
        renderView('hr/settings/ai', [
            'pageTitle'  => 'AI Settings',
            'aiSettings' => $aiSettings,
            'errors'     => $_SESSION['errors'] ?? [],
        ], 'app');

        unset($_SESSION['errors']);
    }

    /**
     * GET  /settings/career-page — manage career page settings.
     * POST /settings/career-page — save career page settings.
     */
    public static function careerPage(Request $r): void
    {
        Auth::requireAuth();
        Auth::requirePermission('settings.manage');

        $db       = Database::getInstance();
        $tenantId = Auth::tenantId();

        if (!$tenantId) {
            Response::error('No tenant context.', 403);
        }

        $careerPage = $db->fetch(
            "SELECT * FROM career_page_settings WHERE tenant_id = ?",
            [$tenantId]
        ) ?: [];

        if ($r->isPost()) {
            $data = $r->only([
                'title',
                'headline',
                'description',
                'logo_url',
                'banner_url',
                'primary_color',
                'secondary_color',
                'custom_domain',
                'is_public',
            ]);

            $v = Validator::make($data, [
                'title'           => 'nullable|max:255',
                'headline'        => 'nullable|max:500',
                'description'     => 'nullable',
                'logo_url'        => 'nullable|url|max:2048',
                'banner_url'      => 'nullable|url|max:2048',
                'primary_color'   => 'nullable|max:20',
                'secondary_color' => 'nullable|max:20',
                'custom_domain'   => 'nullable|max:255',
                'is_public'       => 'nullable|boolean',
            ]);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                $_SESSION['old']    = $data;
                Response::redirect('/settings/career-page');
            }

            $now      = date('Y-m-d H:i:s');
            $saveData = [
                'title'           => trim((string)($data['title'] ?? '')) ?: null,
                'headline'        => trim((string)($data['headline'] ?? '')) ?: null,
                'description'     => trim((string)($data['description'] ?? '')) ?: null,
                'logo_url'        => trim((string)($data['logo_url'] ?? '')) ?: null,
                'banner_url'      => trim((string)($data['banner_url'] ?? '')) ?: null,
                'primary_color'   => trim((string)($data['primary_color'] ?? '#4f46e5')) ?: '#4f46e5',
                'secondary_color' => trim((string)($data['secondary_color'] ?? '#7c3aed')) ?: '#7c3aed',
                'custom_domain'   => trim((string)($data['custom_domain'] ?? '')) ?: null,
                'is_public'       => isset($data['is_public']) ? (int)(bool)$data['is_public'] : 1,
                'updated_at'      => $now,
            ];

            if ($careerPage) {
                $db->update('career_page_settings', $saveData, ['tenant_id' => $tenantId]);
            } else {
                $db->insert('career_page_settings', array_merge($saveData, [
                    'tenant_id'  => $tenantId,
                    'created_at' => $now,
                ]));
            }

            if ($r->isAjax()) {
                Response::json(['success' => true, 'message' => 'Career page settings saved.']);
            }

            $_SESSION['flash_success'] = 'Career page settings saved.';
            Response::redirect('/settings/career-page');
        }

        $tenant = $db->fetch("SELECT slug FROM tenants WHERE id = ?", [$tenantId]);

        renderView('hr/settings/career-page', [
            'pageTitle'      => 'Career Page Settings',
            'careerPage'     => $careerPage,
            'tenantSlug'     => $tenant['slug'] ?? '',
            'errors'         => $_SESSION['errors'] ?? [],
            'old'            => $_SESSION['old'] ?? [],
        ], 'app');

        unset($_SESSION['errors'], $_SESSION['old']);
    }
}
