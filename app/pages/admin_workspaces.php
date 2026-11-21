<?php
/** إدارة المنصة: الشركات والطلبات والاشتراكات (لمدير النظام) */
$pdo = db();
$me = current_user();

/** يسجّل قرار إداري في ws_approvals + سجل النشاط */
$logDecision = function (int $wid, string $action, ?string $old, ?string $new, string $reason = '') use ($pdo, $me) {
    $pdo->prepare("INSERT INTO ws_approvals (workspace_id, admin_id, action, old_state, new_state, reason) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$wid, $me['id'], $action, $old, $new, mb_substr($reason, 0, 400)]);
    log_activity('admin.ws_' . $action, '#' . $wid . ($reason !== '' ? ' — ' . $reason : ''));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $st = $pdo->prepare("SELECT * FROM workspaces WHERE id = ?");
    $st->execute([$id]);
    $w = $st->fetch();
    if ($w && ($w['status'] ?? '') === 'deleted') $w = null;

    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        $ownerId = (int)($_POST['owner'] ?? $me['id']);
        if ($name === '') {
            flash('error', t('اكتب اسم الشركة.'));
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO workspaces (name, created_by, approval_status, sub_status, reviewed_by, reviewed_at) VALUES (?, ?, 'approved', 'active', ?, NOW())")
                    ->execute([mb_substr($name, 0, 120), $me['id'], $me['id']]);
                $nid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')")->execute([$nid, $ownerId ?: $me['id']]);
                $logDecision($nid, 'admin_create', null, 'approved', '');
                $pdo->commit();
                flash('success', t('اتعملت شركة "%s" (معتمدة).', $name));
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('error', t('حصلت مشكلة.')); }
        }
        redirect(url('admin_workspaces', array_filter(['tab' => $_GET['tab'] ?? null])));
    }
    if (!$w) {
        redirect(url('admin_workspaces'));
    }
    $tab = (string)($_GET['tab'] ?? '');
    $back = url('admin_workspaces', array_filter(['tab' => $tab ?: null, 'q' => $_GET['q'] ?? null]));

    switch ($action) {
        case 'approve':
            if (($w['approval_status'] ?? '') !== 'approved') {
                // الموافقة الإدارية فقط. التفعيل التجاري خطوة منفصلة (الدفع). نبدأ الاشتراك pending_payment.
                $pdo->prepare("UPDATE workspaces SET approval_status = 'approved', reviewed_by = ?, reviewed_at = NOW(), reject_reason = NULL,
                    sub_status = CASE WHEN sub_status IN ('canceled','expired') OR sub_status = '' THEN 'pending_payment' ELSE sub_status END WHERE id = ?")
                    ->execute([$me['id'], $id]);
                $logDecision($id, 'approve', $w['approval_status'] ?? '', 'approved', (string)($_POST['note'] ?? ''));
                flash('success', t('تمت الموافقة على الشركة. فعّل الاشتراك بعد تأكيد الدفع.'));
            }
            break;
        case 'reject':
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '') { flash('error', t('اكتب سبب الرفض.')); break; }
            $pdo->prepare("UPDATE workspaces SET approval_status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), reject_reason = ? WHERE id = ?")
                ->execute([$me['id'], mb_substr($reason, 0, 400), $id]);
            $logDecision($id, 'reject', $w['approval_status'] ?? '', 'rejected', $reason);
            flash('success', t('تم رفض الطلب.'));
            break;
        case 'suspend':
        case 'activate':
            $new = $action === 'suspend' ? 'suspended' : 'active';
            $pdo->prepare("UPDATE workspaces SET status = ? WHERE id = ?")->execute([$new, $id]);
            $logDecision($id, $action, $w['status'] ?? 'active', $new, (string)($_POST['reason'] ?? ''));
            flash('success', $action === 'suspend' ? t('الشركة اتوقفت. الوصول اتلغى فورًا.') : t('الشركة رجعت تشتغل.'));
            break;
        case 'billing':
            $plan = mb_substr(trim((string)($_POST['plan'] ?? '')), 0, 40);
            $subStatus = (string)($_POST['sub_status'] ?? '');
            $allowed = ['pending_payment', 'trialing', 'active', 'past_due', 'canceled', 'expired'];
            if (!in_array($subStatus, $allowed, true)) $subStatus = $w['sub_status'] ?? 'pending_payment';
            $start = trim((string)($_POST['sub_start'] ?? '')); $end = trim((string)($_POST['sub_end'] ?? ''));
            $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ? $start : null;
            $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? $end : null;
            $payVerified = !empty($_POST['payment_verified']) ? 1 : 0;
            $override = !empty($_POST['admin_override']) ? 1 : 0;
            $pdo->prepare("UPDATE workspaces SET plan = ?, sub_status = ?, sub_start = ?, sub_end = ?, payment_verified = ?, admin_override = ? WHERE id = ?")
                ->execute([$plan, $subStatus, $start, $end, $payVerified, $override, $id]);
            $logDecision($id, 'billing', $w['sub_status'] ?? '', $subStatus . ($end ? ' →' . $end : '') . ($override ? ' [override]' : ''), $plan);
            flash('success', t('اتحفظت بيانات الاشتراك.'));
            break;
        case 'extend':
            $days = max(1, min(3650, (int)($_POST['days'] ?? 30)));
            $base = ($w['sub_end'] && $w['sub_end'] >= date('Y-m-d')) ? $w['sub_end'] : date('Y-m-d');
            $newEnd = date('Y-m-d', strtotime($base . ' +' . $days . ' days'));
            $pdo->prepare("UPDATE workspaces SET sub_end = ?, sub_status = CASE WHEN sub_status IN ('expired','canceled','pending_payment') THEN 'active' ELSE sub_status END WHERE id = ?")
                ->execute([$newEnd, $id]);
            $logDecision($id, 'extend', (string)($w['sub_end'] ?? ''), $newEnd, '+' . $days . 'd');
            flash('success', t('تم تمديد الاشتراك حتى %s.', $newEnd));
            break;
        case 'apify_cap':
            save_setting('apify_cap_' . $id, (string)max(0, min(1000000, (int)($_POST['cap'] ?? 0))));
            log_activity('admin.apify_cap', $w['name'] . ': ' . (int)($_POST['cap'] ?? 0));
            flash('success', t('اتحفظ.'));
            break;
        case 'enter':
            // دخول مؤقت للدعم: وصول من الجلسة فقط، من غير ما يتحوّل لملكية دائمة في قاعدة البيانات
            $_SESSION['admin_enter'][$id] = time();
            $_SESSION['ws'] = $id; user_workspaces(true);
            $logDecision($id, 'enter', null, null, t('دخول مؤقت للدعم'));
            redirect(url('dashboard'));
            break;
        case 'rename':
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name !== '') {
                $pdo->prepare("UPDATE workspaces SET name = ? WHERE id = ?")->execute([mb_substr($name, 0, 120), $id]);
                log_activity('admin.workspace_rename', $w['name'] . ' -> ' . $name);
                flash('success', t('اتغير الاسم.'));
            }
            break;
        case 'owner':
            $uid = (int)($_POST['user_id'] ?? 0);
            $ex = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
            $ex->execute([$uid]);
            if ($ex->fetchColumn()) {
                $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status) VALUES (?, ?, 'owner', 'active')
                    ON DUPLICATE KEY UPDATE role = 'owner', status = 'active'")->execute([$id, $uid]);
                log_activity('admin.workspace_owner', $w['name'] . ' +' . $uid);
                flash('success', t('اتضاف صاحب شركة.'));
            }
            break;
        case 'delete':
            if (trim((string)($_POST['confirm'] ?? '')) !== $w['name']) {
                flash('error', t('عشان تحذف، اكتب اسم الشركة بالظبط زي ما هو.'));
            } else {
                require_once ROOT_DIR . '/app/ws_purge.php'; ws_purge((int)$id);
                $logDecision($id, 'delete', $w['status'] ?? '', 'deleted', '');
                flash('success', t('اتحذفت "%s".', $w['name']));
            }
            break;
    }
    redirect($back);
}

