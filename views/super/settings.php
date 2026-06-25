<meta name="csrf" content="<?= $req->csrf() ?>">

<div class="space-y-8 max-w-3xl">

    <!-- Header -->
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Platform Settings</h2>
        <p class="text-gray-500 mt-1">Configure global platform settings and defaults.</p>
    </div>

    <!-- Platform Info -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-5">Platform Identity</h3>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Platform Name</label>
                <input type="text" id="platform-name"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="RecruitAI">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Logo URL</label>
                <input type="url" id="platform-logo"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="https://example.com/logo.png">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Support Email</label>
                <input type="email" id="platform-support-email"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="support@recruitai.com">
            </div>
        </div>
        <div class="mt-5 flex items-center gap-3">
            <button onclick="saveSection('platform')"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Save Platform Info
            </button>
            <span id="platform-status" class="text-sm hidden"></span>
        </div>
    </div>

    <!-- Plan Limits -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Default Plan Limits</h3>
        <p class="text-sm text-gray-500 mb-5">Limits applied to new companies by default.</p>

        <div class="space-y-6">
            <?php foreach (['basic', 'pro', 'enterprise'] as $plan): ?>
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-2 h-2 rounded-full <?= ['basic'=>'bg-gray-400','pro'=>'bg-indigo-500','enterprise'=>'bg-purple-500'][$plan] ?>"></div>
                    <h4 class="text-sm font-semibold text-gray-800 capitalize"><?= ucfirst($plan) ?> Plan</h4>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php foreach ([
                        ['max_jobs',              'Max Jobs'],
                        ['max_users',             'Max Users'],
                        ['ai_interviews_per_month','AI Interviews/mo'],
                        ['token_limit',           'Token Limit'],
                    ] as [$key, $label]): ?>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1"><?= $label ?></label>
                        <input type="number" id="plan-<?= $plan ?>-<?= str_replace('_','-',$key) ?>"
                            min="0"
                            class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 flex items-center gap-3">
            <button onclick="saveSection('plans')"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Save Plan Limits
            </button>
            <span id="plans-status" class="text-sm hidden"></span>
        </div>
    </div>

    <!-- SMTP Settings -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">SMTP / Email Settings</h3>
        <p class="text-sm text-gray-500 mb-5">Configure outbound email delivery.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">From Name</label>
                <input type="text" id="smtp-from-name"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="RecruitAI">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">From Email</label>
                <input type="email" id="smtp-from-email"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="noreply@recruitai.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Host</label>
                <input type="text" id="smtp-host"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="smtp.mailgun.org">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">SMTP Port</label>
                <input type="number" id="smtp-port"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="587">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                <input type="text" id="smtp-user"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <input type="password" id="smtp-pass"
                    class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    placeholder="••••••••">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Encryption</label>
                <select id="smtp-encryption" class="w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="tls">TLS (Recommended)</option>
                    <option value="ssl">SSL</option>
                    <option value="none">None</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex items-center gap-3">
            <button onclick="saveSection('smtp')"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Save SMTP Settings
            </button>
            <button onclick="testSmtp()"
                class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-xl transition-colors">
                Send Test Email
            </button>
            <span id="smtp-status" class="text-sm hidden"></span>
        </div>
    </div>

    <!-- Feature Flags -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Feature Flags</h3>
        <p class="text-sm text-gray-500 mb-5">Enable or disable features per subscription plan.</p>

        <?php
        $features = [
            ['key'=>'enable_ai_interviews',     'label'=>'AI Interviews',          'desc'=>'Allow AI-powered candidate screening'],
            ['key'=>'enable_video_interviews',   'label'=>'Video Interviews',        'desc'=>'Human video interview scheduling'],
            ['key'=>'enable_offers',             'label'=>'Offer Management',        'desc'=>'Create and manage job offers'],
            ['key'=>'enable_talent_pool',        'label'=>'Talent Pool',             'desc'=>'Save candidates for future roles'],
            ['key'=>'enable_ai_analytics',       'label'=>'AI Analytics',            'desc'=>'Interview scoring and insights'],
            ['key'=>'enable_custom_avatars',     'label'=>'Custom Avatars',          'desc'=>'Custom AI interview avatars'],
            ['key'=>'enable_bulk_import',        'label'=>'Bulk Import',             'desc'=>'Import candidates via CSV'],
            ['key'=>'enable_api_access',         'label'=>'API Access',              'desc'=>'REST API access for integrations'],
        ];
        ?>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-100">
                        <th class="pb-3 text-left font-medium">Feature</th>
                        <th class="pb-3 text-center font-medium px-4">Basic</th>
                        <th class="pb-3 text-center font-medium px-4">Pro</th>
                        <th class="pb-3 text-center font-medium px-4">Enterprise</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($features as $f): ?>
                    <tr>
                        <td class="py-3.5 pr-4">
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($f['label']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($f['desc']) ?></p>
                        </td>
                        <?php foreach (['basic','pro','enterprise'] as $plan): ?>
                        <td class="py-3.5 px-4 text-center">
                            <label class="inline-flex items-center justify-center cursor-pointer">
                                <input type="checkbox"
                                    id="flag-<?= $plan ?>-<?= str_replace('_','-',$f['key']) ?>"
                                    data-feature="<?= $f['key'] ?>"
                                    data-plan="<?= $plan ?>"
                                    class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-5 flex items-center gap-3">
            <button onclick="saveSection('features')"
                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-xl transition-colors">
                Save Feature Flags
            </button>
            <span id="features-status" class="text-sm hidden"></span>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name=csrf]').content;

