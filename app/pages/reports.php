<?php
/** التقارير: إنشاء تقرير للطباعة، وإعدادات التقرير المجدول بالإيميل */
$wid = (int)ws('id');
$canManage = can('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) {
        flash('error', t('مالكش صلاحية تعدل الإعدادات دي.'));
        redirect(url('reports'));
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_schedule') {
        [$emails, $bad] = rp_parse_emails((string)($_POST['recipients'] ?? ''));
        $freq = in_array($_POST['frequency'] ?? '', ['off', 'weekly', 'monthly'], true) ? $_POST['frequency'] : 'off';
        $lang = ($_POST['lang'] ?? '') === 'ar' ? 'ar' : 'en';
        if ($bad) {
            flash('error', t('الإيميلات دي مش صحيحة: %s', implode(', ', array_slice($bad, 0, 5))));
            redirect(url('reports'));
        }
        if ($freq !== 'off' && !$emails) {
            flash('error', t('اكتب إيميل واحد على الأقل.'));
            redirect(url('reports'));
        }
        rp_save_schedule($wid, implode(', ', $emails), $freq, $lang);
        log_activity('reports.schedule', $freq . ' ' . implode(',', $emails));
        flash('success', t('اتحفظ.'));
    } elseif ($action === 'send_test') {
        $s = rp_schedule($wid);
        [$emails] = rp_parse_emails((string)$s['recipients']);
        if (!$emails) $emails = [(string)(current_user()['email'] ?? '')];
        $kind = $s['frequency'] === 'monthly' ? 'last_month' : 'last_week';
        [$ok, $fail] = rp_send_report($wid, $emails, $kind, (string)$s['lang']);
        rp_mark_sent($wid, gmdate('Y-m-d H:i') . ' test ok=' . $ok . ' fail=' . $fail, false);
        log_activity('reports.test_email', implode(',', $emails));
        if ($fail === 0) {
            flash('success', t('السيرفر قبل الإيميل لـ %s. لو ما وصلش، راجع الـ Spam أو إعدادات الإيميل في الاستضافة.', implode(', ', $emails)));
        } else {
            flash('error', t('ما اتبعتش %s إيميل: %s', $fail, (string)($GLOBALS['rp_last_mail_error'] ?? '')));
        }
    }
    redirect(url('reports'));
}

$accErr = null;
$accounts = rp_clients($wid) ? rp_accounts($wid, $accErr) : [];
$sched = rp_schedule($wid);
$token = $canManage ? rp_cron_token() : '';

