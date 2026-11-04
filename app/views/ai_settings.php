<?php
/**
 * إعدادات الذكاء الاصطناعي (صفحة المساعد): الموديلات والمهام، الميزات، الاستهلاك، معلومات الشركة، العمل في الخلفية.
 * ربط المزودين نفسه في صفحة التكاملات.
 */

/** شعار الخدمة: مربع بلونها */
function connector_logo(array $def, string $size = 'w-12 h-12 text-lg'): string
{
    return '<div class="' . $size . ' shrink-0 rounded-2xl text-[#fff] font-semibold flex items-center justify-center shadow-sm" style="background:linear-gradient(135deg,' . e($def['color']) . ',' . e($def['color']) . 'cc)">' . e($def['logo']) . '</div>';
}

/** حفظ إعدادات الذكاء الاصطناعي (POST من صفحة المساعد) */
function ai_settings_post(string $action): void
{
    $pdo = db();
    $wid = (int)ws('id');
    $provider = 'openrouter';
    if (in_array($action, ['ai_reply_cfg', 'ai_call_consent'], true)) {
        if (!is_ws_owner()) { flash('error', t('هذا الإعداد متاح لمالك الشركة فقط.')); return; }
        if ($action === 'ai_call_consent') {
            save_setting('ai_call_consent_' . $wid, !empty($_POST['consent']) ? '1' : '0');
            flash('success', t('تم الحفظ.'));
            return;
        }
        $days = array_values(array_intersect(array_map('intval', (array)($_POST['days'] ?? [])), range(0, 6)));
        save_setting('ai_reply_cfg_' . $wid, json_encode(['mode' => ($_POST['mode'] ?? '') === 'send' ? 'send' : 'draft', 'from' => (string)($_POST['from'] ?? '09:00'), 'to' => (string)($_POST['to'] ?? '21:00'), 'days' => $days]));
        flash('success', t('تم حفظ إعدادات الرد التلقائي.'));
        return;
    }
    if ($action === 'ai_pick') {
        header('Content-Type: application/json; charset=utf-8');
        if (!is_ws_owner()) { http_response_code(403); exit(json_encode(['error' => t('هذا الإعداد متاح لمالك الشركة فقط.')], JSON_UNESCAPED_UNICODE)); }
        $key = (string)($_POST['key'] ?? '');
        $task = (string)($_POST['task'] ?? '');
        $on = ($_POST['on'] ?? '') === '1';
        if (!isset(ai_tasks()[$task]) || !preg_match('/^(openrouter|anthropic|openai|gemini)\|[\w.:\/@-]{1,160}$/', $key)) { http_response_code(400); exit('{}'); }
        $picks = ai_picks();
        $list = array_values(array_diff($picks[$key] ?? [], [$task]));
        if ($on) $list[] = $task;
        if ($list) $picks[$key] = $list; else unset($picks[$key]);
        save_setting('ai_picks_' . $wid, json_encode($picks));
        $count = [];
        foreach (array_keys(ai_tasks()) as $tk) $count[$tk] = count(array_filter($picks, function ($l) use ($tk) { return in_array($tk, $l, true); }));
        exit(json_encode(['ok' => true, 'count' => $count], JSON_UNESCAPED_UNICODE));
    }
    if ($action === 'ai_auto') {
        if (!is_ws_owner()) { flash('error', t('هذا الإعداد متاح لمالك الشركة فقط.')); redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models'])); }
        $what = in_array($_POST['what'] ?? '', ['classify', 'alerts', 'reply'], true) ? $_POST['what'] : 'classify';
        if ($what === 'reply' && !ai_auto_enabled($wid, 'reply') && ai_kb($wid) === '') { flash('error', t('اكتب معلومات الشركة أولًا. الرد التلقائي يرد منها فقط.')); redirect(url('assistant', ['tab' => 'usage'])); }
        $on = !ai_auto_enabled($wid, $what);
        save_setting('ai_auto_' . $what . '_' . $wid, $on ? '1' : '0');
        flash('success', $on ? t('تم التفعيل. يعمل مع التشغيل الدوري للمنصة.') : t('تم الإيقاف.'));
        redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models']));
    }
    if (in_array($action, ['ai_budget', 'ai_kb'], true)) {
        if (!is_ws_owner()) { flash('error', t('هذا الإعداد متاح لمالك الشركة فقط.')); redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models'])); }
        if ($action === 'ai_budget') {
            $b = max(0, min(100000, round((float)str_replace(',', '.', (string)($_POST['budget'] ?? '0')), 2)));
            save_setting('ai_budget_' . $wid, (string)$b);
            flash('success', $b > 0 ? t('حد الاستهلاك الشهري: $%s.', number_format($b, 2)) : t('تم إلغاء حد الاستهلاك.'));
        } else {
            save_setting('ai_kb_' . $wid, mb_substr(trim((string)($_POST['kb'] ?? '')), 0, 6000));
            flash('success', t('تم حفظ معلومات الشركة.'));
        }
        redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models']));
    }
    if (in_array($action, ['primary', 'ai_mode', 'ai_task'], true) && !is_ws_owner()) {
        flash('error', t('هذا الإعداد متاح لمالك الشركة فقط.'));
        redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models']));
    }
    if ($action === 'ai_mode') {
        $manual = ($_POST['mode'] ?? '') === 'manual';
        save_setting('ai_mode_' . $wid, $manual ? 'manual' : 'auto');
        flash('success', $manual ? t('تم إيقاف التشغيل التلقائي. كل مهمة ستستخدم الموديل الذي تختاره لها.') : t('تم تفعيل التشغيل التلقائي: كل المهام تبدأ بالمزود الأرخص، ثم الأغلى إذا فشل.'));
        redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models']));
    }
}

/** محتوى تبويب معين: models | usage */
function ai_settings_render(string $tab): void
{
    $registry = connector_registry();
    $all = [];
    foreach ($registry as $key => $def) $all[$key] = connections($key, null, true);
    $aiConns = ai_connections();
    $aiMode = ai_mode();
    $owner = is_ws_owner();
    ?>
        <?php $manual = $aiMode === 'manual'; $aiProviders = array_values(array_filter(ai_provider_keys(), function ($p) use ($all) { return !empty($all[$p]); })); ?>
        <?php if ($tab === 'models'): ?>
        <div class="card p-5 sm:p-6 flex flex-wrap items-center gap-5 <?= !$manual ? 'ring-2 ring-brand-500/20 border-brand-300' : '' ?>">
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center shrink-0"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg></div>
          <div class="flex-1 min-w-[240px]">
            <div class="flex items-center gap-2"><h3 class="font-semibold text-ink-900"><?= e(t('التشغيل التلقائي من خلال المنصة')) ?></h3><span class="badge <?= !$manual ? 'badge-green' : '' ?>"><?= !$manual ? e(t('مفعّل')) : e(t('متوقف')) ?></span></div>
            <p class="text-sm text-ink-500 mt-1"><?= !$manual ? e(t('المنصة تختار لكل مهمة أرخص مزود متصل، وإذا فشل تنتقل تلقائيًا للأغلى. لا تحتاج لأي إعداد.')) : e(t('كل مهمة تستخدم الموديل الذي تختاره لها في الجدول بالأسفل.')) ?></p>
            <?php if (!$manual && $aiConns): ?>
            <div class="flex items-center gap-1.5 flex-wrap mt-3 text-xs">
              <span class="text-ink-400"><?= e(t('الترتيب:')) ?></span>
              <?php foreach ($aiConns as $i => $c): ?>
                <?php if ($i): ?><svg class="w-3 h-3 text-ink-400 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg><?php endif; ?>
                <span class="px-2 py-1 rounded-lg <?= $i ? 'bg-ink-100 text-ink-600' : 'bg-brand-600 text-[#fff] font-medium' ?>"><?= e($registry[$c['provider']]['name']) ?> <span dir="ltr" class="opacity-70"><?= e($c['provider'] === 'openrouter' ? ($c['settings']['tier'] ?? 'cheap') : ($c['settings']['model'] ?? '')) ?> · <?= e(ai_fmt_cost(ai_connection_cost($c))) ?></span></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <form method="post" title="<?= $owner ? '' : e(t('هذا الإعداد متاح لمالك الشركة فقط.')) ?>">
            <?= csrf_field() ?><input type="hidden" name="provider" value="openrouter"><input type="hidden" name="back" value="<?= e($tab) ?>"><input type="hidden" name="action" value="ai_mode"><input type="hidden" name="mode" value="<?= $manual ? 'auto' : 'manual' ?>">
            <button type="submit" role="switch" aria-checked="<?= $manual ? 'false' : 'true' ?>" data-switch id="ai-auto" <?= $owner ? '' : 'disabled' ?> class="relative inline-flex w-14 h-8 rounded-full transition disabled:opacity-50 disabled:cursor-not-allowed <?= !$manual ? 'bg-lime-500' : 'bg-ink-300' ?>">
              <span class="absolute top-1 start-1 w-6 h-6 rounded-full bg-[#fff] shadow transition-transform <?= !$manual ? 'translate-x-6 rtl:-translate-x-6' : '' ?>"></span></button>
          </form>
        </div>

        <?php endif; ?>
        <?php if ($tab === 'usage'): ?>
        <?php
          $spend = ai_month_spend((int)ws('id')); $budget = ai_budget((int)ws('id'));
          $st = db()->prepare("SELECT task, COUNT(*) n, COALESCE(SUM(cost),0) c FROM ai_usage WHERE workspace_id = ? AND created_at >= ? GROUP BY task ORDER BY c DESC");
          $st->execute([(int)ws('id'), gmdate('Y-m-01 00:00:00')]); $byTask = $st->fetchAll();
          $st = db()->prepare("SELECT provider, model, COUNT(*) n, COALESCE(SUM(cost),0) c, SUM(cost IS NULL) unk FROM ai_usage WHERE workspace_id = ? AND created_at >= ? GROUP BY provider, model ORDER BY c DESC, n DESC LIMIT 8");
          $st->execute([(int)ws('id'), gmdate('Y-m-01 00:00:00')]); $byModel = $st->fetchAll();
          $calls = array_sum(array_column($byTask, 'n')); $pct = $budget > 0 ? min(100, $spend / $budget * 100) : 0;
          $money = function ($v) { return '$' . ($v > 0 && $v < 0.01 ? number_format($v, 4) : number_format($v, 2)); };
        ?>
        <div class="grid lg:grid-cols-[1fr_1.3fr] gap-4">
          <div class="card p-5">
            <div class="flex items-center justify-between"><h3 class="font-semibold text-ink-900"><?= e(t('الاستهلاك هذا الشهر')) ?></h3><span class="text-xs text-ink-400"><?= e(t('%s طلب', $calls)) ?></span></div>
            <div class="mt-3 flex items-end gap-2"><span class="text-3xl font-semibold text-ink-900" dir="ltr"><?= e($money($spend)) ?></span><?php if ($budget > 0): ?><span class="text-sm text-ink-500 mb-1" dir="ltr">/ <?= e($money($budget)) ?></span><?php endif; ?></div>
            <?php if ($budget > 0): ?>
              <div class="mt-3 h-2.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full <?= $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-amber-500' : 'bg-gradient-to-l from-brand-600 to-lime-500') ?>" style="width:<?= round($pct, 1) ?>%"></div></div>
              <?php if ($pct >= 100): ?><p class="text-xs text-red-600 mt-2"><?= e(t('تم الوصول للحد. ميزات الذكاء الاصطناعي متوقفة حتى بداية الشهر أو رفع الحد.')) ?></p><?php endif; ?>
            <?php endif; ?>
            <form method="post" class="mt-4 flex items-end gap-2">
              <?= csrf_field() ?><input type="hidden" name="provider" value="openrouter"><input type="hidden" name="back" value="<?= e($tab) ?>"><input type="hidden" name="action" value="ai_budget">
              <label class="block flex-1"><span class="label"><?= e(t('حد الاستهلاك الشهري ($)')) ?></span><input name="budget" type="number" min="0" step="0.01" dir="ltr" class="input" value="<?= $budget > 0 ? e((string)$budget) : '' ?>" placeholder="<?= e(t('بدون حد')) ?>" <?= $owner ? '' : 'disabled' ?>></label>
              <button class="btn-ghost" <?= $owner ? '' : 'disabled' ?>><?= e(t('حفظ')) ?></button>
            </form>
            <?php if ($byTask): ?>
            <div class="mt-4 pt-4 border-t border-ink-100 space-y-2">
              <?php foreach ($byTask as $r): $w = $spend > 0 ? (float)$r['c'] / $spend * 100 : 0; ?>
                <div class="text-xs"><div class="flex justify-between"><span class="text-ink-700"><?= e(ai_tasks()[$r['task']]['name'] ?? $r['task']) ?> <span class="text-ink-400">· <?= (int)$r['n'] ?></span></span><span class="text-ink-900 font-medium" dir="ltr"><?= e($money((float)$r['c'])) ?></span></div>
                  <div class="mt-1 h-1.5 rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500" style="width:<?= round($w, 1) ?>%"></div></div></div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($byModel): ?>
            <div class="mt-4 pt-4 border-t border-ink-100">
              <div class="text-xs text-ink-500 mb-2"><?= e(t('أكثر الموديلات استهلاكًا')) ?></div>
              <?php foreach ($byModel as $r): ?>
                <div class="flex items-center justify-between text-xs py-1"><span class="flex items-center gap-1.5 min-w-0"><?= isset($registry[$r['provider']]) ? connector_logo($registry[$r['provider']], 'w-4 h-4 text-[8px] !rounded') : '' ?><span class="truncate text-ink-700" dir="ltr"><?= e($r['model']) ?></span><span class="text-ink-400">· <?= (int)$r['n'] ?></span></span><span class="text-ink-900 shrink-0" dir="ltr"><?= e($money((float)$r['c'])) ?><?= (int)$r['unk'] ? ' *' : '' ?></span></div>
              <?php endforeach; ?>
              <?php if (array_sum(array_column($byModel, 'unk'))): ?><p class="text-[11px] text-ink-400 mt-1">* <?= e(t('بعض الطلبات سعرها غير معروف ولم تُحسب.')) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>

          <div class="space-y-4 flex flex-col">
          <div class="card p-5">
            <h3 class="font-semibold text-ink-900"><?= e(t('العمل في الخلفية')) ?></h3>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('يعمل تلقائيًا مع التشغيل الدوري للمنصة (cron)، ويحتسب من حد الاستهلاك.')) ?></p>
            <?php foreach (['classify' => [t('تصنيف الرسائل الجديدة'), t('نوع الرسالة، إحساس العميل، العاجل، ونية الشراء. طلب واحد لكل 20 رسالة.')], 'alerts' => [t('تنبيهات ذكية يومية'), t('مرة في اليوم: يكتشف أي هبوط أو ارتفاع غير طبيعي ويضيفه للتنبيهات.')], 'reply' => [t('الرد التلقائي خارج المواعيد'), t('يرد من معلومات الشركة فقط. مسودة للمراجعة، أو إرسال مباشر.')]] as $wk => [$wl, $wd]): $won = ai_auto_enabled((int)ws('id'), $wk); ?>
              <form method="post" class="flex items-start gap-3 mt-4">
                <?= csrf_field() ?><input type="hidden" name="provider" value="openrouter"><input type="hidden" name="back" value="<?= e($tab) ?>"><input type="hidden" name="action" value="ai_auto"><input type="hidden" name="what" value="<?= $wk ?>">
                <div class="flex-1"><div class="text-sm text-ink-900"><?= e($wl) ?></div><div class="text-xs text-ink-500"><?= e($wd) ?></div></div>
                <button type="submit" role="switch" aria-checked="<?= $won ? 'true' : 'false' ?>" data-switch <?= $owner ? '' : 'disabled' ?> class="relative inline-flex w-11 h-6 shrink-0 rounded-full transition disabled:opacity-50 <?= $won ? 'bg-lime-500' : 'bg-ink-300' ?>">
                  <span class="absolute top-0.5 start-0.5 w-5 h-5 rounded-full bg-[#fff] shadow transition-transform <?= $won ? 'translate-x-5 rtl:-translate-x-5' : '' ?>"></span></button>
              </form>
            <?php endforeach; ?>
            <?php $rc = ai_reply_cfg((int)ws('id')); $dn = [t('الأحد'), t('الإثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')]; ?>
            <details class="mt-4 rounded-xl border border-ink-200 p-3" <?= ai_auto_enabled((int)ws('id'), 'reply') ? 'open' : '' ?>>
              <summary class="text-sm link cursor-pointer"><?= e(t('إعدادات الرد التلقائي')) ?></summary>
              <form method="post" class="mt-3 space-y-3 text-sm">
                <?= csrf_field() ?><input type="hidden" name="action" value="ai_reply_cfg"><input type="hidden" name="back" value="usage">
                <div class="grid grid-cols-2 gap-2">
                  <label class="block"><span class="label"><?= e(t('بداية العمل')) ?></span><input type="time" name="from" value="<?= e($rc['from']) ?>" class="input" <?= $owner ? '' : 'disabled' ?>></label>
                  <label class="block"><span class="label"><?= e(t('نهاية العمل')) ?></span><input type="time" name="to" value="<?= e($rc['to']) ?>" class="input" <?= $owner ? '' : 'disabled' ?>></label>
                </div>
                <div><span class="label"><?= e(t('أيام العمل')) ?></span><div class="flex flex-wrap gap-1.5 mt-1"><?php foreach ($dn as $di => $dl): ?><label class="chip !py-1 text-xs cursor-pointer"><input type="checkbox" name="days[]" value="<?= $di ?>" class="w-3.5 h-3.5" <?= in_array($di, $rc['days'], true) ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>> <?= e($dl) ?></label><?php endforeach; ?></div></div>
                <div class="space-y-1.5">
                  <label class="flex items-start gap-2"><input type="radio" name="mode" value="draft" class="mt-1" <?= $rc['mode'] === 'draft' ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>><span><b class="text-ink-900"><?= e(t('مسودة للمراجعة (موصى به)')) ?></b><span class="block text-xs text-ink-500"><?= e(t('يجهّز الرد ويظهر في المحادثة، وأنت تضغط إرسال.')) ?></span></span></label>
                  <label class="flex items-start gap-2"><input type="radio" name="mode" value="send" class="mt-1" <?= $rc['mode'] === 'send' ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>><span><b class="text-ink-900"><?= e(t('إرسال مباشر')) ?></b><span class="block text-xs text-red-600"><?= e(t('يرسل للعميل بدون مراجعة. أي خطأ في معلومات الشركة سيصل للعميل.')) ?></span></span></label>
                </div>
                <button class="btn-ghost" <?= $owner ? '' : 'disabled' ?>><?= e(t('حفظ')) ?></button>
              </form>
            </details>
            <form method="post" class="mt-4 flex items-start gap-2 text-sm">
              <?= csrf_field() ?><input type="hidden" name="action" value="ai_call_consent"><input type="hidden" name="back" value="usage">
              <input type="checkbox" name="consent" value="1" class="mt-1" onchange="this.form.requestSubmit()" <?= setting('ai_call_consent_' . (int)ws('id'), '0') === '1' ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>>
              <span><b class="text-ink-900"><?= e(t('تقييم جودة المكالمات')) ?></b><span class="block text-xs text-ink-500"><?= e(t('أؤكد أن الموظفين يعلمون ووافقوا على تحليل مكالماتهم وتقييمها.')) ?></span></span>
            </form>
          </div>
          <form method="post" class="card p-5 flex flex-col">
            <?= csrf_field() ?><input type="hidden" name="provider" value="openrouter"><input type="hidden" name="back" value="<?= e($tab) ?>"><input type="hidden" name="action" value="ai_kb">
            <h3 class="font-semibold text-ink-900"><?= e(t('معلومات الشركة')) ?></h3>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('الأسعار والمواعيد والعنوان والسياسات والعروض. الذكاء الاصطناعي يرد منها فقط ولا يخترع غيرها. كلما كانت أدق، كانت الردود أصح.')) ?></p>
            <textarea name="kb" rows="9" maxlength="6000" class="input flex-1 text-sm leading-relaxed" <?= $owner ? '' : 'disabled' ?> placeholder="<?= e(t("مثال:\nمواعيد العمل: يوميًا 10 ص - 11 م ما عدا الجمعة\nالتوصيل: داخل القاهرة 50 جنيه، خلال 48 ساعة\nالاسترجاع: خلال 14 يوم بالفاتورة\nالأسعار: ...")) ?>"><?= e(ai_kb((int)ws('id'))) ?></textarea>
            <div class="flex items-center justify-between mt-3"><span class="text-[11px] text-ink-400"><?= e(t('حتى 6000 حرف')) ?></span><button class="btn-primary" <?= $owner ? '' : 'disabled' ?>><?= e(t('حفظ')) ?></button></div>
          </form>
          </div>
        </div>

        <?php endif; ?>
        <?php if ($tab === 'models'): ?>
        <?php if (!$aiProviders): ?>
          <div class="card p-8 text-center">
            <p class="text-ink-900 font-medium"><?= e(t('لا يوجد مزود ذكاء اصطناعي متصل')) ?></p>
            <p class="text-sm text-ink-500 mt-1 mb-4"><?= e(t('اربط مزودًا واحدًا على الأقل لتشغيل المهام. OpenRouter و Gemini فيهما استخدام مجاني.')) ?></p>
            <div class="flex flex-wrap justify-center gap-2"><?php foreach (ai_provider_keys() as $p): ?><button type="button" data-href-open="<?= $p ?>" class="chip"><?= e($registry[$p]['name']) ?></button><?php endforeach; ?></div>
          </div>
        <?php endif; ?>

        <?php $picks = ai_picks(); $catalog = ai_catalog(); $canPick = $manual && $owner; ?>
        <!-- ملخص المهام -->
        <div class="grid md:grid-cols-3 gap-4">
          <?php foreach (ai_tasks() as $tk => $task): $chain = ai_task_chain($tk); $now = $chain[0] ?? null; $n = count(array_filter($picks, function ($l) use ($tk) { return in_array($tk, $l, true); })); $noPick = $manual && !$n; ?>
          <div class="card p-4">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-700 flex items-center justify-center shrink-0"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" viewBox="0 0 24 24"><path d="<?= $task['icon'] ?>"/></svg></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2"><b class="text-ink-900"><?= e($task['name']) ?></b><?php if ($manual): ?><span class="badge" data-count="<?= $tk ?>"><?= e(t('%s موديل', $n)) ?></span><?php endif; ?></div>
                <div class="text-xs text-ink-500 mt-0.5"><?= e($task['desc']) ?></div>
              </div>
            </div>
            <div class="mt-3 pt-3 border-t border-ink-100 text-xs">
              <?php if ($now): ?>
                <div class="flex items-center gap-2"><span class="text-ink-400"><?= e(t('يعمل الآن بـ')) ?></span><?= connector_logo($registry[$now['provider']], 'w-5 h-5 text-[9px] !rounded-md') ?><span class="text-ink-900 truncate" dir="ltr"><?= e($now['pick']['model'] ?? ($now['provider'] === 'openrouter' ? 'OpenRouter · ' . ($now['settings']['tier'] ?? 'cheap') : ($now['settings']['model'] ?? ''))) ?></span></div>
                <div class="mt-1.5 flex items-center gap-2"><span class="inline-flex rounded-md bg-lime-100 text-lime-700 px-1.5 py-0.5 font-medium" dir="ltr">≈ <?= e(ai_fmt_cost(ai_connection_cost($now))) ?></span><?php if (count($chain) > 1): ?><span class="text-ink-400">+<?= count($chain) - 1 ?> <?= e(t('بديل')) ?></span><?php endif; ?></div>
                <?php if ($noPick): ?><div class="text-amber-700 mt-1.5"><?= e(t('لم تختر موديلات لهذه المهمة، فيُستخدم التلقائي.')) ?></div><?php endif; ?>
              <?php else: ?><span class="text-ink-400"><?= e(t('لا يوجد مزود')) ?></span><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- جدول كل الموديلات -->
        <?php if ($catalog): $provCount = array_count_values(array_column($catalog, 'provider')); ?>
        <div class="card overflow-hidden" id="mdl">
          <div class="p-4 border-b border-ink-200 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div><h3 class="font-semibold text-ink-900"><?= e(t('كل الموديلات المتاحة')) ?> <span class="text-ink-400 font-normal text-sm" id="mdl-n"><?= count($catalog) ?></span></h3>
                <p class="text-xs text-ink-500 mt-0.5"><?= $canPick ? e(t('علّم على المهام التي يمكن لكل موديل أن يعمل عليها. المنصة تبدأ بالأرخص من بين الموديلات المختارة لكل مهمة.')) : ($manual ? e(t('هذا الإعداد متاح لمالك الشركة فقط.')) : e(t('أوقف التشغيل التلقائي لتختار الموديلات بنفسك.'))) ?></p></div>
              <span id="mdl-saved" class="text-xs text-lime-700 opacity-0 transition">✓ <?= e(t('تم الحفظ')) ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative flex-1 min-w-[200px] max-w-sm"><svg class="w-4 h-4 absolute start-3 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg><input id="mdl-q" type="search" class="input !mt-0 !py-2 ps-9 text-sm" dir="ltr" placeholder="claude, gpt, gemini, llama..."></div>
              <div class="flex gap-1.5 overflow-x-auto" id="mdl-prov">
                <button type="button" class="chip on !py-1 text-xs" data-p=""><?= e(t('الكل')) ?></button>
                <?php foreach ($provCount as $pk => $pn): ?><button type="button" class="chip !py-1 text-xs" data-p="<?= e($pk) ?>"><?= e($registry[$pk]['name']) ?> <span class="text-ink-400"><?= $pn ?></span></button><?php endforeach; ?>
              </div>
              <label class="chip !py-1 text-xs cursor-pointer"><input type="checkbox" id="mdl-free" class="w-3.5 h-3.5"> <?= e(t('المجاني فقط')) ?></label>
              <label class="chip !py-1 text-xs cursor-pointer"><input type="checkbox" id="mdl-mine" class="w-3.5 h-3.5"> <?= e(t('المختار فقط')) ?></label>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-ink-50 text-xs text-ink-500 sticky top-0">
                <tr>
                  <th class="text-start font-medium px-4 py-2.5"><?= e(t('الموديل')) ?></th>
                  <th class="text-start font-medium px-3 py-2.5"><?= e(t('عن طريق')) ?></th>
                  <th class="text-end font-medium px-3 py-2.5 whitespace-nowrap"><?= e(t('المدخلات / مليون')) ?></th>
                  <th class="text-end font-medium px-3 py-2.5 whitespace-nowrap"><?= e(t('المخرجات / مليون')) ?></th>
                  <th class="text-end font-medium px-3 py-2.5 whitespace-nowrap"><?= e(t('السؤال الواحد')) ?></th>
                  <?php foreach (ai_tasks() as $tk => $task): ?><th class="text-center font-medium px-3 py-2.5 whitespace-nowrap"><?= e($task['name']) ?></th><?php endforeach; ?>
                </tr>
              </thead>
              <tbody id="mdl-body">
                <?php foreach ($catalog as $row): $mine = $picks[$row['key']] ?? []; $fmt = function ($v) { return $v === null ? '—' : '$' . rtrim(rtrim(number_format($v, 3), '0'), '.'); }; ?>
                <tr class="mdl-row border-t border-ink-100 hover:bg-ink-50/70 transition <?= $mine ? 'bg-brand-50/40' : '' ?>" data-p="<?= e($row['provider']) ?>" data-free="<?= $row['free'] ? 1 : 0 ?>" data-mine="<?= $mine ? 1 : 0 ?>" data-q="<?= e(strtolower($row['model'] . ' ' . $row['name'])) ?>">
                  <td class="px-4 py-2.5"><div class="font-medium text-ink-900" dir="ltr" style="text-align:start"><?= e($row['name']) ?></div><?php if ($row['name'] !== $row['model']): ?><div class="text-[11px] text-ink-400" dir="ltr" style="text-align:start"><?= e($row['model']) ?></div><?php endif; ?></td>
                  <td class="px-3 py-2.5"><div class="flex items-center gap-1.5 whitespace-nowrap"><?= connector_logo($registry[$row['provider']], 'w-5 h-5 text-[9px] !rounded-md') ?><span class="text-xs text-ink-600"><?= e($registry[$row['provider']]['name']) ?></span></div></td>
                  <td class="px-3 py-2.5 text-end text-ink-600" dir="ltr"><?= $fmt($row['in']) ?></td>
                  <td class="px-3 py-2.5 text-end text-ink-600" dir="ltr"><?= $fmt($row['out']) ?></td>
                  <td class="px-3 py-2.5 text-end whitespace-nowrap"><span class="inline-flex rounded-md px-1.5 py-0.5 text-xs font-medium <?= $row['free'] ? 'bg-lime-100 text-lime-700' : 'bg-ink-100 text-ink-700' ?>" dir="ltr"><?= e(ai_fmt_cost($row['cost'])) ?></span></td>
                  <?php foreach (ai_tasks() as $tk => $task): ?>
                    <td class="px-3 py-2.5 text-center"><input type="checkbox" class="mdl-pick w-4 h-4 rounded cursor-pointer disabled:cursor-not-allowed disabled:opacity-40" data-key="<?= e($row['key']) ?>" data-task="<?= $tk ?>" <?= in_array($tk, $mine, true) ? 'checked' : '' ?> <?= $canPick ? '' : 'disabled' ?> aria-label="<?= e($task['name'] . ' · ' . $row['model']) ?>"></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="p-3 border-t border-ink-200 flex items-center justify-between text-xs text-ink-500">
            <span id="mdl-shown"></span>
            <button type="button" id="mdl-more" class="btn-ghost !py-1.5 !px-3 !text-xs hidden"><?= e(t('عرض المزيد')) ?></button>
          </div>
        </div>
        <?php endif; ?>
        <p class="text-xs text-ink-400"><?= e(t('الأسعار الرسمية لكل مزود، من كتالوج OpenRouter العام، وتتحدث كل 12 ساعة. OpenRouter محسوب مجانيًا لأنه يبدأ بالموديلات المجانية. الحصة المجانية في Gemini غير محسوبة.')) ?></p>
        <?php endif; ?>
    <script>
    (function () {
      document.addEventListener('click', function (e) { var b = e.target.closest('[data-href-open]'); if (b) location.href = 'index.php?p=connectors#' + b.dataset.hrefOpen; });
      (function () {
        var body = document.getElementById('mdl-body'); if (!body) return;
        var rows = [].slice.call(body.querySelectorAll('.mdl-row')), q = document.getElementById('mdl-q'), prov = '', limit = 50;
        var free = document.getElementById('mdl-free'), mine = document.getElementById('mdl-mine'), more = document.getElementById('mdl-more');
        function apply(reset) {
          if (reset) limit = 50;
          var term = q.value.trim().toLowerCase(), n = 0, shown = 0;
          rows.forEach(function (r) {
            var ok = (!prov || r.dataset.p === prov) && (!free.checked || r.dataset.free === '1') && (!mine.checked || r.dataset.mine === '1') && (!term || r.dataset.q.indexOf(term) > -1);
            if (ok) n++;
            var vis = ok && n <= limit; r.classList.toggle('hidden', !vis); if (vis) shown++;
          });
          document.getElementById('mdl-n').textContent = n;
          document.getElementById('mdl-shown').textContent = shown + ' / ' + n;
          more.classList.toggle('hidden', n <= limit);
        }
        q.addEventListener('input', function () { apply(true); });
        free.addEventListener('change', function () { apply(true); }); mine.addEventListener('change', function () { apply(true); });
        more.addEventListener('click', function () { limit += 100; apply(); });
        document.querySelectorAll('#mdl-prov [data-p]').forEach(function (b) { b.addEventListener('click', function () {
          prov = b.dataset.p; document.querySelectorAll('#mdl-prov .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); apply(true); }); });
        var saved = document.getElementById('mdl-saved'), tmr;
        body.addEventListener('change', function (e) {
          var c = e.target.closest('.mdl-pick'); if (!c) return;
          var fd = new FormData(); fd.append('action', 'ai_pick'); fd.append('key', c.dataset.key); fd.append('task', c.dataset.task); fd.append('on', c.checked ? '1' : '0');
          var tr = c.closest('tr');
          fetch('index.php?p=assistant', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
              if (!d.ok) throw 0;
              var any = tr.querySelector('.mdl-pick:checked'); tr.dataset.mine = any ? '1' : '0'; tr.classList.toggle('bg-brand-50/40', !!any);
              Object.keys(d.count).forEach(function (k) { var el = document.querySelector('[data-count=' + k + ']'); if (el) el.textContent = el.textContent.replace(/\d+/, d.count[k]); });
              saved.classList.remove('opacity-0'); clearTimeout(tmr); tmr = setTimeout(function () { saved.classList.add('opacity-0'); }, 1500);
            })
            .catch(function () { c.checked = !c.checked; alert((window.T && T.liveFailed) || 'Error'); });
        });
        apply(true);
      })();
    })();
    </script>
    <?php
}

/** حفظ تفعيل/إيقاف ميزة (JSON) */
function ai_feature_post(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (!is_ws_owner()) { http_response_code(403); exit(json_encode(['error' => t('هذا الإعداد متاح لمالك الشركة فقط.')], JSON_UNESCAPED_UNICODE)); }
    $valid = array_merge(array_keys(ai_presets()), array_keys(ai_insights()), array_keys(ai_special_features()));
    $keys = array_values(array_intersect((array)($_POST['keys'] ?? []), $valid));
    $on = ($_POST['on'] ?? '') === '1';
    $wid = (int)ws('id');
    $off = ai_disabled($wid);
    $off = $on ? array_values(array_diff($off, $keys)) : array_values(array_unique(array_merge($off, $keys)));
    save_setting('ai_off_' . $wid, json_encode($off));
    exit(json_encode(['ok' => true, 'off' => count($off)]));
}

/** تبويب الميزات: كل ميزة ليها مفتاح تشغيل */
function ai_features_render(): void
{
    $owner = is_ws_owner();
    $off = ai_disabled();
    $groups = [];
    $tasks = ai_tasks();
    foreach (ai_special_features() as $k => [$l, $d]) $groups[t('ميزات أساسية')][$k] = [$l, $d];
    foreach (ai_presets() as $k => $p) $groups[t('أوامر على خانات النص') . ' · ' . ($tasks[$p['group']]['name'] ?? $p['group'])][$k] = [$p['label'], ''];
    foreach (ai_insights() as $k => $i) $groups[t('تحليلات من بياناتك') . ' · ' . ($tasks[$i['group']]['name'] ?? $i['group'])][$k] = [$i['label'], ''];
    $total = 0; foreach ($groups as $g) $total += count($g);
    ?>
    <div class="card p-5 mb-4 flex flex-wrap items-center gap-4">
      <div class="flex-1 min-w-[240px]">
        <h3 class="font-semibold text-ink-900"><?= e(t('تفعيل وإيقاف الميزات')) ?></h3>
        <p class="text-sm text-ink-500 mt-0.5"><?= e(t('أوقف أي ميزة لا تحتاجها: تختفي من المنصة كلها ولا تستهلك أي رصيد.')) ?></p>
      </div>
      <div class="text-sm"><b class="text-ink-900" id="ft-on"><?= $total - count(array_intersect($off, array_merge(...array_map('array_keys', array_values($groups))))) ?></b> <span class="text-ink-500">/ <?= $total ?> <?= e(t('مفعّلة')) ?></span></div>
      <input id="ft-q" type="search" class="input !mt-0 !w-64" placeholder="<?= e(t('ابحث عن ميزة...')) ?>">
      <?php if (!$owner): ?><p class="w-full text-xs text-amber-700"><?= e(t('هذا الإعداد متاح لمالك الشركة فقط.')) ?></p><?php endif; ?>
    </div>
    <div class="grid lg:grid-cols-2 gap-4" id="ft">
      <?php foreach ($groups as $gname => $items): $gOn = count(array_diff(array_keys($items), $off)); ?>
      <div class="card overflow-hidden ft-group">
        <div class="flex items-center justify-between gap-2 px-4 py-3 bg-ink-50 border-b border-ink-200">
          <b class="text-sm text-ink-900"><?= e($gname) ?> <span class="text-ink-400 font-normal ft-count"><?= $gOn ?>/<?= count($items) ?></span></b>
          <?php if ($owner): ?><span class="flex gap-1 text-xs"><button type="button" class="ft-all link" data-on="1"><?= e(t('تفعيل الكل')) ?></button><span class="text-ink-300">|</span><button type="button" class="ft-all link !text-ink-500" data-on="0"><?= e(t('إيقاف الكل')) ?></button></span><?php endif; ?>
        </div>
        <?php foreach ($items as $k => [$l, $d]): $on = !in_array($k, $off, true); ?>
          <label class="ft-item flex items-center gap-3 px-4 py-2.5 border-b border-ink-100 last:border-0 hover:bg-ink-50/60 <?= $owner ? 'cursor-pointer' : '' ?>" data-q="<?= e(mb_strtolower($l . ' ' . $d)) ?>">
            <span class="flex-1 min-w-0"><span class="block text-sm text-ink-800"><?= e($l) ?></span><?php if ($d): ?><span class="block text-xs text-ink-500"><?= e($d) ?></span><?php endif; ?></span>
            <input type="checkbox" class="ft-box sr-only peer" data-k="<?= e($k) ?>" <?= $on ? 'checked' : '' ?> <?= $owner ? '' : 'disabled' ?>>
            <span class="relative inline-flex w-10 h-6 shrink-0 rounded-full bg-ink-300 peer-checked:bg-lime-500 transition peer-disabled:opacity-50 after:absolute after:top-0.5 after:start-0.5 after:w-5 after:h-5 after:rounded-full after:bg-[#fff] after:shadow after:transition-transform peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <script>
    (function () {
      var root = document.getElementById('ft'), csrf = document.querySelector('meta[name=csrf-token]').content;
      function count() {
        var on = 0; root.querySelectorAll('.ft-group').forEach(function (g) { var b = g.querySelectorAll('.ft-box'), c = g.querySelectorAll('.ft-box:checked').length; on += c; g.querySelector('.ft-count').textContent = c + '/' + b.length; });
        document.getElementById('ft-on').textContent = on;
      }
      function save(keys, on, boxes) {
        var fd = new FormData(); fd.append('action', 'ai_feature'); fd.append('on', on ? '1' : '0'); keys.forEach(function (k) { fd.append('keys[]', k); });
        fetch('index.php?p=assistant', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } }).then(function (r) { return r.json(); })
          .then(function (d) { if (!d.ok) throw 0; count(); })
          .catch(function () { boxes.forEach(function (b) { b.checked = !on; }); count(); alert((window.T && T.liveFailed) || 'Error'); });
      }
      root.addEventListener('change', function (e) { var b = e.target.closest('.ft-box'); if (b) save([b.dataset.k], b.checked, [b]); });
      root.addEventListener('click', function (e) {
        var a = e.target.closest('.ft-all'); if (!a) return; var on = a.dataset.on === '1';
        var boxes = [].slice.call(a.closest('.ft-group').querySelectorAll('.ft-box')).filter(function (b) { return b.checked !== on; });
        boxes.forEach(function (b) { b.checked = on; }); if (boxes.length) save(boxes.map(function (b) { return b.dataset.k; }), on, boxes);
      });
      document.getElementById('ft-q').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        root.querySelectorAll('.ft-item').forEach(function (i) { i.classList.toggle('hidden', !!q && i.dataset.q.indexOf(q) < 0); });
        root.querySelectorAll('.ft-group').forEach(function (g) { g.classList.toggle('hidden', !g.querySelector('.ft-item:not(.hidden)')); });
      });
    })();
    </script>
    <?php
}
