<?php
/** فريق الشركة المفتوحة: الأعضاء والدعوات (لصاحب الشركة) */
$pdo = db();
$me = current_user();
$w = current_workspace();
$wid = (int)$w['id'];
$newLink = $_SESSION['new_invite_link'] ?? null;
unset($_SESSION['new_invite_link']);

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
    $role = ($_POST['role'] ?? '') === 'owner' ? 'owner' : 'editor';
    $uid = (int)($_POST['user_id'] ?? 0);

    if ($action === 'invite_email') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'الإيميل مش صحيح.');
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
                flash('error', 'الشخص ده عضو في الشركة أصلاً.');
            } elseif ($status === 'pending') {
                flash('error', 'اتبعتله دعوة قبل كده ولسه ما ردش.');
            } else {
                $pdo->prepare("INSERT INTO workspace_users (workspace_id, user_id, role, status, invited_by) VALUES (?, ?, ?, 'pending', ?)")
                    ->execute([$wid, $existing, $role, $me['id']]);
                log_activity('team.invite', $email);
                flash('success', 'اتبعتت الدعوة. هتظهرله جوه البرنامج يوافق أو يرفض.');
            }
        } else {
            $pdo->prepare("DELETE FROM workspace_invites WHERE workspace_id = ? AND email = ?")->execute([$wid, $email]);
            $token = make_invite($pdo, $wid, $email, $role, (int)$me['id'], 14);
            $_SESSION['new_invite_link'] = ['url' => invite_url($token), 'for' => $email];
            log_activity('team.invite', $email);
            flash('success', 'الإيميل ده مالوش حساب. ابعتله اللينك اللي تحت، ولما يعمل حساب بنفس الإيميل الدعوة هتظهرله.');
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
        flash('success', 'اتلغت الدعوة.');
        redirect(url('team'));
    }

    if ($action === 'cancel_pending') {
        $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'pending'")->execute([$wid, $uid]);
        flash('success', 'اتلغت الدعوة.');
        redirect(url('team'));
    }

    if ($action === 'role' || $action === 'remove') {
        $st = $pdo->prepare("SELECT role FROM workspace_users WHERE workspace_id = ? AND user_id = ? AND status = 'active'");
        $st->execute([$wid, $uid]);
        $current = $st->fetchColumn();
        $losingOwner = $current === 'owner' && ($action === 'remove' || $role !== 'owner');
        if ($current === false) {
            flash('error', 'العضو ده مش موجود.');
        } elseif ($losingOwner && owners_count($wid) <= 1) {
            flash('error', 'لازم يفضل صاحب شركة واحد على الأقل.');
        } elseif ($action === 'role') {
            $pdo->prepare("UPDATE workspace_users SET role = ? WHERE workspace_id = ? AND user_id = ?")->execute([$role, $wid, $uid]);
            log_activity('team.role', $uid . ' ' . $role);
            flash('success', 'اتغيرت الصلاحية.');
        } else {
            $pdo->prepare("DELETE FROM workspace_users WHERE workspace_id = ? AND user_id = ?")->execute([$wid, $uid]);
            log_activity('team.remove', (string)$uid);
            flash('success', 'اتشال من الشركة.');
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

render('فريق ' . $w['name'], 'team', function () use ($members, $links, $me, $newLink) {
    $select = function () {
        return '<select name="role" class="border border-slate-300 rounded-lg px-3 py-2 text-sm"><option value="editor">محرر: ينشر ويرد</option><option value="owner">صاحب شركة: كل الصلاحيات</option></select>';
    };
    if ($newLink): ?>
      <div class="bg-green-50 border border-green-200 rounded-xl p-5 mb-6">
        <p class="font-bold mb-2">لينك الدعوة<?= $newLink['for'] ? ' لـ ' . e($newLink['for']) : '' ?>. انسخه وابعته على واتساب:</p>
        <div class="flex gap-2">
          <input readonly value="<?= e($newLink['url']) ?>" class="flex-1 border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white" dir="ltr" id="newLink">
          <button type="button" data-copy="#newLink" class="bg-green-600 text-white font-bold rounded-lg px-4">نسخ</button>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <?= card_open() ?>
          <div class="px-5 py-4 border-b border-slate-200 font-bold">الأعضاء</div>
          <?php foreach ($members as $m): $self = (int)$m['id'] === (int)$me['id']; ?>
            <div class="px-5 py-4 border-b last:border-0 border-slate-100 flex flex-wrap items-center gap-3">
              <?= avatar(null, $m['name']) ?>
              <div class="flex-1 min-w-[150px]">
                <div class="font-semibold"><?= e($m['name']) ?> <?= $self ? '<span class="text-xs text-slate-400">(أنت)</span>' : '' ?></div>
                <div class="text-xs text-slate-500" dir="ltr"><?= e($m['email']) ?></div>
              </div>
              <?php if ($m['status'] === 'pending'): ?>
                <span class="text-xs bg-amber-100 text-amber-800 rounded-full px-2 py-1">مستني يوافق</span>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel_pending"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <button class="text-sm text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50">إلغاء الدعوة</button></form>
              <?php else: ?>
                <form method="post" class="flex gap-1"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <select name="role" onchange="this.form.requestSubmit()" class="border border-slate-300 rounded-lg px-2 py-1.5 text-sm">
                    <option value="editor" <?= $m['role'] === 'editor' ? 'selected' : '' ?>>محرر</option>
                    <option value="owner" <?= $m['role'] === 'owner' ? 'selected' : '' ?>>صاحب شركة</option>
                  </select></form>
                <?php if (!$self): ?>
                <form method="post" onsubmit="return confirm('تشيل <?= e($m['name']) ?> من الشركة؟')"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                  <button class="text-sm text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50">شيله</button></form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($links): ?>
        <?= card_open() ?>
          <div class="px-5 py-4 border-b border-slate-200 font-bold">لينكات الدعوة الشغالة</div>
          <?php foreach ($links as $l): $id = 'inv' . (int)$l['id']; ?>
            <div class="px-5 py-3 border-b last:border-0 border-slate-100 flex flex-wrap items-center gap-2 text-sm">
              <div class="flex-1 min-w-[180px]">
                <?= $l['email'] ? 'لـ <span dir="ltr">' . e($l['email']) . '</span>' : 'لينك عام (أي حد معاه اللينك)' ?> · <?= e(role_label($l['role'])) ?>
                <div class="text-xs text-slate-500">ينتهي: <?= e(substr($l['expires_at'], 0, 10)) ?></div>
              </div>
              <input type="hidden" id="<?= $id ?>" value="<?= e(invite_url($l['token'])) ?>">
              <button type="button" data-copy="#<?= $id ?>" class="border border-slate-300 rounded-lg px-3 py-1.5">نسخ اللينك</button>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="invite_id" value="<?= (int)$l['id'] ?>">
                <button class="text-red-600 rounded-lg px-3 py-1.5 hover:bg-red-50">إلغاء</button></form>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="space-y-6">
        <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="invite_email">
          <h2 class="font-bold">دعوة بالإيميل</h2>
          <input name="email" type="email" required placeholder="الإيميل" class="w-full border border-slate-300 rounded-lg px-3 py-2" dir="ltr">
          <?= $select() ?>
          <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg py-2.5">ابعت دعوة</button>
          <p class="text-xs text-slate-500">لو عنده حساب: الدعوة بتظهرله جوه البرنامج. لو معندوش: هيطلعلك لينك تبعتهوله.</p>
        </form>

        <form method="post" class="bg-white border border-slate-200 rounded-xl p-5 space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="invite_link">
          <h2 class="font-bold">لينك دعوة عام</h2>
          <?= $select() ?>
          <select name="days" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
            <option value="1">صالح يوم واحد</option><option value="7" selected>صالح أسبوع</option><option value="30">صالح شهر</option>
          </select>
          <button class="w-full border border-indigo-600 text-indigo-700 font-bold rounded-lg py-2.5 hover:bg-indigo-50">اعمل لينك</button>
          <p class="text-xs text-slate-500">أي حد معاه اللينك يقدر يدخل الشركة. ابعته للناس اللي تثق فيهم بس، والغيه لما تخلص.</p>
        </form>
      </div>
    </div>
    <?php
});
