<?php
/** صفحة حالة طلب الشركة: رقم الطلب، تاريخه، حالته، وإمكانية الإلغاء أو المتابعة. */
$pdo = db();
$me = current_user();

// الشركة من ?id= بشرط إن المستخدم عضو فيها، وإلا نرجّعه لشركاته
$id = (int)($_GET['id'] ?? ($_SESSION['ws'] ?? 0));
$w = null;
if ($id) {
    $st = $pdo->prepare("SELECT w.*, wu.role AS my_role FROM workspaces w
        JOIN workspace_users wu ON wu.workspace_id = w.id
        WHERE w.id = ? AND wu.user_id = ? AND wu.status = 'active' AND COALESCE(w.status,'active') <> 'deleted'");
    $st->execute([$id, $me['id']]);
    $w = $st->fetch() ?: null;
}
if (!$w) { redirect(url('workspaces')); }

$gate = ws_access_state($w);
// لو الشركة بقت متاحة فعلاً، مفيش داعي لصفحة الحالة: افتحها
if ($gate['ok']) { $_SESSION['ws'] = (int)$w['id']; redirect(url('dashboard')); }

$meta = json_decode((string)($w['request_meta'] ?? ''), true) ?: [];
$reqNo = ws_request_no($w);
$submitted = $meta['submitted_at'] ?? ($w['created_at'] ?? '');
$cs = countries();
$cc = strtoupper((string)($meta['country'] ?? setting('country_' . (int)$w['id'], '')));

// خطوات التقدّم (قيد المراجعة → معتمدة → مفعّلة)
$steps = [
    ['pending_review', t('قيد المراجعة')],
    ['approved', t('تمت الموافقة')],
    ['active', t('مفعّلة')],
];
$stageIndex = 0;
if (($w['approval_status'] ?? '') === 'approved') $stageIndex = $gate['reason'] === 'pending_payment' ? 1 : 2;
if (($w['approval_status'] ?? '') === 'rejected') $stageIndex = -1;

render(t('حالة طلب الشركة'), 'ws_status', function () use ($w, $gate, $meta, $reqNo, $submitted, $steps, $stageIndex, $cs, $cc) {
    $reason = $gate['reason'];
    $tone = ['pending_review' => ['amber', '🕒'], 'rejected' => ['red', '⛔'], 'suspended' => ['red', '⏸'], 'expired' => ['orange', '⌛'], 'pending_payment' => ['indigo', '💳']][$reason] ?? ['ink', 'ℹ️'];
    ?>
    <div class="max-w-2xl mx-auto space-y-6">
      <div class="card p-6 sm:p-8">
        <div class="flex items-start gap-4">
          <span class="w-14 h-14 shrink-0 rounded-2xl bg-<?= $tone[0] ?>-50 text-2xl flex items-center justify-center"><?= $tone[1] ?></span>
          <div class="min-w-0 flex-1">
            <h1 class="text-xl font-bold text-ink-900"><?= e($w['name']) ?></h1>
            <p class="text-sm text-ink-600 mt-1"><?= e($gate['label']) ?></p>
          </div>
        </div>

        <?php if ($stageIndex >= 0): ?>
        <div class="flex items-center gap-2 mt-6">
          <?php foreach ($steps as $i => $s): ?>
            <?php if ($i): ?><span class="flex-1 h-1 rounded <?= $i <= $stageIndex ? 'bg-brand-500' : 'bg-ink-200' ?>"></span><?php endif; ?>
            <span class="flex flex-col items-center gap-1">
              <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold <?= $i <= $stageIndex ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-500' ?>"><?= $i < $stageIndex ? '✓' : ($i + 1) ?></span>
            </span>
          <?php endforeach; ?>
        </div>
        <div class="flex justify-between text-[11px] text-ink-500 mt-1.5 px-1">
          <?php foreach ($steps as $s): ?><span><?= e($s[1]) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 mt-6 text-sm">
          <div><dt class="text-ink-500 text-xs"><?= e(t('رقم الطلب')) ?></dt><dd class="font-semibold text-ink-900" dir="ltr"><?= e($reqNo) ?></dd></div>
          <div><dt class="text-ink-500 text-xs"><?= e(t('تاريخ التقديم')) ?></dt><dd class="font-semibold text-ink-900"><?= e(fmt_date($submitted, 'd/m/Y')) ?></dd></div>
          <?php if (!empty($meta['admin_name'])): ?><div><dt class="text-ink-500 text-xs"><?= e(t('المسؤول')) ?></dt><dd class="text-ink-800"><?= e($meta['admin_name']) ?></dd></div><?php endif; ?>
          <?php if ($cc && isset($cs[$cc])): ?><div><dt class="text-ink-500 text-xs"><?= e(t('الدولة')) ?></dt><dd class="text-ink-800"><?= e($cs[$cc][2] . ' ' . (lang() === 'en' ? $cs[$cc][1] : $cs[$cc][0])) ?></dd></div><?php endif; ?>
          <?php if (!empty($meta['biz_type'])): ?><div><dt class="text-ink-500 text-xs"><?= e(t('نوع النشاط')) ?></dt><dd class="text-ink-800"><?= e($meta['biz_type']) ?></dd></div><?php endif; ?>
          <?php if (!empty($w['plan'])): ?><div><dt class="text-ink-500 text-xs"><?= e(t('الخطة')) ?></dt><dd class="text-ink-800"><?= e($w['plan']) ?></dd></div><?php endif; ?>
        </dl>

        <?php if ($reason === 'rejected' && !empty($w['reject_reason'])): ?>
          <div class="mt-5 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
            <b><?= e(t('سبب الرفض:')) ?></b> <?= e($w['reject_reason']) ?>
          </div>
        <?php endif; ?>

        <?php if ($reason === 'pending_payment'): ?>
          <div class="mt-5 rounded-xl bg-indigo-50 border border-indigo-200 p-4 text-sm text-indigo-900">
            <?= e(t('تمت الموافقة على شركتك. التفعيل النهائي بعد تأكيد الدفع من إدارة المنصة. تواصل معنا لإتمام الاشتراك.')) ?>
          </div>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap gap-3">
          <a href="<?= e(url('workspaces')) ?>" class="btn-ghost"><?= e(t('رجوع لشركاتي')) ?></a>
          <?php if ($reason === 'pending_review' && $w['my_role'] === 'owner'): ?>
            <form method="post" action="<?= e(url('workspaces')) ?>" data-confirm="<?= e(t('إلغاء الطلب نهائيًا؟ مش هينفع ترجعه.')) ?>" data-confirm-danger>
              <?= csrf_field() ?><input type="hidden" name="action" value="cancel_request"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
              <button class="btn-danger-ghost"><?= e(t('إلغاء الطلب')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <p class="text-center text-xs text-ink-400"><?= e(t('هتتم مراجعة الطلبات في أقرب وقت. بتوصلك النتيجة هنا وعلى بريدك.')) ?></p>
    </div>
    <?php
});
