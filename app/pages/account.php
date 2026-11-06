<?php
/** حسابي: الاسم، كلمة السر، وآخر نشاطك */
$pdo = db();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'password');
    if ($action === 'profile') {
        // تغيير الاسم مش محتاج كلمة السر
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            flash('error', t('اكتب اسمك.'));
        } else {
            $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([mb_substr($name, 0, 120), $me['id']]);
            log_activity('account.profile');
            flash('success', t('اتحفظ.'));
        }
    } else {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $new2 = (string)($_POST['new2'] ?? $new);
        $st = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $st->execute([$me['id']]);
        if (!password_verify($cur, (string)$st->fetchColumn())) {
            flash('error', t('كلمة السر الحالية غلط.'));
        } elseif (strlen($new) < 8) {
            flash('error', t('كلمة السر الجديدة لازم تكون 8 حروف على الأقل.'));
        } elseif ($new !== $new2) {
            flash('error', t('كلمتين السر الجداد مش زي بعض.'));
        } elseif (password_verify($new, (string)$pdo->query("SELECT password_hash FROM users WHERE id = " . (int)$me['id'])->fetchColumn())) {
            flash('error', t('كلمة السر الجديدة هي نفس القديمة.'));
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            log_activity('account.password');
            flash('success', t('كلمة السر اتغيرت.'));
        }
    }
    redirect(url('account'));
}

$st = $pdo->prepare("SELECT a.action, a.details, a.created_at, w.name ws FROM activity_log a LEFT JOIN workspaces w ON w.id = a.workspace_id WHERE a.user_id = ? ORDER BY a.id DESC LIMIT 12");
$st->execute([$me['id']]);
$recent = $st->fetchAll();
$wsCount = count(user_workspaces(true));

