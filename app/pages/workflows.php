<?php
$err = null;
$z = zernio_or_null($err);
$viewId = (string)($_GET['id'] ?? '');
$isNew = !empty($_GET['new']);
$statusFilter = (string)($_GET['status'] ?? '');
if (!in_array($statusFilter, ['', 'draft', 'active', 'paused'], true)) $statusFilter = '';

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
            if (!is_ws_owner()) {
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
if ($z) {
    try {
        if ($viewId !== '') {
            $wf = ip_workflow($z, $viewId);
            try { $runs = ip_workflow_runs($z, $viewId)['executions'] ?? []; } catch (ZernioException $ex) { $runs = []; }
        } elseif ($isNew) {
            $accounts = ip_accounts($z, ip_workflow_platforms());
        } else {
            $list = ip_workflows($z, $statusFilter ?: null);
        }
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

function wf_status_badge(string $s): string
{
    $labels = ['active' => t('شغال'), 'paused' => t('متوقف'), 'draft' => t('مسودة')];
    return '<span class="badge ' . ($s === 'active' ? 'badge-green' : '') . '">' . e($labels[$s] ?? $s) . '</span>';
}

function wf_node_summary(array $n): string
{
    $c = $n['config'] ?? [];
    switch ($n['type'] ?? '') {
        case 'trigger':
            $tt = $c['triggerType'] ?? 'inbound_message';
            $kw = $tt === 'comment' ? ($c['comment']['keywords'] ?? []) : ($c['keywords'] ?? []);
            $types = ['inbound_message' => t('رسالة واردة'), 'comment' => t('تعليق'), 'api_call' => t('استدعاء API'), 'referral' => t('إعلان أو رابط'), 'story_mention' => t('منشن في ستوري'), 'reaction' => t('تفاعل'), 'whatsapp_event' => t('حالة رسالة واتساب'), 'lead_received' => t('عميل محتمل من إعلان')];
            return ($types[$tt] ?? $tt) . ($kw ? ' · ' . implode('، ', $kw) : '');
        case 'send_message':
            return (string)($c['text'] ?? $c['template']['name'] ?? $c['messageType'] ?? '');
        case 'delay':
            return json_encode($c, JSON_UNESCAPED_UNICODE);
        default:
            return $c ? mb_substr(json_encode($c, JSON_UNESCAPED_UNICODE), 0, 140) : '';
    }
}

render(t('الـ Workflows'), 'workflows', function () use ($z, $list, $wf, $runs, $accounts, $isNew, $viewId, $statusFilter, $apiError) {
    if (!$z) { no_api_box(); return; }
    if ($apiError) { error_box($apiError); }

    if ($isNew): ?>
      <a href="<?= e(url('workflows')) ?>" class="link text-sm"><?= e(t('رجوع للقائمة')) ?></a>
      <div class="grid lg:grid-cols-3 gap-4 mt-3">
        <form method="post" class="card p-5 space-y-4 lg:col-span-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <h2 class="font-bold text-lg"><?= e(t('Workflow بسيط: لما يحصل كذا، ابعت رسالة')) ?></h2>
          <div><label class="label"><?= e(t('الاسم')) ?></label><input name="name" required maxlength="100" class="input" placeholder="<?= e(t('مثلاً: رد الترحيب')) ?>"></div>
          <div><label class="label"><?= e(t('الحساب')) ?></label>
            <select name="account_id" required class="input"><?php foreach ($accounts as $a): ?><option value="<?= e($a['_id']) ?>"><?= e(ip_account_label($a)) ?></option><?php endforeach; ?></select>
            <?php if (!$accounts): ?><p class="text-xs text-red-600 mt-1"><?= e(t('مفيش حساب رسائل مربوط.')) ?></p><?php endif; ?></div>
          <div><label class="label"><?= e(t('يبدأ لما')) ?></label>
            <select name="trigger" class="input">
              <option value="inbound_message"><?= e(t('حد يبعت رسالة خاصة')) ?></option>
              <option value="comment"><?= e(t('حد يكتب تعليق (إنستجرام وفيسبوك بس)')) ?></option>
            </select></div>
          <div><label class="label"><?= e(t('الكلمات (اختياري)')) ?></label>
            <input name="keywords" class="input" placeholder="<?= e(t('افصل بينهم بفاصلة. فاضي = أي رسالة')) ?>"></div>
          <div><label class="label"><?= e(t('طريقة المطابقة')) ?></label>
            <select name="match_type" class="input"><option value="contains"><?= e(t('الرسالة فيها الكلمة')) ?></option><option value="exact"><?= e(t('الرسالة هي الكلمة بالظبط')) ?></option></select></div>
          <div><label class="label"><?= e(t('الرسالة اللي هتتبعت')) ?></label>
            <textarea name="text" required rows="3" maxlength="1000" class="input" placeholder="<?= e(t('أهلاً {{contact.name}}! إزاي نقدر نساعدك؟')) ?>"></textarea>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('تقدر تستخدم {{contact.name}} لاسم الشخص.')) ?></p></div>
          <button class="btn-primary"><?= e(t('إنشاء كمسودة')) ?></button>
        </form>
        <div class="card p-5 text-sm text-ink-600 space-y-2 h-fit">
          <h3 class="font-bold text-ink-900"><?= e(t('ملاحظات')) ?></h3>
          <p><?= e(t('الـ Workflow بيتعمل كمسودة، ومش هيشتغل غير لما تدوس تشغيل.')) ?></p>
          <p><?= e(t('الرسائل بتتبعت بس جوه نافذة الـ 24 ساعة بتاعة المنصة.')) ?></p>
          <p><?= e(t('لو عايز فروع وشروط وانتظار رد، اعمله من لوحة التكامل وهيظهر هنا.')) ?></p>
        </div>
      </div>
    <?php return; endif;

    if ($viewId !== ''):
        if (!$wf) { echo '<a href="' . e(url('workflows')) . '" class="link text-sm">' . e(t('رجوع للقائمة')) . '</a>'; return; }
        $st = (string)($wf['status'] ?? '');
        ?>
        <a href="<?= e(url('workflows')) ?>" class="link text-sm"><?= e(t('رجوع للقائمة')) ?></a>
        <div class="card p-5 mt-3 flex flex-wrap items-center gap-3">
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg"><?= e($wf['name'] ?? '') ?> <?= wf_status_badge($st) ?></h2>
            <p class="text-sm text-ink-500"><?= e(platform_label($wf['platform'] ?? '')) ?><?php if (!empty($wf['description'])): ?> · <?= e($wf['description']) ?><?php endif; ?></p>
          </div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="from_view" value="1"><input type="hidden" name="id" value="<?= e($wf['id'] ?? $viewId) ?>">
            <input type="hidden" name="action" value="<?= $st === 'active' ? 'pause' : 'activate' ?>">
            <button class="<?= $st === 'active' ? 'btn-ghost' : 'btn-primary' ?>"><?= e($st === 'active' ? t('إيقاف') : t('تشغيل')) ?></button></form>
          <?php if (is_ws_owner()): ?>
            <form method="post" action="<?= e(url('workflows')) ?>" onsubmit="return confirm('<?= tjs('حذف الـ Workflow ده؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($wf['id'] ?? $viewId) ?>">
              <button class="btn-ghost text-red-600"><?= e(t('حذف')) ?></button></form>
          <?php endif; ?>
        </div>
        <div class="grid lg:grid-cols-2 gap-4 mt-4">
          <div class="card p-5">
            <h3 class="font-bold mb-3"><?= e(t('الخطوات')) ?></h3>
            <ol class="space-y-2">
              <?php foreach ($wf['nodes'] ?? [] as $i => $n): ?>
                <li class="border border-ink-200 rounded-lg p-3">
                  <div class="text-xs text-ink-500"><?= (int)$i + 1 ?> · <code dir="ltr"><?= e($n['type'] ?? '') ?></code><?php if (!empty($n['label'])): ?> · <?= e($n['label']) ?><?php endif; ?></div>
                  <div class="text-sm break-words"><?= e(wf_node_summary($n)) ?></div>
                </li>
              <?php endforeach; ?>
              <?php if (empty($wf['nodes'])): ?><li class="text-sm text-ink-500"><?= e(t('مفيش خطوات.')) ?></li><?php endif; ?>
            </ol>
          </div>
          <div class="card p-5">
            <h3 class="font-bold mb-3"><?= e(t('آخر التشغيلات')) ?></h3>
            <?php if (!$runs): ?><p class="text-sm text-ink-500"><?= e(t('لسه ماشتغلش.')) ?></p><?php endif; ?>
            <ul class="space-y-2 text-sm">
              <?php foreach ($runs as $r): ?>
                <li class="flex items-center gap-2 border-b border-ink-100 pb-1">
                  <span class="badge <?= ($r['status'] ?? '') === 'completed' ? 'badge-green' : '' ?>"><?= e($r['status'] ?? '') ?></span>
                  <span class="flex-1 truncate text-xs text-ink-500" dir="ltr"><?= e($r['platformIdentifier'] ?? '') ?></span>
                  <span class="text-xs text-ink-400"><?= e(fmt_date($r['createdAt'] ?? null, 'd/m h:i A')) ?></span>
                </li>
                <?php if (!empty($r['lastError'])): ?><li class="text-xs text-red-600"><?= e($r['lastError']) ?></li><?php endif; ?>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <?php
        return;
    endif;
    ?>
    <div class="card p-5 mb-4 flex flex-wrap items-start gap-4">
      <div class="flex-1 min-w-[16rem] text-sm text-ink-600">
        <p class="font-bold text-ink-900 mb-1"><?= e(t('الـ Workflows')) ?></p>
        <p><?= e(t('محادثات أوتوماتيك: لما حد يبعت رسالة أو يكتب تعليق فيه كلمة معينة، البرنامج يرد عليه بالخطوات اللي حددتها.')) ?></p>
      </div>
      <form method="get" class="flex items-center gap-2"><input type="hidden" name="p" value="workflows">
        <select name="status" class="input w-auto text-sm py-1.5" onchange="this.form.submit()">
          <option value=""><?= e(t('كل الحالات')) ?></option>
          <?php foreach (['active' => t('شغال'), 'paused' => t('متوقف'), 'draft' => t('مسودة')] as $k => $lbl): ?><option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
        </select></form>
      <a href="<?= e(url('workflows', ['new' => 1])) ?>" class="btn-primary"><?= e(t('Workflow جديد')) ?></a>
    </div>
    <?php if (!$list): ?><div class="card p-10 text-center text-sm text-ink-500"><?= e(t('مفيش Workflows.')) ?></div><?php endif; ?>
    <div class="grid md:grid-cols-2 gap-4">
      <?php foreach ($list as $w): $st = (string)($w['status'] ?? ''); ?>
        <div class="card p-4 space-y-2">
          <div class="flex items-center gap-2"><a href="<?= e(url('workflows', ['id' => $w['id']])) ?>" class="font-bold flex-1 truncate link"><?= e($w['name'] ?? '') ?></a><?= wf_status_badge($st) ?></div>
          <p class="text-xs text-ink-500"><?= e(platform_label($w['platform'] ?? '')) ?><?php if (!empty($w['accountName'])): ?> · <?= e($w['accountName']) ?><?php endif; ?> · <?= e(t('%s خطوة', (int)($w['nodeCount'] ?? 0))) ?></p>
          <p class="text-xs text-ink-500"><?= e(t('بدأ %s مرة · خلص %s', (int)($w['totalStarted'] ?? 0), (int)($w['totalCompleted'] ?? 0))) ?></p>
          <div class="flex flex-wrap gap-2 pt-1">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($w['id']) ?>"><input type="hidden" name="action" value="<?= $st === 'active' ? 'pause' : 'activate' ?>">
              <button class="btn-ghost text-xs"><?= e($st === 'active' ? t('إيقاف') : t('تشغيل')) ?></button></form>
            <a href="<?= e(url('workflows', ['id' => $w['id']])) ?>" class="btn-ghost text-xs"><?= e(t('عرض')) ?></a>
            <?php if (is_ws_owner()): ?>
              <form method="post" onsubmit="return confirm('<?= tjs('حذف الـ Workflow ده؟') ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($w['id']) ?>">
                <button class="btn-ghost text-xs text-red-600"><?= e(t('حذف')) ?></button></form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
});
