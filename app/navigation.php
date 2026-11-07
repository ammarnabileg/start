<?php
/**
 * تنظيم القائمة: كل عنصر في القائمة الجانبية ممكن يكون "مجموعة" صفحاتها بتظهر كتابات (tabs) فوق.
 * الأقسام الاختيارية (module) صاحب الشركة يقدر يقفلها من الإعدادات.
 */

function module_catalog(): array
{
    return [
        'tenders' => t('آخر المنافسات'),
        'crm' => t('إدارة العملاء'),
        'competitors' => t('المنافسين'),
        'studio' => t('استوديو المحتوى'),
        'publishing' => t('النشر والمنشورات'),
        'inbox' => t('الرسائل والتعليقات'),
        'automations' => t('الردود التلقائية والـ Workflows'),
        'whatsapp' => t('واتساب للبيزنس'),
        'analytics' => t('التحليلات'),
        'ads' => t('الإعلانات'),
        'gmb' => 'Google Business',
        'phone' => t('التليفون وSMS'),
        'knowledge' => t('قاعدة المعرفة'),
        'media' => t('مكتبة الوسائط'),
    ];
}

function disabled_modules(): array
{
    $d = json_decode((string)ws('disabled_modules'), true);
    return is_array($d) ? $d : [];
}

function module_enabled(?string $m): bool
{
    return $m === null || !in_array($m, disabled_modules(), true);
}

/** عناصر القائمة بالترتيب. tabs = صفحات المجموعة. owner = لصاحب الشركة بس */
function nav_items(): array
{
    $owner = is_ws_owner();
    // القايمة مقسمة على رحلة المنصة: السوق ← المحتوى ← النشر وجمع العملاء ← المبيعات ← النتائج، والإدارة في الآخر
    $s1 = t('شركتك'); $s2 = t('السوق والفرص'); $sC = t('المحتوى'); $s3 = t('النشر والوصول'); $s4 = t('النشر وجمع العملاء'); $s5 = t('المبيعات'); $s6 = t('النتائج');
    $items = [
        ['key' => 'dashboard', 'label' => t('الرئيسية'), 'section' => ''],
        ['key' => 'tenders', 'module' => 'tenders', 'label' => t('آخر المنافسات'), 'section' => $s2],
        ['key' => 'crm', 'label' => t('الشركات المستهدفة'), 'section' => $s2, 'module' => 'crm'],
        ['key' => 'prospects', 'label' => t('العملاء'), 'section' => $s2, 'module' => 'crm', 'also' => ['crm_doc', 'crm_leads']],
        ['key' => 'crm_capture', 'label' => t('المصادر والإعدادات'), 'section' => $s2, 'module' => 'crm', 'also' => ['leads', 'crm_settings']],
        ['key' => 'competitors', 'module' => 'competitors', 'label' => t('المنافسين'), 'section' => $s2],
        ['key' => 'studio', 'module' => 'studio', 'label' => t('استوديو المحتوى'), 'section' => $sC,
            'tabs' => ['studio' => t('الكتابة بالذكاء الاصطناعي'), 'studio_media' => t('الصور والفيديو'), 'designer' => t('المصمم')]],
        ['key' => 'compose', 'module' => 'publishing', 'label' => t('منشور جديد'), 'section' => $s3],
        ['key' => 'posts', 'module' => 'publishing', 'label' => t('المنشورات'), 'section' => $s3,
            'tabs' => ['posts' => t('كل المنشورات'), 'calendar' => t('التقويم'), 'approvals' => t('الموافقات'), 'post_log' => t('سجل النشر')], 'also' => ['post_edit', 'queue', 'bulk']],
        ['key' => 'ads', 'label' => t('الإعلانات'), 'section' => $s3, 'module' => 'ads',
            'tabs' => ['ads' => t('الحملات'), 'ad_builder' => t('منشئ الحملات'), 'ad_create' => t('حملة سريعة'), 'ad_audiences' => t('الجماهير')], 'also' => ['ad_campaign']],
        ['key' => 'inbox', 'module' => 'inbox', 'label' => t('الرسائل والتعليقات'), 'section' => $s4, 'tabs' => ['inbox' => t('الرسائل'), 'comments' => t('التعليقات')]],
        ['key' => 'automations', 'label' => t('الردود التلقائية'), 'section' => $s4, 'module' => 'automations',
            'tabs' => ['automations' => t('الرد على التعليقات'), 'workflows' => 'Workflows', 'crm_plans' => t('خطط متابعة العملاء')]],
        ['key' => 'gmb', 'label' => 'Google Business', 'section' => $s4, 'module' => 'gmb',
            'tabs' => ['gmb_reviews' => t('التقييمات'), 'gmb_location' => t('بيانات الفرع'), 'gmb_media' => t('الصور')]],
        ['key' => 'whatsapp', 'label' => t('واتساب'), 'section' => $s4, 'module' => 'whatsapp',
            'tabs' => ['broadcasts' => t('رسائل جماعية'), 'contacts' => t('جهات الاتصال'), 'wa_templates' => t('القوالب'), 'sequences' => t('رسائل متتابعة'), 'wa_profile' => t('البروفايل')]],
        ['key' => 'phone', 'label' => t('التليفون'), 'section' => $s4, 'module' => 'phone',
            'tabs' => ['phone_numbers' => t('الأرقام'), 'calls' => t('المكالمات'), 'sms' => 'SMS', 'sms_mine' => t('رقمي')]],
        ['key' => 'crm_reports', 'label' => t('تقارير المبيعات'), 'section' => $s5, 'module' => 'crm'],
        ['key' => 'analytics', 'label' => t('التحليلات'), 'section' => $s6, 'module' => 'analytics',
            'tabs' => ['analytics' => t('المنشورات'), 'inbox_analytics' => t('الرسائل'), 'reports' => t('التقارير'), 'goals' => t('الأهداف'), 'alerts' => t('التنبيهات')], 'also' => ['report_print']],
        ['key' => 'knowledge', 'module' => 'knowledge', 'label' => t('قاعدة المعرفة'), 'section' => t('الإدارة')],
        ['key' => 'media', 'module' => 'media', 'label' => t('مكتبة الوسائط'), 'section' => t('الإدارة')],
        ['key' => 'accounts', 'label' => t('الحسابات المربوطة'), 'section' => t('الإدارة')],
        ['key' => 'connectors', 'label' => t('التكاملات'), 'section' => t('الإدارة'), 'owner' => true],
        ['key' => 'assistant', 'label' => t('الذكاء الاصطناعي'), 'section' => t('الإدارة')],
        ['key' => 'settings', 'label' => t('إعدادات الشركة'), 'section' => t('الإدارة'),
            'tabs' => ['settings' => t('عام'), 'team' => t('الفريق')], 'owner' => true],
    ];
    if (is_admin()) {
        $items[] = ['key' => 'admin', 'label' => t('إدارة المنصة'), 'section' => t('النظام'),
            'tabs' => ['admin' => t('نظرة عامة'), 'admin_workspaces' => t('الشركات'), 'admin_users' => t('المستخدمين'), 'admin_activity' => t('سجل النشاط'), 'admin_compare' => t('مقارنة الشركات'), 'system' => t('إعدادات المنصة')]];
    }
    $out = [];
    foreach ($items as $i) {
        if (!module_enabled($i['module'] ?? null)) continue;
        if (!empty($i['apollo']) && (!function_exists('crm_has_key') || !crm_has_key())) continue; // مزايا Apollo بتظهر بس لو مربوط
        if ($i['key'] === 'admin') { $out[] = $i; continue; }
        // التابات اللي مالوش صلاحية عليها بتختفي، ولو مفضلش حاجة العنصر كله بيختفي
        if (isset($i['tabs'])) {
            $i['tabs'] = array_filter($i['tabs'], function ($tk) { $p = page_permission($tk); return ($p === null || can($p)) && module_enabled(page_module($tk)); }, ARRAY_FILTER_USE_KEY);
            if (!$i['tabs']) continue;
        } else {
            $p = page_permission($i['key']);
            if ($p !== null && !can($p)) continue;
        }
        $out[] = $i;
    }
    return $out;
}