render(t('التقارير'), 'reports', function () use ($wid, $accounts, $accErr, $sched, $token, $canManage) {
    $tz = rp_tz($wid);
    $w = rp_period('last_week', $tz);
    $m = rp_period('last_month', $tz);
    $dmy = function ($d) { return date('d/m/Y', strtotime($d)); };
    ?>
    <div class="grid lg:grid-cols-5 gap-5">
      <form method="get" action="index.php" target="_blank" class="card p-6 space-y-5 lg:col-span-3" id="rp-form">
        <input type="hidden" name="p" value="report_print">
        <div>
          <h2 class="font-bold text-ink-900"><?= e(t('تقرير جاهز للطباعة')) ?></h2>
          <p class="text-sm text-ink-500 mt-1"><?= e(t('اختار الفترة والحساب، والتقرير هيفتح في صفحة جديدة تقدر تطبعها أو تحفظها PDF.')) ?></p>
        </div>
        <fieldset class="space-y-2">
          <legend class="label"><?= e(t('الفترة')) ?></legend>
          <?php foreach ([['last_week', t('الأسبوع اللي فات'), $w], ['last_month', t('الشهر اللي فات'), $m]] as $i => [$k, $l, $p]): ?>
            <label class="flex items-center gap-3 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:bg-ink-50">
              <input type="radio" name="period" value="<?= e($k) ?>" <?= $i === 0 ? 'checked' : '' ?> class="w-4 h-4 accent-brand-600">
              <span class="text-sm text-ink-800"><?= e($l) ?></span>
              <span class="text-xs text-ink-500 ms-auto"><bdi dir="ltr"><?= e($dmy($p['from']) . ' – ' . $dmy($p['to'])) ?></bdi></span>
            </label>
          <?php endforeach; ?>
          <label class="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:bg-ink-50">
            <input type="radio" name="period" value="custom" class="w-4 h-4 accent-brand-600" id="rp-custom">
            <span class="text-sm text-ink-800"><?= e(t('فترة مخصصة')) ?></span>
            <span class="flex items-center gap-2 ms-auto">
              <input type="date" name="from" class="input py-1.5 w-auto" value="<?= e($w['from']) ?>" max="<?= e(gmdate('Y-m-d')) ?>" onfocus="document.getElementById('rp-custom').checked=true" aria-label="<?= e(t('من')) ?>">
              <span class="text-ink-400">–</span>
              <input type="date" name="to" class="input py-1.5 w-auto" value="<?= e($w['to']) ?>" max="<?= e(gmdate('Y-m-d')) ?>" onfocus="document.getElementById('rp-custom').checked=true" aria-label="<?= e(t('إلى')) ?>">
            </span>
          </label>
          <div class="flex flex-wrap items-center gap-1.5 pt-1" id="rp-quick">
            <span class="text-xs text-ink-500"><?= e(t('فترات سريعة:')) ?></span>
            <?php foreach (['d7' => t('آخر 7 أيام'), 'd30' => t('آخر 30 يوم'), 'mtd' => t('الشهر ده لحد النهارده'), 'q' => t('الربع اللي فات'), 'ytd' => t('السنة دي')] as $k => $l): ?>
              <button type="button" data-q="<?= $k ?>" class="rounded-full border border-ink-200 px-3 py-1 text-xs hover:border-brand-400 hover:bg-brand-50"><?= e($l) ?></button>
            <?php endforeach; ?>
            <span id="rp-days" class="text-xs text-ink-500 ms-auto"></span>
          </div>
        </fieldset>
        <label class="block"><span class="label"><?= e(t('الحساب')) ?></span>
          <select name="a" class="input">
            <option value=""><?= e(t('كل الحسابات')) ?></option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?= e((string)($a['_id'] ?? '')) ?>"><?= e(rp_acc_name($a) . ' · ' . platform_label((string)($a['platform'] ?? ''))) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($accErr): ?><span class="text-xs text-red-600"><?= e($accErr) ?></span><?php endif; ?>
        </label>
        <?php if (!rp_clients($wid)): ?>
          <?php no_api_box(); ?>
        <?php else: ?>
          <div class="flex flex-wrap items-center gap-2">
            <button class="btn-primary"><?= e(t('إنشاء التقرير')) ?></button>
            <button type="button" id="rp-save" class="btn-ghost"><?= e(t('احفظ الإعداد ده')) ?></button>
            <span class="text-xs text-ink-500"><?= e(t('بيفتح في تبويب جديد.')) ?></span>
          </div>
          <div id="rp-saved" class="flex flex-wrap gap-1.5"></div>
        <?php endif; ?>
      </form>

      <div class="lg:col-span-2 space-y-5">
        <div class="card p-6">
          <h2 class="font-bold text-ink-900"><?= e(t('التقرير بالإيميل')) ?></h2>
          <?php if (!$canManage): ?>
            <p class="text-sm text-ink-500 mt-2"><?= e(t('صاحب الشركة بس اللي يقدر يظبط التقرير المجدول.')) ?></p>
            <?php if ($sched['frequency'] !== 'off'): ?>
              <p class="text-sm text-ink-700 mt-2"><?= e($sched['frequency'] === 'weekly' ? t('بيتبعت كل اتنين.') : t('بيتبعت أول كل شهر.')) ?></p>
            <?php endif; ?>
          <?php else: ?>
            <form method="post" class="space-y-4 mt-4">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save_schedule">
              <label class="block"><span class="label"><?= e(t('المستلمين')) ?></span>
                <input name="recipients" class="input" dir="ltr" value="<?= e((string)$sched['recipients']) ?>" placeholder="name@company.com, boss@company.com">
                <span class="text-xs text-ink-400"><?= e(t('افصل بين الإيميلات بفاصلة.')) ?></span></label>
              <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="label"><?= e(t('كل قد إيه')) ?></span>
                  <select name="frequency" class="input">
                    <?php foreach (['off' => t('متوقف'), 'weekly' => t('أسبوعي (كل اتنين)'), 'monthly' => t('شهري (يوم 1)')] as $k => $l): ?>
                      <option value="<?= e($k) ?>" <?= $sched['frequency'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <label class="block"><span class="label"><?= e(t('لغة التقرير')) ?></span>
                  <select name="lang" class="input">
                    <option value="en" <?= $sched['lang'] === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="ar" <?= $sched['lang'] === 'ar' ? 'selected' : '' ?>>العربية</option>
                  </select></label>
              </div>
              <?php
              $nx = null; $nowL = new DateTime('now', new DateTimeZone($tz));
              if ($sched['frequency'] === 'weekly') $nx = (clone $nowL)->modify('next monday');
              elseif ($sched['frequency'] === 'monthly') $nx = (clone $nowL)->modify('first day of next month');
              ?>
              <?php if ($nx): ?><p class="text-xs rounded-xl bg-brand-50 text-brand-800 px-3 py-2"><?= e(t('الإرسال الجاي: %s (لو الـ Cron شغال).', $nx->format('d/m/Y'))) ?></p><?php endif; ?>
              <div id="rp-rc" class="flex flex-wrap gap-1"></div>
              <button class="btn-primary"><?= e(t('حفظ')) ?></button>
            </form>
            <form method="post" class="mt-3" onsubmit="return confirm('<?= tjs('نبعت تقرير تجريبي دلوقتي؟') ?>')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="send_test">
              <button class="btn-ghost w-full"><?= e(t('ابعت تجربة دلوقتي')) ?></button>
            </form>
            <p class="text-xs text-ink-500 mt-3">
              <?= e(t('آخر إرسال: %s', $sched['last_sent'] ? rp_fmt($sched['last_sent'] . 'Z', $tz, 'd/m/Y h:i A') : t('لسه'))) ?>
              <?php if ($sched['last_result']): ?><br><span dir="ltr" class="text-ink-400"><?= e((string)$sched['last_result']) ?></span><?php endif; ?>
            </p>
          <?php endif; ?>
        </div>

        <?php if ($canManage): $cmd = 'curl -s "' . base_url() . '/index.php?p=cron&token=' . $token . '"'; ?>
          <div class="card p-6 space-y-3">
            <?php $cl = (string)setting('cron_last_run', ''); $age = $cl !== '' ? time() - strtotime($cl . ' UTC') : null; ?>
            <div class="flex items-center gap-2"><h2 class="font-bold text-ink-900 me-auto"><?= e(t('تشغيل تلقائي (Cron)')) ?></h2>
              <?php if ($age === null): ?><span class="rounded-full bg-red-50 text-red-700 text-[11px] px-2.5 py-0.5"><?= e(t('ما اشتغلش ولا مرة')) ?></span>
              <?php elseif ($age < 7200): ?><span class="rounded-full bg-lime-50 text-lime-700 text-[11px] px-2.5 py-0.5"><?= e(t('شغال · آخر تشغيل من %s دقيقة', max(1, (int)round($age / 60)))) ?></span>
              <?php else: ?><span class="rounded-full bg-amber-50 text-amber-700 text-[11px] px-2.5 py-0.5"><?= e(t('متوقف؟ آخر تشغيل من %s ساعة', (int)round($age / 3600))) ?></span><?php endif; ?></div>
            <p class="text-sm text-ink-600"><?= e(t('عشان التقارير تتبعت والتنبيهات تتحدث لوحدها، ضيف الأمر ده في Cron Jobs في لوحة الاستضافة يشتغل كل ساعة:')) ?></p>
            <div class="flex items-stretch gap-2">
              <code id="rp-cron" class="flex-1 min-w-0 block rounded-xl bg-ink-100 text-ink-800 text-xs p-3 break-all" dir="ltr"><?= e($cmd) ?></code>
              <button type="button" class="btn-ghost shrink-0" onclick="navigator.clipboard.writeText(document.getElementById('rp-cron').textContent).then(()=>{this.textContent='<?= tjs('اتنسخ') ?>'})"><?= e(t('نسخ')) ?></button>
            </div>
            <p class="text-xs text-ink-500"><?= e(t('الرابط ده سري. أي حد معاه يقدر يشغل الإرسال، فما تشاركهوش.')) ?></p>
            <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-3 text-xs">
              <?php $smc = smtp_config(); if ($smc): ?>
                <?= e($smc['scope'] === 'workspace' ? t('الإيميلات بتتبعت من SMTP الخاص بالشركة (%s).', $smc['host']) : t('الإيميلات بتتبعت عن طريق SMTP (%s).', $smc['host'])) ?>
                <?php if ($smc['scope'] !== 'workspace' && can('integrations.manage')): ?><a class="underline" href="<?= e(url('connectors')) ?>"><?= e(t('استخدم إيميل خاص بالشركة')) ?></a><?php endif; ?>
              <?php else: ?>
                <?= e(t('الإيميلات بتتبعت من الاستضافة مباشرة، وممكن توصل الـ Spam أو ما توصلش. الأضمن تظبط SMTP.')) ?>
                <?php if (is_admin()): ?><a class="underline" href="<?= e(url('system')) ?>#smtp"><?= e(t('اظبط SMTP')) ?></a><?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <script>
    (function () {
      var f = document.getElementById('rp-form'); if (!f) return;
      var from = f.querySelector('[name=from]'), to = f.querySelector('[name=to]'), cus = document.getElementById('rp-custom'), days = document.getElementById('rp-days');
      var T = <?= json_encode(['days' => t('%s يوم'), 'name' => t('اسم للإعداد ده؟'), 'del' => t('امسح'), 'bad' => t('إيميل مش صحيح')], JSON_UNESCAPED_UNICODE) ?>;
      var iso = function (d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); };
      function count() {
        var r = f.querySelector('[name=period]:checked'), a, b;
        if (r && r.value === 'custom') { a = new Date(from.value); b = new Date(to.value); }
        else { var sp = r && r.closest('label').querySelector('bdi'); if (!sp) { days.textContent = ''; return; } var m = sp.textContent.match(/(\d\d)\/(\d\d)\/(\d{4}).*?(\d\d)\/(\d\d)\/(\d{4})/); if (!m) return; a = new Date(m[3], m[2] - 1, m[1]); b = new Date(m[6], m[5] - 1, m[4]); }
        var n = Math.round((b - a) / 864e5) + 1; days.textContent = n > 0 ? T.days.replace('%s', n) : '';
      }
      document.getElementById('rp-quick').addEventListener('click', function (e) {
        var b = e.target.closest('[data-q]'); if (!b) return;
        var t = new Date(), y = new Date(t); y.setDate(t.getDate() - 1); var s, en = y;
        switch (b.dataset.q) {
          case 'd7': s = new Date(y); s.setDate(y.getDate() - 6); break;
          case 'd30': s = new Date(y); s.setDate(y.getDate() - 29); break;
          case 'mtd': s = new Date(t.getFullYear(), t.getMonth(), 1); en = t; break;
          case 'q': var q = Math.floor(t.getMonth() / 3) - 1, yr = t.getFullYear(); if (q < 0) { q = 3; yr--; } s = new Date(yr, q * 3, 1); en = new Date(yr, q * 3 + 3, 0); break;
          case 'ytd': s = new Date(t.getFullYear(), 0, 1); en = t; break;
        }
        from.value = iso(s); to.value = iso(en); cus.checked = true; count();
        f.querySelectorAll('#rp-quick [data-q]').forEach(function (x) { x.classList.toggle('bg-brand-600', x === b); x.classList.toggle('text-white', x === b); });
      });
      f.addEventListener('change', count); count();
      // إعدادات محفوظة (على الجهاز ده)
      var KEY = 'rp_saved_<?= (int)$wid ?>', box = document.getElementById('rp-saved');
      function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
      function paint() {
        if (!box) return;
        box.innerHTML = load().map(function (x, i) { return '<span class="inline-flex items-center rounded-full bg-ink-50 text-xs"><button type="button" data-i="' + i + '" class="ps-3 py-1 hover:text-brand-700">★ ' + x.n.replace(/[<>&"]/g, '') + '</button><button type="button" data-d="' + i + '" class="px-2 text-ink-400 hover:text-red-600" title="' + T.del + '">×</button></span>'; }).join('');
      }
      if (box) box.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return; var L = load();
        if (b.dataset.d != null) { L.splice(+b.dataset.d, 1); try { localStorage.setItem(KEY, JSON.stringify(L)); } catch (e) {} return paint(); }
        var x = L[+b.dataset.i]; if (!x) return;
        var r = f.querySelector('[name=period][value="' + x.p + '"]'); if (r) r.checked = true;
        if (x.p === 'custom') { from.value = x.f; to.value = x.t; }
        f.querySelector('[name=a]').value = x.a; count();
      });
      var sv = document.getElementById('rp-save');
      if (sv) sv.onclick = function () {
        var n = prompt(T.name); if (!n) return;
        var L = load(); L.unshift({ n: n.slice(0, 30), p: f.querySelector('[name=period]:checked').value, f: from.value, t: to.value, a: f.querySelector('[name=a]').value });
        try { localStorage.setItem(KEY, JSON.stringify(L.slice(0, 8))); } catch (e) {} paint();
      };
      paint();
      // المستلمين: عرض كل إيميل كـ chip وتعليم الغلط
      var rc = document.querySelector('[name=recipients]'), rb = document.getElementById('rp-rc');
      if (rc && rb) {
        var pr = function () { rb.innerHTML = rc.value.split(/[,;\s]+/).filter(Boolean).map(function (m) { var ok = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(m); return '<span class="rounded-full px-2.5 py-0.5 text-[11px] ' + (ok ? 'bg-ink-100 text-ink-700' : 'bg-red-50 text-red-700') + '" dir="ltr" title="' + (ok ? '' : T.bad) + '">' + m.replace(/[<>&"]/g, '') + '</span>'; }).join(''); };
        rc.addEventListener('input', pr); pr();
      }
    })();
    </script>
    <?php
});
