<?php
/** شركاتي: إنشاء شركة، والرد على الدعوات */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($name === '') {
            flash('error', 'اكتب اسم الشركة.');
            redirect(url('workspaces'));
        }
        if ($key !== '') {
            try {
                (new Zernio($key))->profiles();
            } catch (ZernioException $ex) {
                flash('error', 'الشركة ما اتعملتش. المفتاح فيه مشكلة: ' . $ex->getMessage());
                redirect(url('workspaces'));
            }
        }
        $pdo->prepare("INSERT INTO workspaces (name, api_key, created_by) VALUES (?, ?, ?)")
            ->execute([mb_substr($name, 0, 120), $key ?: null, $me['id']]);
        $wid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
            ->execute([$wid, $me['id']]);
        $_SESSION['ws'] = $wid;
        log_activity('workspace.add', $name);
        flash('success', 'اتعملت شركة "' . $name . '" وانت صاحبها.' . ($key ? '' : ' ضيف مفتاح Zernio من الإعدادات.'));
        redirect(url($key ? 'accounts' : 'settings'));
    }

    if ($action === 'accept' || $action === 'decline') {
        $st = $pdo->prepare("SELECT 1 FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'pending'");
        $st->execute([$id, $me['id']]);
        if ($st->fetchColumn()) {
            if ($action === 'accept') {
                $pdo->prepare("UPDATE workspace_users SET status = 'active' WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
                $_SESSION['ws'] = $id;
                flash('success', 'انضميت للشركة.');
                redirect(url('dashboard'));
            }
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
            flash('success', 'رفضت الدعوة.');
        }
        redirect(url('workspaces'));
    }

    if ($action === 'leave') {
        $st = $pdo->prepare("SELECT role FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
        $st->execute([$id, $me['id']]);
        $role = $st->fetchColumn();
        if ($role === 'owner' && owners_count($id) <= 1) {
            flash('error', 'انت صاحب الشركة الوحيد. اعمل حد تاني صاحب شركة الأول، أو احذف الشركة.');
        } elseif ($role !== false) {
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
            flash('success', 'خرجت من الشركة.');
        }
        redirect(url('workspaces'));
    }

    if ($action === 'delete') {
        $st = $pdo->prepare("SELECT w.name FROM workspaces w JOIN workspace_users wu ON wu.workspace_id = w.id
            WHERE w.id = ? AND wu.user_id = ? AND wu.role = 'owner' AND wu.status = 'active'");
        $st->execute([$id, $me['id']]);
        $name = $st->fetchColumn();
        if ($name === false) {
            flash('error', 'صاحب الشركة بس هو اللي يقدر يحذفها.');
        } elseif (trim((string)($_POST['confirm'] ?? '')) !== $name) {
            flash('error', 'عشان تحذف، اكتب اسم الشركة بالظبط زي ما هو.');
        } else {
            $pdo->prepare("DELETE FROM workspaces WHERE id = ?")->execute([$id]);
            log_activity('workspace.delete', $name);
            flash('success', 'اتحذفت "' . $name . '" من البرنامج. حساباتها ومنشوراتها في Zernio زي ما هي.');
        }
        redirect(url('workspaces'));
    }
}

$list = user_workspaces(true);
$invites = pending_invites();
$cur = current_workspace();

render('شركاتي', 'workspaces', function () use ($list, $invites, $cur) {
    if ($invites): ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6">
        <h2 class="font-bold mb-3">دعوات مستنية ردك</h2>
        <?php foreach ($invites as $inv): ?>
          <div class="flex flex-wrap items-center gap-3 py-2">
            <div class="flex-1 min-w-[200px] text-sm">
              <b><?= e($inv['invited_by_name'] ?: 'حد') ?></b> ضافك على شركة <b><?= e($inv['name']) ?></b> كـ <?= e(role_label($inv['role'])) ?>
            </div>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
              <button class="bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg px-4 py-2">موافق</button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
              <button class="border border-slate-300 bg-white text-sm rounded-lg px-4 py-2">رفض</button></form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-white border border-slate-200 rounded-xl">
        <?php if (!$list): ?>
          <div class="p-8 text-center">
            <p class="font-bold text-lg mb-1">لسه مالكش شركات</p>
            <p class="text-sm text-slate-500">اعمل شركة جديدة من الفورم، أو اطلب من صاحب شركة يبعتلك لينك دعوة.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($list as $w): $isCur = $cur && (int)$cur['id'] === (int)$w['id']; $owner = $w['my_role'] === 'owner'; ?>
          <div class="p-5 border-b last:border-0 border-slate-100">
            <div class="flex flex-wrap items-center gap-3">
              <div class="flex-1 min-w-0">
                <div class="font-bold"><?= e($w['name']) ?> <?= $isCur ? '<span class="text-xs bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 mr-1">مفتوحة دلوقتي</span>' : '' ?></div>
                <div class="text-xs text-slate-500 mt-0.5"><?= e(role_label($w['my_role'])) ?> ·
                  <?= $w['api_key'] ? '<span class="text-green-700">مربوطة بـ Zernio</span>' : '<span class="text-red-700">مفيش مفتاح API</span>' ?></div>
              </div>
              <?php if (!$isCur): ?>
              <form method="post" action="<?= e(url('switch')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                <button class="text-sm bg-indigo-600 text-white rounded-lg px-3 py-1.5">افتح</button></form>
              <?php endif; ?>
              <form method="post" onsubmit="return confirm('تخرج من الشركة دي؟')"><?= csrf_field() ?><input type="hidden" name="action" value="leave"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                <button class="text-sm text-slate-500 hover:bg-slate-100 rounded-lg px-3 py-1.5">خروج منها</button></form>
            </div>
            <?php if ($owner): ?>
            <details class="mt-3">
              <summary class="text-xs text-red-600 cursor-pointer">حذف الشركة</summary>
              <form method="post" class="flex flex-wrap gap-2 mt-2"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                <input name="confirm" placeholder="اكتب: <?= e($w['name']) ?>" class="border border-red-300 rounded-lg px-3 py-1.5 text-sm flex-1 min-w-[180px]">
                <button class="text-sm bg-red-600 text-white rounded-lg px-3 py-1.5">حذف نهائي</button>
              </form>
              <p class="text-xs text-slate-500 mt-1">بيشيلها من البرنامج لكل الفريق. حساباتها ومنشوراتها في Zernio مش بتتمسح.</p>
            </details>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3 h-fit">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <h2 class="font-bold">شركة جديدة</h2>
        <input name="name" placeholder="اسم الشركة" required class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input name="api_key" type="password" autocomplete="off" placeholder="مفتاح Zernio API (اختياري)" class="w-full border border-slate-300 rounded-lg px-3 py-2" dir="ltr">
        <p class="text-xs text-slate-500">كل شركة ليها مفتاح Zernio وفريق منفصلين. ممكن تضيف المفتاح بعدين.</p>
        <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-2.5">إنشاء</button>
      </form>
    </div>
    <?php
});
