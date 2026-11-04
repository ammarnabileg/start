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
    <div data-ai-insights="monthly" class="mb-6"></div>
    <div class="grid lg:grid-cols-5 gap-5">
      <form method="get" action="index.php" target="_blank" class="card p-6 space-y-5 lg:col-span-3">
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
            <span class="text-xs text-ink-500"><?= e(t('بيفتح في تبويب جديد.')) ?></span>
          </div>
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
            <h2 class="font-bold text-ink-900"><?= e(t('تشغيل تلقائي (Cron)')) ?></h2>
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
    <?php
});
