<?php
require_once __DIR__ . '/_bootstrap.php';
api_authenticate();

api_ok([
    'name' => 'HalaOps CRM API',
    'version' => '1.0',
    'endpoints' => [
        'GET  /api/v1/me'         => 'الحساب الحالي',
        'GET  /api/v1/clients'    => 'قائمة العملاء',
        'POST /api/v1/clients'    => 'إنشاء عميل',
        'GET  /api/v1/deals'      => 'قائمة الصفقات',
        'GET  /api/v1/tasks'      => 'قائمة المهام',
        'POST /api/v1/tasks'      => 'إنشاء مهمة',
        'POST /api/v1/tasks/:id/complete' => 'إنهاء مهمة',
        'GET  /api/v1/candidates' => 'قائمة المرشحين',
        'GET  /api/v1/dashboard'  => 'إحصائيات الحساب',
    ],
]);
