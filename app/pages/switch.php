<?php
/** التنقل بين الشركات */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    foreach (user_workspaces() as $w) {
        if ((int)$w['id'] === $id) {
            $_SESSION['ws'] = $id;
            $to = ($_POST['to'] ?? '') === 'settings' && $w['my_role'] === 'owner' ? 'settings' : 'dashboard';
            redirect(url($to));
        }
    }
    flash('error', t('مالكش صلاحية على الشركة دي.'));
}
redirect(url('dashboard'));
