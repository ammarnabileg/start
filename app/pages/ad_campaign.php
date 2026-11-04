<?php
/** تفاصيل حملة: المجموعات والإعلانات، رسم يومي، تعديل الميزانية، إيقاف/تشغيل */
$err = null;
$z = zernio_or_null($err);
$cid = (string)($_GET['id'] ?? '');
$pf = (string)($_GET['pf'] ?? '');
if ($pf !== '' && !in_array($pf, ads_campaign_platforms(), true)) $pf = '';
$range = (string)($_GET['r'] ?? '30');
if (!isset(ads_date_presets()[$range])) $range = '30';
$back = url('ad_campaign', array_filter(['id' => $cid, 'pf' => $pf, 'r' => $range]));

if ($cid === '') {
    redirect(url('ads'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $z) {
    $action = (string)($_POST['action'] ?? '');
    $platform = (string)($_POST['platform'] ?? $pf);
    $to = ($_POST['to'] ?? '') === 'active' ? 'active' : 'paused';
    $needsOwner = ($action === 'budget') || (in_array($action, ['campaign_status', 'adset_status', 'ad_status'], true) && $to === 'active');
    try {
        if (!in_array($platform, ads_campaign_platforms(), true)) {
            flash('error', t('بيانات الحملة ناقصة.'));
        } elseif ($needsOwner && !can('ads.spend')) {
            flash('error', t('التشغيل وتعديل الميزانية لصاحب الشركة بس.'));
        } elseif ($needsOwner && empty($_POST['confirm_spend'])) {
            flash('error', t('لازم تعلّم على خانة التأكيد الأول.'));
        } elseif ($action === 'campaign_status') {
            $r = ads_set_campaign_status($z, $cid, $platform, $to);
            log_activity('ads.campaign_' . $to, $cid);
            flash('success', !empty($r['skipped']) ? t('الحملة كانت أصلاً في الحالة دي.') : ($to === 'active' ? t('تم تشغيل الحملة.') : t('تم إيقاف الحملة.')));
        } elseif ($action === 'adset_status') {
            $sid = (string)($_POST['adset_id'] ?? '');
            ads_set_adset_status($z, $sid, $platform, $to);
            log_activity('ads.adset_' . $to, $sid);
            flash('success', $to === 'active' ? t('تم تشغيل المجموعة.') : t('تم إيقاف المجموعة.'));
        } elseif ($action === 'ad_status') {
            $aid = (string)($_POST['ad_id'] ?? '');
            $r = ads_set_ad_status($z, $aid, $to);
            log_activity('ads.ad_' . $to, $aid);
            flash('success', !empty($r['skipped']) ? ($r['message'] ?? t('الإعلان كان أصلاً في الحالة دي.')) : ($to === 'active' ? t('تم تشغيل الإعلان.') : t('تم إيقاف الإعلان.')));
        } elseif ($action === 'budget') {
            $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? ''));
            $type = ($_POST['type'] ?? '') === 'lifetime' ? 'lifetime' : 'daily';
            $sid = (string)($_POST['adset_id'] ?? '');
            if ($amount <= 0) {
                flash('error', t('اكتب ميزانية أكبر من صفر.'));
            } else {
                if ($sid !== '') {
                    ads_update_adset_budget($z, $sid, $platform, $amount, $type);
                } else {
                    ads_update_campaign_budget($z, $cid, $platform, $amount, $type);
                }
                log_activity('ads.budget', ($sid ?: $cid) . ' ' . $type . ' ' . $amount);
                flash('success', t('تم تعديل الميزانية.'));
            }
        }
    } catch (ZernioException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($back);
}

$camp = null;
$apiError = null;
[$from, $to] = ads_date_range($range);
$fx = getenv('UI_FIXTURES') ? getenv('UI_FIXTURES') . '/ad_campaign.json' : '';
if ($fx && is_file($fx)) {
    $camp = json_decode((string)file_get_contents($fx), true);
} elseif ($z) {
    try {
        $camp = ads_campaign_tree($z, $cid, $from, $to, $pf ?: null);
    } catch (ZernioException $ex) {
        $apiError = $ex->getMessage();
    }
}

/** فورم إيقاف/تشغيل صغير */
function ad_toggle_form(string $action, string $status, string $platform, array $hidden, string $amountText): void
{
    $active = in_array($status, ['active', 'pending_review'], true);
    if ($active) { ?>
      <form method="post" class="inline" onsubmit="return confirm('<?= tjs('متأكد إنك عايز توقفه؟') ?>')">
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="to" value="paused"><input type="hidden" name="platform" value="<?= e($platform) ?>">
        <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <button class="btn-ghost !py-1 !px-3"><?= e(t('إيقاف')) ?></button>
      </form>
    <?php } elseif ($status === 'paused') {
        if (!can('ads.spend')) { echo '<span class="text-xs text-ink-400">' . e(t('التشغيل لصاحب الشركة')) . '</span>'; return; } ?>
      <details class="relative inline-block">
        <summary class="btn-ghost !py-1 !px-3 cursor-pointer list-none"><?= e(t('تشغيل…')) ?></summary>
        <form method="post" class="absolute end-0 z-10 mt-1 w-72 card p-3 space-y-2 text-start">
          <?= csrf_field() ?><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="to" value="active"><input type="hidden" name="platform" value="<?= e($platform) ?>">
          <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
          <?= ads_spend_confirm(t('موافق إن ده هيبدأ يصرف فلوس فعلاً. الميزانية: %s', $amountText)) ?>
          <button class="btn-primary w-full"><?= e(t('تشغيل')) ?></button>
        </form>
      </details>
    <?php }
}

/** فورم تعديل الميزانية (لصاحب الشركة) */
function ad_budget_form(?array $budget, ?string $cur, string $platform, string $adSetId = ''): void
{
    if (!can('ads.spend')) return; ?>
    <details class="mt-2">
      <summary class="link text-xs cursor-pointer"><?= e(t('تعديل الميزانية')) ?></summary>
      <form method="post" class="mt-2 space-y-2 max-w-md" data-budget-form>
        <?= csrf_field() ?><input type="hidden" name="action" value="budget"><input type="hidden" name="platform" value="<?= e($platform) ?>">
        <?php if ($adSetId !== ''): ?><input type="hidden" name="adset_id" value="<?= e($adSetId) ?>"><?php endif; ?>
        <div class="flex gap-2">
          <input type="number" step="0.01" min="0.01" name="amount" required class="input !mt-0" value="<?= e($budget['amount'] ?? '') ?>" placeholder="<?= e(t('المبلغ')) ?>">
          <select name="type" class="input !mt-0 !w-auto">
            <option value="daily" <?= ($budget['type'] ?? 'daily') === 'daily' ? 'selected' : '' ?>><?= e(t('يومي')) ?></option>
            <option value="lifetime" <?= ($budget['type'] ?? '') === 'lifetime' ? 'selected' : '' ?>><?= e(t('إجمالي')) ?></option>
          </select>
          <span class="self-center text-sm text-ink-500"><?= e($cur ?? '') ?></span>
        </div>
        <p class="text-xs text-ink-500"><?= e(t('الميزانية الحالية: %s', ads_budget_text($budget, $cur))) ?></p>
        <?= ads_spend_confirm(t('راجعت المبلغ الجديد وموافق إنه ممكن يزوّد الصرف.')) ?>
        <button class="btn-primary"><?= e(t('حفظ الميزانية')) ?></button>
      </form>
    </details>
<?php }

render(t('تفاصيل الحملة'), 'ads', function () use ($z, $apiError, $camp, $cid, $pf, $range, $from, $to) {
    if (!$z) { no_api_box(); return; }
    ?>
    <div class="space-y-5">
      <a href="<?= e(url('ads', ['r' => $range])) ?>" class="link text-sm"><?= e(t('→ رجوع للحملات')) ?></a>
      <?php if ($apiError) error_box($apiError); ?>
      <?php if (!$camp && !$apiError): ?>
        <div class="card p-8 text-center text-ink-500 text-sm"><?= e(t('الحملة دي مش موجودة أو مالهاش بيانات في الفترة دي.')) ?></div>
      <?php endif; ?>
      <?php if ($camp):
        $m = $camp['metrics'] ?? [];
        $cur = $camp['currency'] ?? null;
        $platform = (string)($camp['platform'] ?? $pf);
        $st = (string)($camp['status'] ?? '');
        $cBudget = $camp['campaignBudget'] ?? null;
        $isCbo = ($camp['budgetLevel'] ?? '') === 'campaign' || $cBudget;
        $daily = $camp['daily'] ?? [];
        ?>
        <section class="card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-lg font-bold text-ink-900"><?= e($camp['campaignName'] ?? $cid) ?></h2>
              <div class="text-xs text-ink-500 mt-1">
                <?= e(ads_platform_label($platform)) ?> · <?= e($camp['platformAdAccountName'] ?? $camp['platformAdAccountId'] ?? '') ?>
                <?php if (!empty($camp['platformObjective'])): ?> · <?= e(t('الهدف: %s', $camp['platformObjective'])) ?><?php endif; ?>
              </div>
              <div class="mt-2 flex flex-wrap items-center gap-2"><?= ads_status_badge($st) ?>
                <span class="text-sm text-ink-700"><?= e(t('الميزانية: %s', $isCbo ? ads_budget_text($cBudget, $cur) : t('على مستوى المجموعات'))) ?></span>
              </div>
            </div>
            <div><?php ad_toggle_form('campaign_status', $st, $platform, [], $isCbo ? ads_budget_text($cBudget, $cur) : t('حسب ميزانيات المجموعات')); ?></div>
          </div>
          <?php if ($isCbo) ad_budget_form($cBudget, $cur, $platform); ?>
          <?php foreach ($camp['campaignIssuesInfo'] ?? [] as $iss): ?>
            <div class="mt-3 text-sm bg-red-50 border border-red-200 text-red-800 rounded-xl p-3"><?= e($iss['error_summary'] ?? $iss['error_message'] ?? json_encode($iss)) ?></div>
          <?php endforeach; ?>
        </section>

        <div class="flex flex-wrap gap-1">
          <?php foreach (ads_date_presets() as $k => $v): ?>
            <a href="<?= e(url('ad_campaign', ['id' => $cid, 'pf' => $pf, 'r' => $k])) ?>" class="px-3 py-1.5 rounded-lg text-sm border <?= (string)$k === $range ? 'bg-brand-600 text-white border-brand-600' : 'border-ink-200 text-ink-700 hover:bg-ink-50' ?>"><?= e($v) ?></a>
          <?php endforeach; ?>
          <span class="text-xs text-ink-500 ms-auto self-center" dir="ltr"><?= e($from . ' → ' . $to) ?></span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <?php foreach ([
              [t('الصرف'), ads_money($m['spend'] ?? 0, $cur)],
              [t('مرات الظهور'), ads_num($m['impressions'] ?? 0)],
              [t('الوصول'), ads_num($m['reach'] ?? 0)],
              [t('الضغطات'), ads_num($m['clicks'] ?? 0)],
              [t('نسبة الضغط') . ' / ' . t('تكلفة الضغطة'), number_format((float)($m['ctr'] ?? 0), 2) . '% / ' . ads_money($m['cpc'] ?? 0)],
              [t('النتايج'), ads_num(ads_results($m))],
              [t('تكلفة الألف ظهور'), ($m['impressions'] ?? 0) > 0 ? ads_money(($m['spend'] ?? 0) / $m['impressions'] * 1000, $cur) : '—'],
              [t('تكلفة النتيجة'), ads_results($m) > 0 ? ads_money(($m['spend'] ?? 0) / ads_results($m), $cur) : '—'],
          ] as $k): ?>
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e($k[0]) ?></div><div class="ads-num text-lg font-bold text-ink-900 mt-1 tabular-nums text-start" dir="ltr"><?= e($k[1]) ?></div></div>
          <?php endforeach; ?>
        </div>

        <section class="card p-5 grid md:grid-cols-2 gap-6">
          <?php
          $pts = function (string $key) use ($daily) {
              $out = [];
              foreach ($daily as $d) {
                  $out[] = ['label' => (string)($d['date'] ?? ''), 'value' => (float)($d[$key] ?? $d['metrics'][$key] ?? 0)];
              }
              return $out;
          };
          echo ads_bar_chart($pts('spend'), t('الصرف اليومي'), function ($v) use ($cur) { return ads_money($v, $cur); });
          echo ads_bar_chart($pts('clicks'), t('الضغطات اليومية'), 'ads_num');
          ?>
        </section>

        <!-- المجموعات والإعلانات -->
        <section class="space-y-4">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-bold text-ink-900 me-auto"><?= e(t('المجموعات الإعلانية والإعلانات')) ?></h3>
            <input id="acx-q" class="input !py-1.5 w-56 text-sm" placeholder="<?= e(t('ابحث في الإعلانات...')) ?>" aria-label="<?= e(t('ابحث في الإعلانات...')) ?>">
            <button type="button" id="acx-toggle" class="btn-ghost text-sm"><?= e(t('اطوِ الكل')) ?></button>
            <button type="button" id="acx-help" class="btn-ghost text-sm" aria-label="?">?</button>
          </div>
          <?php if (empty($camp['adSets'])): ?><p class="text-sm text-ink-500"><?= e(t('مفيش مجموعات.')) ?></p><?php endif; ?>
          <?php foreach ($camp['adSets'] ?? [] as $set):
            $sm = $set['metrics'] ?? [];
            $sb = $set['adSetBudget'] ?? $set['budget'] ?? null;
            $sid = (string)($set['platformAdSetId'] ?? '');
            $sst = (string)($set['status'] ?? '');
            ?>
            <div class="card p-4 acx-set" data-spend="<?= e((float)($sm['spend'] ?? 0)) ?>">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <button type="button" class="acx-head font-semibold text-ink-900 flex items-center gap-2 text-start"><span class="acx-chev inline-block transition-transform">▾</span><?= e($set['adSetName'] ?? $sid) ?></button>
                  <div class="acx-share mt-2 h-1.5 rounded-full bg-ink-100 overflow-hidden max-w-xs"><div class="h-full bg-brand-500 rounded-full" style="width:0"></div></div>
                  <div class="text-xs text-ink-500 mt-1 flex flex-wrap gap-2 items-center"><?= ads_status_badge($sst) ?>
                    <?php if (!$isCbo): ?><span><?= e(t('الميزانية: %s', ads_budget_text($sb, $cur))) ?></span><?php endif; ?>
                    <span><?= e(t('الصرف: %s', ads_money($sm['spend'] ?? 0, $cur))) ?></span>
                    <span><?= e(t('%s ضغطة', ads_num($sm['clicks'] ?? 0))) ?></span>
                  </div>
                </div>
                <?php if ($sid !== '' && strpos($sid, 'ungrouped') === false) ad_toggle_form('adset_status', $sst, $platform, ['adset_id' => $sid], $isCbo ? ads_budget_text($cBudget, $cur) : ads_budget_text($sb, $cur)); ?>
              </div>
              <?php if (!$isCbo && $sid !== '') ad_budget_form($sb, $cur, $platform, $sid); ?>
              <div class="acx-body mt-3 divide-y divide-ink-100 border-t border-ink-100">
                <?php foreach ($set['ads'] ?? [] as $ad):
                  $cr = $ad['creative'] ?? [];
                  $img = $cr['thumbnailUrl'] ?? $cr['imageUrl'] ?? ($cr['mediaUrls'][0] ?? null);
                  $am = $ad['metrics'] ?? [];
                  $ast = (string)($ad['status'] ?? '');
                  ?>
                  <div class="acx-ad py-3 flex gap-3" data-q="<?= e(mb_strtolower(($ad['name'] ?? '') . ' ' . ($cr['headline'] ?? '') . ' ' . ($cr['body'] ?? ''))) ?>" data-ctr="<?= e(($am['impressions'] ?? 0) > 0 ? round(($am['clicks'] ?? 0) / $am['impressions'] * 100, 2) : -1) ?>">
                    <?php if ($img): ?><img src="<?= e($img) ?>" alt="" class="w-16 h-16 rounded-lg object-cover bg-ink-100 shrink-0" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><div class="w-16 h-16 rounded-lg bg-ink-100 shrink-0"></div><?php endif; ?>
                    <div class="flex-1 min-w-0">
                      <div class="flex flex-wrap items-center gap-2"><span class="font-medium text-sm text-ink-900"><?= e($ad['name'] ?? '') ?></span><?= ads_status_badge($ast) ?></div>
                      <?php if (!empty($cr['headline']) || !empty($cr['body'])): ?>
                        <p class="text-xs text-ink-600 mt-1 line-clamp-2"><?php if (!empty($cr['headline'])): ?><b><?= e($cr['headline']) ?></b> — <?php endif; ?><?= e(mb_substr((string)($cr['body'] ?? ''), 0, 160)) ?></p>
                      <?php endif; ?>
                      <?php if (!empty($ad['rejectionReason'])): ?><p class="text-xs text-red-700 mt-1"><?= e(is_string($ad['rejectionReason']) ? $ad['rejectionReason'] : json_encode($ad['rejectionReason'])) ?></p><?php endif; ?>
                      <div class="text-xs text-ink-500 mt-1"><?= e(t('الصرف: %s · الظهور: %s · الضغطات: %s', ads_money($am['spend'] ?? 0, $cur), ads_num($am['impressions'] ?? 0), ads_num($am['clicks'] ?? 0))) ?>
                        <?php if (!empty($cr['linkUrl'])): ?> · <a href="<?= e($cr['linkUrl']) ?>" target="_blank" rel="noopener" class="link"><?= e(t('الرابط ↗')) ?></a><?php endif; ?>
                        <?php if (!empty($cr['instagramPermalinkUrl'])): ?> · <a href="<?= e($cr['instagramPermalinkUrl']) ?>" target="_blank" rel="noopener" class="link"><?= e(t('المنشور ↗')) ?></a><?php endif; ?>
                      </div>
                    </div>
                    <div class="shrink-0"><?php if (!empty($ad['_id'])) ad_toggle_form('ad_status', $ast, $platform, ['ad_id' => (string)$ad['_id']], $isCbo ? ads_budget_text($cBudget, $cur) : ads_budget_text($sb, $cur)); ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
    </div>
    <script>window.ACX = <?= json_encode(['collapse' => t('اطوِ الكل'), 'expand' => t('افتح الكل'), 'best' => t('أعلى نسبة ضغط'), 'share' => t('%s من صرف الحملة'), 'ctr' => t('نسبة الضغط %s'),
      'empty' => t('مفيش إعلانات مطابقة'), 'keys' => t('اختصارات لوحة المفاتيح'), 'kSearch' => t('البحث في الإعلانات'), 'kToggle' => t('طي/فتح المجموعات'), 'kBack' => t('رجوع للحملات'), 'back' => url('ads', ['r' => $range])], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-ad_campaign.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
}, ['full' => true]);
