<?php
/** فريق الشركة المفتوحة: الأعضاء والدعوات (لصاحب الشركة) */
$pdo = db();
$me = current_user();
$w = current_workspace();
$wid = (int)$w['id'];
$newLink = $_SESSION['new_invite_link'] ?? null;
unset($_SESSION['new_invite_link']);

/** بعد نقل الملكية: هل الدور الجديد لسه بيدير الفريق؟ */
function can_after_transfer(string $role): bool
{
    return in_array('team.manage', role_permissions($role), true);
}

function invite_url(string $token): string
{
    return base_url() . '/' . url('invite', ['t' => $token]);
}

function make_invite(PDO $pdo, int $wid, ?string $email, string $role, int $by, int $days): string
{
    $token = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO workspace_invites (workspace_id, token, email, role, created_by, expires_at) VALUES (?, ?, ?, ?, ?, NOW() + INTERVAL ? DAY)")
        ->execute([$wid, $token, $email, $role, $by, $days]);
    return $token;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $role = (string)($_POST['role'] ?? 'editor');
    if (!valid_role($role)) $role = 'editor';
    if ($role === 'owner' && !is_ws_owner()) $role = 'editor'; // صلاحية المالك يديها المالك بس
    $uid = (int)($_POST['user_id'] ?? 0);

    // إنشاء أو تعديل دور مخصص
    if ($action === 'role_save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), all_permissions()));
        $rid = (int)($_POST['role_id'] ?? 0);
        if ($name === '') {
            flash('error', t('اكتب اسم الدور.'));
        } elseif ($rid) {
            $pdo->prepare("UPDATE workspace_roles SET name = ?, permissions = ? WHERE id = ? AND workspace_id = ?")->execute([mb_substr($name, 0, 80), json_encode($perms), $rid, $wid]);
            log_activity('team.role_update', $name);
            flash('success', t('اتحفظ الدور "%s".', $name));
        } else {
            $pdo->prepare("INSERT INTO workspace_roles (workspace_id, name, permissions) VALUES (?, ?, ?)")->execute([$wid, mb_substr($name, 0, 80), json_encode($perms)]);
            log_activity('team.role_create', $name);
            flash('success', t('اتعمل الدور "%s". تقدر تديه لأي عضو من القائمة.', $name));
        }
        redirect(url('team') . '#roles');
    }
    if ($action === 'role_delete') {
        $rid = (int)($_POST['role_id'] ?? 0);
        // الأعضاء اللي عندهم الدور ده بيرجعوا محررين
        $pdo->prepare("UPDATE workspace_users SET role = 'editor' WHERE workspace_id = ? AND role = ?")->execute([$wid, 'r' . $rid]);
        $pdo->prepare("UPDATE workspace_invites SET role = 'editor' WHERE workspace_id = ? AND role = ?")->execute([$wid, 'r' . $rid]);
        $pdo->prepare("DELETE FROM workspace_roles WHERE id = ? AND workspace_id = ?")->execute([$rid, $wid]);
        log_activity('team.role_delete', (string)$rid);
        flash('success', t('اتحذف الدور، والأعضاء اللي كانوا عليه بقوا محررين.'));
        redirect(url('team') . '#roles');
    }

    // نقل الملكية لعضو تاني (للمالك بس)
    if ($action === 'transfer') {
        $myNew = (string)($_POST['my_role'] ?? 'editor');
        if (!valid_role($myNew) || $myNew === 'owner') $myNew = 'editor';
        $st = $pdo->prepare("SELECT u.name, wu.role FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = ? AND wu.user_id = ? AND wu.status = 'active'");
        $st->execute([$wid, $uid]);
        $target = $st->fetch();
        if (!is_ws_owner()) {
            flash('error', t('نقل الملكية للمالك بس.'));
        } elseif (!$target || $uid === (int)$me['id']) {
            flash('error', t('اختار عضو نشط في الشركة.'));
        } elseif (empty($_POST['confirm'])) {
            flash('error', t('علّم على مربع التأكيد الأول.'));
        } else {
            $pdo->prepare("UPDATE workspace_users SET role = 'owner' WHERE workspace_id = ? AND user_id = ?")->execute([$wid, $uid]);
            $pdo->prepare("UPDATE workspace_users SET role = ? WHERE workspace_id = ? AND user_id = ?")->execute([$myNew, $wid, $me['id']]);
            log_activity('team.transfer_ownership', $target['name'] . ' (' . $uid . ')');
            flash('success', t('الملكية اتنقلت لـ %s.', $target['name']));
            redirect(url(can_after_transfer($myNew) ? 'team' : 'dashboard'));
        }
        redirect(url('team') . '#transfer');
    }

    if ($action === 'invite_email') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', t('الإيميل مش صحيح.'));
            redirect(url('team'));
        }
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        $existing = $st->fetchColumn();
        if ($existing) {
            $st = $pdo->prepare("SELECT status FROM workspace_users WHERE workspace_id = ? AND user_id = ?");
            $st->execute([$wid, $existing]);
            $status = $st->fetchColumn();
            if ($status === 'active') {
                flash('error', t('الشخص ده عضو في الشركة أصلاً.'));
            } elseif ($status === 'pending') {
                flash('error', t('اتبعتله دعوة قبل كده ولسه ما ردش.'));
            } else {
                $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status, invited_by) VALUES (?, ?, ?, 'pending', ?)")
                    ->execute([$wid, $existing, $role, $me['id']]);
                log_activity('team.invite', $email);
                flash('success', t('اتبعتت الدعوة. هتظهرله جوه البرنامج يوافق أو يرفض.'));
            }
        } else {
            $pdo->prepare("DELETE FROM workspace_invites WHERE workspace_id = ? AND email = ?")->execute([$wid, $email]);
            $token = make_invite($pdo, $wid, $email, $role, (int)$me['id'], 14);
            $_SESSION['new_invite_link'] = ['url' => invite_url($token), 'for' => $email];
            log_activity('team.invite', $email);
            flash('success', t('الإيميل ده مالوش حساب. ابعتله اللينك اللي تحت، ولما يعمل حساب بنفس الإيميل الدعوة هتظهرله.'));
        }
        redirect(url('team'));
    }

    if ($action === 'invite_link') {
        $days = in_array((int)($_POST['days'] ?? 7), [1, 7, 30], true) ? (int)$_POST['days'] : 7;
        $token = make_invite($pdo, $wid, null, $role, (int)$me['id'], $days);
        $_SESSION['new_invite_link'] = ['url' => invite_url($token), 'for' => null];
        log_activity('team.invite_link', $role);
        redirect(url('team'));
    }

    if ($action === 'revoke') {
        $pdo->prepare("DELETE FROM workspace_invites WHERE id = ? AND workspace_id = ?")->execute([(int)$_POST['invite_id'], $wid]);
        flash('success', t('اتلغت الدعوة.'));
        redirect(url('team'));
    }

    if ($action === 'cancel_pending') {
        $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'pending'")->execute([$wid, $uid]);
        flash('success', t('اتلغت الدعوة.'));
        redirect(url('team'));
    }

    if ($action === 'role' || $action === 'remove') {
        $st = $pdo->prepare("SELECT role FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
        $st->execute([$wid, $uid]);
        $current = $st->fetchColumn();
        $losingOwner = $current === 'owner' && ($action === 'remove' || $role !== 'owner');
        if ($current === 'owner' && !is_ws_owner()) {
            flash('error', t('مالك الشركة بس يقدر يغيّر دور مالك تاني أو يشيله.'));
        } elseif ($current === false) {
            flash('error', t('العضو ده مش موجود.'));
        } elseif ($losingOwner && owners_count($wid) <= 1) {
            flash('error', t('لازم يفضل صاحب شركة واحد على الأقل.'));
        } elseif ($action === 'role') {
            $pdo->prepare("UPDATE workspace_users SET role = ? WHERE workspace_id = ? AND user_id = ?")->execute([$role, $wid, $uid]);
            log_activity('team.role', $uid . ' ' . $role);
            flash('success', t('اتغيرت الصلاحية.'));
        } else {
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$wid, $uid]);
            log_activity('team.remove', (string)$uid);
            flash('success', t('اتشال من الشركة.'));
        }
        redirect(url($uid === (int)$me['id'] ? 'workspaces' : 'team'));
    }
}

