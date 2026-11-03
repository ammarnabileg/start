<?php
$err = null;
$z = zernio_or_null($err);
$editId = (string)($_GET['id'] ?? '');
$isNew = !empty($_GET['new']);

/** Build the shared part of the automation body from POST; returns [body, error]. */
function ca_form_body(string $platform, bool $isCreate): array
{
    $name = trim((string)($_POST['name'] ?? ''));
    $keywords = ip_split_keywords((string)($_POST['keywords'] ?? ''));
    $match = (string)($_POST['match_mode'] ?? 'contains');
    if (!in_array($match, ['exact', 'contains', 'word'], true)) $match = 'contains';
    $dm = trim((string)($_POST['dm_message'] ?? ''));
    $reply = trim((string)($_POST['comment_reply'] ?? ''));
    if ($name === '') return [null, t('اكتب اسم للأتمتة.')];
    $canDm = ip_automation_dm_platform($platform);
    if ($canDm && $dm === '') return [null, t('اكتب الرسالة الخاصة اللي هتتبعت.')];
    if (!$canDm && $reply === '') return [null, t('المنصة دي بتدعم الرد العام بس، فاكتب الرد العام.')];
    $body = ['name' => mb_substr($name, 0, 100), 'keywords' => $keywords, 'matchMode' => $match];
    if ($canDm) $body['dmMessage'] = $dm;
    if ($reply !== '' || !$isCreate) $body['commentReply'] = $reply;
    $body['isActive'] = !empty($_POST['is_active']);
    return [$body, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $id = (string)($_POST['id'] ?? '');
    $back = url('automations');
    try {
        if ($action === 'create') {
            $accountId = (string)($_POST['account_id'] ?? '');
            $account = null;
            foreach (ip_accounts($z, ip_automation_platforms()) as $a) {
                if ($a['_id'] === $accountId) $account = $a;
            }
            if (!$account) {
                flash('error', t('اختار الحساب.'));
                redirect(url('automations', ['new' => 1]));
            }
            [$body, $error] = ca_form_body($account['platform'], true);
            if ($error) {
                flash('error', $error);
                redirect(url('automations', ['new' => 1]));
            }
            $pp = trim((string)($_POST['platform_post_id'] ?? ''));
            $profileId = is_string($account['profileId'] ?? null) ? $account['profileId'] : (string)($account['profileId']['_id'] ?? '');
            $body = ['profileId' => $profileId !== '' ? $profileId : current_profile_id($z), 'accountId' => $accountId, 'trigger' => 'comment'] + $body;
            if ($pp !== '') $body['platformPostId'] = $pp;
            ip_create_automation($z, $body);
            log_activity('automation.create', $body['name']);
            flash('success', t('تم إنشاء الأتمتة.'));
        } elseif ($action === 'update' && $id !== '') {
            $platform = (string)($_POST['platform'] ?? '');
            [$body, $error] = ca_form_body($platform, false);
            if ($error) {
                flash('error', $error);
                redirect(url('automations', ['id' => $id]));
            }
            ip_update_automation($z, $id, $body);
            log_activity('automation.update', $id);
            flash('success', t('تم حفظ التعديلات.'));
        } elseif ($action === 'toggle' && $id !== '') {
            $on = !empty($_POST['on']);
            ip_update_automation($z, $id, ['isActive' => $on]);
            log_activity('automation.' . ($on ? 'enable' : 'disable'), $id);
            flash('success', $on ? t('الأتمتة اشتغلت.') : t('الأتمتة اتوقفت.'));
        } elseif ($action === 'delete' && $id !== '') {
            if (!can('automations.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                ip_delete_automation($z, $id);
                log_activity('automation.delete', $id);
                flash('success', t('تم حذف الأتمتة.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
        if ($action === 'create') $back = url('automations', ['new' => 1]);
        if ($action === 'update') $back = url('automations', ['id' => $id]);
    }
    redirect($back);
}

$list = [];
$accounts = [];
$current = null;
$logs = [];
$apiError = null;
if ($z) {
    try {
        if ($editId !== '') {
            $r = ip_automation($z, $editId);
            $current = $r['automation'] ?? null;
            $logs = $r['logs'] ?? $r['recentLogs'] ?? $current['recentLogs'] ?? [];
        } elseif ($isNew) {
            $accounts = ip_accounts($z, ip_automation_platforms());
        } else {
            $list = ip_automations($z);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الردود التلقائية على التعليقات'), 'automations', function () use ($z, $list, $accounts, $current, $logs, $editId, $isNew, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    $modes = ['contains' => t('التعليق فيه الكلمة'), 'word' => t('الكلمة موجودة ككلمة مستقلة'), 'exact' => t('التعليق هو الكلمة بالظبط')];

    if ($isNew || $current):
        $a = $current ?? [];
        $plat = $a['platform'] ?? '';
        ?>
        <a href="<?= e(url('automations')) ?>" class="link text-sm"><?= e(t('رجوع للقائمة')) ?></a>
        <div class="grid lg:grid-cols-3 gap-4 mt-3">
          <form method="post" class="card p-5 space-y-4 lg:col-span-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $current ? 'update' : 'create' ?>">
            <?php if ($current): ?><input type="hidden" name="id" value="<?= e($a['id'] ?? '') ?>"><input type="hidden" name="platform" value="<?= e($plat) ?>"><?php endif; ?>
            <h2 class="font-bold text-lg"><?= e($current ? t('تعديل الأتمتة') : t('أتمتة جديدة')) ?></h2>
            <div><label class="label"><?= e(t('الاسم')) ?></label><input name="name" required maxlength="100" class="input" value="<?= e($a['name'] ?? '') ?>" placeholder="<?= e(t('مثلاً: السعر في الخاص')) ?>"></div>
            <?php if (!$current): ?>
              <div><label class="label"><?= e(t('الحساب')) ?></label>
                <select name="account_id" required class="input">
                  <?php foreach ($accounts as $acc): ?><option value="<?= e($acc['_id']) ?>"><?= e(ip_account_label($acc)) ?></option><?php endforeach; ?>
                </select>
                <?php if (!$accounts): ?><p class="text-xs text-red-600 mt-1"><?= e(t('مفيش حساب مربوط يدعم الأتمتة (إنستجرام، فيسبوك، تيك توك، ثريدز، لينكدإن، يوتيوب).')) ?></p><?php endif; ?>
              </div>
              <div><label class="label"><?= e(t('رقم المنشور على المنصة (اختياري)')) ?></label><input name="platform_post_id" class="input" dir="ltr" placeholder="<?= e(t('سيبه فاضي عشان تشتغل على كل المنشورات')) ?>"></div>
            <?php else: ?>
              <p class="text-sm text-ink-600"><?= e(platform_label($plat)) ?> · <?= e(!empty($a['platformPostId']) ? t('منشور واحد: %s', $a['postTitle'] ?? $a['platformPostId']) : t('كل المنشورات')) ?></p>
            <?php endif; ?>
            <div><label class="label"><?= e(t('الكلمات اللي تشغلها')) ?></label>
              <textarea name="keywords" rows="2" class="input" placeholder="<?= e(t('افصل بينهم بفاصلة: سعر، بكام، price')) ?>"><?= e(implode(', ', $a['keywords'] ?? [])) ?></textarea>
              <p class="text-xs text-ink-500 mt-1"><?= e(t('لو سبتها فاضية، أي تعليق هيشغلها.')) ?></p></div>
            <div><label class="label"><?= e(t('طريقة المطابقة')) ?></label>
              <select name="match_mode" class="input"><?php foreach ($modes as $k => $lbl): ?><option value="<?= e($k) ?>" <?= ($a['matchMode'] ?? 'contains') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select></div>
            <?php if (!$current || ip_automation_dm_platform($plat)): ?>
              <div><label class="label"><?= e(t('الرسالة الخاصة (DM)')) ?></label>
                <textarea name="dm_message" rows="3" maxlength="1000" class="input" placeholder="<?= e(t('أهلاً! السعر ... وللطلب ابعتلنا هنا')) ?>"><?= e($a['dmMessage'] ?? '') ?></textarea>
                <p class="text-xs text-ink-500 mt-1"><?= e(t('مطلوبة لإنستجرام وفيسبوك. باقي المنصات مبتدعمش الرسالة الخاصة وهتتجاهل.')) ?></p></div>
            <?php endif; ?>
            <div><label class="label"><?= e(t('الرد العام على التعليق (اختياري)')) ?></label>
              <textarea name="comment_reply" rows="2" maxlength="1000" class="input" placeholder="<?= e(t('بعتنالك التفاصيل في الخاص')) ?>"><?= e($a['commentReply'] ?? '') ?></textarea></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= ($a['isActive'] ?? true) ? 'checked' : '' ?>> <?= e(t('شغالة')) ?></label>
            <button class="btn-primary"><?= e($current ? t('حفظ') : t('إنشاء')) ?></button>
          </form>
          <div class="space-y-4">
            <div class="card p-5 text-sm text-ink-600 space-y-2">
              <h3 class="font-bold text-ink-900"><?= e(t('إزاي بتشتغل؟')) ?></h3>
              <p><?= e(t('لما حد يكتب تعليق فيه واحدة من الكلمات دي، البرنامج بيبعتله الرسالة الخاصة أوتوماتيك، وممكن كمان يرد عليه رد عام تحت التعليق.')) ?></p>
              <p><?= e(t('كل شخص بياخد الرسالة مرة واحدة بس لكل أتمتة.')) ?></p>
            </div>
            <?php if ($current): $s = $current['stats'] ?? []; ?>
              <div class="card p-5 text-sm">
                <h3 class="font-bold mb-2"><?= e(t('الإحصائيات')) ?></h3>
                <div class="grid grid-cols-2 gap-2">
                  <div><div class="text-xl font-bold"><?= (int)($s['triggered'] ?? 0) ?></div><div class="text-xs text-ink-500"><?= e(t('مرات التشغيل')) ?></div></div>
                  <div><div class="text-xl font-bold"><?= (int)($s['dmsSent'] ?? 0) ?></div><div class="text-xs text-ink-500"><?= e(t('رسائل اتبعتت')) ?></div></div>
                  <div><div class="text-xl font-bold"><?= (int)($s['dmsFailed'] ?? 0) ?></div><div class="text-xs text-ink-500"><?= e(t('رسائل فشلت')) ?></div></div>
                  <div><div class="text-xl font-bold"><?= (int)($s['uniqueContacts'] ?? 0) ?></div><div class="text-xs text-ink-500"><?= e(t('أشخاص')) ?></div></div>
                </div>
              </div>
              <?php if ($logs): ?>
                <div class="card p-5 text-sm">
                  <h3 class="font-bold mb-2"><?= e(t('آخر التشغيلات')) ?></h3>
                  <ul class="space-y-2">
                    <?php foreach (array_slice($logs, 0, 10) as $l): ?>
                      <li class="border-b border-ink-100 pb-1"><span class="font-semibold"><?= e($l['commenterName'] ?? $l['commenterUsername'] ?? '') ?></span> <span class="badge"><?= e($l['status'] ?? '') ?></span>
                        <div class="text-xs text-ink-500 truncate"><?= e($l['commentText'] ?? '') ?></div>
                        <div class="text-[11px] text-ink-400"><?= e(fmt_date($l['createdAt'] ?? null, 'd/m h:i A')) ?></div></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php
        return;
    endif;
    ?>
    <div class="card p-5 mb-4 flex flex-wrap items-start gap-4">
      <div class="flex-1 min-w-[16rem] text-sm text-ink-600">
        <p class="font-bold text-ink-900 mb-1"><?= e(t('الردود التلقائية على التعليقات')) ?></p>
        <p><?= e(t('اختار كلمات زي (سعر) أو (بكام). لما حد يكتبها في تعليق على منشوراتك، هيوصله رد تلقائي في الخاص و/أو رد عام تحت التعليق. الرسائل الخاصة متاحة على إنستجرام وفيسبوك بس.')) ?></p>
      </div>
      <a href="<?= e(url('automations', ['new' => 1])) ?>" class="btn-primary"><?= e(t('أتمتة جديدة')) ?></a>
    </div>
    <?php if (!$list): ?>
      <div class="card p-10 text-center text-sm text-ink-500"><?= e(t('مفيش أتمتة لسه.')) ?></div>
    <?php endif; ?>
    <div class="grid md:grid-cols-2 gap-4">
      <?php foreach ($list as $a): $on = !empty($a['isActive']); $s = $a['stats'] ?? []; ?>
        <div class="card p-4 space-y-2">
          <div class="flex items-center gap-2">
            <span class="font-bold flex-1 truncate"><?= e($a['name'] ?? '') ?></span>
            <span class="badge <?= $on ? 'badge-green' : '' ?>"><?= e($on ? t('شغالة') : t('متوقفة')) ?></span>
          </div>
          <p class="text-xs text-ink-500"><?= e(platform_label($a['platform'] ?? '')) ?> · <?= e(!empty($a['platformPostId']) ? t('منشور واحد') : t('كل المنشورات')) ?></p>
          <p class="text-sm"><?= e(t('الكلمات:')) ?> <?= $a['keywords'] ?? [] ? e(implode('، ', $a['keywords'])) : '<em>' . e(t('أي تعليق')) . '</em>' ?></p>
          <?php if (!empty($a['dmMessage'])): ?><p class="text-xs text-ink-600 line-clamp-2"><?= e(t('خاص:')) ?> <?= e($a['dmMessage']) ?></p><?php endif; ?>
          <?php if (!empty($a['commentReply'])): ?><p class="text-xs text-ink-600 line-clamp-2"><?= e(t('عام:')) ?> <?= e($a['commentReply']) ?></p><?php endif; ?>
          <p class="text-xs text-ink-500"><?= e(t('اشتغلت %s مرة · اتبعت %s رسالة', (int)($s['triggered'] ?? 0), (int)($s['dmsSent'] ?? 0))) ?></p>
          <div class="flex flex-wrap gap-2 pt-1">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e($a['id']) ?>"><input type="hidden" name="on" value="<?= $on ? '' : '1' ?>">
              <button class="btn-ghost text-xs"><?= e($on ? t('إيقاف') : t('تشغيل')) ?></button></form>
            <a href="<?= e(url('automations', ['id' => $a['id']])) ?>" class="btn-ghost text-xs"><?= e(t('تعديل')) ?></a>
            <?php if (can('automations.manage')): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الأتمتة دي؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <button class="btn-ghost text-xs text-red-600"><?= e(t('حذف')) ?></button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
