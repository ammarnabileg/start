<?php
/**
 * الأدوار والصلاحيات داخل الشركة.
 * - owner: كل الصلاحيات (ثابت).
 * - editor: دور جاهز بصلاحيات النشر والرد.
 * - r<id>: دور مخصص عمله المالك من صفحة الفريق.
 */

/** كل الصلاحيات المتاحة، متقسمة لمجموعات */
function permission_catalog(): array
{
    return [
        t('المحتوى') => [
            'posts.view' => t('عرض المنشورات والتقويم'),
            'posts.create' => t('إنشاء وجدولة منشورات'),
            'posts.manage' => t('تعديل وحذف المنشورات وإدارة الطابور'),
        ],
        t('التواصل') => [
            'inbox.reply' => t('قراءة الرسائل والرد عليها'),
            'inbox.manage' => t('أرشفة وحذف المحادثات وإدارة التصنيفات'),
            'comments.reply' => t('قراءة التعليقات والرد عليها'),
            'automations.manage' => t('إدارة الردود التلقائية والـ Workflows'),
        ],
        t('العملاء') => [
            'crm.view' => t('عرض الشركات المستهدفة والـ Leads والتعامل مع العملاء بتوعه'),
            'crm.reveal' => t('كشف أرقام وإيميلات الأشخاص (بيخصم كريديت)'),
            'crm.manage' => t('إدارة كل الـ Leads وتوزيعها وإعدادات العميل المثالي'),
        ],
        t('الأدوات') => [
            'whatsapp.view' => t('عرض أدوات واتساب'),
            'whatsapp.manage' => t('إرسال الرسائل الجماعية وإدارة القوالب والجهات'),
            'analytics.view' => t('عرض التحليلات'),
            'ai.use' => t('استخدام الذكاء الاصطناعي (بيستهلك رصيد المزود)'),
            'ads.view' => t('عرض الإعلانات والحملات'),
            'ads.spend' => t('تشغيل الحملات وتعديل الميزانيات (بيصرف فلوس)'),
            'gmb.view' => t('عرض Google Business'),
            'gmb.manage' => t('الرد على التقييمات وتعديل بيانات الفرع'),
            'phone.view' => t('عرض الأرقام والرسائل والمكالمات'),
            'phone.spend' => t('شراء الأرقام والاتصال وإرسال SMS (بيصرف فلوس)'),
        ],
        t('الشركة') => [
            'accounts.view' => t('عرض الحسابات المربوطة'),
            'accounts.manage' => t('ربط وفصل الحسابات'),
            'team.manage' => t('إدارة الفريق والأدوار والدعوات'),
            'integrations.manage' => t('إدارة التكاملات ومفاتيح الـ API'),
            'settings.manage' => t('تعديل إعدادات الشركة'),
        ],
    ];
}

function all_permissions(): array
{
    $all = [];
    foreach (permission_catalog() as $group) {
        $all = array_merge($all, array_keys($group));
    }
    return $all;
}

function editor_permissions(): array
{
    return ['posts.view', 'posts.create', 'posts.manage', 'crm.view', 'inbox.reply', 'comments.reply', 'whatsapp.view',
        'analytics.view', 'ai.use', 'ads.view', 'gmb.view', 'phone.view', 'accounts.view'];
}

/** الأدوار المخصصة للشركة */
function workspace_roles(?int $wid = null): array
{
    static $cache = [];
    $wid = $wid ?? (int)ws('id');
    if (!isset($cache[$wid])) {
        $cache[$wid] = [];
        try {
            $st = db()->prepare("SELECT * FROM workspace_roles WHERE workspace_id = ? ORDER BY name");
            $st->execute([$wid]);
            foreach ($st->fetchAll() as $r) {
                $r['permissions'] = json_decode((string)$r['permissions'], true) ?: [];
                $cache[$wid]['r' . $r['id']] = $r;
            }
        } catch (Throwable $t) {
            // قبل تحديث قاعدة البيانات
        }
    }
    return $cache[$wid];
}

/** صلاحيات دور معين */
function role_permissions(string $role, ?int $wid = null): array
{
    if ($role === 'owner') return all_permissions();
    if ($role === 'editor') return editor_permissions();
    $roles = workspace_roles($wid);
    return isset($roles[$role]) ? array_values(array_intersect($roles[$role]['permissions'], all_permissions())) : [];
}

/** المستخدم الحالي عنده الصلاحية دي في الشركة المفتوحة؟ */
function can(string $perm): bool
{
    $role = (string)ws('my_role');
    if ($role === '') return false;
    if ($role === 'owner') return true;
    return in_array($perm, role_permissions($role), true);
}

/** كل الأدوار اللي ينفع تتختار (للقوائم) */
function role_options(?int $wid = null): array
{
    $o = ['owner' => t('صاحب الشركة'), 'editor' => t('محرر')];
    foreach (workspace_roles($wid) as $k => $r) {
        $o[$k] = $r['name'];
    }
    return $o;
}

function valid_role(string $role, ?int $wid = null): bool
{
    return isset(role_options($wid)[$role]);
}

/** الصلاحية المطلوبة لفتح كل صفحة */
function page_permission(string $page): ?string
{
    $map = [
        'compose' => 'posts.create', 'campaign' => 'posts.create', 'approvals' => 'posts.create', 'post_log' => 'posts.manage', 'bulk' => 'posts.create',
        'posts' => 'posts.view', 'calendar' => 'posts.view', 'post_edit' => 'posts.view', 'queue' => 'posts.manage',
        'inbox' => 'inbox.reply', 'comments' => 'comments.reply',
        'automations' => 'automations.manage', 'workflows' => 'automations.manage',
        'broadcasts' => 'whatsapp.view', 'wa_templates' => 'whatsapp.view', 'contacts' => 'whatsapp.view', 'sequences' => 'whatsapp.view', 'wa_profile' => 'whatsapp.view',
        'analytics' => 'analytics.view', 'inbox_analytics' => 'analytics.view', 'assistant' => 'ai.use', 'ai_task' => 'ai.use', 'knowledge' => 'ai.use', 'media' => 'posts.view',
        'reports' => 'analytics.view', 'report_print' => 'analytics.view', 'goals' => 'analytics.view', 'alerts' => 'analytics.view',
        'ads' => 'ads.view', 'ad_campaign' => 'ads.view', 'ad_audiences' => 'ads.view', 'leads' => 'ads.view', 'ad_create' => 'ads.spend', 'ad_builder' => 'ads.spend',
        'competitors' => 'analytics.view', 'tenders' => 'crm.view', 'prospects' => 'crm.view', 'gmb_reviews' => 'gmb.view', 'gmb_location' => 'gmb.view', 'gmb_media' => 'gmb.view',
        'phone_numbers' => 'phone.view', 'phone_kyc' => 'phone.view', 'sms_mine' => 'phone.view', 'sms' => 'phone.view', 'calls' => 'phone.view',
        'crm' => 'crm.view', 'crm_find' => 'crm.view', 'crm_leads' => 'crm.view', 'crm_reports' => 'crm.view', 'crm_capture' => 'crm.view', 'crm_settings' => 'crm.view', 'crm_doc' => 'crm.view',
        'accounts' => 'accounts.view', 'team' => 'team.manage', 'connectors' => 'integrations.manage', 'settings' => 'settings.manage',
    ];
    return $map[$page] ?? null;
}