function showStatus(section, msg, ok) {
    const el = document.getElementById(`${section}-status`);
    el.textContent = msg;
    el.className = `text-sm ${ok ? 'text-green-600' : 'text-red-500'}`;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

async function loadSettings() {
    try {
        const r = await fetch('/api/v1/super/settings', {headers:{'X-CSRF-Token':CSRF}});
        const j = await r.json();
        if (!j.ok) return;
        const s = j.data;

        // Platform
        if (s.platform_name)    document.getElementById('platform-name').value          = s.platform_name;
        if (s.logo_url)         document.getElementById('platform-logo').value          = s.logo_url;
        if (s.support_email)    document.getElementById('platform-support-email').value = s.support_email;

        // SMTP
        if (s.smtp) {
            ['from_name','from_email','host','port','user','encryption'].forEach(k => {
                const el = document.getElementById('smtp-'+k.replace('_','-'));
                if (el && s.smtp[k]) el.value = s.smtp[k];
            });
        }

        // Plan limits
        if (s.plan_limits) {
            ['basic','pro','enterprise'].forEach(plan => {
                const limits = s.plan_limits[plan] || {};
                ['max_jobs','max_users','ai_interviews_per_month','token_limit'].forEach(key => {
                    const id = `plan-${plan}-${key.replace(/_/g,'-')}`;
                    const el = document.getElementById(id);
                    if (el && limits[key] !== undefined) el.value = limits[key];
                });
            });
        }

        // Feature flags
        if (s.feature_flags) {
            ['basic','pro','enterprise'].forEach(plan => {
                const planFlags = s.feature_flags[plan] || {};
                document.querySelectorAll(`[data-plan="${plan}"]`).forEach(cb => {
                    const feat = cb.dataset.feature;
                    cb.checked = !!planFlags[feat];
                });
            });
        }
    } catch(e) { console.error('Settings load error:', e); }
}

async function saveSection(section) {
    let payload = {};

    if (section === 'platform') {
        payload = {
            platform_name:  document.getElementById('platform-name').value,
            logo_url:       document.getElementById('platform-logo').value,
            support_email:  document.getElementById('platform-support-email').value,
        };
    }

    if (section === 'smtp') {
        payload = {
            smtp: {
                from_name:  document.getElementById('smtp-from-name').value,
                from_email: document.getElementById('smtp-from-email').value,
                host:       document.getElementById('smtp-host').value,
                port:       document.getElementById('smtp-port').value,
                user:       document.getElementById('smtp-user').value,
                pass:       document.getElementById('smtp-pass').value,
                encryption: document.getElementById('smtp-encryption').value,
            },
        };
    }

    if (section === 'plans') {
        const plan_limits = {};
        ['basic','pro','enterprise'].forEach(plan => {
            plan_limits[plan] = {};
            ['max_jobs','max_users','ai_interviews_per_month','token_limit'].forEach(key => {
                const id = `plan-${plan}-${key.replace(/_/g,'-')}`;
                const el = document.getElementById(id);
                if (el) plan_limits[plan][key] = parseInt(el.value) || 0;
            });
        });
        payload = {plan_limits};
    }

    if (section === 'features') {
        const feature_flags = {};
        ['basic','pro','enterprise'].forEach(plan => {
            feature_flags[plan] = {};
            document.querySelectorAll(`[data-plan="${plan}"]`).forEach(cb => {
                feature_flags[plan][cb.dataset.feature] = cb.checked;
            });
        });
        payload = {feature_flags};
    }

    try {
        const r = await fetch('/api/v1/super/settings', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-Token':CSRF},
            body: JSON.stringify({section, ...payload}),
        });
        const j = await r.json();
        showStatus(section === 'plans' ? 'plans' : section === 'features' ? 'features' : section, j.ok ? 'Saved successfully!' : (j.message || 'Failed.'), j.ok);
    } catch(e) {
        showStatus(section, 'Network error.', false);
    }
}

async function testSmtp() {
    try {
        const r = await fetch('/api/v1/super/settings/test-smtp', {
            method: 'POST',
            headers: {'X-CSRF-Token':CSRF}
        });
        const j = await r.json();
        showStatus('smtp', j.ok ? 'Test email sent!' : (j.message || 'Failed.'), j.ok);
    } catch(e) {
        showStatus('smtp', 'Network error.', false);
    }
}

loadSettings();
</script>
