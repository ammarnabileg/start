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

// تشغيل/إيقاف من القائمة من غير تحميل الصفحة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_json') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$z) { http_response_code(422); exit(json_encode(['error' => 'Zernio'])); }
    $id = (string)($_POST['id'] ?? ''); $on = ($_POST['on'] ?? '') === '1';
    try {
        ip_update_automation($z, $id, ['isActive' => $on]);
        log_activity('automation.' . ($on ? 'enable' : 'disable'), $id);
        exit(json_encode(['ok' => true, 'on' => $on]));
    } catch (ZernioException $ex) { http_response_code(502); exit(json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE)); }
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
// بيئة الاختبار فقط: بيانات تجريبية من ملف (المتغير ده مش موجود على أي سيرفر حقيقي)
$fixture = getenv('AUTOMATIONS_FIXTURE');
if ($z) {
    try {
        if ($fixture && is_file($fixture)) {
            $fx = json_decode((string)file_get_contents($fixture), true) ?: [];
            $list = $fx['automations'] ?? [];
            foreach ($list as $it) if (($it['id'] ?? '') === $editId) { $current = $it; $logs = $it['recentLogs'] ?? []; }
            $accounts = $fx['accounts'] ?? [];
        } else {
            $list = ip_automations($z);
            if ($editId !== '') {
                $r = ip_automation($z, $editId);
                $current = $r['automation'] ?? null;
                $logs = $r['logs'] ?? $r['recentLogs'] ?? $current['recentLogs'] ?? [];
            }
            if ($isNew) $accounts = ip_accounts($z, ip_automation_platforms());
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

render(t('الردود التلقائية على التعليقات'), 'automations', function () use ($z, $list, $accounts, $current, $logs, $editId, $isNew, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    $modes = ['contains' => [t('يحتوي على الكلمة'), t('أي تعليق فيه الكلمة، حتى لو جزء من كلمة أطول.')], 'word' => [t('كلمة مستقلة'), t('الكلمة لازم تكون لوحدها، مش جزء من كلمة تانية.')], 'exact' => [t('مطابق تمامًا'), t('التعليق كله لازم يكون الكلمة دي بس.')]];
    $editing = $isNew || $current;
    $a = $current ?? [];
    $plat = (string)($a['platform'] ?? '');
    $on = 0; foreach ($list as $it) if (!empty($it['isActive'])) $on++;
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="relative flex-1 min-w-[14rem] max-w-md">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg>
        <input type="search" id="au-q" class="input !mt-0 !h-10 !py-0 ps-9 text-sm" placeholder="<?= e(t('ابحث بالاسم أو الكلمة...')) ?> (/)" dir="auto">
      </div>
      <div class="tb p-0.5 gap-0.5" id="au-f">
        <button type="button" data-f="all" class="on h-9 px-3 rounded-lg text-xs"><?= e(t('الكل')) ?> <span class="tabular-nums text-ink-400"><?= count($list) ?></span></button>
        <button type="button" data-f="on" class="h-9 px-3 rounded-lg text-xs"><?= e(t('شغالة')) ?> <span class="tabular-nums text-ink-400" id="au-non"><?= $on ?></span></button>
        <button type="button" data-f="off" class="h-9 px-3 rounded-lg text-xs"><?= e(t('متوقفة')) ?> <span class="tabular-nums text-ink-400" id="au-noff"><?= count($list) - $on ?></span></button>
      </div>
      <button type="button" id="au-help" class="tb w-10 justify-center text-sm font-semibold text-ink-500" title="<?= e(t('اختصارات الكيبورد')) ?> (?)">?</button>
      <a href="<?= e(url('automations', ['new' => 1])) ?>" class="btn-primary ms-auto"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><?= e(t('أتمتة جديدة')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">N</kbd></a>
    </div>

    <div class="grid lg:grid-cols-[22rem_1fr] 2xl:grid-cols-[22rem_1fr_20rem] gap-4 lg:h-[calc(100vh-13rem)] min-h-[560px]" id="au">
      <!-- القائمة -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $editing ? 'hidden lg:flex' : 'flex' ?>">
        <div class="flex items-center gap-2 px-4 h-12 border-b border-ink-200 shrink-0"><b class="text-sm text-ink-900"><?= e(t('الأتمتة')) ?></b><span class="text-xs text-ink-400" id="au-vis"></span></div>
        <div class="flex-1 min-h-0 overflow-y-auto" id="au-list">
          <?php if (!$list): ?>
            <div class="p-8 text-center">
              <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center"><svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 3L4 14h7l-1 7 9-11h-7z"/></svg></div>
              <p class="text-sm font-medium text-ink-900"><?= e(t('مفيش أتمتة لسه')) ?></p>
              <p class="text-xs text-ink-500 mt-1"><?= e(t('اعمل أول أتمتة: كلمة زي "بكام" في تعليق = رد تلقائي في الخاص.')) ?></p>
            </div>
          <?php endif; ?>
          <?php foreach ($list as $it): $io = !empty($it['isActive']); $s = $it['stats'] ?? []; $sel = ($it['id'] ?? '') === $editId; $kw = (array)($it['keywords'] ?? []); ?>
            <div class="au-item group relative border-b border-ink-100 transition <?= $sel ? 'bg-brand-50/70' : 'hover:bg-ink-50' ?>" data-id="<?= e($it['id'] ?? '') ?>" data-on="<?= $io ? 1 : 0 ?>" data-q="<?= e(mb_strtolower(($it['name'] ?? '') . ' ' . implode(' ', $kw))) ?>">
              <?php if ($sel): ?><span class="absolute inset-y-0 start-0 w-1 bg-brand-600 rounded-e"></span><?php endif; ?>
              <a href="<?= e(url('automations', ['id' => $it['id'] ?? ''])) ?>" class="flex gap-3 p-3.5 pe-16">
                <span class="relative shrink-0 w-10 h-10 rounded-xl <?= $io ? 'bg-brand-50 text-brand-600' : 'bg-ink-100 text-ink-400' ?> flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 3L4 14h7l-1 7 9-11h-7z"/></svg>
                  <span class="absolute -bottom-1 -end-1"><?= platform_icon((string)($it['platform'] ?? ''), 'w-[16px] h-[16px]', 'badge') ?></span>
                </span>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2"><span class="au-name text-sm font-medium text-ink-900 truncate"><?= e($it['name'] ?? '') ?></span><span class="au-dot w-1.5 h-1.5 rounded-full shrink-0 <?= $io ? 'bg-lime-500 animate-pulse' : 'bg-ink-300' ?>"></span></div>
                  <div class="flex flex-wrap gap-1 mt-1.5">
                    <?php foreach (array_slice($kw, 0, 4) as $k): ?><span class="rounded-md bg-ink-100 text-ink-600 px-1.5 py-0.5 text-[10px]"><?= e($k) ?></span><?php endforeach; ?>
                    <?php if (count($kw) > 4): ?><span class="text-[10px] text-ink-400">+<?= count($kw) - 4 ?></span><?php endif; ?>
                    <?php if (!$kw): ?><span class="rounded-md bg-amber-50 text-amber-700 px-1.5 py-0.5 text-[10px]"><?= e(t('أي تعليق')) ?></span><?php endif; ?>
                  </div>
                  <div class="flex items-center gap-3 mt-1.5 text-[11px] text-ink-500 tabular-nums">
                    <span title="<?= e(t('مرات التشغيل')) ?>">⚡ <?= (int)($s['triggered'] ?? 0) ?></span>
                    <span title="<?= e(t('رسائل اتبعتت')) ?>">✉ <?= (int)($s['dmsSent'] ?? 0) ?></span>
                    <?php if ((int)($s['dmsFailed'] ?? 0)): ?><span class="text-red-600" title="<?= e(t('رسائل فشلت')) ?>">⚠ <?= (int)$s['dmsFailed'] ?></span><?php endif; ?>
                    <span class="truncate"><?= e(!empty($it['platformPostId']) ? t('منشور واحد') : t('كل المنشورات')) ?></span>
                  </div>
                </div>
              </a>
              <label class="absolute top-4 end-3.5 inline-flex cursor-pointer" title="<?= e($io ? t('إيقاف') : t('تشغيل')) ?>">
                <input type="checkbox" class="au-toggle sr-only peer" <?= $io ? 'checked' : '' ?>>
                <span class="w-9 h-5 rounded-full bg-ink-300 peer-checked:bg-lime-500 transition relative after:absolute after:top-0.5 after:start-0.5 after:w-4 after:h-4 after:rounded-full after:bg-[#fff] after:shadow after:transition peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"></span>
              </label>
            </div>
          <?php endforeach; ?>
          <div id="au-empty" class="hidden p-8 text-center text-sm text-ink-500"><?= e(t('مفيش نتائج.')) ?></div>
        </div>
      </div>

      <!-- المحرر -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= $editing ? 'flex' : 'hidden lg:flex' ?>">
        <?php if (!$editing): ?>
          <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center mb-4"><svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M13 3L4 14h7l-1 7 9-11h-7z"/></svg></div>
            <p class="font-medium text-ink-900"><?= e(t('اختار أتمتة عشان تعدّلها، أو اعمل واحدة جديدة')) ?></p>
            <p class="text-xs text-ink-500 mt-1 max-w-sm"><?= e(t('لما حد يكتب كلمة معينة في تعليق، يوصله رد تلقائي في الخاص و/أو رد عام تحت تعليقه. الرسائل الخاصة متاحة على إنستجرام وفيسبوك بس.')) ?></p>
            <a href="<?= e(url('automations', ['new' => 1])) ?>" class="btn-primary mt-4"><?= e(t('أتمتة جديدة')) ?></a>
          </div>
        <?php else: ?>
          <form method="post" id="au-form" class="flex flex-col min-h-0 flex-1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $current ? 'update' : 'create' ?>">
            <?php if ($current): ?><input type="hidden" name="id" value="<?= e($a['id'] ?? '') ?>"><input type="hidden" name="platform" value="<?= e($plat) ?>"><?php endif; ?>
            <div class="px-4 h-14 border-b border-ink-200 flex items-center gap-3 shrink-0">
              <a href="<?= e(url('automations')) ?>" class="lg:hidden p-2 -ms-2 rounded-lg hover:bg-ink-100"><svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></a>
              <input name="name" required maxlength="100" class="flex-1 min-w-0 bg-transparent border-0 p-0 text-base font-semibold text-ink-900 focus:ring-0 focus:outline-none placeholder:text-ink-400" value="<?= e($a['name'] ?? '') ?>" placeholder="<?= e(t('اسم الأتمتة، مثلًا: السعر في الخاص')) ?>" dir="auto">
              <label class="inline-flex items-center gap-2 text-xs text-ink-600 cursor-pointer"><input type="checkbox" name="is_active" value="1" class="sr-only peer" <?= ($a['isActive'] ?? true) ? 'checked' : '' ?>><span class="w-9 h-5 rounded-full bg-ink-300 peer-checked:bg-lime-500 transition relative after:absolute after:top-0.5 after:start-0.5 after:w-4 after:h-4 after:rounded-full after:bg-[#fff] after:shadow after:transition peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"></span><span class="au-onlbl"></span></label>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-6">
              <!-- 1 المكان -->
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">1</span><b class="text-sm text-ink-900"><?= e(t('فين؟')) ?></b></div>
                <?php if (!$current): ?>
                  <div class="grid sm:grid-cols-2 gap-2" id="au-accs">
                    <?php foreach ($accounts as $i => $acc): ?>
                      <label class="flex items-center gap-2.5 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:bg-ink-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 transition">
                        <input type="radio" name="account_id" value="<?= e($acc['_id']) ?>" data-plat="<?= e($acc['platform'] ?? '') ?>" class="sr-only" <?= $i === 0 ? 'checked' : '' ?> required>
                        <?= platform_icon((string)($acc['platform'] ?? ''), 'w-5 h-5') ?><span class="text-sm truncate"><?= e(ip_account_label($acc)) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!$accounts): ?><p class="text-xs text-red-600"><?= e(t('مفيش حساب مربوط يدعم الأتمتة (إنستجرام، فيسبوك، تيك توك، ثريدز، لينكدإن، يوتيوب).')) ?></p><?php endif; ?>
                  <details class="text-sm"><summary class="cursor-pointer text-xs text-brand-700"><?= e(t('على منشور واحد بس؟ (اختياري)')) ?></summary>
                    <input name="platform_post_id" class="input mt-2" dir="ltr" placeholder="<?= e(t('رقم المنشور على المنصة. سيبه فاضي عشان تشتغل على كل المنشورات')) ?>"></details>
                <?php else: ?>
                  <p class="flex items-center gap-2 text-sm text-ink-700"><?= platform_icon($plat, 'w-5 h-5') ?><?= e(platform_label($plat)) ?> · <?= e(!empty($a['platformPostId']) ? t('منشور واحد: %s', $a['postTitle'] ?? $a['platformPostId']) : t('كل المنشورات')) ?></p>
                <?php endif; ?>
              </section>
              <!-- 2 الكلمات -->
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">2</span><b class="text-sm text-ink-900"><?= e(t('لما التعليق فيه...')) ?></b></div>
                <input type="hidden" name="keywords" id="au-kw" value="<?= e(implode(', ', (array)($a['keywords'] ?? []))) ?>">
                <div id="au-chips" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-ink-200 bg-white px-2 py-2 min-h-[2.75rem] focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/10 transition cursor-text">
                  <input id="au-kwin" class="flex-1 min-w-[8rem] border-0 bg-transparent p-1 text-sm focus:ring-0" placeholder="<?= e(t('اكتب كلمة واضغط Enter')) ?>" dir="auto">
                </div>
                <div class="flex flex-wrap gap-1.5" id="au-sugg"></div>
                <p class="text-xs text-ink-500" id="au-kwhint"><?= e(t('لو سبتها فاضية، أي تعليق هيشغلها.')) ?></p>
                <input type="hidden" name="match_mode" id="au-mode" value="<?= e($a['matchMode'] ?? 'contains') ?>">
                <div class="grid sm:grid-cols-3 gap-2" id="au-modes">
                  <?php foreach ($modes as $mk => [$ml, $md]): ?>
                    <button type="button" data-mode="<?= $mk ?>" class="text-start rounded-xl border border-ink-200 px-3 py-2 hover:bg-ink-50 transition"><b class="block text-xs text-ink-900"><?= e($ml) ?></b><span class="block text-[11px] text-ink-500 mt-0.5"><?= e($md) ?></span></button>
                  <?php endforeach; ?>
                </div>
              </section>
              <!-- 3 الرد -->
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">3</span><b class="text-sm text-ink-900"><?= e(t('يرد بـ...')) ?></b></div>
                <div id="au-dmwrap" class="<?= ($current && !ip_automation_dm_platform($plat)) ? 'hidden' : '' ?>">
                  <div class="flex items-center justify-between"><label class="label !mb-0"><?= e(t('رسالة في الخاص (DM)')) ?></label><span class="au-cnt text-[11px] text-ink-400 tabular-nums" data-for="dm_message"></span></div>
                  <textarea data-ai="auto_msg,improve" name="dm_message" rows="3" maxlength="1000" class="input" dir="auto" placeholder="<?= e(t('أهلاً! السعر ... وللطلب ابعتلنا هنا')) ?>"><?= e($a['dmMessage'] ?? '') ?></textarea>
                  <p class="text-[11px] text-ink-500 mt-1"><?= e(t('متاحة على إنستجرام وفيسبوك بس، ومطلوبة فيهم.')) ?></p>
                </div>
                <div id="au-nodm" class="hidden rounded-xl bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800"><?= e(t('المنصة دي مش بتدعم الرسائل الخاصة، فالرد العام هو اللي هيتبعت.')) ?></div>
                <div>
                  <div class="flex items-center justify-between"><label class="label !mb-0"><?= e(t('رد عام تحت التعليق')) ?> <span class="text-ink-400 font-normal" id="au-reqlbl"><?= e(t('(اختياري)')) ?></span></label><span class="au-cnt text-[11px] text-ink-400 tabular-nums" data-for="comment_reply"></span></div>
                  <textarea data-ai="auto_msg,polish_reply" name="comment_reply" rows="2" maxlength="1000" class="input" dir="auto" placeholder="<?= e(t('بعتنالك التفاصيل في الخاص')) ?>"><?= e($a['commentReply'] ?? '') ?></textarea>
                </div>
              </section>
            </div>
            <div class="px-4 py-3 border-t border-ink-200 flex items-center gap-3 shrink-0 bg-white">
              <span id="au-dirty" class="hidden text-xs text-amber-700">● <?= e(t('تعديلات مش محفوظة')) ?></span>
              <span id="au-err" class="text-xs text-red-600"></span>
              <?php if ($current && can('automations.manage')): ?>
                <button type="submit" form="au-del" class="btn-ghost !text-xs text-red-600 ms-auto"><?= e(t('حذف')) ?></button>
              <?php endif; ?>
              <button class="btn-primary <?= $current && can('automations.manage') ? '' : 'ms-auto' ?>"><?= e($current ? t('حفظ') : t('إنشاء')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">Ctrl S</kbd></button>
            </div>
          </form>
          <?php if ($current && can('automations.manage')): ?>
            <form method="post" id="au-del" class="hidden" onsubmit="return confirm('<?= tjs('حذف الأتمتة دي؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($a['id'] ?? '') ?>"></form>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- المعاينة والإحصائيات -->
      <aside class="hidden 2xl:flex card flex-col min-h-0 overflow-y-auto">
        <?php if ($editing): ?>
          <div class="p-4 border-b border-ink-100">
            <b class="text-sm text-ink-900"><?= e(t('جرّب تعليق')) ?></b>
            <input id="au-try" class="input mt-2 text-sm" dir="auto" placeholder="<?= e(t('اكتب تعليق زي ما العميل هيكتبه...')) ?>">
            <div id="au-res" class="mt-2 rounded-xl px-3 py-2 text-xs hidden"></div>
          </div>
          <div class="p-4 border-b border-ink-100 space-y-3">
            <b class="text-sm text-ink-900"><?= e(t('هيظهر كده')) ?></b>
            <div class="rounded-2xl bg-ink-50 border border-ink-200 p-3 space-y-2.5 text-sm">
              <div class="flex gap-2"><span class="w-7 h-7 rounded-full bg-ink-200 shrink-0"></span><div class="rounded-2xl rounded-ss-md bg-white border border-ink-200 px-3 py-1.5"><b class="block text-[11px] text-ink-900"><?= e(t('عميل')) ?></b><span id="pv-c" class="text-ink-700" dir="auto"></span></div></div>
              <div id="pv-rw" class="flex gap-2 ms-9"><span class="w-6 h-6 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500 shrink-0"></span><div class="rounded-2xl rounded-ss-md bg-brand-50 border border-brand-200 px-3 py-1.5"><b class="block text-[11px] text-ink-900"><?= e(ws('name')) ?></b><span id="pv-r" class="text-ink-700 whitespace-pre-wrap" dir="auto"></span></div></div>
            </div>
            <div id="pv-dw" class="rounded-2xl bg-ink-50 border border-ink-200 p-3">
              <div class="text-[11px] text-ink-500 mb-2 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></svg><?= e(t('في الخاص')) ?></div>
              <div class="flex justify-start"><div class="max-w-[90%] rounded-2xl rounded-ss-md bg-gradient-to-br from-brand-600 to-brand-700 text-[#fff] px-3 py-2 text-sm whitespace-pre-wrap" id="pv-d" dir="auto"></div></div>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($current): $s = $current['stats'] ?? []; $tr = (int)($s['triggered'] ?? 0); $ok = (int)($s['dmsSent'] ?? 0); $fl = (int)($s['dmsFailed'] ?? 0); ?>
          <div class="p-4 border-b border-ink-100">
            <b class="text-sm text-ink-900"><?= e(t('الإحصائيات')) ?></b>
            <div class="grid grid-cols-2 gap-2 mt-3">
              <?php foreach ([[$tr, t('مرات التشغيل'), 'text-ink-900'], [$ok, t('رسائل اتبعتت'), 'text-lime-700'], [$fl, t('رسائل فشلت'), $fl ? 'text-red-600' : 'text-ink-900'], [(int)($s['uniqueContacts'] ?? 0), t('أشخاص'), 'text-ink-900']] as [$v, $l, $c]): ?>
                <div class="rounded-xl bg-ink-50 border border-ink-200 p-3"><div class="text-xl font-bold tabular-nums <?= $c ?>" data-count="<?= $v ?>">0</div><div class="text-[11px] text-ink-500"><?= e($l) ?></div></div>
              <?php endforeach; ?>
            </div>
            <?php if ($ok + $fl): $rate = round(100 * $ok / ($ok + $fl)); ?>
              <div class="mt-3"><div class="flex justify-between text-[11px] text-ink-500"><span><?= e(t('نسبة النجاح')) ?></span><b class="text-ink-900"><?= $rate ?>%</b></div><div class="h-1.5 rounded-full bg-ink-100 mt-1 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-lime-500 to-brand-600" style="width:<?= $rate ?>%"></div></div></div>
            <?php endif; ?>
          </div>
          <div class="p-4">
            <b class="text-sm text-ink-900"><?= e(t('آخر التشغيلات')) ?></b>
            <?php if (!$logs): ?><p class="text-xs text-ink-500 mt-2"><?= e(t('لسه ماشتغلتش.')) ?></p><?php endif; ?>
            <ul class="mt-2 space-y-2">
              <?php foreach (array_slice($logs, 0, 15) as $l): $stt = (string)($l['status'] ?? ''); ?>
                <li class="rounded-xl border border-ink-200 p-2.5">
                  <div class="flex items-center gap-2"><b class="text-xs text-ink-900 truncate flex-1"><?= e($l['commenterName'] ?? $l['commenterUsername'] ?? '') ?></b><span class="rounded-md px-1.5 py-0.5 text-[10px] <?= in_array($stt, ['sent', 'success', 'ok'], true) ? 'bg-lime-100 text-lime-700' : (in_array($stt, ['failed', 'error'], true) ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-600') ?>"><?= e($stt) ?></span></div>
                  <p class="text-xs text-ink-600 mt-1 line-clamp-2" dir="auto"><?= e($l['commentText'] ?? '') ?></p>
                  <p class="text-[10px] text-ink-400 mt-0.5"><?= e(fmt_date($l['createdAt'] ?? null, 'd/m h:i A')) ?></p>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php elseif (!$editing): ?>
          <div class="p-5 text-sm text-ink-600 space-y-2">
            <b class="text-ink-900"><?= e(t('إزاي بتشتغل؟')) ?></b>
            <p><?= e(t('لما حد يكتب تعليق فيه واحدة من الكلمات دي، البرنامج بيبعتله الرسالة الخاصة أوتوماتيك، وممكن كمان يرد عليه رد عام تحت التعليق.')) ?></p>
            <p><?= e(t('كل شخص بياخد الرسالة مرة واحدة بس لكل أتمتة.')) ?></p>
          </div>
        <?php endif; ?>
      </aside>
    </div>
    <script>window.AUX = <?= json_encode(['dm' => array_values(array_filter(ip_automation_platforms(), 'ip_automation_dm_platform')), 'plat' => $plat, 'editing' => (bool)$editing,
      'on' => t('شغالة'), 'off' => t('متوقفة'), 'match' => t('هتشتغل ✓ — اتطابقت مع: %s'), 'nomatch' => t('مش هتشتغل — مفيش كلمة مطابقة'), 'any' => t('هتشتغل ✓ — أي تعليق بيشغلها'), 'sampleC' => t('بكام ده؟'), 'emptyR' => t('(من غير رد عام)'), 'emptyD' => t('(اكتب الرسالة الخاصة)'),
      'kwN' => t('%s كلمة · أي تعليق فيه واحدة منهم يشغلها'), 'kwAny' => t('لو سبتها فاضية، أي تعليق هيشغلها.'), 'sugg' => ['سعر', 'بكام', 'price', 'تفاصيل', 'متاح', 'طلب', 'عرض', 'how much', 'info'], 'optional' => t('(اختياري)'), 'required' => t('(مطلوب)'),
      'needName' => t('اكتب اسم للأتمتة.'), 'needDm' => t('اكتب الرسالة الخاصة اللي هتتبعت.'), 'needReply' => t('المنصة دي بتدعم الرد العام بس، فاكتب الرد العام.'), 'leave' => t('فيه تعديلات مش محفوظة. تخرج من غير حفظ؟'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
      'toggled_on' => t('الأتمتة اشتغلت.'), 'toggled_off' => t('الأتمتة اتوقفت.'), 'keysTitle' => t('اختصارات الكيبورد'),
      'keys' => [['j k', t('الأتمتة التالية / السابقة')], ['n', t('أتمتة جديدة')], ['/', t('البحث')], ['t', t('تشغيل / إيقاف المحددة')], ['Ctrl S', t('حفظ')], ['?', t('الاختصارات')]]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/automations.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
