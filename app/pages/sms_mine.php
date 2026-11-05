<?php
/* رقمي: رسايل SMS من الخط بتاعك عن طريق موبايل أندرويد (تطبيق SMS Gateway المفتوح المصدر) */
$wid = (int)ws('id');
$c = smsgate_conf();
$fxd = getenv('UI_FIXTURES');
$fake = $fxd && is_file($fxd . '/smsgate_fake.php') ? require $fxd . '/smsgate_fake.php' : null; // اختبارات بس
$owner = can('phone.spend');
$phone = smsgate_norm((string)($_GET['to'] ?? $_POST['to'] ?? ''));

/* ---------- ajax: تحديث المحادثة ---------- */
if (!empty($_GET['ajax']) && $c) {
    header('Content-Type: application/json; charset=utf-8');
    $after = (int)($_GET['after'] ?? 0);
    $rows = [];
    if ($phone !== '') {
        $st = db()->prepare('SELECT id, direction, body, state, error, created_at FROM sms_gate_messages WHERE workspace_id = ? AND phone = ? AND id > ? ORDER BY id LIMIT 200');
        $st->execute([$wid, $phone, $after]); $rows = array_map(function ($m) { $m['time'] = fmt_date($m['created_at'], 'd/m H:i'); return $m; }, $st->fetchAll());
        db()->prepare("UPDATE sms_gate_messages SET seen = 1 WHERE workspace_id = ? AND phone = ? AND direction = 'in'")->execute([$wid, $phone]);
        // حالة الرسايل القديمة كمان (sent → delivered)
        $st = db()->prepare("SELECT id, state, error FROM sms_gate_messages WHERE workspace_id = ? AND phone = ? AND direction = 'out' AND id <= ? ORDER BY id DESC LIMIT 30");
        $st->execute([$wid, $phone, $after]); $states = $st->fetchAll();
    }
    $st = db()->prepare("SELECT COUNT(*) FROM sms_gate_messages WHERE workspace_id = ? AND direction = 'in' AND seen = 0");
    $st->execute([$wid]);
    echo json_encode(['rows' => $rows, 'states' => $states ?? [], 'unread' => (int)$st->fetchColumn()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $c) {
    $action = (string)($_POST['action'] ?? '');
    $ajax = !empty($_POST['ajax']);
    $out = ['ok' => false];
    try {
        if ($action === 'send') {
            $text = trim((string)($_POST['text'] ?? ''));
            if (!preg_match('/^\+\d{7,15}$/', $phone)) throw new SmsGateException(t('اكتب الرقم بالصيغة الدولية، مثلاً %s.', cx_phone()));
            if ($text === '') throw new SmsGateException(t('اكتب الرسالة الأول.'));
            if (mb_strlen($text) > 1000) throw new SmsGateException(t('الرسالة طويلة جدًا (أقصى حاجة 1000 حرف).'));
            // حماية الخط من الحظر: حد أقصى للإرسال في الدقيقة
            $st = db()->prepare("SELECT COUNT(*) FROM sms_gate_messages WHERE workspace_id = ? AND direction = 'out' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            $st->execute([$wid]);
            if ((int)$st->fetchColumn() >= 20) throw new SmsGateException(t('بعت رسايل كتير في دقيقة. استنى شوية عشان شركة الاتصالات ماتوقفش الخط.'));
            $r = $fake ? $fake('send', $phone, $text) : smsgate_send($c, $phone, $text);
            $id = smsgate_log($wid, 'out', $phone, $text, (string)($r['id'] ?? ''), strtolower((string)($r['state'] ?? 'pending')), null, (int)(current_user()['id'] ?? 0) ?: null);
            log_activity('sms_mine.send', $phone);
            $out = ['ok' => true, 'id' => $id];
        } elseif ($action === 'hooks' && $owner) {
            $n = $fake ? $fake('hooks') : smsgate_register_hooks($c);
            $out = ['ok' => true];
            flash('success', t('الموبايل هيبعت الرسايل الجاية للمنصة. ابعت رسالة تجربة لرقمك من موبايل تاني.'));
        }
    } catch (Throwable $ex) {
        $out = ['ok' => false, 'error' => $ex->getMessage()];
        if (!$ajax) flash('error', $ex->getMessage());
    }
    if ($ajax) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($out, JSON_UNESCAPED_UNICODE); exit; }
    redirect(url('sms_mine', array_filter(['to' => $phone])));
}

$threads = $c ? smsgate_threads($wid) : [];
$msgs = $c && $phone !== '' ? smsgate_thread($wid, $phone) : [];
$device = null; $devErr = null;
if ($c && !$phone) {
    try { $d = $fake ? $fake('devices') : smsgate_request($c, 'GET', '/devices'); $device = is_array($d) && $d ? $d[0] : null; }
    catch (Throwable $ex) { $devErr = $ex->getMessage(); }
}

render(t('رقمي'), 'sms_mine', function () use ($c, $owner, $threads, $msgs, $phone, $device, $devErr) {
    if (!$c): ?>
      <div class="rounded-2xl border border-ink-200 bg-white overflow-hidden">
        <div class="p-6 bg-gradient-to-l from-lime-50 to-white border-b border-ink-100 flex items-center gap-4">
          <span class="w-14 h-14 rounded-2xl bg-lime-600 text-white flex items-center justify-center text-sm font-bold">SIM</span>
          <div><h2 class="text-lg font-bold text-ink-900"><?= e(t('ابعت واستقبل SMS من رقمك نفسه')) ?></h2>
            <p class="text-sm text-ink-600"><?= e(t('أي خط، أي شبكة (فودافون، أورنج، اتصالات، WE…)، عن طريق موبايل أندرويد قديم عليه الخط. الرسايل بتتحسب من باقة الخط نفسه.')) ?></p></div>
        </div>
        <ol class="p-6 grid md:grid-cols-2 gap-4">
          <?php foreach ([
              [t('نزّل التطبيق على الموبايل اللي فيه الخط'), t('تطبيق SMS Gateway for Android (مفتوح المصدر، مجاني). من صفحة الإصدارات على GitHub نزّل ملف الـ APK وثبّته.'), 'https://github.com/capcom6/android-sms-gateway/releases', t('صفحة التحميل ↗')],
              [t('فعّل السحابة جوه التطبيق'), t('افتح التطبيق ← Settings ← Cloud server ← Sign Up. هيديك اسم مستخدم وكلمة سر.'), 'https://docs.sms-gate.app/getting-started/public-cloud-server/', t('الشرح ↗')],
              [t('حط البيانات هنا في المنصة'), t('من صفحة التكاملات اختار "رقمي من موبايل أندرويد" وحط اسم المستخدم وكلمة السر. المنصة هتجرب الاتصال قبل الحفظ.'), url('connectors'), t('افتح التكاملات')],
              [t('ارجع هنا وشغّل الاستقبال'), t('بعد الحفظ هيظهر زرار يخلي الموبايل يبعت أي رسالة جاية للمنصة.'), '', ''],
          ] as $i => $s): ?>
            <li class="rounded-xl border border-ink-200 p-4 flex gap-3">
              <span class="w-8 h-8 shrink-0 rounded-full bg-lime-600 text-white font-bold flex items-center justify-center"><?= $i + 1 ?></span>
              <div><div class="font-medium text-ink-900"><?= e($s[0]) ?></div><p class="text-sm text-ink-600 mt-1"><?= e($s[1]) ?></p>
                <?php if ($s[2]): ?><a class="link text-sm mt-2 inline-block" href="<?= e($s[2]) ?>" <?= strpos($s[2], 'http') === 0 ? 'target="_blank" rel="noopener"' : '' ?>><?= e($s[3]) ?></a><?php endif; ?></div>
            </li>
          <?php endforeach; ?>
        </ol>
        <div class="px-6 pb-6 text-xs text-ink-500 space-y-1">
          <p>• <?= e(t('الموبايل لازم يفضل شغال ومتوصل بالنت والشاحن. لو اتقفل، الرسايل بتستنى لحد ما يرجع.')) ?></p>
          <p>• <?= e(t('ده للرسايل بس. المكالمات من رقمك مش مدعومة بالطريقة دي.')) ?></p>
          <p>• <?= e(t('ماتستخدموش في رسايل جماعية كتير: شركات الاتصالات بتوقف الخطوط اللي بتبعت سبام، والمنصة حاطة حد أقصى 20 رسالة في الدقيقة.')) ?></p>
        </div>
      </div>
    <?php return; endif;
    $https = strpos(base_url(), 'https://') === 0;
    ?>
    <div class="grid lg:grid-cols-[300px_minmax(0,1fr)] gap-4 items-start">
      <aside class="card overflow-hidden">
        <div class="p-3 border-b border-ink-100 space-y-2">
          <form method="get" class="flex gap-2"><input type="hidden" name="p" value="sms_mine">
            <input name="to" data-phone="any" class="input !mt-0 !py-1.5 flex-1 min-w-0 text-sm" dir="ltr" placeholder="<?= e(t('رقم جديد…')) ?>"><button class="btn-primary !py-1.5 !px-3">+</button></form>
          <input type="search" id="sm-q" class="input !mt-0 !py-1.5 text-sm" placeholder="<?= e(t('دوّر في المحادثات…')) ?>">
        </div>
        <div id="sm-threads" class="max-h-[70vh] overflow-y-auto">
          <?php if (!$threads): ?><p class="p-6 text-center text-sm text-ink-500"><?= e(t('مفيش رسايل لسه.')) ?></p><?php endif; ?>
          <?php foreach ($threads as $t): $on = $t['phone'] === $phone; ?>
            <a href="<?= e(url('sms_mine', ['to' => $t['phone']])) ?>" data-q="<?= e(mb_strtolower($t['phone'] . ' ' . $t['body'])) ?>" class="sm-th flex gap-3 px-3 py-2.5 border-b border-ink-100 hover:bg-ink-50 <?= $on ? 'bg-brand-50' : '' ?>">
              <span class="w-9 h-9 shrink-0 rounded-full bg-ink-100 text-ink-600 flex items-center justify-center text-xs">✉</span>
              <span class="min-w-0 flex-1">
                <span class="flex items-center gap-2"><span class="font-medium text-sm text-ink-900 truncate" dir="ltr" style="unicode-bidi:isolate"><?= e($t['phone']) ?></span>
                  <span class="ms-auto text-[10px] text-ink-400"><?= e(fmt_date($t['created_at'], 'd/m H:i')) ?></span></span>
                <span class="flex items-center gap-1"><span class="text-xs text-ink-500 truncate flex-1" dir="auto"><?= $t['direction'] === 'out' ? '↗ ' : '' ?><?= e($t['body']) ?></span>
                  <?php if ((int)$t['unread']): ?><span class="rounded-full bg-lime-600 text-white text-[10px] px-1.5"><?= (int)$t['unread'] ?></span><?php endif; ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      </aside>

      <section class="min-w-0">
        <?php if ($phone === ''): ?>
          <div class="card p-6 space-y-4">
            <div class="flex items-center gap-3">
              <span class="w-12 h-12 rounded-2xl bg-lime-600 text-white flex items-center justify-center text-xs font-bold">SIM</span>
              <div class="flex-1"><div class="font-bold text-ink-900"><?= e(t('الموبايل')) ?></div>
                <div class="text-sm <?= $devErr ? 'text-red-600' : 'text-ink-500' ?>"><?= e($devErr ?: ($device ? trim(($device['name'] ?? '') . ' · ' . t('آخر ظهور: %s', fmt_date($device['lastSeen'] ?? null, 'd/m H:i'))) : t('مفيش موبايل متسجل على الحساب ده لسه.'))) ?></div></div>
            </div>
            <?php if ($owner): ?>
              <div class="rounded-xl border border-ink-200 p-4">
                <div class="font-medium text-ink-900"><?= e(t('استقبال الرسايل في المنصة')) ?></div>
                <p class="text-sm text-ink-600 mt-1"><?= e(t('دوس هنا مرة واحدة عشان الموبايل يبعت أي SMS جاية لرقمك للمنصة، ويبلغها بحالة الرسايل اللي بتتبعت.')) ?></p>
                <?php if (!$https): ?><p class="text-xs text-amber-700 mt-2"><?= e(t('تنبيه: رابط المنصة مش https، وسحابة التطبيق مش بتقبل غير https. الاستقبال هيشتغل بعد ما الموقع يبقى عليه شهادة SSL.')) ?></p><?php endif; ?>
                <form method="post" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="hooks"><button class="btn-primary"><?= e(t('شغّل الاستقبال')) ?></button></form>
              </div>
            <?php endif; ?>
            <p class="text-sm text-ink-500"><?= e(t('اختار محادثة من الجنب أو اكتب رقم جديد عشان تبعت.')) ?></p>
          </div>
        <?php else: ?>
          <div class="card flex flex-col h-[70vh]">
            <div class="flex items-center gap-3 px-4 py-3 border-b border-ink-100">
              <span class="font-bold text-ink-900" dir="ltr" style="unicode-bidi:isolate"><?= e($phone) ?></span>
              <span id="sm-live" class="text-[11px] text-ink-400"></span>
            </div>
            <div id="sm-msgs" class="flex-1 overflow-y-auto p-4 space-y-2 bg-ink-50"></div>
            <form id="sm-form" class="border-t border-ink-100 p-3 flex items-end gap-2">
              <div class="flex-1 min-w-0">
                <textarea id="sm-text" rows="2" maxlength="1000" class="input !mt-0 resize-none" dir="auto" placeholder="<?= e(t('اكتب رسالتك… (Enter للإرسال، Shift+Enter لسطر جديد)')) ?>"></textarea>
                <div id="sm-count" class="text-[11px] text-ink-400 mt-1"></div>
              </div>
              <button class="btn-primary" id="sm-send"><?= e(t('ابعت')) ?></button>
            </form>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <script>window.SMX = <?= json_encode(['csrf' => csrf_token(), 'url' => url('sms_mine'), 'to' => $phone, 'msgs' => array_map(function ($m) { return ['id' => (int)$m['id'], 'direction' => $m['direction'], 'body' => $m['body'], 'state' => $m['state'], 'error' => $m['error'], 'created_at' => $m['created_at'], 'time' => fmt_date($m['created_at'], 'd/m H:i')]; }, $msgs), 's' => [
      'pending' => t('في الطابور'), 'processed' => t('وصل للموبايل'), 'sent' => t('اتبعتت'), 'delivered' => t('اتسلمت'), 'failed' => t('فشلت'), 'received' => '',
      'seg' => t('%s حرف · %s رسالة'), 'unicode' => t('عربي: 70 حرف للرسالة'), 'live' => t('بيتحدث تلقائي'), 'err' => t('ما اتبعتتش'),
    ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/ui-sms_mine.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
