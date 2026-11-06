<?php
/** إعدادات العملاء: المراحل (#66)، الـ Pipelines (#65)، الحقول المخصصة (#64)، التاجات الأوتوماتيك (#62)، وقواعد الخطط (#78 #79 #80) */
$wid = (int)ws('id');
$mgr = can('crm.manage') || is_ws_owner();
$back = function (string $h) { redirect(url('crm_settings') . '#' . $h); };
$slug = function (string $s, string $pre) { $k = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($s)), '_'); return $k !== '' ? substr($k, 0, 18) : $pre . substr(bin2hex(random_bytes(3)), 0, 5); };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$mgr) { flash('error', t('مالكش صلاحية.')); redirect(url('crm_settings')); }
    $a = (string)($_POST['action'] ?? ''); $pal = crm_stage_palette();
    if ($a === 'stages') {
        $c = crm_stage_cfg(); $c['labels'] = []; $c['colors'] = [];
        // الاسم بيتحفظ بس لو اتغير فعلًا، عشان الاسم الأصلي يفضل يتترجم مع لغة الواجهة
        $defs = ['new' => t('جديد'), 'contacted' => t('اتكلمنا معاه'), 'qualified' => t('مهتم'), 'proposal' => t('عرض سعر'), 'won' => t('بقى عميل'), 'lost' => t('مش مناسب')];
        foreach ((array)($_POST['label'] ?? []) as $k => $v) { $v = mb_substr(trim((string)$v), 0, 40); if ($v !== '' && ($defs[$k] ?? null) !== $v) $c['labels'][(string)$k] = $v; }
        foreach ((array)($_POST['color'] ?? []) as $k => $v) if (isset($pal[$v])) $c['colors'][(string)$k] = $v;
        $cust = [];
        foreach ((array)$c['custom'] as $x) {
            if (!empty($_POST['del'][$x['key']])) {
                $st = db()->prepare('SELECT COUNT(*) FROM crm_leads WHERE workspace_id = ? AND stage = ?'); $st->execute([$wid, $x['key']]);
                if ((int)$st->fetchColumn()) { flash('error', t('المرحلة "%s" فيها عملاء. انقلهم لمرحلة تانية الأول.', $x['label'])); $back('stages'); }
                continue;
            }
            $x['label'] = $c['labels'][$x['key']] ?? $x['label']; $x['color'] = $c['colors'][$x['key']] ?? $x['color']; unset($c['labels'][$x['key']]); $cust[] = $x;
        }
        $nl = mb_substr(trim((string)($_POST['new_label'] ?? '')), 0, 40);
        if ($nl !== '' && count($cust) < 4) $cust[] = ['key' => 'c' . substr(bin2hex(random_bytes(3)), 0, 5), 'label' => $nl, 'color' => isset($pal[$_POST['new_color'] ?? '']) ? $_POST['new_color'] : 'teal'];
        $c['custom'] = $cust;
        save_setting('crm_stages_' . $wid, json_encode($c, JSON_UNESCAPED_UNICODE)); log_activity('crm.stages', (string)count($cust));
        flash('success', t('اتحفظ ✓')); $back('stages');
    }
    if ($a === 'pipes') {
        $o = [];
        foreach ((array)($_POST['pl'] ?? []) as $k => $v) { $v = mb_substr(trim((string)$v), 0, 40); if ($v !== '' && empty($_POST['pdel'][$k])) $o[] = ['key' => (string)$k, 'label' => $v]; elseif ($k === 'sales') $o[] = ['key' => 'sales', 'label' => t('المبيعات')]; }
        foreach ((array)($_POST['pdel'] ?? []) as $k => $_) if ($k !== 'sales') db()->prepare("UPDATE crm_leads SET pipeline = 'sales' WHERE workspace_id = ? AND pipeline = ?")->execute([$wid, (string)$k]);
        $n = mb_substr(trim((string)($_POST['new_pipe'] ?? '')), 0, 40);
        if ($n !== '' && count($o) < 8) $o[] = ['key' => $slug('', 'p'), 'label' => $n];
        save_setting('crm_pipes_' . $wid, json_encode($o, JSON_UNESCAPED_UNICODE)); flash('success', t('اتحفظ ✓')); $back('pipes');
    }
    if ($a === 'fields') {
        $o = [];
        foreach (crm_custom_fields() as $f) {
            if (!empty($_POST['fdel'][$f['key']])) continue;
            $f['label'] = mb_substr(trim((string)($_POST['flabel'][$f['key']] ?? $f['label'])), 0, 40) ?: $f['label'];
            if ($f['type'] === 'select') $f['opts'] = array_values(array_filter(array_map('trim', preg_split('/[,،]/u', (string)($_POST['fopts'][$f['key']] ?? implode(',', $f['opts'])))), 'strlen'));
            $o[] = $f;
        }
        $nl = mb_substr(trim((string)($_POST['new_label'] ?? '')), 0, 40); $ty = in_array($_POST['new_type'] ?? '', ['text', 'number', 'select'], true) ? $_POST['new_type'] : 'text';
        if ($nl !== '' && count($o) < 12) $o[] = ['key' => 'f' . substr(bin2hex(random_bytes(3)), 0, 5), 'label' => $nl, 'type' => $ty, 'opts' => $ty === 'select' ? array_values(array_filter(array_map('trim', preg_split('/[,،]/u', (string)($_POST['new_opts'] ?? ''))), 'strlen')) : []];
        save_setting('crm_fields_' . $wid, json_encode($o, JSON_UNESCAPED_UNICODE)); flash('success', t('اتحفظ ✓')); $back('fields');
    }
    if ($a === 'tagrules' || $a === 'tagrun') {
        if ($a === 'tagrules') {
            $o = []; $fs = (array)($_POST['field'] ?? []); $hs = (array)($_POST['has'] ?? []); $ts = (array)($_POST['tag'] ?? []);
            foreach ($hs as $i => $h) { $h = mb_substr(trim((string)$h), 0, 60); $tg = mb_substr(trim((string)($ts[$i] ?? '')), 0, 30); if ($h !== '' && $tg !== '') $o[] = ['field' => in_array($fs[$i] ?? '', ['any', 'name', 'company', 'title', 'country', 'note', 'email', 'phone', 'custom'], true) ? $fs[$i] : 'any', 'has' => $h, 'tag' => $tg]; }
            save_setting('crm_tagrules_' . $wid, json_encode(array_slice($o, 0, 30), JSON_UNESCAPED_UNICODE));
        }
        $n = 0; if ($a === 'tagrun' || !empty($_POST['apply'])) { $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? LIMIT 5000'); $st->execute([$wid]); foreach ($st->fetchAll() as $l) if (crm_auto_tag($l)) $n++; }
        flash('success', $n ? t('اتحطت تاجات على %s عميل ✓', $n) : t('اتحفظ ✓')); $back('tags');
    }
    if ($a === 'mailtpl') {
        save_setting('crm_mail_tpl_' . $wid, json_encode(['on' => !empty($_POST['on']) ? 1 : 0, 'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['color'] ?? '')) ? $_POST['color'] : '#4f46e5',
            'logo' => preg_match('~^https://~i', trim((string)($_POST['logo'] ?? ''))) ? mb_substr(trim((string)$_POST['logo']), 0, 500) : '', 'sig' => mb_substr(trim((string)($_POST['sig'] ?? '')), 0, 500), 'footer' => mb_substr(trim((string)($_POST['footer'] ?? '')), 0, 300)], JSON_UNESCAPED_UNICODE));
        flash('success', t('اتحفظ ✓')); $back('mail');
    }
    if ($a === 'plans') {
        $days = array_values(array_intersect(array_map('intval', (array)($_POST['days'] ?? [])), range(0, 6)));
        $tm = function ($v, $d) { return preg_match('/^\d{2}:\d{2}$/', (string)$v) ? $v : $d; };
        save_setting('crm_plan_rules_' . $wid, json_encode(['hours' => !empty($_POST['hours']) ? 1 : 0, 'days' => $days, 'from' => $tm($_POST['from'] ?? '', '09:00'), 'to' => $tm($_POST['to'] ?? '', '18:00'),
            'stop_reply' => !empty($_POST['stop_reply']) ? 1 : 0, 'na_n' => max(0, min(10, (int)($_POST['na_n'] ?? 0))), 'na_plan' => (int)($_POST['na_plan'] ?? 0)]));
        flash('success', t('اتحفظ ✓')); $back('plans');
    }
}