$tab = (string)($_GET['tab'] ?? 'requests');
if (!in_array($tab, ['requests', 'active', 'suspended', 'rejected'], true)) $tab = 'requests';
$q = trim((string)($_GET['q'] ?? ''));

// عدّادات التبويبات
$counts = ['requests' => 0, 'active' => 0, 'suspended' => 0, 'rejected' => 0];
foreach ($pdo->query("SELECT approval_status, status, sub_status, sub_end, admin_override FROM workspaces WHERE COALESCE(status,'active') <> 'deleted'")->fetchAll() as $r) {
    if (($r['approval_status'] ?? '') === 'pending_review') $counts['requests']++;
    elseif (($r['approval_status'] ?? '') === 'rejected') $counts['rejected']++;
    elseif (($r['status'] ?? 'active') === 'suspended') $counts['suspended']++;
    else {
        $gg = ws_access_state($r);
        if ($gg['ok']) $counts['active']++; else $counts['suspended']++; // منتهي/ملغي = ضمن "موقوفة/منتهية"
    }
}

$where = "COALESCE(w.status,'active') <> 'deleted'";
if ($tab === 'requests') $where .= " AND w.approval_status = 'pending_review'";
elseif ($tab === 'rejected') $where .= " AND w.approval_status = 'rejected'";
elseif ($tab === 'active') $where .= " AND w.approval_status = 'approved' AND COALESCE(w.status,'active') = 'active' AND (w.admin_override=1 OR (COALESCE(w.sub_status,'active') NOT IN ('canceled','expired','pending_payment') AND (w.sub_end IS NULL OR w.sub_end >= CURDATE())))";
elseif ($tab === 'suspended') $where .= " AND w.approval_status = 'approved' AND (COALESCE(w.status,'active') = 'suspended' OR (w.admin_override=0 AND (COALESCE(w.sub_status,'active') IN ('canceled','expired','pending_payment') OR (w.sub_end IS NOT NULL AND w.sub_end < CURDATE()))))";
if ($q !== '') $where .= " AND w.name LIKE " . $pdo->quote('%' . $q . '%');

