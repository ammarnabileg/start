<?php
/** التكاملات: ربط الشركة بالخدمات الخارجية. الخدمة الواحدة ممكن يبقى ليها أكتر من اتصال. (لصاحب الشركة) */
$pdo = db();
$wid = (int)ws('id');
$registry = connector_registry();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = (string)($_POST['provider'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    $cid = (int)($_POST['cid'] ?? 0);
    if (!isset($registry[$provider])) {
        redirect(url('connectors'));
    }
    $def = $registry[$provider];
    if (!empty($def['owner_only']) && !is_ws_owner()) {
        flash('error', t('تعديل الاتصال ده لصاحب الشركة بس.'));
        redirect(url('connectors'));
    }
    $existing = null;
    foreach (connections($provider, null, true) as $c) {
        if ((int)$c['id'] === $cid) $existing = $c;
    }

    if ($action === 'save') {
        $label = trim((string)($_POST['label'] ?? ''));
        $creds = [];
        foreach ($def['fields'] as $f) {
            $v = trim((string)($_POST['f'][$f['key']] ?? ''));
            if ($v === '' && $existing) {
                $v = (string)($existing['credentials'][$f['key']] ?? ''); // خانة سرية فاضية = سيب القديمة
            }
            if (($f['type'] ?? '') === 'select' && !isset($f['options'][$v])) $v = (string)($f['default'] ?? array_key_first($f['options']));
            if ($v === '' && empty($f['optional'])) {
                flash('error', t('اكتب %s.', $f['label']));
                redirect(url('connectors'));
            }
            $creds[$f['key']] = $v;
        }
        foreach (connections($provider, null, true) as $c) {
            if ((int)$c['id'] !== $cid && $c['credentials'] == $creds) {
                flash('error', t('المفتاح ده متضاف قبل كده باسم "%s".', $c['label']));
                redirect(url('connectors'));
            }
        }
        $models = null;
        try {
            if (!empty($def['ai_models'])) {
                $models = ai_test_key($provider, $creds['api_key']);
                $info = t('%s موديل متاح', count($models));
            } else {
                $info = (string)$def['test']($creds);
            }
        } catch (Throwable $ex) {
            flash('error', t('%s رفض المفاتيح، وما اتحفظش حاجة: %s', $def['name'], $ex->getMessage()));
            redirect(url('connectors'));
        }
        $same = $existing && $existing['credentials'] == $creds;
        $settings = $same ? $existing['settings'] : [];
        if ($models !== null) {
            $settings['models'] = $models;
            if (!in_array($settings['model'] ?? '', $models, true)) $settings['model'] = ai_default_model($models);
            if (!ai_connections() || !array_filter(ai_provider_keys(), function ($p) { foreach (connections($p, null, true) as $c) if (!empty($c['settings']['primary'])) return true; return false; })) $settings['primary'] = true;
        }
        save_connector($pdo, $wid, $provider, $creds, $settings, $info, $label !== '' ? mb_substr($label, 0, 120) : ($existing ? null : $def['name']), $existing ? (int)$existing['id'] : null);
        log_activity('connector.save', $provider . ' ' . $label);
        flash('success', $existing ? t('اتحفظ الاتصال.') : t('اتضاف اتصال جديد بنجاح.'));
    }

    if ($action === 'tier' && $existing && $provider === 'openrouter') {
        $settings = $existing['settings'];
        $settings['tier'] = in_array($_POST['tier'] ?? '', ['free', 'cheap', 'any'], true) ? $_POST['tier'] : 'cheap';
        $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($settings), $existing['id']]);
        flash('success', t('اتحفظ.'));
    }

    if ($action === 'model' && $existing && !empty($def['ai_models'])) {
        $settings = $existing['settings'];
        $m = (string)($_POST['model'] ?? '');
        if (in_array($m, $settings['models'] ?? [], true)) {
            $settings['model'] = $m;
            $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($settings), $existing['id']]);
            flash('success', t('سيتم استخدام %s.', $m));
        }
    }

    if ($action === 'retest' && $existing) {
        try {
            $settings = $existing['settings'];
            if (!empty($def['ai_models'])) {
                $settings['models'] = ai_test_key($provider, $existing['credentials']['api_key']);
                if (!in_array($settings['model'] ?? '', $settings['models'], true)) $settings['model'] = ai_default_model($settings['models']);
                $info = t('%s موديل متاح', count($settings['models']));
            } else {
                $info = (string)$def['test']($existing['credentials']);
            }
            $pdo->prepare("UPDATE connectors SET settings = ?, info = ?, checked_at = NOW() WHERE id = ?")->execute([json_encode($settings), $info, $existing['id']]);
            flash('success', t('الاتصال يعمل: %s', $info));
        } catch (Throwable $ex) {
            flash('error', t('الاختبار فشل: %s', $ex->getMessage()));
        }
    }




    if ($action === 'toggle' && $existing) {
        $settings = $existing['settings'];
        $settings['disabled'] = empty($settings['disabled']);
        $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($settings), $existing['id']]);
        log_activity('connector.toggle', $provider . ' ' . ($settings['disabled'] ? 'off' : 'on'));
        flash('success', $settings['disabled'] ? t('تم إيقاف "%s". لن يُستخدم في أي مكان حتى تعيد تفعيله.', $existing['label']) : t('تم تفعيل "%s".', $existing['label']));
    }

    if ($action === 'primary' && $existing && ($def['category'] ?? '') === 'ai') {
        foreach (ai_provider_keys() as $p) {
            foreach (connections($p, null, true) as $c) {
                $st = $c['settings'];
                $st['primary'] = (int)$c['id'] === (int)$existing['id'];
                $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($st), $c['id']]);
            }
        }
        flash('success', t('%s أصبح المزود المختار للمساعد.', $def['name']));
    }

    if ($action === 'profile' && $existing) {
        $settings = $existing['settings'];
        $settings['profile_id'] = trim((string)($_POST['profile_id'] ?? '')) ?: null;
        $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($settings), $existing['id']]);
        flash('success', t('اتحفظ.'));
    }

    if ($action === 'delete' && $existing) {
        $pdo->prepare("DELETE FROM connectors WHERE id = ? AND workspace_id = ?")->execute([$existing['id'], $wid]);
        log_activity('connector.delete', $provider . ' ' . $existing['label']);
        flash('success', t('اتشال الاتصال "%s". الحسابات اللي عليه مش هتظهر هنا تاني.', $existing['label']));
    }
    redirect(url('connectors') . '#' . $provider);
}