$sc = crm_stage_cfg(); $stages = crm_stages(); $pal = crm_stage_palette(); $colors = crm_stage_colors();
$st = db()->prepare('SELECT stage, COUNT(*) n FROM crm_leads WHERE workspace_id = ? GROUP BY stage'); $st->execute([$wid]); $cnt = array_column($st->fetchAll(), 'n', 'stage');
$st = db()->prepare('SELECT pipeline, COUNT(*) n FROM crm_leads WHERE workspace_id = ? GROUP BY pipeline'); $st->execute([$wid]); $pcnt = array_column($st->fetchAll(), 'n', 'pipeline');
$custKeys = array_column((array)$sc['custom'], 'key');
$pr = crm_plan_rules();

render(t('إعدادات العملاء'), 'crm_settings', function () use ($mgr, $sc, $stages, $pal, $colors, $cnt, $pcnt, $custKeys, $pr) {
    $dis = $mgr ? '' : 'disabled'; $sec = 'card p-5 scroll-mt-24';
    $palSel = function (string $name, string $cur) use ($pal, $dis) { $h = '<select name="' . e($name) . '" class="input !mt-0 !py-1 !w-28 text-xs" ' . $dis . '>'; foreach ($pal as $k => $cls) $h .= '<option value="' . e($k) . '"' . ($k === $cur ? ' selected' : '') . '>' . e($k) . '</option>'; return $h . '</select>'; };
    $curColor = function (string $k) use ($colors, $pal) { $c = $colors[$k] ?? ''; foreach ($pal as $n => $cls) if ($cls === $c) return $n; return 'teal'; };
    ?>
  <?php if (!$mgr): ?><div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800"><?= e(t('الإعدادات دي بيعدّلها مدير العملاء بس.')) ?></div><?php endif; ?>
  <div class="mb-4 flex flex-wrap gap-1.5 text-xs"><?php foreach (['stages' => '🎚 ' . t('المراحل'), 'pipes' => '🛤 Pipelines', 'fields' => '🧩 ' . t('حقول مخصصة'), 'tags' => '🏷 ' . t('تاجات أوتوماتيك'), 'mail' => '✉️ ' . t('قالب الإيميل'), 'plans' => '🔁 ' . t('قواعد خطط المتابعة')] as $k => $v): ?><a href="#<?= $k ?>" class="chip"><?= e($v) ?></a><?php endforeach; ?></div>
  <div class="grid xl:grid-cols-2 gap-4">
    <section id="stages" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1">🎚 <?= e(t('المراحل')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('غيّر الأسماء والألوان، وضيف لحد 4 مراحل زيادة (بتتحط قبل "بقى عميل"). "جديد" و"بقى عميل" و"مش مناسب" أساسيين في التقارير فمابيتمسحوش.')) ?></p>
      <form method="post" class="space-y-1.5"><?= csrf_field() ?><input type="hidden" name="action" value="stages">
        <?php foreach ($stages as $k => $v): $isC = in_array($k, $custKeys, true); ?>
          <div class="flex items-center gap-2 text-sm"><span class="w-24 shrink-0 text-center text-[11px] px-2 py-0.5 rounded-full <?= e($colors[$k]) ?>"><?= e($v) ?></span>
            <input name="label[<?= e($k) ?>]" value="<?= e($v) ?>" maxlength="40" class="input !mt-0 !py-1 text-sm flex-1" <?= $dis ?>><?= $palSel('color[' . $k . ']', $curColor($k)) ?>
            <span class="w-10 text-[11px] text-ink-400 text-center"><?= (int)($cnt[$k] ?? 0) ?></span>
            <?php if ($isC): ?><label class="text-[11px] text-red-600 flex items-center gap-1"><input type="checkbox" name="del[<?= e($k) ?>]" value="1" <?= $dis ?>>✕</label><?php else: ?><span class="w-6"></span><?php endif; ?></div>
        <?php endforeach; ?>
        <?php if (count($custKeys) < 4): ?><div class="flex items-center gap-2 pt-2 border-t border-ink-100"><input name="new_label" maxlength="40" class="input !mt-0 !py-1 text-sm flex-1" placeholder="<?= e(t('+ مرحلة جديدة (مثلًا: تفاوض)')) ?>" <?= $dis ?>><?= $palSel('new_color', 'teal') ?></div><?php endif; ?>
        <?php if ($mgr): ?><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><?php endif; ?></form>
      <p class="text-[11px] text-ink-400 mt-2"><?= e(t('احتمال الإغلاق لكل مرحلة وتوقع الإيراد في نافذة الإعدادات في صفحة العملاء، والنتيجة في تقارير المبيعات.')) ?></p></section>

    <section id="pipes" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1">🛤 <?= e(t('أكتر من Pipeline')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('افصل المبيعات عن التوظيف أو الشراكات. كل Pipeline ليه قايمته ولوحته في صفحة العملاء، والعميل تقدر تنقله من تفاصيله.')) ?></p>
      <form method="post" class="space-y-1.5"><?= csrf_field() ?><input type="hidden" name="action" value="pipes">
        <?php foreach (crm_pipelines() as $k => $v): ?><div class="flex items-center gap-2 text-sm"><input name="pl[<?= e($k) ?>]" value="<?= e($v) ?>" maxlength="40" class="input !mt-0 !py-1 text-sm flex-1" <?= $dis ?>><span class="text-[11px] text-ink-400 w-16 text-center"><?= e(t('%s عميل', (int)($pcnt[$k] ?? 0))) ?></span>
          <?php if ($k !== 'sales'): ?><label class="text-[11px] text-red-600 flex items-center gap-1" title="<?= e(t('عملاءه هيرجعوا للمبيعات')) ?>"><input type="checkbox" name="pdel[<?= e($k) ?>]" value="1" <?= $dis ?>>✕</label><?php else: ?><span class="w-6"></span><?php endif; ?></div><?php endforeach; ?>
        <input name="new_pipe" maxlength="40" class="input !mt-0 !py-1 text-sm" placeholder="<?= e(t('+ Pipeline جديد (مثلًا: توظيف)')) ?>" <?= $dis ?>>
        <?php if ($mgr): ?><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><?php endif; ?></form></section>

    <section id="fields" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1">🧩 <?= e(t('حقول مخصصة')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('خانات زيادة بتظهر في تفاصيل كل عميل، زي الميزانية أو المنتج المطلوب أو المدينة. التاجات الأوتوماتيك تقدر تقراها.')) ?></p>
      <form method="post" class="space-y-1.5"><?= csrf_field() ?><input type="hidden" name="action" value="fields">
        <?php foreach (crm_custom_fields() as $f): ?><div class="flex flex-wrap items-center gap-2 text-sm"><input name="flabel[<?= e($f['key']) ?>]" value="<?= e($f['label']) ?>" class="input !mt-0 !py-1 text-sm flex-1 min-w-[120px]" <?= $dis ?>><span class="text-[11px] rounded bg-ink-100 px-1.5"><?= e(['text' => t('نص'), 'number' => t('رقم'), 'select' => t('اختيارات')][$f['type']]) ?></span>
          <?php if ($f['type'] === 'select'): ?><input name="fopts[<?= e($f['key']) ?>]" value="<?= e(implode('، ', $f['opts'])) ?>" class="input !mt-0 !py-1 text-xs flex-1 min-w-[140px]" <?= $dis ?>><?php endif; ?>
          <label class="text-[11px] text-red-600 flex items-center gap-1"><input type="checkbox" name="fdel[<?= e($f['key']) ?>]" value="1" <?= $dis ?>>✕</label></div><?php endforeach; ?>
        <div class="flex flex-wrap gap-2 pt-2 border-t border-ink-100"><input name="new_label" maxlength="40" class="input !mt-0 !py-1 text-sm flex-1 min-w-[120px]" placeholder="<?= e(t('+ حقل جديد (مثلًا: المدينة)')) ?>" <?= $dis ?>>
          <select name="new_type" class="input !mt-0 !py-1 !w-28 text-xs" <?= $dis ?>><option value="text"><?= e(t('نص')) ?></option><option value="number"><?= e(t('رقم')) ?></option><option value="select"><?= e(t('اختيارات')) ?></option></select>
          <input name="new_opts" class="input !mt-0 !py-1 text-xs w-full" placeholder="<?= e(t('للاختيارات: الرياض، جدة، الدمام')) ?>" <?= $dis ?>></div>
        <?php if ($mgr): ?><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><?php endif; ?></form></section>

    <section id="tags" class="<?= $sec ?>"><h2 class="font-semibold text-ink-900 mb-1">🏷 <?= e(t('تاجات أوتوماتيك')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('لو الخانة فيها الكلمة دي، العميل ياخد التاج ده لوحده أول ما يتضاف. مثلًا: الشركة فيها "مطعم" ← مطاعم، أو الرقم يبدأ بـ 966 ← السعودية.')) ?></p>
      <?php $fl = ['any' => t('أي خانة'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'name' => t('الاسم'), 'country' => t('الدولة'), 'phone' => t('الرقم يبدأ بـ'), 'email' => t('الإيميل'), 'note' => t('الملاحظات'), 'custom' => t('الحقول المخصصة')]; $rules = crm_tag_rules(); $rules[] = ['field' => 'any', 'has' => '', 'tag' => '']; $rules[] = ['field' => 'any', 'has' => '', 'tag' => '']; ?>
      <form method="post" class="space-y-1.5"><?= csrf_field() ?><input type="hidden" name="action" value="tagrules">
        <?php foreach ($rules as $r): ?><div class="grid grid-cols-[110px_1fr_1fr] gap-1.5 text-sm"><select name="field[]" class="input !mt-0 !py-1 text-xs" <?= $dis ?>><?php foreach ($fl as $k => $v): ?><option value="<?= $k ?>" <?= $r['field'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
          <input name="has[]" value="<?= e($r['has']) ?>" class="input !mt-0 !py-1 text-sm" dir="auto" placeholder="<?= e(t('فيها…')) ?>" <?= $dis ?>><input name="tag[]" value="<?= e($r['tag']) ?>" class="input !mt-0 !py-1 text-sm" dir="auto" placeholder="<?= e(t('← التاج')) ?>" <?= $dis ?>></div><?php endforeach; ?>
        <?php if ($mgr): ?><div class="flex flex-wrap items-center gap-2"><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><label class="flex items-center gap-1 text-xs"><input type="checkbox" name="apply" value="1"> <?= e(t('وطبّقها على العملاء الموجودين')) ?></label></div><?php endif; ?></form></section>

    <?php $mt = crm_mail_tpl(); ?>
    <section id="mail" class="<?= $sec ?> xl:col-span-2"><h2 class="font-semibold text-ink-900 mb-1">✉️ <?= e(t('قالب الإيميل')) ?></h2>
      <p class="text-xs text-ink-500 mb-3"><?= e(t('شكل موحد لكل إيميلات العملاء (من التفاصيل، والخطط، والإيميل الجماعي): لوجو ولون وتوقيع. كل إيميل فيه رابط إلغاء اشتراك، واللي يلغي مابيوصلوش حاجة تاني.')) ?></p>
      <div class="grid lg:grid-cols-2 gap-4"><form method="post" class="space-y-2 text-sm" id="mt-form"><?= csrf_field() ?><input type="hidden" name="action" value="mailtpl">
        <label class="flex items-center gap-2"><input type="checkbox" name="on" value="1" <?= $mt['on'] ? 'checked' : '' ?> <?= $dis ?>> <b><?= e(t('استخدم القالب')) ?></b></label>
        <div class="grid grid-cols-[1fr_90px] gap-2"><label class="text-xs text-ink-600"><?= e(t('رابط اللوجو (https)')) ?><input name="logo" value="<?= e($mt['logo']) ?>" class="input text-sm" dir="ltr" <?= $dis ?>></label><label class="text-xs text-ink-600"><?= e(t('اللون')) ?><input type="color" name="color" value="<?= e($mt['color']) ?>" class="input !h-10 !p-1" <?= $dis ?>></label></div>
        <label class="block text-xs text-ink-600"><?= e(t('التوقيع')) ?><textarea name="sig" rows="3" class="input text-sm" dir="auto" <?= $dis ?>><?= e($mt['sig']) ?></textarea></label>
        <label class="block text-xs text-ink-600"><?= e(t('سطر تحت (العنوان، الرقم الضريبي…)')) ?><input name="footer" value="<?= e($mt['footer']) ?>" class="input text-sm" dir="auto" <?= $dis ?>></label>
        <?php if ($mgr): ?><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button><?php endif; ?></form>
        <div><div class="text-xs text-ink-500 mb-1"><?= e(t('معاينة')) ?></div><div class="rounded-xl border border-ink-200 overflow-hidden"><?= crm_mail_wrap(nl2br(e(t("أهلًا أحمد،
ده مثال لشكل الإيميل اللي هيوصل لعملائك."))), '#') ?></div></div></div></section>

    <section id="plans" class="<?= $sec ?> xl:col-span-2"><h2 class="font-semibold text-ink-900 mb-1">🔁 <?= e(t('قواعد خطط المتابعة')) ?></h2>
      <form method="post" class="grid md:grid-cols-3 gap-4 text-sm"><?= csrf_field() ?><input type="hidden" name="action" value="plans">
        <div class="space-y-2"><label class="flex items-center gap-2"><input type="checkbox" name="hours" value="1" <?= $pr['hours'] ? 'checked' : '' ?> <?= $dis ?>> <b><?= e(t('ساعات العمل بس')) ?></b></label>
          <p class="text-[11px] text-ink-500"><?= e(t('الإيميلات والمهام مابتطلعش بالليل ولا يوم الإجازة. بتستنى لأول ساعة شغل.')) ?></p>
          <div class="flex flex-wrap gap-1"><?php foreach ([t('أحد'), t('اتنين'), t('تلات'), t('أربع'), t('خميس'), t('جمعة'), t('سبت')] as $d => $n): ?><label class="cursor-pointer"><input type="checkbox" class="peer sr-only" name="days[]" value="<?= $d ?>" <?= in_array($d, $pr['days'], true) ? 'checked' : '' ?> <?= $dis ?>><span class="inline-block px-2 py-0.5 rounded-lg border border-ink-200 text-[11px] peer-checked:bg-brand-600 peer-checked:text-white"><?= e($n) ?></span></label><?php endforeach; ?></div>
          <div class="flex items-center gap-2 text-xs"><input type="time" name="from" value="<?= e($pr['from']) ?>" class="input !mt-0 !py-1 text-xs" <?= $dis ?>> → <input type="time" name="to" value="<?= e($pr['to']) ?>" class="input !mt-0 !py-1 text-xs" <?= $dis ?>></div></div>
        <div class="space-y-2"><label class="flex items-center gap-2"><input type="checkbox" name="stop_reply" value="1" <?= $pr['stop_reply'] ? 'checked' : '' ?> <?= $dis ?>> <b><?= e(t('وقّف الخطة لو العميل رد')) ?></b></label>
          <p class="text-[11px] text-ink-500"><?= e(t('لما رسالة أو تعليق جديد من العميل يتسجّل عليه (من زرار "ضيفه Lead" أو الجمع الأوتوماتيك) بعد ما الخطة بدأت، الخطة بتقف ويتعمل له مهمة "رد عليه". ردود الإيميل مش بتتقري.')) ?></p></div>
        <div class="space-y-2"><b class="block"><?= e(t('"لم يرد" كذا مرة ← خطة تانية')) ?></b>
          <p class="text-[11px] text-ink-500"><?= e(t('لما تسجّل نتيجة مكالمة "مردّش" العدد ده، العميل بيتنقل لخطة تانية لوحده.')) ?></p>
          <div class="flex items-center gap-2 text-xs"><input type="number" min="0" max="10" name="na_n" value="<?= (int)$pr['na_n'] ?>" class="input !mt-0 !py-1 !w-16 text-xs" <?= $dis ?>> <?= e(t('مرات ← ')) ?>
            <select name="na_plan" class="input !mt-0 !py-1 text-xs flex-1" <?= $dis ?>><option value="0"><?= e(t('من غير')) ?></option><?php foreach (crm_plans() as $p): ?><option value="<?= (int)$p['id'] ?>" <?= (int)$pr['na_plan'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div></div>
        <?php if ($mgr): ?><div class="md:col-span-3"><button class="btn-primary !py-1.5 !text-xs"><?= e(t('حفظ')) ?></button></div><?php endif; ?>
      </form></section>
  </div>
<?php });
