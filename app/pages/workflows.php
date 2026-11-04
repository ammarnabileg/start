<?php
$err = null;
$z = zernio_or_null($err);
$viewId = (string)($_GET['id'] ?? '');
$isNew = !empty($_GET['new']);
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'draft', 'active', 'paused'], true)) $statusFilter = '';

// تشغيل/إيقاف من غير تحميل الصفحة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_json') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$z) { http_response_code(422); exit(json_encode(['error' => 'Zernio'])); }
    $id = (string)($_POST['id'] ?? ''); $on = ($_POST['on'] ?? '') === '1';
    try {
        ip_workflow_action($z, $id, $on ? 'activate' : 'pause');
        log_activity('workflow.' . ($on ? 'activate' : 'pause'), $id);
        exit(json_encode(['ok' => true, 'status' => $on ? 'active' : 'paused']));
    } catch (ZernioException $ex) { http_response_code(502); exit(json_encode(['error' => $ex->getMessage()], JSON_UNESCAPED_UNICODE)); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $id = (string)($_POST['id'] ?? '');
    $back = url('workflows');
    try {
        if ($action === 'create') {
            $back = url('workflows', ['new' => 1]);
            $accountId = (string)($_POST['account_id'] ?? '');
            $account = null;
            foreach (ip_accounts($z, ip_workflow_platforms()) as $a) {
                if ($a['_id'] === $accountId) $account = $a;
            }
            $name = trim((string)($_POST['name'] ?? ''));
            $trigger = (string)($_POST['trigger'] ?? 'inbound_message');
            if (!in_array($trigger, ['inbound_message', 'comment'], true)) $trigger = 'inbound_message';
            $match = (string)($_POST['match_type'] ?? 'contains');
            if (!in_array($match, ['contains', 'exact'], true)) $match = 'contains';
            $text = trim((string)($_POST['text'] ?? ''));
            $keywords = ip_split_keywords((string)($_POST['keywords'] ?? ''));
            if (!$account) {
                flash('error', t('اختار الحساب.'));
            } elseif ($name === '' || $text === '') {
                flash('error', t('الاسم والرسالة مطلوبين.'));
            } elseif ($trigger === 'comment' && !in_array($account['platform'], ['instagram', 'facebook'], true)) {
                flash('error', t('التشغيل بالتعليقات متاح لإنستجرام وفيسبوك بس.'));
            } else {
                $profileId = is_string($account['profileId'] ?? null) ? $account['profileId'] : (string)($account['profileId']['_id'] ?? '');
                $body = ip_simple_workflow_body($profileId !== '' ? $profileId : current_profile_id($z), $account, mb_substr($name, 0, 100), $trigger, $keywords, $match, $text);
                $r = $z->request('POST', '/v1/workflows', [], $body);
                $newId = (string)($r['workflow']['id'] ?? '');
                log_activity('workflow.create', $name);
                flash('success', t('اتعمل الـ Workflow كمسودة. دوس (تشغيل) عشان يبدأ يشتغل.'));
                $back = $newId !== '' ? url('workflows', ['id' => $newId]) : url('workflows');
            }
        } elseif (($action === 'activate' || $action === 'pause') && $id !== '') {
            ip_workflow_action($z, $id, $action);
            log_activity('workflow.' . $action, $id);
            flash('success', $action === 'activate' ? t('الـ Workflow اشتغل.') : t('الـ Workflow اتوقف.'));
            $back = !empty($_POST['from_view']) ? url('workflows', ['id' => $id]) : url('workflows');
        } elseif ($action === 'delete' && $id !== '') {
            if (!can('automations.manage')) {
                flash('error', t('الحذف لصاحب الشركة بس.'));
            } else {
                ip_delete_workflow($z, $id);
                log_activity('workflow.delete', $id);
                flash('success', t('تم حذف الـ Workflow.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
        if ($id !== '' && !empty($_POST['from_view'])) $back = url('workflows', ['id' => $id]);
    }
    redirect($back);
}

$list = [];
$wf = null;
$runs = [];
$accounts = [];
$apiError = null;
$fixture = getenv('WORKFLOWS_FIXTURE'); // بيئة الاختبار فقط
if ($z) {
    try {
        if ($fixture && is_file($fixture)) {
            $fx = json_decode((string)file_get_contents($fixture), true) ?: [];
            $list = $fx['workflows'] ?? [];
            foreach ($list as $it) if (($it['id'] ?? '') === $viewId) { $wf = $it; $runs = $it['runs'] ?? []; }
            $accounts = $fx['accounts'] ?? [];
        } else {
            $list = ip_workflows($z, null);
            if ($viewId !== '') {
                $wf = ip_workflow($z, $viewId);
                try { $runs = ip_workflow_runs($z, $viewId)['executions'] ?? []; } catch (ZernioException $ex) { $runs = []; }
            }
            if ($isNew) $accounts = ip_accounts($z, ip_workflow_platforms());
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function wf_status_badge(string $s): string
{
    $labels = ['active' => t('شغال'), 'paused' => t('متوقف'), 'draft' => t('مسودة')];
    $cls = ['active' => 'bg-lime-100 text-lime-700', 'paused' => 'bg-amber-100 text-amber-800', 'draft' => 'bg-ink-100 text-ink-600'][$s] ?? 'bg-ink-100 text-ink-600';
    return '<span class="wf-badge rounded-md px-1.5 py-0.5 text-[10px] font-medium ' . $cls . '">' . e($labels[$s] ?? $s) . '</span>';
}

function wf_node_summary(array $n): string
{
    $c = $n['config'] ?? [];
    switch ($n['type'] ?? '') {
        case 'trigger':
            $tt = $c['triggerType'] ?? 'inbound_message';
            $kw = $tt === 'comment' ? ($c['comment']['keywords'] ?? []) : ($c['keywords'] ?? []);
            $types = ['inbound_message' => t('رسالة واردة'), 'comment' => t('تعليق'), 'api_call' => t('استدعاء API'), 'referral' => t('إعلان أو رابط'), 'story_mention' => t('منشن في ستوري'), 'reaction' => t('تفاعل'), 'whatsapp_event' => t('حالة رسالة واتساب')];
            return ($types[$tt] ?? $tt) . ($kw ? ' · ' . implode('، ', $kw) : ' · ' . t('أي رسالة'));
        case 'send_message':
            return (string)($c['text'] ?? $c['template']['name'] ?? $c['messageType'] ?? '');
        case 'delay':
            $d = (int)($c['duration'] ?? $c['seconds'] ?? 0);
            return $d ? t('انتظار %s', $d >= 3600 ? round($d / 3600, 1) . ' ' . t('ساعة') : ($d >= 60 ? round($d / 60) . ' ' . t('دقيقة') : $d . ' ' . t('ثانية'))) : json_encode($c, JSON_UNESCAPED_UNICODE);
        default:
            return $c ? mb_substr(json_encode($c, JSON_UNESCAPED_UNICODE), 0, 140) : '';
    }
}

/** ترتيب الخطوات حسب الأسهم (entry ← ... ) عشان الرسم يمشي صح */
function wf_ordered_nodes(array $wf): array
{
    $nodes = []; foreach ((array)($wf['nodes'] ?? []) as $n) $nodes[(string)($n['id'] ?? '')] = $n;
    $next = []; foreach ((array)($wf['edges'] ?? []) as $e) $next[(string)($e['source'] ?? '')][] = (string)($e['target'] ?? '');
    $start = (string)($wf['entryNodeId'] ?? ''); if (!isset($nodes[$start])) $start = (string)array_key_first($nodes);
    $out = []; $seen = []; $queue = [$start];
    while ($queue) { $id = array_shift($queue); if ($id === '' || isset($seen[$id]) || !isset($nodes[$id])) continue; $seen[$id] = 1; $out[] = $nodes[$id]; foreach ($next[$id] ?? [] as $t) $queue[] = $t; }
    foreach ($nodes as $id => $n) if (!isset($seen[$id])) $out[] = $n;
    return $out;
}

render(t('الـ Workflows'), 'workflows', function () use ($z, $list, $wf, $runs, $accounts, $isNew, $viewId, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }
    $cnt = ['active' => 0, 'paused' => 0, 'draft' => 0]; foreach ($list as $w) { $s = (string)($w['status'] ?? ''); if (isset($cnt[$s])) $cnt[$s]++; }
    $viewing = $viewId !== '' && $wf;
    $nodeIco = ['trigger' => 'M13 3L4 14h7l-1 7 9-11h-7z', 'send_message' => 'M4 5h16v11H9l-5 4zM8 9h8M8 12h5', 'delay' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2', 'condition' => 'M6 3v6a3 3 0 003 3h6a3 3 0 013 3v6M18 3v3M6 21v-3'];
    $nodeLbl = ['trigger' => t('يبدأ لما'), 'send_message' => t('يبعت رسالة'), 'delay' => t('ينتظر'), 'condition' => t('شرط')];
    ?>
    <div class="flex flex-wrap items-center gap-2 mb-4">
      <div class="relative flex-1 min-w-[14rem] max-w-md">
        <svg class="w-4 h-4 absolute top-1/2 -translate-y-1/2 start-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/></svg>
        <input type="search" id="wf-q" class="input !mt-0 !h-10 !py-0 ps-9 text-sm" placeholder="<?= e(t('ابحث بالاسم...')) ?> (/)" dir="auto">
      </div>
      <div class="tb p-0.5 gap-0.5" id="wf-f">
        <?php foreach (['all' => [t('الكل'), count($list)], 'active' => [t('شغال'), $cnt['active']], 'paused' => [t('متوقف'), $cnt['paused']], 'draft' => [t('مسودة'), $cnt['draft']]] as $fk => [$fl, $fn]): ?>
          <button type="button" data-f="<?= $fk ?>" class="h-9 px-3 rounded-lg text-xs <?= $fk === 'all' ? 'on bg-white shadow-sm text-ink-900 font-medium' : '' ?>"><?= e($fl) ?> <span class="wf-n tabular-nums text-ink-400" data-n="<?= $fk ?>"><?= $fn ?></span></button>
        <?php endforeach; ?>
      </div>
      <button type="button" id="wf-help" class="tb w-10 justify-center text-sm font-semibold text-ink-500" title="<?= e(t('اختصارات الكيبورد')) ?> (?)">?</button>
      <a href="<?= e(url('workflows', ['new' => 1])) ?>" class="btn-primary ms-auto"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><?= e(t('Workflow جديد')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">N</kbd></a>
    </div>

    <div class="grid lg:grid-cols-[22rem_1fr] 2xl:grid-cols-[22rem_1fr_20rem] gap-4 lg:h-[calc(100vh-13rem)] min-h-[560px]" id="wf">
      <!-- القائمة -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= ($isNew || $viewing) ? 'hidden lg:flex' : 'flex' ?>">
        <div class="flex items-center gap-2 px-4 h-12 border-b border-ink-200 shrink-0"><b class="text-sm text-ink-900"><?= e(t('الـ Workflows')) ?></b><span class="text-xs text-ink-400" id="wf-vis"></span></div>
        <div class="flex-1 min-h-0 overflow-y-auto" id="wf-list">
          <?php if (!$list): ?>
            <div class="p-8 text-center">
              <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center"><svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 4h4v4H5zM15 16h4v4h-4zM7 8v4a2 2 0 002 2h6a2 2 0 012 2"/></svg></div>
              <p class="text-sm font-medium text-ink-900"><?= e(t('مفيش Workflows لسه')) ?></p>
              <p class="text-xs text-ink-500 mt-1"><?= e(t('اعمل أول واحد: لما حد يبعت رسالة فيها "أهلاً"، يوصله رد ترحيب تلقائي.')) ?></p>
            </div>
          <?php endif; ?>
          <?php foreach ($list as $w): $st = (string)($w['status'] ?? ''); $sel = ($w['id'] ?? '') === $viewId; $bs = (int)($w['totalStarted'] ?? 0); $bc = (int)($w['totalCompleted'] ?? 0); $rate = $bs ? round(100 * $bc / $bs) : 0; ?>
            <div class="wf-item group relative border-b border-ink-100 transition <?= $sel ? 'bg-brand-50/70' : 'hover:bg-ink-50' ?>" data-id="<?= e($w['id'] ?? '') ?>" data-st="<?= e($st) ?>" data-q="<?= e(mb_strtolower(($w['name'] ?? '') . ' ' . ($w['accountName'] ?? ''))) ?>">
              <?php if ($sel): ?><span class="absolute inset-y-0 start-0 w-1 bg-brand-600 rounded-e"></span><?php endif; ?>
              <a href="<?= e(url('workflows', ['id' => $w['id'] ?? ''])) ?>" class="flex gap-3 p-3.5 pe-16">
                <span class="relative shrink-0 w-10 h-10 rounded-xl <?= $st === 'active' ? 'bg-brand-50 text-brand-600' : 'bg-ink-100 text-ink-400' ?> flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 4h4v4H5zM15 16h4v4h-4zM7 8v4a2 2 0 002 2h6a2 2 0 012 2"/></svg>
                  <span class="absolute -bottom-1 -end-1"><?= platform_icon((string)($w['platform'] ?? ''), 'w-[16px] h-[16px]', 'badge') ?></span>
                </span>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2"><span class="text-sm font-medium text-ink-900 truncate"><?= e($w['name'] ?? '') ?></span><?= wf_status_badge($st) ?></div>
                  <p class="text-[11px] text-ink-500 mt-0.5 truncate"><?= e($w['accountName'] ?? platform_label((string)($w['platform'] ?? ''))) ?> · <?= e(t('%s خطوة', (int)($w['nodeCount'] ?? count($w['nodes'] ?? [])))) ?></p>
                  <div class="flex items-center gap-2 mt-1.5"><div class="flex-1 h-1 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-lime-500 to-brand-600" style="width:<?= $rate ?>%"></div></div><span class="text-[10px] text-ink-500 tabular-nums" title="<?= e(t('بدأ %s مرة · خلص %s', $bs, $bc)) ?>"><?= $bc ?>/<?= $bs ?></span></div>
                </div>
              </a>
              <?php if ($st !== 'draft' || true): ?>
              <label class="absolute top-4 end-3.5 inline-flex cursor-pointer" title="<?= e($st === 'active' ? t('إيقاف') : t('تشغيل')) ?>">
                <input type="checkbox" class="wf-toggle sr-only peer" <?= $st === 'active' ? 'checked' : '' ?>>
                <span class="w-9 h-5 rounded-full bg-ink-300 peer-checked:bg-lime-500 transition relative after:absolute after:top-0.5 after:start-0.5 after:w-4 after:h-4 after:rounded-full after:bg-[#fff] after:shadow after:transition peer-checked:after:translate-x-4 rtl:peer-checked:after:-translate-x-4"></span>
              </label>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <div id="wf-empty" class="hidden p-8 text-center text-sm text-ink-500"><?= e(t('مفيش نتائج.')) ?></div>
        </div>
      </div>

      <!-- الوسط -->
      <div class="card flex flex-col min-h-0 overflow-hidden <?= ($isNew || $viewing) ? 'flex' : 'hidden lg:flex' ?>">
        <?php if ($isNew): ?>
          <form method="post" id="wf-form" class="flex flex-col min-h-0 flex-1">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="px-4 h-14 border-b border-ink-200 flex items-center gap-3 shrink-0">
              <a href="<?= e(url('workflows')) ?>" class="lg:hidden p-2 -ms-2 rounded-lg hover:bg-ink-100"><svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></a>
              <input name="name" required maxlength="100" class="flex-1 min-w-0 bg-transparent border-0 p-0 text-base font-semibold text-ink-900 focus:ring-0 focus:outline-none placeholder:text-ink-400" placeholder="<?= e(t('اسم الـ Workflow، مثلًا: رد الترحيب')) ?>" dir="auto">
              <span class="rounded-md bg-ink-100 text-ink-600 px-2 py-0.5 text-[11px]"><?= e(t('هيتعمل كمسودة')) ?></span>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-6">
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">1</span><b class="text-sm text-ink-900"><?= e(t('على أنهي حساب؟')) ?></b></div>
                <div class="grid sm:grid-cols-2 gap-2" id="wf-accs">
                  <?php foreach ($accounts as $i => $acc): ?>
                    <label class="flex items-center gap-2.5 rounded-xl border border-ink-200 px-3 py-2.5 cursor-pointer hover:bg-ink-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20 transition">
                      <input type="radio" name="account_id" value="<?= e($acc['_id']) ?>" data-plat="<?= e($acc['platform'] ?? '') ?>" class="sr-only" <?= $i === 0 ? 'checked' : '' ?> required>
                      <?= platform_icon((string)($acc['platform'] ?? ''), 'w-5 h-5') ?><span class="text-sm truncate"><?= e(ip_account_label($acc)) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
                <?php if (!$accounts): ?><p class="text-xs text-red-600"><?= e(t('مفيش حساب رسائل مربوط.')) ?></p><?php endif; ?>
              </section>
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">2</span><b class="text-sm text-ink-900"><?= e(t('يبدأ لما...')) ?></b></div>
                <input type="hidden" name="trigger" id="wf-trig" value="inbound_message">
                <div class="grid sm:grid-cols-2 gap-2" id="wf-trigs">
                  <button type="button" data-t="inbound_message" class="text-start rounded-xl border border-ink-200 p-3 hover:bg-ink-50 transition"><span class="flex items-center gap-2"><svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z"/></svg><b class="text-sm text-ink-900"><?= e(t('حد يبعت رسالة خاصة')) ?></b></span><span class="block text-[11px] text-ink-500 mt-1"><?= e(t('على كل المنصات المربوطة')) ?></span></button>
                  <button type="button" data-t="comment" class="text-start rounded-xl border border-ink-200 p-3 hover:bg-ink-50 transition disabled:opacity-40 disabled:cursor-not-allowed"><span class="flex items-center gap-2"><svg class="w-5 h-5 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 4h16v12H8l-4 4zM8 9h8M8 12h4"/></svg><b class="text-sm text-ink-900"><?= e(t('حد يكتب تعليق')) ?></b></span><span class="block text-[11px] text-ink-500 mt-1"><?= e(t('إنستجرام وفيسبوك بس')) ?></span></button>
                </div>
              </section>
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">3</span><b class="text-sm text-ink-900"><?= e(t('وفيها الكلمات دي (اختياري)')) ?></b></div>
                <input type="hidden" name="keywords" id="wf-kw" value="">
                <div id="wf-chips" class="flex flex-wrap items-center gap-1.5 rounded-xl border border-ink-200 bg-white px-2 py-2 min-h-[2.75rem] focus-within:border-brand-400 focus-within:ring-4 focus-within:ring-brand-500/10 transition cursor-text">
                  <input id="wf-kwin" class="flex-1 min-w-[8rem] border-0 bg-transparent p-1 text-sm focus:ring-0 focus:outline-none" placeholder="<?= e(t('اكتب كلمة واضغط Enter')) ?>" dir="auto">
                </div>
                <p class="text-xs text-ink-500" id="wf-kwhint"></p>
                <input type="hidden" name="match_type" id="wf-mt" value="contains">
                <div class="grid sm:grid-cols-2 gap-2" id="wf-mts">
                  <button type="button" data-m="contains" class="text-start rounded-xl border border-ink-200 px-3 py-2 hover:bg-ink-50 transition"><b class="block text-xs text-ink-900"><?= e(t('الرسالة فيها الكلمة')) ?></b><span class="block text-[11px] text-ink-500 mt-0.5"><?= e(t('"عاوز أعرف السعر" بتشغّل كلمة "السعر"')) ?></span></button>
                  <button type="button" data-m="exact" class="text-start rounded-xl border border-ink-200 px-3 py-2 hover:bg-ink-50 transition"><b class="block text-xs text-ink-900"><?= e(t('الرسالة هي الكلمة بالظبط')) ?></b><span class="block text-[11px] text-ink-500 mt-0.5"><?= e(t('لازم يكتب "السعر" بس')) ?></span></button>
                </div>
              </section>
              <section class="space-y-3">
                <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-brand-600 text-[#fff] text-xs font-semibold flex items-center justify-center">4</span><b class="text-sm text-ink-900"><?= e(t('يرد بالرسالة دي')) ?></b></div>
                <div class="flex flex-wrap items-center gap-1.5"><span class="text-[11px] text-ink-500"><?= e(t('إدراج:')) ?></span><button type="button" data-var="{{contact.name}}" class="rounded-lg border border-brand-200 bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700 hover:bg-brand-100"><?= e(t('اسم الشخص')) ?></button><span class="ms-auto wf-cnt text-[11px] text-ink-400 tabular-nums"></span></div>
                <textarea data-ai="auto_msg,improve" name="text" required rows="4" maxlength="1000" class="input" dir="auto" placeholder="<?= e(t('أهلاً {{contact.name}}! إزاي نقدر نساعدك؟')) ?>"></textarea>
                <p class="text-[11px] text-ink-500"><?= e(t('الرسائل بتتبعت بس جوه نافذة الـ 24 ساعة بتاعة المنصة.')) ?></p>
              </section>
            </div>
            <div class="px-4 py-3 border-t border-ink-200 flex items-center gap-3 shrink-0 bg-white">
              <span id="wf-dirty" class="hidden text-xs text-amber-700">● <?= e(t('تعديلات مش محفوظة')) ?></span>
              <span id="wf-err" class="text-xs text-red-600"></span>
              <button class="btn-primary ms-auto"><?= e(t('إنشاء كمسودة')) ?> <kbd class="hidden sm:inline text-[10px] opacity-70">Ctrl S</kbd></button>
            </div>
          </form>
        <?php elseif ($viewing): $st = (string)($wf['status'] ?? ''); $nodes = wf_ordered_nodes($wf); ?>
          <div class="px-4 h-14 border-b border-ink-200 flex items-center gap-3 shrink-0">
            <a href="<?= e(url('workflows')) ?>" class="lg:hidden p-2 -ms-2 rounded-lg hover:bg-ink-100"><svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg></a>
            <?= platform_icon((string)($wf['platform'] ?? ''), 'w-5 h-5') ?>
            <div class="min-w-0 flex-1"><p class="text-sm font-semibold text-ink-900 truncate"><?= e($wf['name'] ?? '') ?> <span id="wf-stbadge"><?= wf_status_badge($st) ?></span></p><p class="text-[11px] text-ink-500 truncate"><?= e(platform_label((string)($wf['platform'] ?? ''))) ?><?php if (!empty($wf['description'])): ?> · <?= e($wf['description']) ?><?php endif; ?></p></div>
            <button type="button" id="wf-run" data-id="<?= e($wf['id'] ?? $viewId) ?>" data-st="<?= e($st) ?>" class="<?= $st === 'active' ? 'btn-ghost' : 'btn-primary' ?> !py-1.5 !text-xs"><?= e($st === 'active' ? t('إيقاف') : t('تشغيل')) ?></button>
            <?php if (can('automations.manage')): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الـ Workflow ده؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($wf['id'] ?? $viewId) ?>"><button class="tb w-9 !h-9 justify-center text-red-600" title="<?= e(t('حذف')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></svg></button></form>
            <?php endif; ?>
          </div>
          <div class="flex-1 min-h-0 overflow-y-auto p-6 bg-ink-50/70" style="background-image:radial-gradient(rgb(var(--ink-200)/.6) 1px,transparent 1px);background-size:18px 18px">
            <div class="max-w-md mx-auto" id="wf-flow">
              <?php foreach ($nodes as $i => $n): $tp = (string)($n['type'] ?? ''); ?>
                <?php if ($i): ?><div class="wf-edge flex justify-center"><span class="block w-0.5 h-8 bg-gradient-to-b from-brand-300 to-brand-500"></span></div><div class="flex justify-center -mt-1.5 mb-0.5"><svg class="w-3 h-3 text-brand-500" viewBox="0 0 12 12" fill="currentColor"><path d="M6 10L1 4h10z"/></svg></div><?php endif; ?>
                <div class="wf-node group rounded-2xl border <?= $tp === 'trigger' ? 'border-brand-300 bg-gradient-to-br from-brand-50 to-cyan-50' : 'border-ink-200 bg-white' ?> shadow-sm p-4 transition hover:shadow-md hover:-translate-y-0.5" style="animation:wfIn .35s ease-out <?= $i * 0.08 ?>s both">
                  <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl <?= $tp === 'trigger' ? 'bg-brand-600 text-[#fff]' : 'bg-ink-100 text-ink-600' ?> flex items-center justify-center shrink-0"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="<?= $nodeIco[$tp] ?? 'M5 5h14v14H5z' ?>"/></svg></span>
                    <div class="min-w-0 flex-1"><b class="block text-xs text-ink-900"><?= e($nodeLbl[$tp] ?? ($n['label'] ?? $tp)) ?></b><span class="text-[10px] text-ink-400" dir="ltr"><?= e($tp) ?></span></div>
                    <span class="text-[10px] text-ink-400 tabular-nums"><?= $i + 1 ?></span>
                  </div>
                  <p class="text-sm text-ink-700 mt-2.5 whitespace-pre-wrap break-words" dir="auto"><?= preg_replace('/\{\{\s*contact\.name\s*\}\}/', '<span class="rounded-md bg-brand-100 text-brand-700 px-1.5 py-0.5 text-xs font-medium">' . e(t('اسم الشخص')) . '</span>', e(wf_node_summary($n))) ?></p>
                </div>
              <?php endforeach; ?>
              <?php if (!$nodes): ?><p class="text-sm text-ink-500 text-center"><?= e(t('مفيش خطوات.')) ?></p><?php endif; ?>
              <p class="text-[11px] text-ink-400 text-center mt-6"><?= e(t('لو عايز فروع وشروط وانتظار رد، اعمله من لوحة التكامل وهيظهر هنا.')) ?></p>
            </div>
          </div>
        <?php else: ?>
          <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-brand-600/10 to-cyan-500/10 text-brand-600 flex items-center justify-center mb-4"><svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 4h4v4H5zM15 16h4v4h-4zM7 8v4a2 2 0 002 2h6a2 2 0 012 2"/></svg></div>
            <p class="font-medium text-ink-900"><?= e(t('اختار Workflow عشان تشوف خطواته، أو اعمل واحد جديد')) ?></p>
            <p class="text-xs text-ink-500 mt-1 max-w-sm"><?= e(t('محادثات أوتوماتيك: لما حد يبعت رسالة أو يكتب تعليق فيه كلمة معينة، البرنامج يرد عليه بالخطوات اللي حددتها.')) ?></p>
            <a href="<?= e(url('workflows', ['new' => 1])) ?>" class="btn-primary mt-4"><?= e(t('Workflow جديد')) ?></a>
          </div>
        <?php endif; ?>
      </div>

      <!-- اليمين -->
      <aside class="hidden 2xl:flex card flex-col min-h-0 overflow-y-auto">
        <?php if ($isNew): ?>
          <div class="p-4 border-b border-ink-100">
            <b class="text-sm text-ink-900"><?= e(t('جرّب رسالة')) ?></b>
            <input id="wf-try" class="input mt-2 text-sm" dir="auto" placeholder="<?= e(t('اكتب رسالة زي ما العميل هيكتبها...')) ?>">
            <div id="wf-res" class="mt-2 rounded-xl px-3 py-2 text-xs hidden"></div>
          </div>
          <div class="p-4 space-y-3">
            <b class="text-sm text-ink-900"><?= e(t('هيظهر كده')) ?></b>
            <div class="rounded-2xl bg-ink-50 border border-ink-200 p-3 space-y-2 text-sm">
              <div class="flex justify-end"><div class="max-w-[85%] rounded-2xl rounded-se-md bg-white border border-ink-200 px-3 py-1.5 text-ink-800" id="pv-in" dir="auto"></div></div>
              <div class="flex justify-start"><div class="max-w-[85%] rounded-2xl rounded-ss-md bg-gradient-to-br from-brand-600 to-brand-700 text-[#fff] px-3 py-1.5 whitespace-pre-wrap" id="pv-out" dir="auto"></div></div>
            </div>
            <p class="text-[11px] text-ink-500"><?= e(t('الاسم في المعاينة مثال. في الحقيقة بيتحط اسم الشخص.')) ?></p>
          </div>
        <?php elseif ($viewing): $bs = (int)($wf['totalStarted'] ?? 0); $bc = (int)($wf['totalCompleted'] ?? 0); $fails = 0; foreach ($runs as $r) if (!empty($r['lastError']) || in_array($r['status'] ?? '', ['failed', 'error'], true)) $fails++; ?>
          <div class="p-4 border-b border-ink-100">
            <b class="text-sm text-ink-900"><?= e(t('الإحصائيات')) ?></b>
            <div class="grid grid-cols-2 gap-2 mt-3">
              <div class="rounded-xl bg-ink-50 border border-ink-200 p-3"><div class="text-xl font-bold tabular-nums" data-count="<?= $bs ?>">0</div><div class="text-[11px] text-ink-500"><?= e(t('بدأ')) ?></div></div>
              <div class="rounded-xl bg-ink-50 border border-ink-200 p-3"><div class="text-xl font-bold tabular-nums text-lime-700" data-count="<?= $bc ?>">0</div><div class="text-[11px] text-ink-500"><?= e(t('خلص')) ?></div></div>
            </div>
            <?php if ($bs): $rate = round(100 * $bc / $bs); ?><div class="mt-3"><div class="flex justify-between text-[11px] text-ink-500"><span><?= e(t('نسبة الإكمال')) ?></span><b class="text-ink-900"><?= $rate ?>%</b></div><div class="h-1.5 rounded-full bg-ink-100 mt-1 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-l from-lime-500 to-brand-600" style="width:<?= $rate ?>%"></div></div></div><?php endif; ?>
          </div>
          <div class="p-4">
            <div class="flex items-center gap-2"><b class="text-sm text-ink-900 flex-1"><?= e(t('آخر التشغيلات')) ?></b><?php if ($fails): ?><button type="button" id="wf-onlyfail" class="rounded-md bg-red-50 text-red-700 px-2 py-0.5 text-[11px] font-medium"><?= e(t('الفاشلة فقط (%s)', $fails)) ?></button><?php endif; ?></div>
            <?php if (!$runs): ?><p class="text-xs text-ink-500 mt-2"><?= e(t('لسه ماشتغلش.')) ?></p><?php endif; ?>
            <ul class="mt-2 space-y-2">
              <?php foreach ($runs as $r): $rs = (string)($r['status'] ?? ''); $bad = !empty($r['lastError']) || in_array($rs, ['failed', 'error'], true); ?>
                <li class="wf-runrow rounded-xl border <?= $bad ? 'border-red-200 bg-red-50/40' : 'border-ink-200' ?> p-2.5" data-bad="<?= $bad ? 1 : 0 ?>">
                  <div class="flex items-center gap-2"><span class="rounded-md px-1.5 py-0.5 text-[10px] <?= $rs === 'completed' ? 'bg-lime-100 text-lime-700' : ($bad ? 'bg-red-100 text-red-700' : 'bg-ink-100 text-ink-600') ?>"><?= e($rs) ?></span><span class="flex-1 truncate text-[11px] text-ink-500" dir="ltr"><?= e($r['platformIdentifier'] ?? '') ?></span><span class="text-[10px] text-ink-400"><?= e(fmt_date($r['createdAt'] ?? null, 'd/m h:i A')) ?></span></div>
                  <?php if (!empty($r['lastError'])): ?><details class="mt-1"><summary class="cursor-pointer text-[11px] text-red-700"><?= e(t('سبب الفشل')) ?></summary><p class="text-[11px] text-red-700 mt-1 break-words" dir="ltr"><?= e($r['lastError']) ?></p></details><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php else: ?>
          <div class="p-5 text-sm text-ink-600 space-y-2">
            <b class="text-ink-900"><?= e(t('ملاحظات')) ?></b>
            <p><?= e(t('الـ Workflow بيتعمل كمسودة، ومش هيشتغل غير لما تدوس تشغيل.')) ?></p>
            <p><?= e(t('الرسائل بتتبعت بس جوه نافذة الـ 24 ساعة بتاعة المنصة.')) ?></p>
            <p><?= e(t('لو عايز فروع وشروط وانتظار رد، اعمله من لوحة التكامل وهيظهر هنا.')) ?></p>
          </div>
        <?php endif; ?>
      </aside>
    </div>
    <style>@keyframes wfIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}</style>
    <script>window.WFX = <?= json_encode(['commentPlats' => ['instagram', 'facebook'], 'st' => ['active' => t('شغال'), 'paused' => t('متوقف'), 'draft' => t('مسودة')], 'run' => t('تشغيل'), 'stop' => t('إيقاف'),
      'on' => t('الـ Workflow اشتغل.'), 'off' => t('الـ Workflow اتوقف.'), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'kwN' => t('%s كلمة · أي رسالة فيها واحدة منهم تشغّله'), 'kwAny' => t('فاضي = أي رسالة تشغّله'),
      'match' => t('هيشتغل ✓ — اتطابقت مع: %s'), 'nomatch' => t('مش هيشتغل — مفيش كلمة مطابقة'), 'any' => t('هيشتغل ✓ — أي رسالة بتشغّله'), 'sampleIn' => t('أهلاً، عاوز أعرف التفاصيل'), 'sampleName' => t('أحمد'), 'emptyOut' => t('(اكتب الرسالة)'),
      'needName' => t('الاسم والرسالة مطلوبين.'), 'noComment' => t('التشغيل بالتعليقات متاح لإنستجرام وفيسبوك بس.'), 'leave' => t('فيه تعديلات مش محفوظة. تخرج من غير حفظ؟'), 'keysTitle' => t('اختصارات الكيبورد'),
      'keys' => [['j k', t('الـ Workflow التالي / السابق')], ['n', t('Workflow جديد')], ['/', t('البحث')], ['t', t('تشغيل / إيقاف')], ['Ctrl S', t('حفظ')], ['?', t('الاختصارات')]]], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/workflows.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
