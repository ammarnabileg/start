<?php
/**
 * Canonical permission catalog (extended).
 */

function crm_permission_groups(): array {
    return [
        'لوحة التحكم' => [
            'dashboard.view' => 'عرض لوحة التحكم',
        ],
        'المستخدمون' => [
            'users.view'   => 'عرض المستخدمين',
            'users.manage' => 'إدارة المستخدمين (إنشاء/تعديل/حذف)',
        ],
        'الأدوار والصلاحيات' => [
            'roles.view'   => 'عرض الأدوار',
            'roles.manage' => 'إدارة الأدوار والصلاحيات',
        ],
        'العملاء' => [
            'clients.view.own' => 'عرض عملائي فقط',
            'clients.view.all' => 'عرض كل العملاء',
            'clients.manage'   => 'إدارة العملاء',
        ],
        'الصفقات' => [
            'deals.view.own' => 'عرض صفقاتي فقط',
            'deals.view.all' => 'عرض كل الصفقات',
            'deals.manage'   => 'إدارة الصفقات',
        ],
        'المهام' => [
            'tasks.view.own' => 'عرض مهامي فقط',
            'tasks.view.all' => 'عرض كل المهام',
            'tasks.manage'   => 'إدارة المهام',
        ],
        'التوظيف' => [
            'candidates.view.own' => 'عرض مرشحيّ',
            'candidates.view.all' => 'عرض كل المرشحين',
            'candidates.manage'   => 'إدارة المرشحين',
            'vacancies.view'      => 'عرض الشواغر',
            'vacancies.manage'    => 'إدارة الشواغر',
            'placements.view'     => 'عرض التعيينات',
            'placements.manage'   => 'إدارة التعيينات',
        ],
        'الأداء' => [
            'performance.view.own' => 'عرض أدائي',
            'performance.view.all' => 'عرض أداء الكل',
        ],
        'Arena (الجيميفيكيشن)' => [
            'arena.view'  => 'عرض الـ Arena (الـ XP والشارات)',
            'arena.admin' => 'إدارة المهمات والشارات',
        ],
        'الإشعارات' => [
            'notifications.manage' => 'إدارة الإشعارات (إرسال يدوي)',
        ],
        'API' => [
            'api.use' => 'استخدام REST API',
        ],
        'AI Copilot' => [
            'ai.use' => 'استخدام مساعد الذكاء الاصطناعي',
        ],
        'السجلات والإعدادات' => [
            'activities.view'  => 'عرض سجل الأنشطة',
            'settings.manage'  => 'إدارة إعدادات النظام',
        ],
    ];
}

function crm_all_permissions(): array {
    $all = [];
    foreach (crm_permission_groups() as $group => $perms) {
        foreach ($perms as $key => $label) $all[] = $key;
    }
    return $all;
}

function crm_default_roles(): array {
    return [
        [
            'key'   => 'admin',
            'name'  => 'مدير النظام',
            'permissions' => ['*'],
        ],
        [
            'key'   => 'manager',
            'name'  => 'مدير',
            'permissions' => [
                'dashboard.view',
                'users.view', 'roles.view',
                'clients.view.all', 'clients.manage',
                'deals.view.all',   'deals.manage',
                'tasks.view.all',   'tasks.manage',
                'candidates.view.all', 'candidates.manage',
                'vacancies.view',  'vacancies.manage',
                'placements.view', 'placements.manage',
                'performance.view.all', 'arena.view', 'arena.admin',
                'notifications.manage',
                'ai.use', 'api.use',
                'activities.view',
            ],
        ],
        [
            'key'   => 'sales',
            'name'  => 'مبيعات',
            'permissions' => [
                'dashboard.view',
                'clients.view.own', 'clients.manage',
                'deals.view.own',   'deals.manage',
                'tasks.view.own',   'tasks.manage',
                'performance.view.own', 'arena.view',
                'ai.use',
            ],
        ],
        [
            'key'   => 'recruiter',
            'name'  => 'توظيف',
            'permissions' => [
                'dashboard.view',
                'clients.view.own', 'clients.manage',
                'candidates.view.own', 'candidates.manage',
                'vacancies.view', 'vacancies.manage',
                'placements.view', 'placements.manage',
                'tasks.view.own', 'tasks.manage',
                'performance.view.own', 'arena.view',
                'ai.use',
            ],
        ],
        [
            'key'   => 'viewer',
            'name'  => 'مشاهد',
            'permissions' => [
                'dashboard.view',
                'clients.view.own',
                'deals.view.own',
                'tasks.view.own',
                'performance.view.own', 'arena.view',
            ],
        ],
    ];
}