render(t('حسابي'), 'account', function () use ($me, $recent, $wsCount) {
    ?>
    <div class="grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-6 items-start max-w-5xl">
      <div class="space-y-6">
        <form method="post" class="card p-6 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile">
          <div class="flex items-center gap-4">
            <span class="w-16 h-16 rounded-2xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-2xl font-bold flex items-center justify-center" id="ac-av"><?= e(mb_strtoupper(mb_substr((string)$me['name'], 0, 1))) ?></span>
            <div class="min-w-0"><div class="font-bold text-ink-900 text-lg truncate"><?= e($me['name']) ?></div>
              <div class="text-sm text-ink-500 truncate" dir="ltr" style="text-align:start"><?= e($me['email']) ?></div>
              <div class="text-xs text-ink-400 mt-0.5"><?= e(t('عضو في %s شركة', $wsCount)) ?></div></div>
          </div>
          <label class="block"><span class="label"><?= e(t('الاسم')) ?></span><input name="name" id="ac-name" value="<?= e($me['name']) ?>" required maxlength="120" class="input"></label>
          <div>
            <span class="label"><?= e(t('لغة الواجهة')) ?></span>
            <div class="flex gap-2">
              <?php foreach (['ar' => 'العربية', 'en' => 'English'] as $k => $l): ?><a href="<?= e(url('pref', ['lang' => $k])) ?>" class="flex-1 text-center rounded-xl border px-3 py-2 text-sm <?= lang() === $k ? 'border-brand-600 bg-brand-50 text-brand-800 font-medium' : 'border-ink-200 hover:bg-ink-50' ?>"><?= $l ?></a><?php endforeach; ?>
            </div>
          </div>
          <div class="flex justify-end"><button class="btn-primary"><?= e(t('حفظ')) ?></button></div>
        </form>

        <form method="post" class="card p-6 space-y-4" id="ac-pw">
          <?= csrf_field() ?><input type="hidden" name="action" value="password">
          <h2 class="font-bold text-ink-900"><?= e(t('تغيير كلمة السر')) ?></h2>
          <label class="block"><span class="label"><?= e(t('كلمة السر الحالية')) ?></span>
            <span class="relative block"><input type="password" name="current" required autocomplete="current-password" class="input pe-12" dir="ltr"><button type="button" class="ac-eye absolute inset-y-0 end-0 px-3 text-ink-400 hover:text-ink-700" tabindex="-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z"/></svg></button></span></label>
          <label class="block"><span class="label"><?= e(t('كلمة السر الجديدة')) ?></span>
            <span class="relative block"><input type="password" name="new" id="ac-new" required minlength="8" autocomplete="new-password" class="input pe-12" dir="ltr"><button type="button" class="ac-eye absolute inset-y-0 end-0 px-3 text-ink-400 hover:text-ink-700" tabindex="-1"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z"/></svg></button></span></label>
          <div><div class="grid grid-cols-4 gap-1" id="ac-meter"><span></span><span></span><span></span><span></span></div><p id="ac-tip" class="text-[11px] text-ink-500 mt-1"></p></div>
          <label class="block"><span class="label"><?= e(t('اكتبها تاني')) ?></span>
            <input type="password" name="new2" id="ac-new2" required autocomplete="new-password" class="input" dir="ltr"><span id="ac-match" class="text-[11px]"></span></label>
          <div class="flex justify-end"><button class="btn-primary disabled:opacity-50" id="ac-go" disabled><?= e(t('غيّر كلمة السر')) ?></button></div>
        </form>
      </div>

      <div class="card p-6">
        <h2 class="font-bold text-ink-900 mb-1"><?= e(t('آخر نشاط ليك')) ?></h2>
        <p class="text-xs text-ink-500 mb-4"><?= e(t('لو شايف حاجة انت ماعملتهاش، غيّر كلمة السر فورًا.')) ?></p>
        <?php if (!$recent): ?><p class="text-sm text-ink-500"><?= e(t('مفيش نشاط مسجل.')) ?></p><?php endif; ?>
        <ol class="relative border-s border-ink-200 ms-2 space-y-4">
          <?php foreach ($recent as $r): ?>
            <li class="ms-4">
              <span class="absolute -start-1.5 mt-1.5 w-3 h-3 rounded-full bg-brand-500 ring-4 ring-white"></span>
              <div class="text-sm text-ink-800 font-mono" dir="ltr" style="text-align:start"><?= e($r['action']) ?></div>
              <div class="text-xs text-ink-500"><?= e(fmt_date($r['created_at'] . 'Z', 'd/m h:i A')) ?><?php if (!empty($r['ws'])): ?> · <?= e($r['ws']) ?><?php endif; ?><?php if (!empty($r['details'])): ?> · <span dir="auto"><?= e(mb_substr($r['details'], 0, 60)) ?></span><?php endif; ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
    <script>
    (function () {
      var T = <?= json_encode(['weak' => t('ضعيفة — زوّد الطول وحط أرقام ورموز'), 'ok' => t('مقبولة'), 'good' => t('كويسة'), 'great' => t('قوية جدًا'), 'match' => t('✓ زي بعض'), 'nomatch' => t('مش زي بعض')], JSON_UNESCAPED_UNICODE) ?>;
      var nm = document.getElementById('ac-name'); nm.addEventListener('input', function () { document.getElementById('ac-av').textContent = (nm.value.trim()[0] || '?').toUpperCase(); });
      document.querySelectorAll('.ac-eye').forEach(function (b) { b.onclick = function () { var i = b.previousElementSibling; i.type = i.type === 'password' ? 'text' : 'password'; }; });
      var n = document.getElementById('ac-new'), n2 = document.getElementById('ac-new2'), bars = document.querySelectorAll('#ac-meter span'), tip = document.getElementById('ac-tip'), mt = document.getElementById('ac-match'), go = document.getElementById('ac-go');
      function score(p) { var s = 0; if (p.length >= 8) s++; if (p.length >= 12) s++; if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++; if (/\d/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++; if (/^(.)\1+$/.test(p) || /^(1234|password|qwerty)/i.test(p)) s = 0; return Math.min(4, s); }
      function paint() {
        var p = n.value, s = p ? Math.max(1, score(p)) : 0, col = ['#e5e7eb', '#dc2626', '#d97706', '#65a30d', '#16a34a'][s];
        bars.forEach(function (b, i) { b.className = 'h-1.5 rounded-full'; b.style.background = i < s ? col : '#eef0f3'; });
        tip.textContent = p ? [T.weak, T.weak, T.ok, T.good, T.great][s] : '';
        var m = n2.value && n2.value === p; mt.textContent = n2.value ? (m ? T.match : T.nomatch) : ''; mt.className = 'text-[11px] ' + (m ? 'text-lime-700' : 'text-red-600');
        go.disabled = !(p.length >= 8 && m);
      }
      n.addEventListener('input', paint); n2.addEventListener('input', paint); paint();
    })();
    </script>
    <?php
});
