<?php
/**
 * Canonical permission catalog.
 * Used by installer + roles UI.
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
        'السجلات' => [
            'activities.view' => 'عرض سجل الأنشطة',
        ],
        'الإعدادات' => [
            'settings.manage' => 'إدارة إعدادات النظام',
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
            'permissions' => ['*'], // wildcard
        ],
        [
            'key'   => 'manager',
            'name'  => 'مدير',
            'permissions' => [
                'dashboard.view',
                'users.view',
                'roles.view',
                'clients.view.all', 'clients.manage',
                'deals.view.all',   'deals.manage',
                'tasks.view.all',   'tasks.manage',
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
            ],
        ],
    ];
}
