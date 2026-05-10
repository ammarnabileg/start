<?php
require_once __DIR__ . '/../../includes/auth.php';
require_perm('tasks.manage');
csrf_check();

$id = (int)($_POST['id'] ?? 0);
$task = db_one('SELECT * FROM ' . tbl('tasks') . ' WHERE id = :id', ['id' => $id]);
if (!$task) { flash('error', 'المهمة غير موجودة.'); back(); }

db_update(tbl('tasks'),
    ['status' => 'done', 'completed_at' => date('Y-m-d H:i:s')],
    'id = :id', ['id' => $id]
);
activity_log('complete', 'task', $id, ['title' => $task['title']]);
flash('success', 'تم إنهاء المهمة.');
back();
