<?php
/**
 * تنظيم القائمة: كل عنصر في القائمة الجانبية ممكن يكون "مجموعة" صفحاتها بتظهر كتابات (tabs) فوق.
 * الأقسام الاختيارية (module) صاحب الشركة يقدر يقفلها من الإعدادات.
 */

function module_catalog(): array
{
    return [
        'crm' => t('إدارة العملاء'),
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
        ['key' => 'crm', 'label' => t('إدارة العملاء'), 'section' => t('العملاء'), 'module' => 'crm',
            'tabs' => ['crm' => t('الشركات المستهدفة'), 'crm_find' => t('ابحث عن أشخاص'), 'crm_leads' => 'Leads', 'crm_reports' => t('تقارير المبيعات'), 'leads' => t('Leads الإعلانات'), 'contacts' => t('جهات الاتصال'), 'calls' => t('المكالمات')]],
        ['key' => 'compose', 'label' => t('منشور جديد'), 'section' => t('المحتوى')],
        ['key' => 'posts', 'label' => t('المنشورات'), 'section' => t('المحتوى'),
            'tabs' => ['posts' => t('كل المنشورات'), 'calendar' => t('التقويم')], 'also' => ['post_edit', 'queue', 'bulk']],
        ['key' => 'inbox', 'label' => t('الرسائل'), 'section' => t('التواصل')],
        ['key' => 'comments', 'label' => t('التعليقات'), 'section' => t('التواصل')],
        ['key' => 'automations', 'label' => t('الردود التلقائية'), 'section' => t('التواصل'), 'module' => 'automations',
            'tabs' => ['automations' => t('الرد على التعليقات'), 'workflows' => 'Workflows']],
        ['key' => 'assistant', 'label' => t('الذكاء الاصطناعي'), 'section' => t('الأدوات')],
        ['key' => 'whatsapp', 'label' => t('واتساب'), 'section' => t('الأدوات'), 'module' => 'whatsapp',
            'tabs' => ['broadcasts' => t('رسائل جماعية'), 'wa_templates' => t('القوالب'), 'sequences' => t('رسائل متتابعة'), 'wa_profile' => t('البروفايل')]],
        ['key' => 'analytics', 'label' => t('التحليلات'), 'section' => t('الأدوات'), 'module' => 'analytics',
            'tabs' => ['analytics' => t('المنشورات'), 'inbox_analytics' => t('الرسائل'), 'reports' => t('التقارير'), 'goals' => t('الأهداف'), 'alerts' => t('التنبيهات')], 'also' => ['report_print']],
        ['key' => 'ads', 'label' => t('الإعلانات'), 'section' => t('الأدوات'), 'module' => 'ads',
            'tabs' => ['ads' => t('الحملات'), 'ad_builder' => t('منشئ الحملات'), 'ad_create' => t('حملة سريعة'), 'ad_audiences' => t('الجماهير')], 'also' => ['ad_campaign']],
        ['key' => 'gmb', 'label' => 'Google Business', 'section' => t('الأدوات'), 'module' => 'gmb',
            'tabs' => ['gmb_reviews' => t('التقييمات'), 'gmb_location' => t('بيانات الفرع'), 'gmb_media' => t('الصور')]],
        ['key' => 'phone', 'label' => t('التليفون'), 'section' => t('الأدوات'), 'module' => 'phone',
            'tabs' => ['phone_numbers' => t('الأرقام'), 'sms' => 'SMS', 'sms_mine' => t('رقمي')]],
        ['key' => 'knowledge', 'label' => t('قاعدة المعرفة'), 'section' => t('الشركة')],
        ['key' => 'media', 'label' => t('مكتبة الوسائط'), 'section' => t('الشركة')],
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
            $i['tabs'] = array_filter($i['tabs'], function ($tk) { if (($tk === 'crm' || $tk === 'crm_find') && function_exists('crm_has_key') && !crm_has_key()) return false; $p = page_permission($tk); return $p === null || can($p); }, ARRAY_FILTER_USE_KEY);
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
        'crm' => ['crm', 'crm_find', 'crm_leads', 'crm_reports'],
        'automations' => ['automations', 'workflows'],
        'whatsapp' => ['broadcasts', 'wa_templates', 'contacts', 'sequences', 'wa_profile'],
        'analytics' => ['analytics', 'inbox_analytics', 'reports', 'report_print', 'goals', 'alerts', 'assistant'],
        'ads' => ['ads', 'ad_builder', 'ad_create', 'ad_audiences', 'leads', 'ad_campaign'],
        'gmb' => ['gmb_reviews', 'gmb_location', 'gmb_media'],
        'phone' => ['phone_numbers', 'sms', 'calls', 'phone_kyc', 'sms_mine'],
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
