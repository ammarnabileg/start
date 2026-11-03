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
    $existing = null;
    foreach (connections($provider) as $c) {
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
            if ($v === '') {
                flash('error', t('اكتب %s.', $f['label']));
                redirect(url('connectors'));
            }
            $creds[$f['key']] = $v;
        }
        foreach (connections($provider) as $c) {
            if ((int)$c['id'] !== $cid && $c['credentials'] == $creds) {
                flash('error', t('المفتاح ده متضاف قبل كده باسم "%s".', $c['label']));
                redirect(url('connectors'));
            }
        }
        try {
            $info = (string)$def['test']($creds);
        } catch (Throwable $ex) {
            flash('error', t('%s رفض المفاتيح، وما اتحفظش حاجة: %s', $def['name'], $ex->getMessage()));
            redirect(url('connectors'));
        }
        $same = $existing && $existing['credentials'] == $creds;
        save_connector($pdo, $wid, $provider, $creds, $same ? $existing['settings'] : [], $info, $label !== '' ? mb_substr($label, 0, 120) : null, $existing ? (int)$existing['id'] : null);
        log_activity('connector.save', $provider . ' ' . $label);
        flash('success', $existing ? t('اتحفظ الاتصال.') : t('اتضاف اتصال جديد بنجاح.'));
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
    redirect(url('connectors'));
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

render(t('التكاملات'), 'connectors', function () use ($registry, $details) {
    ?>
    <p class="text-sm text-ink-500 mb-6 max-w-3xl"><?= e(t('اربط الشركة بالخدمات اللي بتستخدمها. تقدر تضيف أكتر من اتصال لنفس الخدمة، وكل الحسابات من كل الاتصالات بتظهر مع بعض في البرنامج. المفاتيح بتتخزن مشفرة ومحدش يقدر يشوفها بعد الحفظ.')) ?></p>

    <?php foreach ($registry as $key => $def): $conns = connections($key); ?>
      <section class="mb-10">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 7h3a5 5 0 010 10h-3m-4 0H7A5 5 0 017 7h3m-2 5h8"/></svg>
          </div>
          <div class="flex-1">
            <h2 class="font-semibold text-lg text-ink-900"><?= e($def['name']) ?></h2>
            <p class="text-sm text-ink-500"><?= e($def['description']) ?></p>
          </div>
          <span class="badge <?= $conns ? 'badge-green' : '' ?>"><?= $conns ? e(t('%s اتصال', count($conns))) : e(t('مش متصل')) ?></span>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
          <?php foreach ($conns as $i => $c): $d = $details[(int)$c['id']] ?? ['profiles' => [], 'accounts' => null, 'error' => null]; ?>
            <div class="card p-5 space-y-4">
              <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <b class="text-ink-900 truncate"><?= e($c['label']) ?></b>
                    <?php if ($i === 0 && count($conns) > 1): ?><span class="badge"><?= e(t('الأساسي')) ?></span><?php endif; ?>
                  </div>
                  <p class="text-xs mt-1 <?= $d['error'] ? 'text-red-600' : 'text-ink-500' ?>">
                    <?php if ($d['error']): ?><?= e($d['error']) ?>
                    <?php else: ?><span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-lime-500"></span><?= e(t('شغال')) ?></span> · <?= e(t('%s حساب مربوط', (int)$d['accounts'])) ?><?php endif; ?>
                  </p>
                </div>
                <form method="post" onsubmit="return confirm('<?= tjs('تشيل الاتصال "%s"؟ الحسابات اللي عليه هتختفي من البرنامج (مش هتتمسح من عندهم).', $c['label']) ?>')">
                  <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete">
                  <button class="text-xs text-red-600 hover:underline"><?= e(t('فصل')) ?></button>
                </form>
              </div>

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

              <details>
                <summary class="text-sm link cursor-pointer"><?= e(t('تعديل الاسم أو المفتاح')) ?></summary>
                <form method="post" class="mt-3 space-y-3">
                  <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="cid" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="save">
                  <label class="block"><span class="label"><?= e(t('اسم الاتصال')) ?></span>
                    <input name="label" class="input" value="<?= e($c['label']) ?>" maxlength="120"></label>
                  <?php foreach ($def['fields'] as $f): ?>
                    <label class="block"><span class="label"><?= e($f['label']) ?></span>
                      <input name="f[<?= e($f['key']) ?>]" type="password" autocomplete="off" dir="ltr" class="input" placeholder="<?= e(t('•••••••••• متخزن (اكتب جديد لو عاوز تغيره)')) ?>"></label>
                  <?php endforeach; ?>
                  <button class="btn-ghost"><?= e(t('حفظ')) ?></button>
                </form>
              </details>
            </div>
          <?php endforeach; ?>

          <form method="post" class="card p-5 space-y-3 border-dashed">
            <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="action" value="save">
            <b class="text-ink-900"><?= $conns ? e(t('+ اتصال جديد')) : e(t('ربط')) ?></b>
            <label class="block"><span class="label"><?= e(t('اسم الاتصال')) ?></span>
              <input name="label" class="input" maxlength="120" placeholder="<?= e(t('مثلاً: فيسبوك وانستجرام')) ?>"></label>
            <?php foreach ($def['fields'] as $f): ?>
              <label class="block"><span class="label"><?= e($f['label']) ?></span>
                <input name="f[<?= e($f['key']) ?>]" type="password" autocomplete="off" dir="ltr" class="input" placeholder="<?= e($f['placeholder'] ?? '') ?>" required></label>
            <?php endforeach; ?>
            <p class="text-xs text-ink-400"><?= e($def['help']) ?></p>
            <button class="btn-primary"><?= e(t('ربط')) ?></button>
          </form>
        </div>
      </section>
    <?php endforeach; ?>
    <?php
});
