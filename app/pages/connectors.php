<?php
/** Connectors: ربط الشركة بالخدمات الخارجية (لصاحب الشركة) */
$pdo = db();
$wid = (int)ws('id');
$registry = connector_registry();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = (string)($_POST['provider'] ?? '');
    $action = (string)($_POST['action'] ?? '');
    if (!isset($registry[$provider])) {
        redirect(url('connectors'));
    }
    $def = $registry[$provider];
    $existing = connector($provider);

    if ($action === 'save') {
        $creds = [];
        foreach ($def['fields'] as $f) {
            $v = trim((string)($_POST['f'][$f['key']] ?? ''));
            // خانة سرية فاضية = سيب القيمة القديمة
            if ($v === '' && $existing) {
                $v = (string)($existing['credentials'][$f['key']] ?? '');
            }
            if ($v === '') {
                flash('error', t('اكتب %s.', $f['label']));
                redirect(url('connectors'));
            }
            $creds[$f['key']] = $v;
        }
        try {
            $info = (string)$def['test']($creds);
        } catch (Throwable $ex) {
            flash('error', t('%s رفض المفاتيح، وما اتحفظش حاجة: %s', $def['name'], $ex->getMessage()));
            redirect(url('connectors'));
        }
        $same = $existing && $existing['credentials'] == $creds;
        save_connector($pdo, $wid, $provider, $creds, $same ? $existing['settings'] : [], $info);
        log_activity('connector.save', $provider);
        flash('success', t('اتربط %s بنجاح.', $def['name']));
    }

    if ($action === 'settings' && $existing && $provider === 'zernio') {
        $settings = $existing['settings'];
        $settings['profile_id'] = trim((string)($_POST['profile_id'] ?? '')) ?: null;
        $pdo->prepare("UPDATE connectors SET settings = ? WHERE id = ?")->execute([json_encode($settings), $existing['id']]);
        flash('success', t('اتحفظ.'));
    }

    if ($action === 'delete' && $existing) {
        $pdo->prepare("DELETE FROM connectors WHERE id = ?")->execute([$existing['id']]);
        log_activity('connector.delete', $provider);
        flash('success', t('اتشال ربط %s من الشركة دي.', $def['name']));
    }
    redirect(url('connectors'));
}

// قائمة البروفايلات لو Zernio مربوط
$profiles = [];
$profilesError = null;
if (connector('zernio')) {
    try {
        $profiles = (new Zernio())->profiles();
    } catch (ZernioException $ex) {
        $profilesError = $ex->getMessage();
    }
}

render('Connectors', 'connectors', function () use ($registry, $profiles, $profilesError) {
    ?>
    <p class="text-sm text-ink-500 mb-6 max-w-2xl"><?= e(t('كل شركة ليها مفاتيحها الخاصة. المفاتيح بتتخزن مشفرة، ومحدش يقدر يشوفها تاني بعد الحفظ، حتى صاحب الشركة.')) ?></p>
    <div class="grid md:grid-cols-2 gap-5">
      <?php foreach ($registry as $key => $def): $c = connector($key); ?>
        <div class="card p-6">
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg shrink-0" style="background:<?= e($def['color']) ?>"><?= e(mb_substr($def['name'], 0, 1)) ?></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <h2 class="font-bold text-lg"><?= e($def['name']) ?></h2>
                <?php if ($c): ?><span class="badge badge-green"><?= e(t('متصل')) ?></span><?php else: ?><span class="badge"><?= e(t('مش متصل')) ?></span><?php endif; ?>
              </div>
              <p class="text-sm text-ink-500"><?= e($def['description']) ?></p>
              <?php if ($c): ?><p class="text-xs text-ink-400 mt-1"><?= e(t('آخر تأكيد:')) ?> <?= e(substr((string)$c['checked_at'], 0, 16)) ?><?= $c['info'] ? ' · ' . e($c['info']) : '' ?></p><?php endif; ?>
            </div>
          </div>

          <form method="post" class="mt-5 space-y-3">
            <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="action" value="save">
            <?php foreach ($def['fields'] as $f): $has = $c && !empty($c['credentials'][$f['key']]); ?>
              <label class="block"><span class="label"><?= e($f['label']) ?></span>
                <input name="f[<?= e($f['key']) ?>]" type="<?= !empty($f['secret']) ? 'password' : 'text' ?>" autocomplete="off" dir="ltr" class="input"
                  placeholder="<?= $has ? t('•••••••••• متخزن (اكتب جديد لو عاوز تغيره)') : e($f['placeholder'] ?? '') ?>" <?= $has ? '' : 'required' ?>></label>
            <?php endforeach; ?>
            <p class="text-xs text-ink-400"><?= e($def['help']) ?> <a href="<?= e($def['docs']) ?>" target="_blank" rel="noopener" class="link"><?= e(t('افتح ↗')) ?></a></p>
            <button class="btn btn-primary"><?= $c ? t('تحديث المفتاح') : t('ربط') ?></button>
          </form>

          <?php if ($c && $key === 'zernio'): ?>
            <form method="post" class="mt-5 pt-5 border-t border-ink-100 space-y-2">
              <?= csrf_field() ?><input type="hidden" name="provider" value="zernio"><input type="hidden" name="action" value="settings">
              <?php if ($profilesError) error_box($profilesError); ?>
              <?php if ($profiles): ?>
                <label class="block"><span class="label"><?= e(t('الـ Profile في Zernio')) ?></span>
                  <select name="profile_id" class="input" onchange="this.form.requestSubmit()">
                    <option value=""><?= e(t('كل البروفايلات')) ?></option>
                    <?php foreach ($profiles as $p): ?>
                      <option value="<?= e($p['_id']) ?>" <?= ($c['settings']['profile_id'] ?? '') === $p['_id'] ? 'selected' : '' ?>><?= e($p['name']) ?> (<?= (int)($p['accountCount'] ?? 0) ?> <?= e(t('حساب)')) ?></option>
                    <?php endforeach; ?>
                  </select></label>
                <p class="text-xs text-ink-400"><?= e(t('لو حساب Zernio فيه أكتر من شركة، اختار البروفايل بتاع الشركة دي عشان حساباتها بس هي اللي تظهر.')) ?></p>
              <?php endif; ?>
            </form>
          <?php endif; ?>

          <?php if ($c): ?>
            <form method="post" class="mt-4" onsubmit="return confirm('<?= tjs('تشيل ربط %s من الشركة دي؟', $def['name']) ?>')">
              <?= csrf_field() ?><input type="hidden" name="provider" value="<?= e($key) ?>"><input type="hidden" name="action" value="delete">
              <button class="text-sm text-red-600 hover:underline"><?= e(t('فصل')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