/** العنصر اللي الصفحة دي تابعة له */
function nav_item_for(string $page): ?array
{
    foreach (nav_items() as $i) {
        if ($i['key'] === $page || isset($i['tabs'][$page]) || in_array($page, $i['also'] ?? [], true)) {
            return $i;
        }
    }
    return null;
}

/** الصفحة دي تبع قسم مقفول؟ */
function module_pages(): array
{
    return [
        'crm' => ['crm', 'crm_find', 'prospects', 'crm_leads', 'crm_reports', 'crm_capture', 'crm_settings', 'crm_doc', 'crm_plans'],
        'automations' => ['automations', 'workflows'],
        'whatsapp' => ['broadcasts', 'wa_templates', 'contacts', 'sequences', 'wa_profile'],
        'analytics' => ['analytics', 'inbox_analytics', 'reports', 'report_print', 'goals', 'alerts'],
        'ads' => ['ads', 'ad_builder', 'ad_create', 'ad_audiences', 'leads', 'ad_campaign'],
        'gmb' => ['gmb_reviews', 'gmb_location', 'gmb_media'],
        'phone' => ['phone_numbers', 'sms', 'calls', 'phone_kyc', 'sms_mine'],
        'tenders' => ['tenders'],
        'competitors' => ['competitors'],
        'studio' => ['studio', 'studio_media', 'designer'],
        'publishing' => ['compose', 'posts', 'calendar', 'approvals', 'post_log', 'post_edit', 'queue', 'bulk'],
        'inbox' => ['inbox', 'comments'],
        'knowledge' => ['knowledge'],
        'media' => ['media'],
    ];
}

function page_module(string $page): ?string
{
    foreach (module_pages() as $m => $pages) {
        if (in_array($page, $pages, true)) return $m;
    }
    return null;
}

/** رابط عنصر القائمة: أول تاب مسموح */
function nav_href(array $i): string
{
    return url(isset($i['tabs']) ? array_key_first($i['tabs']) : $i['key']);
}

/** صفحات الأقسام المقفولة للشركة الحالية (عشان نخفي الروابط اللي بتوديلها) */
function disabled_pages(): array
{
    $out = []; $mp = module_pages();
    foreach (disabled_modules() as $m) $out = array_merge($out, $mp[$m] ?? []);
    return array_values(array_unique($out));
}
