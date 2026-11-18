<?php
/* المنافسات المرساة: ابحث في ترسيات اعتماد بمجالك ← شوف مين كسب وبكام ومين اتقدم ← بيانات التواصل ← طابق خدماتك ← عميل وعرض سعر */
$wid = (int)ws('id'); $mgr = can('crm.manage') || is_ws_owner();
$J = function (array $d, int $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); exit(json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); };
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    try {
        if ($a === 'search') {
            if (!$mgr) $J(['ok' => false, 'error' => t('البحث في الترسيات بيصرف من رصيد Apify، ومتاح لمدير العملاء فقط.')], 403);
            if (!taw_on()) $J(['ok' => false, 'error' => t('اربط Apify من التكاملات الأول.')]);
            if (isset($_POST['actor']) && isset(taw_actors()[$_POST['actor']])) save_setting('taw_actor_' . $wid, (string)$_POST['actor']);
            $words = preg_split('/[\n,،]+/u', (string)($_POST['words'] ?? '')) ?: [];
            $runs = taw_start($words, (int)($_POST['days'] ?? 90), (int)($_POST['max'] ?? 100));
            $J(['ok' => true, 'runs' => count($runs)]);
        }
        if ($a === 'poll') { session_write_close(); $r = taw_on() ? taw_collect() : ['new' => 0, 'running' => 0, 'error' => '']; $J(['ok' => true] + $r); }
        $row = taw_get((int)($_POST['id'] ?? 0)); if (!$row) $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 404);
        if ($a === 'detail') $J(['ok' => true, 'match' => taw_match($row), 'bidders' => json_decode((string)$row['bidders'], true) ?: [], 'contact' => json_decode((string)($row['contact'] ?? ''), true) ?: null]);
        if ($a === 'contact') { if (!$mgr) $J(['ok' => false, 'error' => t('مالكش صلاحية.')], 403); session_write_close(); $J(['ok' => true, 'contact' => taw_contact($row)]); }
        if ($a === 'lead') { if (!can('crm.view')) $J(['ok' => false, 'error' => t('مالكش صلاحية.')], 403); $r = taw_to_lead($row); $J(['ok' => true, 'lead' => url('prospects', ['lead' => $r['lead']]), 'quote' => url('crm_doc', ['deal' => $r['deal']])]); }
    } catch (Throwable $e) { $J(['ok' => false, 'error' => $e->getMessage()]); }
    $J(['ok' => false, 'error' => t('طلب غير صحيح.')], 400);
}
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$rows = taw_list($q);
render(t('المنافسات المرساة'), 'tender_awards', function () use ($rows, $q, $mgr, $wid) {
    $on = taw_on(); $runs = json_decode((string)setting('taw_runs_' . $wid, '[]'), true) ?: []; $err = (string)setting('taw_err_' . $wid, '');
    $tot = array_sum(array_map(function ($r) { return (float)$r['award_sar']; }, $rows));
    $winners = []; foreach ($rows as $r) $winners[$r['winner']] = ($winners[$r['winner']] ?? 0) + 1; arsort($winners);
    ?>
    <p class="text-sm text-ink-500 mb-4"><?= e(t('مين كسب منافسات اعتماد في مجالك وبكام، ومين اتقدم معاه. كل شركة فايزة عندها مشروع شغال ومحتاجة موردين: هات بيانات التواصل، وطابق خدماتك مع المطلوب، واعمل عرض سعر.')) ?></p>
    <?php if (!$on): ?><div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 mb-4"><?= e(t('الترسيات بتيجي من Apify. اربط حساب Apify من صفحة التكاملات أولًا.')) ?> <a class="link" href="<?= e(url('connectors')) ?>"><?= e(t('التكاملات')) ?></a></div><?php endif; ?>
    <?php if ($mgr && $on): $acts = taw_actors(); $cur = taw_actor(); ?>
    <form id="aw-f" class="card p-5 mb-4 grid lg:grid-cols-[2fr_1fr_1fr_1fr_auto] gap-3 items-end">
      <label class="block"><span class="label"><?= e(t('كلمات المجال (من ملف الشركة، وتقدر تعدّل)')) ?></span><input name="words" class="input" dir="auto" value="<?= e(implode('، ', array_slice(taw_words(), 0, 6))) ?>"></label>
      <label class="block"><span class="label"><?= e(t('آخر كام يوم')) ?></span><select name="days" class="input"><?php foreach ([30, 90, 180, 365] as $d): ?><option value="<?= $d ?>" <?= $d === 90 ? 'selected' : '' ?>><?= e(t('%s يوم', $d)) ?></option><?php endforeach; ?></select></label>
      <label class="block"><span class="label"><?= e(t('أقصى عدد (سقف التكلفة)')) ?></span><select name="max" class="input"><?php foreach ([50, 100, 300, 1000] as $m): ?><option value="<?= $m ?>" <?= $m === 100 ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select></label>
      <label class="block"><span class="label"><?= e(t('الأداة')) ?></span><select name="actor" class="input"><?php foreach ($acts as $k => $x): ?><option value="<?= e($k) ?>" data-price="<?= $x['price'] ?>" <?= $k === $cur ? 'selected' : '' ?>><?= e('$' . $x['price'] . ' · ' . $x['name']) ?></option><?php endforeach; ?></select></label>
      <button class="btn-primary"><?= e(t('ابحث في الترسيات')) ?></button>
      <p id="aw-cost" class="lg:col-span-5 text-xs text-ink-500"></p>
    </form>
    <?php endif; ?>
    <div id="aw-status" class="<?= $runs ? '' : 'hidden' ?> rounded-2xl border border-brand-200 bg-brand-50/50 p-3 text-sm text-brand-900 mb-4 flex items-center gap-2"><span class="w-4 h-4 rounded-full border-2 border-brand-600 border-t-transparent animate-spin"></span><span><?= e(t('جارٍ البحث في ترسيات اعتماد… تظهر النتائج هنا تلقائيًا (قد يستغرق بضع دقائق).')) ?></span></div>
    <?php if ($err !== ''): ?><div class="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 mb-4"><?= e($err) ?></div><?php endif; ?>
    <?php if ($rows): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('ترسيات')) ?></div><div class="text-2xl font-bold"><?= count($rows) ?></div></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('إجمالي القيم')) ?></div><div class="text-2xl font-bold tabular-nums" dir="ltr"><?= e(number_format($tot)) ?> <span class="text-sm">SAR</span></div></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('شركات فايزة')) ?></div><div class="text-2xl font-bold"><?= count($winners) ?></div></div>
      <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('الأكثر فوزًا')) ?></div><div class="text-sm font-bold truncate mt-1"><?= e((string)array_key_first($winners)) ?></div><div class="text-xs text-ink-500"><?= e(t('%s منافسة', (int)reset($winners))) ?></div></div>
    </div>
    <?php endif; ?>
    <div class="card overflow-hidden">
      <form method="get" class="p-3 border-b border-ink-100"><input type="hidden" name="p" value="tender_awards"><input type="search" name="q" value="<?= e($q) ?>" class="input !mt-0 !py-1.5 text-sm" placeholder="<?= e(t('ابحث باسم المنافسة أو الشركة الفايزة أو الجهة…')) ?>"></form>
      <?php if (!$rows): ?><p class="p-10 text-center text-sm text-ink-500"><?= e(t('لا توجد ترسيات بعد. ابدأ بحثًا بكلمات مجالك.')) ?></p><?php endif; ?>
      <div id="aw-list">
      <?php foreach ($rows as $r): ?>
        <div class="aw-row border-b border-ink-100 last:border-0" data-id="<?= (int)$r['id'] ?>">
          <button type="button" class="aw-open w-full text-start px-4 py-3 hover:bg-ink-50 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="min-w-0 flex-1"><span class="block font-medium text-ink-900 truncate"><?= e($r['tender_name']) ?></span><span class="block text-xs text-ink-500 truncate"><?= e(implode(' · ', array_filter([$r['agency'], $r['ttype'], $r['published_at'] ? substr((string)$r['published_at'], 0, 10) : '']))) ?></span></span>
            <span class="text-sm"><span class="text-ink-500 text-xs block"><?= e(t('الفائز')) ?></span><b class="text-ink-900"><?= e($r['winner']) ?></b><?php if ($r['lead_id']): ?> <span class="badge badge-green"><?= e(t('عميل')) ?></span><?php endif; ?></span>
            <span class="text-sm text-end tabular-nums" dir="ltr"><span class="text-ink-500 text-xs block"><?= e(t('القيمة')) ?></span><b><?= e(number_format((float)$r['award_sar'])) ?></b> SAR</span>
            <span class="text-xs text-ink-500"><?= e(t('%s متقدم', (int)$r['bidder_count'])) ?></span>
          </button>
          <div class="aw-d hidden px-4 pb-4"></div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
    <script>window.AW = <?= json_encode(['url' => url('tender_awards'), 'csrf' => csrf_token(), 'mgr' => $mgr, 'running' => (bool)$runs, 't' => [
      'cost' => t('التكلفة القصوى التقريبية: %s دولار (تُحتسب فقط على الترسيات التي تُرجعها الأداة).'), 'bidders' => t('المتقدمون وعروضهم'), 'match' => t('خدماتك المطابقة للمطلوب'), 'noMatch' => t('لا توجد خدمة مطابقة في ملف الشركة. أضف خدماتك وأسعارك في قاعدة المعرفة.'),
      'contact' => t('بيانات التواصل'), 'getContact' => t('اجلب بيانات التواصل (≈ 0.004 دولار)'), 'noContact' => t('لم نجد بيانات تواصل لهذه الشركة.'), 'toLead' => t('أضفها كعميل + عرض سعر'), 'openLead' => t('افتح العميل'), 'quote' => t('عرض السعر'),
      'etimad' => t('صفحة المنافسة في اعتماد'), 'scope' => t('المطلوب في المنافسة'), 'booklet' => t('كراسة الشروط تُشترى من اعتماد بحساب مورد؛ المطلوب هنا من اسم المنافسة ونشاطها.'), 'winner' => t('الفائز'), 'phone' => t('الهاتف'), 'site' => t('الموقع'), 'addr' => t('العنوان'), 'maps' => t('خرائط جوجل'), 'emp' => t('%s موظف'), 'err' => t('حصلت مشكلة. حاول مرة أخرى.'), 'loading' => t('جارٍ التحميل…'), 'started' => t('بدأ البحث (%s تشغيلة).')]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="assets/tender-awards.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
