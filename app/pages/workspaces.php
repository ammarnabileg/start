<?php
/** شركاتي: إنشاء شركة، والرد على الدعوات */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('اكتب اسم الشركة.'));
            redirect(url('workspaces'));
        }
        $pdo->prepare("INSERT INTO workspaces (name, created_by) VALUES (?, ?)")
            ->execute([mb_substr($name, 0, 120), $me['id']]);
        $wid = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")
            ->execute([$wid, $me['id']]);
        $_SESSION['ws'] = $wid;
        log_activity('workspace.add', $name);
        flash('success', t('اتعملت شركة "%s" وانت صاحبها. اربطها من صفحة التكاملات عشان تبدأ.', $name));
        redirect(url('connectors'));
    }

    if ($action === 'accept' || $action === 'decline') {
        $st = $pdo->prepare("SELECT 1 FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'pending'");
        $st->execute([$id, $me['id']]);
        if ($st->fetchColumn()) {
            if ($action === 'accept') {
                $pdo->prepare("UPDATE workspace_users SET status = 'active' WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
                $_SESSION['ws'] = $id;
                flash('success', t('انضميت للشركة.'));
                redirect(url('dashboard'));
            }
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
            flash('success', t('رفضت الدعوة.'));
        }
        redirect(url('workspaces'));
    }

    if ($action === 'leave') {
        $st = $pdo->prepare("SELECT role FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
        $st->execute([$id, $me['id']]);
        $role = $st->fetchColumn();
        if ($role === 'owner' && owners_count($id) <= 1) {
            flash('error', t('انت صاحب الشركة الوحيد. اعمل حد تاني صاحب شركة الأول، أو احذف الشركة.'));
        } elseif ($role !== false) {
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$id, $me['id']]);
            flash('success', t('خرجت من الشركة.'));
        }
        redirect(url('workspaces'));
    }

    if ($action === 'delete') {
        $st = $pdo->prepare("SELECT w.name FROM workspaces w JOIN workspace_users wu ON wu.workspace_id = w.id
            WHERE w.id = ? AND wu.user_id = ? AND wu.role = 'owner' AND wu.status = 'active'");
        $st->execute([$id, $me['id']]);
        $name = $st->fetchColumn();
        if ($name === false) {
            flash('error', t('صاحب الشركة بس هو اللي يقدر يحذفها.'));
        } elseif (trim((string)($_POST['confirm'] ?? '')) !== $name) {
            flash('error', t('عشان تحذف، اكتب اسم الشركة بالظبط زي ما هو.'));
        } else {
            $pdo->prepare("DELETE FROM workspaces WHERE id = ?")->execute([$id]);
            log_activity('workspace.delete', $name);
            flash('success', t('اتحذفت "%s" من البرنامج. حساباتها ومنشوراتها في خدمة النشر زي ما هي.', $name));
        }
        redirect(url('workspaces'));
    }
}

$list = user_workspaces(true);
$invites = pending_invites();
$cur = current_workspace();
// عدد الأعضاء لكل شركة
$members = [];
if ($list) {
    $ids = array_map(function ($w) { return (int)$w['id']; }, $list);
    $st = $pdo->query("SELECT workspace_id, COUNT(*) n FROM workspace_users WHERE status = 'active' AND workspace_id IN (" . implode(',', $ids) . ") GROUP BY workspace_id");
    foreach ($st->fetchAll() as $r) $members[(int)$r['workspace_id']] = (int)$r['n'];
}

