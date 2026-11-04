<?php
/**
 * تنظيم القائمة: كل عنصر في القائمة الجانبية ممكن يكون "مجموعة" صفحاتها بتظهر كتابات (tabs) فوق.
 * الأقسام الاختيارية (module) صاحب الشركة يقدر يقفلها من الإعدادات.
 */

function module_catalog(): array
{
    return [
        'automations' => t('الردود التلقائية والـ Workflows'),
        'whatsapp' => t('واتساب للبيزنس'),
        'analytics' => t('التحليلات'),
        'ads' => t('الإعلانات'),
        'gmb' => 'Google Business',
        'phone' => t('التليفون وSMS'),
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
    $items = [
        ['key' => 'dashboard', 'label' => t('الرئيسية'), 'section' => ''],
        ['key' => 'compose', 'label' => t('منشور جديد'), 'section' => t('المحتوى')],
        ['key' => 'posts', 'label' => t('المنشورات'), 'section' => t('المحتوى'),
            'tabs' => ['posts' => t('كل المنشورات'), 'calendar' => t('التقويم')], 'also' => ['post_edit', 'queue', 'bulk']],
        ['key' => 'inbox', 'label' => t('الرسائل'), 'section' => t('التواصل')],
        ['key' => 'comments', 'label' => t('التعليقات'), 'section' => t('التواصل')],
        ['key' => 'automations', 'label' => t('الردود التلقائية'), 'section' => t('التواصل'), 'module' => 'automations',
            'tabs' => ['automations' => t('الرد على التعليقات'), 'workflows' => 'Workflows']],
        ['key' => 'assistant', 'label' => t('الذكاء الاصطناعي'), 'section' => t('الأدوات')],
        ['key' => 'whatsapp', 'label' => t('واتساب'), 'section' => t('الأدوات'), 'module' => 'whatsapp',
            'tabs' => ['broadcasts' => t('رسائل جماعية'), 'wa_templates' => t('القوالب'), 'contacts' => t('جهات الاتصال'), 'sequences' => t('رسائل متتابعة'), 'wa_profile' => t('البروفايل')]],
        ['key' => 'analytics', 'label' => t('التحليلات'), 'section' => t('الأدوات'), 'module' => 'analytics',
            'tabs' => ['analytics' => t('المنشورات'), 'inbox_analytics' => t('الرسائل'), 'reports' => t('التقارير'), 'goals' => t('الأهداف'), 'alerts' => t('التنبيهات')], 'also' => ['report_print']],
        ['key' => 'ads', 'label' => t('الإعلانات'), 'section' => t('الأدوات'), 'module' => 'ads',
            'tabs' => ['ads' => t('الحملات'), 'ad_create' => t('حملة جديدة'), 'ad_audiences' => t('الجماهير'), 'leads' => 'Leads'], 'also' => ['ad_campaign']],
        ['key' => 'gmb', 'label' => 'Google Business', 'section' => t('الأدوات'), 'module' => 'gmb',
            'tabs' => ['gmb_reviews' => t('التقييمات'), 'gmb_location' => t('بيانات الفرع'), 'gmb_media' => t('الصور')]],
        ['key' => 'phone', 'label' => t('التليفون'), 'section' => t('الأدوات'), 'module' => 'phone',
            'tabs' => ['phone_numbers' => t('الأرقام'), 'sms' => 'SMS', 'calls' => t('المكالمات')]],
        ['key' => 'accounts', 'label' => t('الحسابات المربوطة'), 'section' => t('الشركة')],
        ['key' => 'connectors', 'label' => t('التكاملات'), 'section' => t('الشركة'), 'owner' => true],
        ['key' => 'settings', 'label' => t('الإعدادات'), 'section' => t('الشركة'),
            'tabs' => ['settings' => t('عام'), 'team' => t('الفريق')], 'owner' => true],
    ];
    if (is_admin()) {
        $items[] = ['key' => 'admin', 'label' => t('إدارة المنصة'), 'section' => t('النظام'),
            'tabs' => ['admin' => t('نظرة عامة'), 'admin_workspaces' => t('الشركات'), 'admin_users' => t('المستخدمين'), 'admin_activity' => t('سجل النشاط'), 'admin_compare' => t('مقارنة الشركات'), 'system' => t('إعدادات المنصة')]];
    }
    $out = [];
    foreach ($items as $i) {
        if (!module_enabled($i['module'] ?? null)) continue;
        if ($i['key'] === 'admin') { $out[] = $i; continue; }
        // التابات اللي مالوش صلاحية عليها بتختفي، ولو مفضلش حاجة العنصر كله بيختفي
        if (isset($i['tabs'])) {
            $i['tabs'] = array_filter($i['tabs'], function ($tk) { $p = page_permission($tk); return $p === null || can($p); }, ARRAY_FILTER_USE_KEY);
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
function page_module(string $page): ?string
{
    $map = [
        'automations' => ['automations', 'workflows'],
        'whatsapp' => ['broadcasts', 'wa_templates', 'contacts', 'sequences', 'wa_profile'],
        'analytics' => ['analytics', 'inbox_analytics', 'reports', 'report_print', 'goals', 'alerts', 'assistant'],
        'ads' => ['ads', 'ad_create', 'ad_audiences', 'leads', 'ad_campaign'],
        'gmb' => ['gmb_reviews', 'gmb_location', 'gmb_media'],
        'phone' => ['phone_numbers', 'sms', 'calls'],
    ];
    foreach ($map as $m => $pages) {
        if (in_array($page, $pages, true)) return $m;
    }
    return null;
}

/** رابط عنصر القائمة: أول تاب مسموح */
function nav_href(array $i): string
{
    return url(isset($i['tabs']) ? array_key_first($i['tabs']) : $i['key']);
}