$st = $pdo->prepare("SELECT u.id, u.name, u.email, wu.role, wu.status FROM workspace_users wu JOIN users u ON u.id = wu.user_id WHERE wu.workspace_id = ? ORDER BY wu.status, u.name");
$st->execute([$wid]);
$members = $st->fetchAll();
$st = $pdo->prepare("SELECT * FROM workspace_invites WHERE workspace_id = ? AND expires_at > NOW() ORDER BY created_at DESC");
$st->execute([$wid]);
$links = $st->fetchAll();
$pdo->exec("DELETE FROM workspace_invites WHERE expires_at < NOW()");

render(t('فريق %s', $w['name']), 'team', function () use ($members, $links, $me, $newLink, $w) {
    echo '<div data-ai-insights="team_perf,role_suggest" class="mb-6"></div>';
    $select = function () {
        $h = '<select name="role" class="input">';
        foreach (role_options() as $k => $l) {
            if ($k === 'owner' && !is_ws_owner()) continue;
            $h .= '<option value="' . e($k) . '"' . ($k === 'editor' ? ' selected' : '') . '>' . e($l) . '</option>';
        }
        return $h . '</select>';
    };
    if ($newLink): ?>
      <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
        <p class="font-bold mb-2"><?= $newLink['for'] ? e(t('لينك الدعوة لـ %s. انسخه وابعته على واتساب:', $newLink['for'])) : e(t('لينك الدعوة. انسخه وابعته على واتساب:')) ?></p>
        <div class="flex gap-2">
          <input readonly value="<?= e($newLink['url']) ?>" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white" dir="ltr" id="newLink">
          <button type="button" data-copy="#newLink" class="bg-green-600 text-white font-bold rounded-lg px-4"><?= e(t('نسخ')) ?></button>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <?= card_open() ?>
          <div class="px-5 py-4 border-b border-slate-200 font-bold"><?= e(t('الأعضاء')) ?></div>
          <?php foreach ($members as $m): $self = (int)$m['id'] === (int)$me['id']; ?>
            <div class="px-5 py-4 border-b last:border-0 border-slate-100 flex flex-wrap items-center gap-3">
              <?= avatar(null, $m['name']) ?>
              <div class="flex-1 min-w-[150px]">
                <div class="font-semibold"><?= e($m['name']) ?> <?= $self ? t('<span class="text-xs text-slate-400">(أنت)</span>') : '' ?></div>
                <div class="text-xs text-slate-500" dir="ltr"><?= e($m['email']) ?></div>
              </div>
              <?php if ($m['status'] === 'pending'): ?>
                <span class="text-xs bg-amber-100 text-amber-800 rounded-full px-2 py-1"><?= e(t('مستني يوافق')) ?></span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_pending"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <button class="text-sm text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50"><?= e(t('إلغاء الدعوة')) ?></button></form>
              <?php else: ?>
                <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <select name="role" onchange="this.form.requestSubmit()" class="input !mt-0 !py-1.5 text-sm" <?= $m['role'] === 'owner' && !is_ws_owner() ? 'disabled' : '' ?>>
                    <?php foreach (role_options() as $rk => $rl): if ($rk === 'owner' && !is_ws_owner() && $m['role'] !== 'owner') continue; ?>
                      <option value="<?= e($rk) ?>" <?= $m['role'] === $rk ? 'selected' : '' ?>><?= e($rl) ?></option>
                    <?php endforeach; ?>
                  </select></form>
                <?php if (!$self): ?>
                <form method="post" onsubmit="return confirm('<?= tjs('تشيل %s من الشركة؟', $m['name']) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <button class="text-sm text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50"><?= e(t('شيله')) ?></button></form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- الأدوار والصلاحيات -->
        <div class="card" id="roles">
          <div class="px-5 py-4 border-b border-ink-200 flex items-center justify-between gap-3">
            <div><b class="text-ink-900"><?= e(t('الأدوار والصلاحيات')) ?></b>
              <p class="text-xs text-ink-500 mt-0.5"><?= e(t('اعمل أدوار باسماء من عندك، واختار لكل دور الحاجات اللي يقدر يعملها.')) ?></p></div>
          </div>
          <?php
            $catalog = permission_catalog();
            $counts = [];
            foreach ($members as $m) { if ($m['status'] === 'active') $counts[$m['role']] = ($counts[$m['role']] ?? 0) + 1; }
            $roleRows = [['owner', t('صاحب الشركة'), all_permissions(), true], ['editor', t('محرر'), editor_permissions(), true]];
            foreach (workspace_roles() as $rk => $r) $roleRows[] = [$rk, $r['name'], $r['permissions'], false, (int)$r['id']];
          ?>
          <?php foreach ($roleRows as $row): [$rk, $rname, $rperms, $builtin] = $row; $rid = $row[4] ?? 0; ?>
            <details class="border-b border-ink-100 last:border-0 group">
              <summary class="px-5 py-3 flex items-center gap-3 cursor-pointer hover:bg-ink-50">
                <span class="w-8 h-8 rounded-lg <?= $rk === 'owner' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand-700' ?> flex items-center justify-center text-sm font-semibold"><?= e(mb_substr($rname, 0, 1)) ?></span>
                <span class="flex-1 min-w-0">
                  <span class="block font-medium text-ink-900"><?= e($rname) ?> <?php if ($builtin): ?><span class="badge ms-1"><?= e(t('جاهز')) ?></span><?php endif; ?></span>
                  <span class="block text-xs text-ink-500"><?= e(t('%s صلاحية · %s عضو', count($rperms), (int)($counts[$rk] ?? 0))) ?></span>
                </span>
                <svg class="w-4 h-4 text-ink-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
              </summary>
              <form method="post" class="px-5 pb-5 pt-2 space-y-4">
                <?= csrf_field() ?><input type="hidden" name="action" value="role_save"><input type="hidden" name="role_id" value="<?= (int)$rid ?>">
                <?php if (!$builtin): ?>
                  <label class="block max-w-sm"><span class="label"><?= e(t('اسم الدور')) ?></span><input name="name" value="<?= e($rname) ?>" required maxlength="80" class="input"></label>
                <?php endif; ?>
                <div class="grid sm:grid-cols-2 gap-4">
                  <?php foreach ($catalog as $group => $perms): ?>
                    <fieldset>
                      <legend class="text-xs font-semibold text-ink-500 mb-2"><?= e($group) ?></legend>
                      <?php foreach ($perms as $pk => $pl): ?>
                        <label class="flex items-start gap-2 py-1 text-sm <?= $builtin ? 'opacity-80' : 'cursor-pointer' ?>">
                          <input type="checkbox" name="perms[]" value="<?= e($pk) ?>" class="mt-0.5 w-4 h-4" <?= in_array($pk, $rperms, true) ? 'checked' : '' ?> <?= $builtin ? 'disabled' : '' ?>>
                          <span class="text-ink-700"><?= e($pl) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </fieldset>
                  <?php endforeach; ?>
                </div>
                <?php if (!$builtin): ?>
                  <div class="flex items-center gap-3">
                    <button class="btn-primary"><?= e(t('حفظ الدور')) ?></button>
                    <button formaction="<?= e(url('team')) ?>" name="action" value="role_delete" class="text-sm text-red-600 hover:underline" onclick="return confirm('<?= tjs('حذف الدور "%s"؟ الأعضاء اللي عليه هيبقوا محررين.', $rname) ?>')"><?= e(t('حذف الدور')) ?></button>
                  </div>
                <?php else: ?>
                  <p class="text-xs text-ink-500"><?= e($rk === 'owner' ? t('المالك ليه كل الصلاحيات دايماً.') : t('الدور الجاهز مش بيتعدل. اعمل دور جديد لو محتاج صلاحيات مختلفة.')) ?></p>
                <?php endif; ?>
              </form>
            </details>
          <?php endforeach; ?>
          <details class="group" <?= workspace_roles() ? '' : 'open' ?>>
            <summary class="px-5 py-3 cursor-pointer link text-sm"><?= e(t('+ دور جديد')) ?></summary>
            <form method="post" class="px-5 pb-5 space-y-4">
              <?= csrf_field() ?><input type="hidden" name="action" value="role_save">
              <label class="block max-w-sm"><span class="label"><?= e(t('اسم الدور')) ?></span><input name="name" required maxlength="80" class="input" placeholder="<?= e(t('مثلاً: خدمة عملاء')) ?>"></label>
              <div class="grid sm:grid-cols-2 gap-4">
                <?php foreach ($catalog as $group => $perms): ?>
                  <fieldset>
                    <legend class="text-xs font-semibold text-ink-500 mb-2 flex items-center gap-2"><?= e($group) ?>
                      <button type="button" class="link text-[11px]" onclick="this.closest('fieldset').querySelectorAll('input').forEach(function(i){i.checked=true})"><?= e(t('الكل')) ?></button></legend>
                    <?php foreach ($perms as $pk => $pl): ?>
                      <label class="flex items-start gap-2 py-1 text-sm cursor-pointer"><input type="checkbox" name="perms[]" value="<?= e($pk) ?>" class="mt-0.5 w-4 h-4"><span class="text-ink-700"><?= e($pl) ?></span></label>
                    <?php endforeach; ?>
                  </fieldset>
                <?php endforeach; ?>
              </div>
              <button class="btn-primary"><?= e(t('إنشاء الدور')) ?></button>
            </form>
          </details>
        </div>

        <?php $others = array_values(array_filter($members, function ($m) use ($me) { return $m['status'] === 'active' && (int)$m['id'] !== (int)$me['id']; }));
          if (is_ws_owner() && $others): ?>
        <!-- نقل الملكية -->
        <form method="post" class="card p-5 space-y-4 border-amber-200" id="transfer" onsubmit="return confirm('<?= tjs('متأكد إنك عاوز تنقل ملكية %s؟', $w['name']) ?>')">
          <?= csrf_field() ?><input type="hidden" name="action" value="transfer">
          <div><b class="text-ink-900"><?= e(t('نقل ملكية الشركة')) ?></b>
            <p class="text-xs text-ink-500 mt-0.5"><?= e(t('العضو اللي هتختاره هيبقى مالك الشركة، وانت هتاخد الدور اللي تختاره تحت.')) ?></p></div>
          <div class="grid sm:grid-cols-2 gap-3">
            <label class="block"><span class="label"><?= e(t('المالك الجديد')) ?></span>
              <select name="user_id" class="input"><?php foreach ($others as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?> · <?= e(role_label($m['role'])) ?></option><?php endforeach; ?></select></label>
            <label class="block"><span class="label"><?= e(t('دورك بعد النقل')) ?></span>
              <select name="my_role" class="input"><?php foreach (role_options() as $rk => $rl): if ($rk === 'owner') continue; ?><option value="<?= e($rk) ?>"><?= e($rl) ?></option><?php endforeach; ?></select></label>
          </div>
          <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" class="mt-0.5 w-4 h-4"> <span><?= e(t('فاهم إني هفقد صلاحيات المالك، والمالك الجديد هو اللي يقدر يرجعهالي.')) ?></span></label>
          <button class="btn-ghost text-amber-700"><?= e(t('نقل الملكية')) ?></button>
        </form>
        <?php endif; ?>

        <?php if ($links): ?>
        <?= card_open() ?>
          <div class="px-5 py-4 border-b border-slate-200 font-bold"><?= e(t('لينكات الدعوة الشغالة')) ?></div>
          <?php foreach ($links as $l): $id = 'inv' . (int)$l['id']; ?>
            <div class="px-5 py-3 border-b last:border-0 border-slate-100 flex flex-wrap items-center gap-2 text-sm">
              <div class="flex-1 min-w-[180px]">
                <?= $l['email'] ? t('لـ <span dir="ltr">') . e($l['email']) . '</span>' : t('لينك عام (أي حد معاه اللينك)') ?> · <?= e(role_label($l['role'])) ?>
                <div class="text-xs text-slate-500"><?= e(t('ينتهي:')) ?> <?= e(substr($l['expires_at'], 0, 10)) ?></div>
              </div>
              <input type="hidden" id="<?= $id ?>" value="<?= e(invite_url($l['token'])) ?>">
              <button type="button" data-copy="#<?= $id ?>" class="border border-slate-300 rounded-lg px-3 py-1.5"><?= e(t('نسخ اللينك')) ?></button>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="invite_id" value="<?= (int)$l['id'] ?>">
                <button class="text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50"><?= e(t('إلغاء')) ?></button></form>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="space-y-6">
        <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="invite_email">
          <h2 class="font-bold"><?= e(t('دعوة بالإيميل')) ?></h2>
          <input name="email" type="email" required placeholder="<?= e(t('الإيميل')) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2" dir="ltr">
          <?= $select() ?>
          <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-2.5"><?= e(t('ابعت دعوة')) ?></button>
          <p class="text-xs text-slate-500"><?= e(t('لو عنده حساب: الدعوة بتظهرله جوه البرنامج. لو معندوش: هيطلعلك لينك تبعتهوله.')) ?></p>
        </form>

        <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="invite_link">
          <h2 class="font-bold"><?= e(t('لينك دعوة عام')) ?></h2>
          <?= $select() ?>
          <select name="days" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="1"><?= e(t('صالح يوم واحد')) ?></option><option value="7" selected><?= e(t('صالح أسبوع')) ?></option><option value="30"><?= e(t('صالح شهر')) ?></option>
          </select>
          <button class="w-full border border-indigo-600 text-indigo-700 font-bold rounded-lg py-2.5 hover:bg-indigo-50"><?= e(t('اعمل لينك')) ?></button>
          <p class="text-xs text-slate-500"><?= e(t('أي حد معاه اللينك يقدر يدخل الشركة. ابعته للناس اللي تثق فيهم بس، والغيه لما تخلص.')) ?></p>
        </form>
      </div>
    </div>
    <?php
});