// لكل اتصال: البروفايلات وعدد الحسابات
$details = [];
foreach (connections('zernio') as $c) {
    $d = ['profiles' => [], 'accounts' => null, 'error' => null];
    try {
        $cl = new Zernio($c['credentials']['api_key'] ?? '');
        $d['profiles'] = $cl->profiles();
        $d['accounts'] = count($cl->request('GET', '/v1/accounts', array_filter(['profileId' => $c['settings']['profile_id'] ?? null]))['accounts'] ?? []);
    } catch (Throwable $ex) {
        $d['error'] = $ex->getMessage();
    }
    $details[(int)$c['id']] = $d;
}

/** خانة إعداد واحدة: سرية (password)، أو قائمة، أو نص عادي بقيمته الحالية */
function connector_field(array $f, ?array $c, bool $isNew): string
{
    $name = 'f[' . e($f['key']) . ']';
    $cur = $c['credentials'][$f['key']] ?? ($f['default'] ?? '');
    if (($f['type'] ?? '') === 'select') {
        $h = '<select name="' . $name . '" class="input">';
        foreach ($f['options'] as $ov => $ol) $h .= '<option value="' . e($ov) . '"' . ((string)$cur === (string)$ov ? ' selected' : '') . '>' . e($ol) . '</option>';
        return $h . '</select>';
    }
    if (!empty($f['secret'])) {
        return '<div class="relative"><input name="' . $name . '" type="password" autocomplete="new-password" dir="ltr" class="input pe-10" placeholder="' . e($isNew ? ($f['placeholder'] ?? '') : t('•••••••••• متخزن (اكتب جديد لو عاوز تغيره)')) . '"' . ($isNew && empty($f['optional']) ? ' required' : '') . '>'
            . '<button type="button" data-reveal class="absolute end-2 top-1/2 -translate-y-1/2 mt-0.5 p-1.5 rounded-lg text-ink-400 hover:text-ink-700 hover:bg-ink-100" title="' . e(t('إظهار')) . '"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>';
    }
    return '<input name="' . $name . '" type="text" dir="ltr" class="input" value="' . e($isNew ? ($f['default'] ?? '') : (string)$cur) . '" placeholder="' . e($f['placeholder'] ?? '') . '"' . ($isNew && empty($f['optional']) ? ' required' : '') . '>';
}