render(t('شركاتي'), 'workspaces', function () use ($list, $invites, $cur, $members) {
    $cs = countries();
    if ($invites): ?>
      <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-5 mb-6">
        <h2 class="font-bold text-ink-900 mb-3"><?= e(t('دعوات مستنية ردك')) ?> <span class="ms-1 rounded-full bg-amber-500 text-white text-xs px-2 py-0.5"><?= count($invites) ?></span></h2>
        <div class="space-y-2">
        <?php foreach ($invites as $inv): ?>
          <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white border border-amber-100 p-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white font-bold flex items-center justify-center"><?= e(mb_strtoupper(mb_substr($inv['name'], 0, 1))) ?></span>
            <div class="flex-1 min-w-[200px] text-sm">
              <b><?= e($inv['invited_by_name'] ?: t('حد')) ?></b> <?= e(t('ضافك على شركة')) ?> <b><?= e($inv['name']) ?></b> <?= e(t('كـ')) ?> <?= e(role_label($inv['role'])) ?>
            </div>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="accept"><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
              <button class="btn-primary !py-1.5"><?= e(t('موافق')) ?></button></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="decline"><input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
              <button class="btn-ghost !py-1.5"><?= e(t('رفض')) ?></button></form>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6 items-start">
      <div class="lg:col-span-2 space-y-4">
        <?php if (count($list) > 3): ?><input type="search" id="ws-q" class="input !mt-0" placeholder="<?= e(t('دوّر على شركة…')) ?>"><?php endif; ?>
        <?php if (!$list): ?>
          <div class="card p-10 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-2xl mb-3">🏢</div>
            <p class="font-bold text-lg text-ink-900 mb-1"><?= e(t('لسه مالكش شركات')) ?></p>
            <p class="text-sm text-ink-500"><?= e(t('اعمل شركة جديدة من الفورم، أو اطلب من صاحب شركة يبعتلك لينك دعوة.')) ?></p>
          </div>
        <?php endif; ?>
        <div class="grid sm:grid-cols-2 gap-4" id="ws-list">
        <?php foreach ($list as $w): $wid = (int)$w['id']; $isCur = $cur && (int)$cur['id'] === $wid; $owner = $w['my_role'] === 'owner';
            $cc = strtoupper((string)setting('country_' . $wid, '')); $flag = isset($cs[$cc]) ? $cs[$cc]['flag'] : '';
            $linked = (bool)connector('zernio', $wid); $n = $members[$wid] ?? 1; ?>
          <div class="ws card p-5 flex flex-col <?= $isCur ? 'ring-2 ring-brand-500' : '' ?>" data-q="<?= e(mb_strtolower($w['name'])) ?>">
            <div class="flex items-start gap-3">
              <span class="relative w-12 h-12 shrink-0 rounded-2xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-lg font-bold flex items-center justify-center"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?>
                <?php if ($flag): ?><span class="absolute -bottom-1 -end-1 text-base leading-none"><?= $flag ?></span><?php endif; ?></span>
              <div class="min-w-0 flex-1">
                <div class="font-bold text-ink-900 truncate"><?= e($w['name']) ?></div>
                <div class="flex flex-wrap gap-1 mt-1 text-[11px]">
                  <?php if ($isCur): ?><span class="rounded-full bg-brand-600 text-white px-2 py-0.5"><?= e(t('مفتوحة دلوقتي')) ?></span><?php endif; ?>
                  <span class="rounded-full <?= $owner ? 'bg-amber-50 text-amber-700' : 'bg-ink-100 text-ink-700' ?> px-2 py-0.5"><?= e(role_label($w['my_role'])) ?></span>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-4 text-xs">
              <div class="rounded-xl bg-ink-50 px-3 py-2"><div class="text-ink-500"><?= e(t('الفريق')) ?></div><div class="font-bold text-ink-900"><?= e(t('%s عضو', $n)) ?></div></div>
              <div class="rounded-xl px-3 py-2 <?= $linked ? 'bg-lime-50' : 'bg-red-50' ?>"><div class="text-ink-500"><?= e(t('خدمة النشر')) ?></div><div class="font-bold <?= $linked ? 'text-lime-700' : 'text-red-700' ?>"><?= e($linked ? t('مربوطة') : t('مش مربوطة')) ?></div></div>
            </div>
            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-ink-100">
              <?php if (!$isCur): ?>
                <form method="post" action="<?= e(url('switch')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $wid ?>">
                  <button class="btn-primary !py-1.5"><?= e(t('افتح')) ?></button></form>
              <?php else: ?>
                <a class="btn-ghost !py-1.5" href="<?= e(url('settings')) ?>"><?= e(t('الإعدادات')) ?></a>
                <?php if (!$linked): ?><a class="btn-primary !py-1.5" href="<?= e(url('connectors')) ?>"><?= e(t('اربطها')) ?></a><?php endif; ?>
              <?php endif; ?>
              <form method="post" class="ms-auto" onsubmit="return confirm('<?= tjs('تخرج من الشركة دي؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="leave"><input type="hidden" name="id" value="<?= $wid ?>">
                <button class="text-xs text-ink-500 hover:bg-ink-100 rounded-lg px-2.5 py-1.5"><?= e(t('خروج منها')) ?></button></form>
            </div>
            <?php if ($owner): ?>
            <details class="mt-2 group">
              <summary class="text-xs text-ink-400 hover:text-red-600 cursor-pointer list-none"><?= e(t('حذف الشركة')) ?> ⌄</summary>
              <form method="post" class="ws-del mt-2 rounded-xl bg-red-50 border border-red-200 p-3 space-y-2"><?= csrf_field() ?>
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $wid ?>">
                <p class="text-xs text-red-800"><?= e(t('بيشيلها من البرنامج لكل الفريق. حساباتها ومنشوراتها في خدمة النشر مش بتتمسح.')) ?></p>
                <input name="confirm" data-name="<?= e($w['name']) ?>" placeholder="<?= e(t('اكتب: %s', $w['name'])) ?>" class="input !mt-0 !py-1.5 text-sm" autocomplete="off">
                <button class="w-full rounded-xl bg-red-600 hover:bg-red-700 text-white font-medium py-1.5 disabled:opacity-40 disabled:cursor-not-allowed" disabled><?= e(t('حذف نهائي')) ?></button>
              </form>
            </details>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
      </div>

      <form method="post" class="card p-5 space-y-3 lg:sticky lg:top-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="add">
        <h2 class="font-bold text-ink-900"><?= e(t('شركة جديدة')) ?></h2>
        <input name="name" placeholder="<?= e(t('اسم الشركة')) ?>" required maxlength="120" class="input">
        <ol class="text-xs text-ink-600 space-y-1 list-decimal ps-4">
          <li><?= e(t('اعمل الشركة')) ?></li>
          <li><?= e(t('اربطها بمفتاح الـ API من صفحة التكاملات')) ?></li>
          <li><?= e(t('اختار دولتها من الإعدادات وادعي فريقها')) ?></li>
        </ol>
        <p class="text-xs text-ink-500"><?= e(t('كل شركة ليها مفاتيحها وفريقها لوحدها.')) ?></p>
        <button class="btn-primary w-full"><?= e(t('إنشاء')) ?></button>
      </form>
    </div>
    <script>
    (function () {
      var q = document.getElementById('ws-q');
      if (q) q.addEventListener('input', function () { var s = q.value.trim().toLowerCase(); document.querySelectorAll('.ws').forEach(function (c) { c.style.display = !s || c.dataset.q.indexOf(s) > -1 ? '' : 'none'; }); });
      document.querySelectorAll('.ws-del').forEach(function (f) {
        var i = f.querySelector('[name=confirm]'), b = f.querySelector('button');
        i.addEventListener('input', function () { b.disabled = i.value.trim() !== i.dataset.name; });
        f.addEventListener('submit', function (e) { if (b.disabled || !confirm(<?= json_encode(t('حذف نهائي؟ مفيش رجوع.'), JSON_UNESCAPED_UNICODE) ?>)) e.preventDefault(); });
      });
    })();
    </script>
    <?php
});