$sql = "SELECT w.*,
    (SELECT COUNT(*) FROM workspace_users wu WHERE wu.workspace_id = w.id AND wu.status = 'active') members,
    (SELECT GROUP_CONCAT(u.name SEPARATOR ', ') FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = w.id AND wu.role = 'owner' AND wu.status = 'active') owners,
    (SELECT COUNT(*) FROM connectors c WHERE c.workspace_id = w.id) conns,
    (SELECT MAX(a.created_at) FROM activity_log a WHERE a.workspace_id = w.id) last_activity
    FROM workspaces w WHERE $where ORDER BY " . ($tab === 'requests' ? 'w.created_at ASC' : 'w.name') ;
$list = $pdo->query($sql)->fetchAll();
$users = $pdo->query("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name")->fetchAll();

// سجل القرارات لآخر الشركات المعروضة (يُعرض داخل تفاصيل كل شركة)
$decisions = [];
if ($list) {
    $ids = implode(',', array_map(function ($w) { return (int)$w['id']; }, $list));
    foreach ($pdo->query("SELECT a.*, u.name admin_name FROM ws_approvals a LEFT JOIN users u ON u.id = a.admin_id WHERE a.workspace_id IN ($ids) ORDER BY a.created_at DESC")->fetchAll() as $d) {
        $decisions[(int)$d['workspace_id']][] = $d;
    }
}