$cats = ['social' => t('السوشيال ميديا'), 'ai' => t('الذكاء الاصطناعي'), 'creative' => t('التصميم والصور'), 'email' => t('الإيميل')];

render(t('التكاملات'), 'connectors', function () use ($registry, $details, $cats) {
    $all = [];
    foreach ($registry as $key => $def) $all[$key] = connections($key, null, true);
    $connectedCount = count(array_filter($all));
    $aiConns = ai_connections();
    $aiMode = ai_mode();
    $owner = is_ws_owner();
    $primaryId = 0;
    foreach (ai_provider_keys() as $p) foreach ($all[$p] ?? [] as $c) if (!empty($c['settings']['primary'])) $primaryId = (int)$c['id'];
    $hid = function ($key, $c, $action) { return csrf_field() . '<input type="hidden" name="provider" value="' . e($key) . '"><input type="hidden" name="cid" value="' . (int)$c['id'] . '"><input type="hidden" name="action" value="' . $action . '">'; };
    $ok = function ($c) { return '<span class="inline-flex items-center gap-1.5"><span class="relative flex w-2 h-2"><span class="absolute inline-flex h-full w-full rounded-full bg-lime-400 opacity-60 animate-ping"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-lime-500"></span></span>' . e($c) . '</span>'; };
    ?>
    <div id="intg" class="space-y-6">
      <!-- الملخص -->
      <div class="card p-5 sm:p-6 relative overflow-hidden">
        <div class="absolute -top-16 -end-16 w-56 h-56 rounded-full bg-gradient-to-br from-brand-500/20 via-cyan-400/20 to-lime-400/20 blur-2xl pointer-events-none"></div>
        <div class="relative flex flex-wrap items-center gap-5">
          <div class="relative w-16 h-16">
            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="rgb(var(--ink-200))" stroke-width="3.5"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="url(#ig)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="<?= round(97.4 * $connectedCount / max(1, count($registry)), 1) ?> 97.4"/><defs><linearGradient id="ig"><stop offset="0" stop-color="rgb(var(--brand-600))"/><stop offset="1" stop-color="rgb(var(--lime-500))"/></linearGradient></defs></svg>
            <div class="absolute inset-0 flex items-center justify-center text-sm font-semibold text-ink-900"><?= $connectedCount ?>/<?= count($registry) ?></div>
          </div>
          <div class="flex-1 min-w-[220px]">
            <h2 class="text-lg font-semibold text-ink-900"><?= e(t('%s من %s خدمات متصلة', $connectedCount, count($registry))) ?></h2>
            <p class="text-sm text-ink-500 mt-0.5"><?= e(t('اضغط على أي خدمة لربطها أو إدارتها. المفاتيح تُخزَّن مشفرة ولا تظهر لأحد بعد الحفظ.')) ?></p>
          </div>
        </div>
      </div>

      <a href="<?= e(url('assistant', ['tab' => 'models'])) ?>" class="card p-4 flex items-center gap-3 hover:border-brand-300 transition">
        <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center">✨</span>
        <span class="flex-1"><b class="text-ink-900 text-sm block"><?= e(t('إعدادات الذكاء الاصطناعي')) ?></b><span class="text-xs text-ink-500"><?= e(t('الموديلات والمهام، تفعيل وإيقاف الميزات، الاستهلاك، ومعلومات الشركة.')) ?></span></span>
        <svg class="w-5 h-5 text-ink-400 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
      </a>
      <div data-pane="services" class="space-y-6">
      <!-- البحث والفلاتر -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[220px] max-w-md">
          <svg class="w-4 h-4 absolute start-3.5 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          <input id="intg-q" type="search" class="input !mt-0 ps-10" placeholder="<?= e(t('ابحث عن خدمة...')) ?>  ( / )">
        </div>
        <div class="flex gap-2 overflow-x-auto" id="intg-cats">
          <button type="button" class="chip on" data-cat=""><?= e(t('الكل')) ?> <span class="text-ink-400"><?= count($registry) ?></span></button>
          <?php foreach ($cats as $ck => $cl): $n = count(array_filter($registry, function ($d) use ($ck) { return ($d['category'] ?? '') === $ck; })); if (!$n) continue; ?>
            <button type="button" class="chip" data-cat="<?= e($ck) ?>"><?= e($cl) ?> <span class="text-ink-400"><?= $n ?></span></button>
          <?php endforeach; ?>
        </div>
        <div class="seg" id="intg-st">
          <a href="#" class="on" data-st=""><?= e(t('الكل')) ?></a><a href="#" data-st="on"><?= e(t('متصل')) ?></a><a href="#" data-st="off"><?= e(t('غير متصل')) ?></a>
        </div>
      </div>

      <!-- الكروت -->
      <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4" id="intg-grid">
        <?php foreach ($registry as $key => $def): $conns = $all[$key]; $active = array_filter($conns, function ($c) { return empty($c['settings']['disabled']); }); $err = false; foreach ($active as $c) if (!empty($details[(int)$c['id']]['error'])) $err = true; $isPrimary = false; ?>
          <button type="button" data-open="<?= e($key) ?>" data-cat="<?= e($def['category'] ?? '') ?>" data-st="<?= $conns ? 'on' : 'off' ?>" data-q="<?= e(mb_strtolower($def['name'] . ' ' . $def['description'] . ' ' . ($cats[$def['category'] ?? ''] ?? ''))) ?>"
            class="intg-card group card p-5 text-start <?= $conns && !$active ? 'opacity-70' : '' ?> transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-ink-900/5 hover:border-brand-300 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20 relative">
            <div class="flex items-start gap-3.5">
              <?= connector_logo($def) ?>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <b class="text-ink-900 truncate"><?= e($def['name']) ?></b>
                  <?php if ($isPrimary): ?><span class="badge !bg-brand-50 !text-brand-700" title="<?= e(t('المزود الأساسي للمساعد')) ?>">★ <?= e(t('أساسي')) ?></span><?php endif; ?>
                </div>
                <div class="text-xs text-ink-400 mt-0.5"><?= e($cats[$def['category'] ?? ''] ?? '') ?></div>
              </div>
              <svg class="w-5 h-5 text-ink-300 group-hover:text-brand-600 transition rtl:rotate-180 group-hover:translate-x-0.5 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
            </div>
            <p class="text-sm text-ink-500 mt-3 line-clamp-2 min-h-[2.5rem]"><?= e($def['description']) ?></p>
            <div class="mt-4 pt-3 border-t border-ink-100 flex items-center justify-between text-xs">
              <?php if ($conns && !$active): ?><span class="inline-flex items-center gap-1.5 text-amber-700"><span class="w-2 h-2 rounded-full bg-amber-500"></span><?= e(t('متوقف')) ?></span>
              <?php elseif ($err): ?><span class="inline-flex items-center gap-1.5 text-red-600"><span class="w-2 h-2 rounded-full bg-red-500"></span><?= e(t('يحتاج مراجعة')) ?></span>
              <?php elseif ($conns): ?><span class="text-lime-700"><?= $ok(count($conns) > 1 ? t('%s اتصال', count($conns)) : t('متصل')) ?></span>
              <?php else: ?><span class="text-ink-400"><?= e(t('غير متصل')) ?></span><?php endif; ?>
              <span class="font-medium <?= $conns ? 'text-ink-600' : 'text-brand-600' ?>"><?= $conns ? e(t('إدارة')) : e(t('ربط +')) ?></span>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
      <div id="intg-empty" class="hidden card p-10 text-center text-sm text-ink-500"><?= e(t('لا توجد خدمات مطابقة.')) ?></div>
      </div>

    </div>

    <!-- اللوحة الجانبية -->
    <div id="intg-overlay" class="fixed inset-0 z-40 bg-ink-900/40 backdrop-blur-[2px] opacity-0 pointer-events-none transition-opacity duration-200"></div>
    <?php foreach ($registry as $key => $def): $conns = $all[$key]; $locked = !empty($def['owner_only']) && !is_ws_owner(); ?>
    <aside id="drawer-<?= e($key) ?>" data-drawer="<?= e($key) ?>" role="dialog" aria-modal="true"
      class="intg-drawer fixed top-0 bottom-0 end-0 z-50 w-full max-w-lg bg-white border-s border-ink-200 shadow-2xl flex flex-col translate-x-full rtl:-translate-x-full transition-transform duration-300 ease-out">
      <div class="p-5 border-b border-ink-200 flex items-start gap-3.5">
        <?= connector_logo($def) ?>
        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-lg text-ink-900"><?= e($def['name']) ?></h3>
          <p class="text-sm text-ink-500"><?= e($def['description']) ?></p>
        </div>
        <button type="button" data-close class="p-2 -m-1 rounded-xl text-ink-400 hover:text-ink-900 hover:bg-ink-100" aria-label="<?= e(t('إغلاق')) ?>"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
      </div>
      <div class="flex-1 overflow-y-auto p-5 space-y-4">
        <?php foreach ($conns as $i => $c): $d = $details[(int)$c['id']] ?? ['profiles' => [], 'accounts' => null, 'error' => null]; $isAi = !empty($def['ai_models']); ?>
          <div class="rounded-2xl border border-ink-200 p-4 space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <b class="text-ink-900 truncate"><?= e($c['label']) ?></b>
                  <?php if ($i === 0 && count($conns) > 1): ?><span class="badge"><?= e(t('الأساسي')) ?></span><?php endif; ?>
                  
                  <?php if (!empty($c['settings']['disabled'])): ?><span class="badge !bg-amber-100 !text-amber-700"><?= e(t('متوقف')) ?></span><?php endif; ?>
                </div>
                <p class="text-xs mt-1 <?= $d['error'] ? 'text-red-600' : 'text-ink-500' ?>">
                  <?php if ($d['error']): ?><?= e($d['error']) ?>
                  <?php elseif ($key === 'zernio'): ?><?= $ok(t('يعمل')) ?> · <?= e(t('%s حساب مربوط', (int)$d['accounts'])) ?>
                  <?php elseif ($isAi && !empty($c['settings']['models'])): ?><?= $ok(t('%s موديل متاح', count($c['settings']['models']))) ?>
                  <?php else: ?><?= $ok($c['info'] ?: t('يعمل')) ?><?php endif; ?>
                </p>
                <?php if (!empty($c['checked_at'])): ?><p class="text-[11px] text-ink-400 mt-0.5"><?= e(t('آخر فحص: %s', $c['checked_at'])) ?></p><?php endif; ?>
              </div>
              <?php $on = empty($c['settings']['disabled']); ?>
              <form method="post" title="<?= !$locked ? ($on ? e(t('إيقاف')) : e(t('تفعيل'))) : e(t('تعديل الاتصال ده لصاحب الشركة بس.')) ?>"><?= $hid($key, $c, 'toggle') ?>
                <button type="submit" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" data-switch <?= !$locked ? '' : 'disabled' ?> class="relative inline-flex w-11 h-6 rounded-full transition disabled:opacity-50 disabled:cursor-not-allowed <?= $on ? 'bg-lime-500' : 'bg-ink-300' ?>">
                  <span class="absolute top-0.5 start-0.5 w-5 h-5 rounded-full bg-[#fff] shadow transition-transform <?= $on ? 'translate-x-5 rtl:-translate-x-5' : '' ?>"></span></button></form>
            </div>

            <?php if (!$locked): ?>
            <div class="flex flex-wrap gap-2">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="retest">
                <button class="btn-ghost !py-1.5 !px-3 !text-xs"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v6h6M20 20v-6h-6M5 13a7 7 0 0012.9 3M19 11A7 7 0 006.1 8"/></svg><?= e(t('اختبار الاتصال')) ?></button></form>

              <form method="post" class="ms-auto" onsubmit="return confirm('<?= tjs('تشيل الاتصال "%s"؟ الحسابات اللي عليه هتختفي من البرنامج (مش هتتمسح من عندهم).', $c['label']) ?>')">
                <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete">
                <button class="!py-1.5 !px-3 !text-xs btn text-red-600 hover:bg-red-50"><?= e(t('فصل')) ?></button>
              </form>
            </div>
            <?php endif; ?>

            <?php if ($isAi && !empty($c['settings']['models'])): $curM = $c['settings']['model'] ?? ai_default_model($c['settings']['models']); ?>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="model">
                <label class="block"><span class="label"><?= e(t('الموديل')) ?></span>
                  <select name="model" class="input" dir="ltr" onchange="this.form.requestSubmit()" <?= $locked ? 'disabled' : '' ?>>
                    <?php foreach ($c['settings']['models'] as $m): ?><option value="<?= e($m) ?>" <?= $m === $curM ? 'selected' : '' ?>><?= e($m) ?> · <?= e(ai_fmt_cost(ai_question_cost(ai_model_price($m)))) ?><?= $m === ai_default_model($c['settings']['models']) ? '  · ' . e(t('مقترح')) : '' ?></option><?php endforeach; ?>
                  </select></label>
                <?php $mp = ai_model_price($curM); ?>
                <div class="mt-2 grid grid-cols-3 gap-2 text-center">
                  <div class="rounded-xl bg-ink-50 border border-ink-200 px-2 py-2"><div class="text-[11px] text-ink-500"><?= e(t('المدخلات / مليون')) ?></div><div class="text-sm font-medium text-ink-900" dir="ltr"><?= $mp ? '$' . e(rtrim(rtrim(number_format($mp['in'], 3), '0'), '.')) : '—' ?></div></div>
                  <div class="rounded-xl bg-ink-50 border border-ink-200 px-2 py-2"><div class="text-[11px] text-ink-500"><?= e(t('المخرجات / مليون')) ?></div><div class="text-sm font-medium text-ink-900" dir="ltr"><?= $mp ? '$' . e(rtrim(rtrim(number_format($mp['out'], 3), '0'), '.')) : '—' ?></div></div>
                  <div class="rounded-xl bg-brand-50 border border-brand-200 px-2 py-2"><div class="text-[11px] text-brand-700"><?= e(t('السؤال الواحد')) ?></div><div class="text-sm font-semibold text-brand-700" dir="ltr">≈ <?= e(ai_fmt_cost(ai_question_cost($mp))) ?></div></div>
                </div>
                <p class="text-xs text-ink-400 mt-1.5"><?= e(t('مرتبة من الأرخص بالأسعار الرسمية. السؤال الواحد محسوب على 3000 توكن داخلة و800 خارجة.')) ?></p>
              </form>
            <?php endif; ?>

            <?php if ($key === 'openrouter'): $tier = $c['settings']['tier'] ?? 'cheap'; ?>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="provider" value="openrouter"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="tier">
                <span class="label"><?= e(t('الموديلات المسموحة')) ?></span>
                <div class="grid gap-2 mt-2">
                  <?php foreach (['free' => [t('مجاني بس'), t('مفيش أي تكلفة. ممكن يبقى أبطأ أو زحمة أحياناً.')], 'cheap' => [t('مجاني الأول، وبعده الرخيص'), t('لو المجاني زحمة، بيستخدم موديل رخيص (أقل من نص سنت للسؤال).')], 'any' => [t('أي موديل'), t('بيبدأ برضه بالأرخص، بس مسموحله يطلع لموديلات أغلى لو اللي قبلها فشل.')]] as $tk => [$tl, $td]): ?>
                    <label class="flex items-start gap-2 rounded-xl border border-ink-200 px-3 py-2 cursor-pointer transition hover:border-brand-300 <?= $tier === $tk ? 'bg-brand-50 border-brand-300' : '' ?>">
                      <input type="radio" name="tier" value="<?= $tk ?>" <?= $tier === $tk ? 'checked' : '' ?> onchange="this.form.requestSubmit()" class="mt-1">
                      <span><span class="block text-sm text-ink-900"><?= e($tl) ?></span><span class="block text-xs text-ink-500"><?= e($td) ?></span></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </form>
            <?php endif; ?>

            <?php if ($d['profiles']): ?>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="profile">
                <label class="block"><span class="label"><?= e(t('الـ Profile')) ?></span>
                  <select name="profile_id" class="input" onchange="this.form.requestSubmit()">
                    <option value=""><?= e(t('كل البروفايلات')) ?></option>
                    <?php foreach ($d['profiles'] as $p): ?>
                      <option value="<?= e($p['_id']) ?>" <?= ($c['settings']['profile_id'] ?? '') === $p['_id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= (int)($p['accountCount'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                  </select></label>
              </form>
            <?php endif; ?>

            <?php if ($locked): ?><p class="text-xs text-ink-500"><?= e(t('تعديل الاتصال ده لصاحب الشركة بس.')) ?></p><?php else: ?>
            <details class="group/d">
              <summary class="text-sm link cursor-pointer inline-flex items-center gap-1"><svg class="w-3.5 h-3.5 transition group-open/d:rotate-90 rtl:rotate-180 rtl:group-open/d:rotate-90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg><?= e(t('تعديل الاسم أو المفتاح')) ?></summary>
              <form method="post" class="mt-3 space-y-3">
                <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="save">
                <label class="block"><span class="label"><?= e(t('اسم الاتصال')) ?></span>
                  <input name="label" class="input" value="<?= e($c['label']) ?>" maxlength="120"></label>
                <?php foreach ($def['fields'] as $f): ?>
                  <label class="block"><span class="label"><?= e($f['label']) ?></span><?= connector_field($f, $c, false) ?></label>
                <?php endforeach; ?>
                <button class="btn-ghost"><?= e(t('حفظ')) ?></button>
              </form>
            </details>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($locked && !$conns): ?>
          <div class="rounded-2xl border border-dashed border-ink-300 p-5 text-sm text-ink-500"><?= e(t('تعديل الاتصال ده لصاحب الشركة بس.')) ?></div>
        <?php elseif (!$locked && (empty($def['single']) || !$conns)): ?>
          <form method="post" class="rounded-2xl border border-dashed border-ink-300 p-5 space-y-3 border-dashed">
            <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="action" value="save">
            <b class="text-ink-900"><?= $conns ? e(t('+ اتصال جديد')) : e(t('ربط %s', $def['name'])) ?></b>
            <label class="block"><span class="label"><?= e(t('اسم الاتصال')) ?> <span class="text-ink-400 font-normal"><?= e(t('(اختياري)')) ?></span></span>
              <input name="label" class="input" maxlength="120" placeholder="<?= e(t('مثلاً: الحساب الرئيسي')) ?>"></label>
            <div class="<?= count($def['fields']) > 2 ? 'grid sm:grid-cols-2 gap-3' : 'space-y-3' ?>">
            <?php foreach ($def['fields'] as $f): ?>
              <label class="block"><span class="label"><?= e($f['label']) ?><?= !empty($f['optional']) ? ' <span class="text-ink-400 font-normal">' . e(t('(اختياري)')) . '</span>' : '' ?></span><?= connector_field($f, null, true) ?></label>
            <?php endforeach; ?>
            </div>
            <p class="text-xs text-ink-400"><?= e($def['help']) ?></p>
            <button class="btn-primary w-full"><?= e(t('اختبار وربط')) ?></button>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($def['docs']): ?>
      <div class="p-4 border-t border-ink-200 text-sm"><a href="<?= e($def['docs']) ?>" target="_blank" rel="noopener" class="link inline-flex items-center gap-1.5"><?= e(t('احصل على المفتاح')) ?><svg class="w-3.5 h-3.5 rtl:-scale-x-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1h5"/></svg></a></div>
      <?php endif; ?>
    </aside>
    <?php endforeach; ?>

    <script>
    (function () {
      var grid = document.getElementById('intg-grid'), q = document.getElementById('intg-q'), ov = document.getElementById('intg-overlay');
      var cat = '', st = '';
      function filter() {
        var term = (q.value || '').trim().toLowerCase(), n = 0;
        grid.querySelectorAll('.intg-card').forEach(function (c) {
          var show = (!cat || c.dataset.cat === cat) && (!st || c.dataset.st === st) && (!term || c.dataset.q.indexOf(term) > -1);
          c.classList.toggle('hidden', !show); if (show) n++;
        });
        document.getElementById('intg-empty').classList.toggle('hidden', n > 0);
      }
      q.addEventListener('input', filter);
      document.querySelectorAll('#intg-cats [data-cat]').forEach(function (b) { b.addEventListener('click', function () {
        cat = b.dataset.cat; document.querySelectorAll('#intg-cats .chip').forEach(function (x) { x.classList.toggle('on', x === b); }); filter(); }); });
      document.querySelectorAll('#intg-st [data-st]').forEach(function (a) { a.addEventListener('click', function (e) {
        e.preventDefault(); st = a.dataset.st; document.querySelectorAll('#intg-st a').forEach(function (x) { x.classList.toggle('on', x === a); }); filter(); }); });

      var cur = null;
      function open(key, push) {
        var d = document.getElementById('drawer-' + key); if (!d) return;
        if (cur && cur !== d) close(true);
        cur = d; d.classList.remove('translate-x-full', 'rtl:-translate-x-full');
        ov.classList.remove('opacity-0', 'pointer-events-none'); document.body.style.overflow = 'hidden';
        if (push !== false) history.replaceState(null, '', '#' + key);
        setTimeout(function () { var f = d.querySelector('form.border-dashed input:not([type=hidden])') || d.querySelector('[data-close]'); f && f.focus({ preventScroll: true }); }, 260);
      }
      function close(keepHash) {
        if (!cur) return; cur.classList.add('translate-x-full', 'rtl:-translate-x-full'); cur = null;
        ov.classList.add('opacity-0', 'pointer-events-none'); document.body.style.overflow = '';
        if (!keepHash) history.replaceState(null, '', location.pathname + location.search);
      }
      grid.addEventListener('click', function (e) { var b = e.target.closest('[data-open]'); if (b) open(b.dataset.open); });
      ov.addEventListener('click', function () { close(); });
      document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', function () { close(); }); });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
        if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); q.focus(); }
      });
      document.querySelectorAll('[data-reveal]').forEach(function (b) { b.addEventListener('click', function () {
        var i = b.parentNode.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password'; b.classList.toggle('text-brand-600'); }); });
      // زرار الحفظ: حالة انتظار عشان الاختبار بياخد ثواني
      document.querySelectorAll('.intg-drawer form').forEach(function (f) { f.addEventListener('submit', function () {
        var b = f.querySelector('button:not([type=button])'); if (b && b.hasAttribute('data-switch')) { b.classList.toggle('bg-lime-500'); b.classList.toggle('bg-ink-300'); var k = b.id === 'ai-auto' ? '6' : '5'; b.firstElementChild.classList.toggle('translate-x-' + k); b.firstElementChild.classList.toggle('rtl:-translate-x-' + k); return; } if (b && b.name === 'mode') return; if (b && !b.disabled) { setTimeout(function () { b.disabled = true; b.innerHTML = '<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>' + (window.T ? T.working : ''); }, 0); } }); });
      if (location.hash.length > 1) open(location.hash.slice(1), false);
      window.addEventListener('hashchange', function () { if (location.hash.length > 1) open(location.hash.slice(1), false); });
    })();
    </script>
    <?php
});
