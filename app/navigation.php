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
    // القايمة مقسمة على رحلة المنصة: شركتك ← السوق ← المحتوى ← النشر وجمع العملاء ← المبيعات
    $s1 = t('١ · شركتك'); $s2 = t('٢ · السوق والفرص'); $s3 = t('٣ · المحتوى والحملات'); $s4 = t('٤ · النشر وجمع العملاء'); $s5 = t('٥ · المبيعات');
    $items = [
        ['key' => 'dashboard', 'label' => t('الرئيسية'), 'section' => ''],
        ['key' => 'knowledge', 'label' => t('ملف الشركة'), 'section' => $s1, 'tabs' => ['knowledge' => t('قاعدة المعرفة'), 'media' => t('مكتبة الوسائط')]],
        ['key' => 'accounts', 'label' => t('الحسابات والتكاملات'), 'section' => $s1, 'tabs' => ['accounts' => t('الحسابات المربوطة')] + ($owner ? ['connectors' => t('التكاملات')] : [])],
        ['key' => 'analytics', 'label' => t('التحليلات'), 'section' => $s2, 'module' => 'analytics',
            'tabs' => ['analytics' => t('المنشورات'), 'inbox_analytics' => t('الرسائل'), 'reports' => t('التقارير'), 'goals' => t('الأهداف'), 'alerts' => t('التنبيهات')], 'also' => ['report_print']],
        ['key' => 'crm_find', 'label' => t('استكشاف السوق'), 'section' => $s2, 'module' => 'crm', 'apollo' => true, 'tabs' => ['crm_find' => t('ابحث عن أشخاص'), 'crm' => t('الشركات المستهدفة')]],
        ['key' => 'gmb', 'label' => 'Google Business', 'section' => $s2, 'module' => 'gmb',
            'tabs' => ['gmb_reviews' => t('التقييمات'), 'gmb_location' => t('بيانات الفرع'), 'gmb_media' => t('الصور')]],
        ['key' => 'compose', 'label' => t('منشور جديد'), 'section' => $s3],
        ['key' => 'posts', 'label' => t('المنشورات'), 'section' => $s3,
            'tabs' => ['posts' => t('كل المنشورات'), 'calendar' => t('التقويم'), 'approvals' => t('الموافقات'), 'post_log' => t('سجل النشر')], 'also' => ['post_edit', 'queue', 'bulk']],
        ['key' => 'ads', 'label' => t('الإعلانات'), 'section' => $s3, 'module' => 'ads',
            'tabs' => ['ads' => t('الحملات'), 'ad_builder' => t('منشئ الحملات'), 'ad_create' => t('حملة سريعة'), 'ad_audiences' => t('الجماهير')], 'also' => ['ad_campaign']],
        ['key' => 'assistant', 'label' => t('الذكاء الاصطناعي'), 'section' => $s3],
        ['key' => 'inbox', 'label' => t('الرسائل والتعليقات'), 'section' => $s4, 'tabs' => ['inbox' => t('الرسائل'), 'comments' => t('التعليقات')]],
        ['key' => 'crm_capture', 'label' => t('جمع العملاء'), 'section' => $s4, 'module' => 'crm'],
        ['key' => 'automations', 'label' => t('الردود التلقائية'), 'section' => $s4, 'module' => 'automations',
            'tabs' => ['automations' => t('الرد على التعليقات'), 'workflows' => 'Workflows']],
        ['key' => 'whatsapp', 'label' => t('واتساب'), 'section' => $s4, 'module' => 'whatsapp',
            'tabs' => ['broadcasts' => t('رسائل جماعية'), 'contacts' => t('جهات الاتصال'), 'wa_templates' => t('القوالب'), 'sequences' => t('رسائل متتابعة'), 'wa_profile' => t('البروفايل')]],
        ['key' => 'phone', 'label' => t('التليفون'), 'section' => $s4, 'module' => 'phone',
            'tabs' => ['phone_numbers' => t('الأرقام'), 'calls' => t('المكالمات'), 'sms' => 'SMS', 'sms_mine' => t('رقمي')]],
        ['key' => 'crm_leads', 'label' => t('العملاء'), 'section' => $s5, 'module' => 'crm',
            'tabs' => ['crm_leads' => t('كل العملاء'), 'leads' => t('عملاء الإعلانات'), 'crm_reports' => t('تقارير المبيعات'), 'crm_settings' => t('الإعدادات')]],
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
function page_module(string $page): ?string
{
    $map = [
        'crm' => ['crm', 'crm_find', 'crm_leads', 'crm_reports', 'crm_capture', 'crm_settings'],
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