render(t('الشركات والطلبات'), 'admin_workspaces', function () use ($list, $users, $q, $me, $tab, $counts, $decisions) {
    $cs = countries();
    $tabs = ['requests' => t('طلبات جديدة'), 'active' => t('الشركات النشطة'), 'suspended' => t('موقوفة/منتهية'), 'rejected' => t('مرفوضة')];
    $subLabels = ['pending_payment' => t('بانتظار الدفع'), 'trialing' => t('تجريبي'), 'active' => t('نشط'), 'past_due' => t('متأخر السداد'), 'canceled' => t('ملغي'), 'expired' => t('منتهي')];
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-5 border-b border-ink-200">
      <?php foreach ($tabs as $k => $lbl): $on = $tab === $k; ?>
        <a href="<?= e(url('admin_workspaces', ['tab' => $k])) ?>" class="relative px-4 py-2.5 text-sm font-medium -mb-px border-b-2 <?= $on ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-800' ?>">
          <?= e($lbl) ?>
          <?php if ($counts[$k]): ?><span class="ms-1 rounded-full <?= $k === 'requests' ? 'bg-amber-500 text-white' : 'bg-ink-100 text-ink-600' ?> text-[11px] px-1.5 py-0.5"><?= (int)$counts[$k] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <div class="ms-auto flex items-center gap-2 py-1.5">
        <form method="get" class="flex gap-2">
          <input type="hidden" name="p" value="admin_workspaces"><input type="hidden" name="tab" value="<?= e($tab) ?>">
          <input type="search" name="q" value="<?= e($q) ?>" class="input !mt-0 !py-1.5 text-sm w-48" placeholder="<?= e(t('دور على شركة...')) ?>">
        </form>
        <details class="relative" data-dropdown>
          <summary class="btn-primary !py-1.5 cursor-pointer"><?= e(t('+ شركة')) ?></summary>
          <form method="post" class="menu end-0 mt-2 !w-80 p-4 space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <label class="block"><span class="label"><?= e(t('اسم الشركة')) ?></span><input name="name" required class="input"></label>
            <label class="block"><span class="label"><?= e(t('صاحب الشركة')) ?></span>
              <select name="owner" class="input"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)$me['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
            <p class="text-xs text-ink-500"><?= e(t('الشركة اللي بينشئها المدير تتعتمد مباشرة.')) ?></p>
            <button class="btn-primary w-full"><?= e(t('إنشاء')) ?></button>
          </form>
        </details>
      </div>
    </div>

    <?php if (!$list): ?>
      <div class="card p-10 text-center text-ink-500"><?= e($tab === 'requests' ? t('مفيش طلبات جديدة دلوقتي.') : t('مفيش شركات في التبويب ده.')) ?></div>
    <?php endif; ?>

    <div class="space-y-3">
    <?php foreach ($list as $w): $wid = (int)$w['id']; $meta = json_decode((string)($w['request_meta'] ?? ''), true) ?: [];
        $gate = ws_access_state($w); $cc = strtoupper((string)($meta['country'] ?? setting('country_' . $wid, ''))); ?>
      <div class="card p-5">
        <div class="flex flex-wrap items-start gap-4">
          <span class="w-12 h-12 shrink-0 rounded-2xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-lg font-bold flex items-center justify-center"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-bold text-ink-900"><?= e($w['name']) ?></h3>
              <span class="badge" dir="ltr"><?= e(ws_request_no($w)) ?></span>
              <?php if (!empty($w['legacy'])): ?><span class="badge bg-ink-100 text-ink-600"><?= e(t('قديمة معتمدة')) ?></span><?php endif; ?>
              <?php $apC = ['pending_review' => 'bg-amber-100 text-amber-800', 'approved' => 'badge-green', 'rejected' => 'bg-red-100 text-red-700'][$w['approval_status']] ?? 'badge'; ?>
              <span class="badge <?= $apC ?>"><?= e(['pending_review' => t('قيد المراجعة'), 'approved' => t('معتمدة'), 'rejected' => t('مرفوضة')][$w['approval_status']] ?? $w['approval_status']) ?></span>
              <?php if (($w['approval_status'] ?? '') === 'approved'): ?>
                <span class="badge <?= $gate['ok'] ? 'badge-green' : 'bg-orange-100 text-orange-800' ?>"><?= e($subLabels[$w['sub_status']] ?? $w['sub_status']) ?></span>
                <?php if (($w['status'] ?? '') === 'suspended'): ?><span class="badge bg-red-100 text-red-700"><?= e(t('موقوفة')) ?></span><?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-ink-600">
              <?php if (!empty($w['owners'])): ?><span><?= e(t('المالك')) ?>: <b class="text-ink-800"><?= e($w['owners']) ?></b></span><?php endif; ?>
              <?php if (!empty($meta['email'])): ?><span dir="ltr"><?= e($meta['email']) ?></span><?php endif; ?>
              <?php if (!empty($meta['contact'])): ?><span dir="ltr"><?= e($meta['contact']) ?></span><?php endif; ?>
              <?php if ($cc && isset($cs[$cc])): ?><span><?= e($cs[$cc][2] . ' ' . (lang() === 'en' ? $cs[$cc][1] : $cs[$cc][0])) ?></span><?php endif; ?>
              <?php if (!empty($meta['biz_type'])): ?><span><?= e($meta['biz_type']) ?></span><?php endif; ?>
              <span><?= e(t('%s عضو', (int)$w['members'])) ?></span>
              <?php if (!empty($w['plan'])): ?><span><?= e(t('خطة')) ?>: <b><?= e($w['plan']) ?></b></span><?php endif; ?>
              <?php if (!empty($w['sub_end'])): ?><span><?= e(t('ينتهي')) ?>: <b dir="ltr"><?= e($w['sub_end']) ?></b></span><?php endif; ?>
              <span class="text-ink-400"><?= e(t('اتعمل %s', substr((string)$w['created_at'], 0, 10))) ?></span>
            </div>
            <?php if (($w['approval_status'] ?? '') === 'rejected' && !empty($w['reject_reason'])): ?>
              <p class="text-xs text-red-700 mt-2"><?= e(t('سبب الرفض:')) ?> <?= e($w['reject_reason']) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-ink-100">
          <?php if (($w['approval_status'] ?? '') === 'pending_review'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $wid ?>">
              <button class="btn-primary !py-1.5"><?= e(t('موافقة')) ?></button></form>
            <details class="relative" data-dropdown>
              <summary class="btn-danger-ghost !py-1.5 cursor-pointer"><?= e(t('رفض')) ?></summary>
              <form method="post" class="menu end-0 mt-2 !w-72 p-3 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= $wid ?>">
                <textarea name="reason" required rows="3" class="input !mt-0 text-sm" placeholder="<?= e(t('سبب الرفض (هيظهر للمستخدم)')) ?>"></textarea>
                <button class="btn-danger w-full !py-1.5"><?= e(t('تأكيد الرفض')) ?></button></form>
            </details>
          <?php endif; ?>

          <?php if (($w['approval_status'] ?? '') === 'approved'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="enter"><input type="hidden" name="id" value="<?= $wid ?>">
              <button class="btn-ghost !py-1.5 text-xs" title="<?= e(t('دخول مؤقت للدعم (مش ملكية دائمة)')) ?>"><?= e(t('دخول مؤقت')) ?></button></form>
            <details class="relative" data-dropdown>
              <summary class="btn-ghost !py-1.5 text-xs cursor-pointer"><?= e(t('الاشتراك والدفع')) ?></summary>
              <form method="post" class="menu end-0 mt-2 !w-80 p-4 space-y-2"><?= csrf_field() ?><input type="hidden" name="action" value="billing"><input type="hidden" name="id" value="<?= $wid ?>">
                <label class="block"><span class="label"><?= e(t('الخطة')) ?></span><input name="plan" value="<?= e($w['plan']) ?>" class="input !mt-0 text-sm" placeholder="Starter / Pro / Business"></label>
                <label class="block"><span class="label"><?= e(t('حالة الاشتراك')) ?></span>
                  <select name="sub_status" class="input !mt-0 text-sm">
                    <?php foreach ($subLabels as $sk => $sl): ?><option value="<?= e($sk) ?>" <?= ($w['sub_status'] ?? '') === $sk ? 'selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?>
                  </select></label>
                <div class="grid grid-cols-2 gap-2">
                  <label class="block"><span class="label"><?= e(t('البداية')) ?></span><input type="date" name="sub_start" value="<?= e($w['sub_start']) ?>" class="input !mt-0 text-sm" dir="ltr"></label>
                  <label class="block"><span class="label"><?= e(t('النهاية')) ?></span><input type="date" name="sub_end" value="<?= e($w['sub_end']) ?>" class="input !mt-0 text-sm" dir="ltr"></label>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="payment_verified" value="1" <?= !empty($w['payment_verified']) ? 'checked' : '' ?>><?= e(t('تم التحقق من الدفع')) ?></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="admin_override" value="1" <?= !empty($w['admin_override']) ? 'checked' : '' ?>><?= e(t('استثناء إداري (وصول رغم الاشتراك)')) ?></label>
                <button class="btn-primary w-full !py-1.5"><?= e(t('حفظ')) ?></button>
              </form>
            </details>
            <form method="post" class="flex items-center gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="extend"><input type="hidden" name="id" value="<?= $wid ?>">
              <input name="days" type="number" min="1" value="30" class="input !mt-0 !py-1.5 text-xs w-16" dir="ltr">
              <button class="btn-ghost !py-1.5 text-xs"><?= e(t('تمديد (يوم)')) ?></button></form>
            <?php if (($w['status'] ?? 'active') === 'suspended'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?= $wid ?>">
                <button class="btn-ghost !py-1.5 text-xs text-lime-700"><?= e(t('إعادة التشغيل')) ?></button></form>
            <?php else: ?>
              <form method="post" data-confirm="<?= e(t('إيقاف الشركة؟ الوصول هيتلغي فورًا.')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="id" value="<?= $wid ?>">
                <button class="btn-ghost !py-1.5 text-xs text-amber-700"><?= e(t('إيقاف')) ?></button></form>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (($w['approval_status'] ?? '') === 'rejected'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $wid ?>">
              <button class="btn-ghost !py-1.5 text-xs text-lime-700"><?= e(t('إعادة النظر والموافقة')) ?></button></form>
          <?php endif; ?>

          <details class="relative ms-auto" data-dropdown>
            <summary class="btn-ghost !py-1.5 text-xs cursor-pointer">⋯</summary>
            <div class="menu end-0 mt-2 !w-72 p-3 space-y-3">
              <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= $wid ?>">
                <input name="name" value="<?= e($w['name']) ?>" class="input !mt-0 text-xs flex-1"><button class="btn-ghost text-xs"><?= e(t('تغيير')) ?></button></form>
              <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="owner"><input type="hidden" name="id" value="<?= $wid ?>">
                <select name="user_id" class="input !mt-0 text-xs flex-1"><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?></select>
                <button class="btn-ghost text-xs"><?= e(t('اعمله صاحب')) ?></button></form>
              <?php if (function_exists('apify_cap')): ?><form method="post" class="flex gap-2 items-center"><?= csrf_field() ?><input type="hidden" name="action" value="apify_cap"><input type="hidden" name="id" value="<?= $wid ?>">
                <span class="text-xs text-ink-500 shrink-0"><?= e(t('حد Apify')) ?></span><input name="cap" type="number" min="0" step="100" value="<?= apify_cap($wid) ?>" class="input !mt-0 text-xs flex-1" dir="ltr"><button class="btn-ghost text-xs"><?= e(t('حفظ')) ?></button></form><?php endif; ?>
              <?php if (!empty($decisions[$wid])): ?>
                <div class="border-t border-ink-100 pt-2">
                  <div class="text-[11px] text-ink-400 mb-1"><?= e(t('سجل القرارات')) ?></div>
                  <div class="space-y-1 max-h-40 overflow-y-auto">
                    <?php foreach (array_slice($decisions[$wid], 0, 12) as $d): ?>
                      <div class="text-[11px] text-ink-600"><span class="text-ink-400" dir="ltr"><?= e(substr((string)$d['created_at'], 0, 16)) ?></span> · <b><?= e($d['action']) ?></b><?= $d['new_state'] ? ' → ' . e($d['new_state']) : '' ?><?= $d['admin_name'] ? ' · ' . e($d['admin_name']) : '' ?><?= $d['reason'] ? '<div class="text-ink-400 ps-2">' . e($d['reason']) . '</div>' : '' ?></div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
              <form method="post" class="border-t border-ink-100 pt-3 space-y-2" data-confirm-danger><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $wid ?>">
                <input name="confirm" class="input !mt-0 text-xs" placeholder="<?= e(t('اكتب: %s', $w['name'])) ?>">
                <button class="btn-danger-ghost !py-1 !px-2.5 !text-xs"><?= e(t('حذف نهائي')) ?></button></form>
            </div>
          </details>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
    <?php
});
