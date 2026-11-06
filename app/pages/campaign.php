<?php
/** #5 حملة متكاملة في خطوة: خدمة ← صفحة هبوط + كوبون + لينك متتبع + واتساب + منشورات موزعة على التقويم + تقرير واحد */
$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/compose_fake_z.php')) $z = require $fxd . '/compose_fake_z.php'; // اختبارات بس: مفيش نشر حقيقي
$wid = (int)ws('id');
$kb = function_exists('kb_get') ? kb_get($wid) : [];
$svcs = array_values(array_filter((array)($kb['services']['rows'] ?? []), function ($r) { return trim((string)($r['name'] ?? '')) !== ''; }));
$accs = [];
if ($z) { try { foreach ($z->accounts(zernio_profile()) as $a) if (in_array((string)($a['platform'] ?? ''), mcp_platforms(), true) && ($a['isActive'] ?? true) && empty($a['needsReconnection'])) $accs[] = $a; } catch (Throwable $e) { $err = $e->getMessage(); } }
if (function_exists('acc_filter')) $accs = acc_filter($accs);
$hour = 11; if ($z && function_exists('best_slots_local')) { try { $hour = (int)(best_slots_local($z, 1)[0]['hour'] ?? 11); } catch (Throwable $e) {} }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $J = function (array $d, int $c = 200) { http_response_code($c); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if (!can('posts.create')) $J(['error' => t('مالكش صلاحية.')], 403);
    $si = (int)($_POST['svc'] ?? -1); $svc = $svcs[$si] ?? null; if (!$svc) $J(['error' => t('اختار خدمة من ملف الشركة.')], 422);
    $days = max(1, min(60, (int)($_POST['days'] ?? 14))); $count = max(1, min(20, (int)($_POST['count'] ?? 5)));
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['start'] ?? '')) ? (string)$_POST['start'] : (new DateTime('tomorrow', new DateTimeZone(site_timezone())))->format('Y-m-d');
    $endD = (new DateTime($start, new DateTimeZone(site_timezone())))->modify('+' . ($days - 1) . ' days');
    $coupon = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['coupon'] ?? ''))); $useCoupon = !empty($_POST['use_coupon']);
    $offer = mb_substr(trim((string)($_POST['offer'] ?? '')), 0, 120);
    $wa = preg_replace('/\D/', '', (string)($kb['contact']['whatsapp'] ?? ''));
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'preview') {
        $posts = mcp_build_posts($svc, $kb, ['link' => '{link}', 'wa' => $wa !== '' ? '{wa}' : '', 'tags' => function_exists('kb_profile_tags') ? kb_profile_tags() : [], 'coupon' => $useCoupon ? ($coupon ?: 'XXXX') : '', 'offer' => $offer, 'end_label' => $endD->format('d/m'), 'count' => $count]);
        $when = mcp_schedule($start, $days, count($posts), $hour);
        foreach ($posts as $i => &$p) $p['when'] = $when[$i]; unset($p);
        $J(['ok' => true, 'posts' => $posts, 'end' => $endD->format('Y-m-d'), 'hour' => $hour]);
    }
    if ($act !== 'create') $J(['error' => t('طلب غير صحيح.')], 400);
    if (!$z) $J(['error' => $err ?: t('اربط Zernio الأول.')], 422);
    $ids = array_values(array_intersect(array_map('strval', (array)($_POST['accounts'] ?? [])), array_map(function ($a) { return (string)$a['_id']; }, $accs)));
    if (!$ids) $J(['error' => t('اختار حساب واحد على الأقل.')], 422);
    $texts = array_slice(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 5000); }, (array)($_POST['text'] ?? [])), 0, 20);
    $whens = array_slice(array_map('strval', (array)($_POST['when'] ?? [])), 0, 20);
    if (!$texts) $J(['error' => t('مفيش منشورات.')], 422);
    $img = preg_match('~^https://~i', trim((string)($_POST['img'] ?? ''))) ? trim((string)$_POST['img']) : '';
    $mode = ($_POST['mode'] ?? 'draft') === 'schedule' ? 'schedule' : 'draft';
    $name = mb_substr(trim((string)($_POST['name'] ?? '')) ?: $svc['name'], 0, 190);
    // 1) صفحة الهبوط
    $slug = 'cmp' . substr(bin2hex(random_bytes(4)), 0, 7);
    $c = (array)($kb['company'] ?? []);
    $lps = lp_list(); $lps[] = ['slug' => $slug, 'on' => 1, 'title' => mb_substr($svc['name'], 0, 140), 'sub' => mb_substr(trim(($svc['price'] ? t('السعر: %s', $svc['price']) . ' · ' : '') . ($offer ?: (string)($c['tagline'] ?? ''))), 0, 240),
        'body' => mb_substr(trim(implode("\n\n", array_filter([(string)($svc['details'] ?? ''), (string)($c['about'] ?? ''), (string)($c['delivery'] ?? '')]))), 0, 3000), 'img' => $img, 'color' => '#4f46e5', 'wa' => $wa, 'cta' => t('اطلب دلوقتي'), 'views' => 0];
    save_setting('crm_lp_' . $wid, json_encode(array_slice($lps, -30), JSON_UNESCAPED_UNICODE));
    // 2) كوبون + لينك متتبع للصفحة + لينك واتساب متتبع
    $cp = ''; if ($useCoupon && function_exists('coupon_make')) { $cp = (string)coupon_make($coupon, t('حملة: %s', $name)); if ($cp === '') $J(['error' => t('الكود ده مستخدم قبل كده. اختار كود تاني.')], 422); }
    $lk = crm_link_make(lp_url($slug), t('حملة: %s', $name)); $link = crm_link_short($lk['code']); $codes = [$lk['code']];
    $waLink = ''; if ($wa !== '' && function_exists('cap_wa_link')) { $w = cap_wa_link($wa, t('عاوز أعرف عن %s', $svc['name']), t('حملة: %s (واتساب)', $name)); if ($w) { $waLink = $w['short']; $codes[] = $w['code']; } }
    // 3) المنشورات
    $byId = []; foreach ($accs as $a) $byId[(string)$a['_id']] = $a;
    $plat = []; $skipped = [];
    foreach ($ids as $id) { $a = $byId[$id]; if ($a['platform'] === 'instagram' && $img === '') { $skipped[] = (string)($a['displayName'] ?? $a['username'] ?? 'Instagram'); continue; } $plat[] = ['platform' => $a['platform'], 'accountId' => $id]; }
    if (!$plat) $J(['error' => t('إنستجرام محتاج صورة. ضيف رابط صورة أو اختار حساب تاني.')], 422);
    $made = []; $fail = [];
    foreach ($texts as $i => $tx) {
        $content = str_replace(['{link}', '{wa}', 'XXXX'], [$link, $waLink ?: $link, $cp], $tx);
        $payload = ['content' => $content, 'platforms' => $plat, 'timezone' => site_timezone()];
        if ($img !== '') $payload['mediaItems'] = [['type' => 'image', 'url' => $img]];
        $w = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $whens[$i] ?? '') ? $whens[$i] . ':00' : null;
        if ($mode === 'schedule' && $w) $payload['scheduledFor'] = $w; else { $payload['isDraft'] = true; if ($w) $payload['scheduledFor'] = $w; }
        try { $r = $z->createPost($payload); $made[] = (string)($r['post']['_id'] ?? ''); } catch (Throwable $e) { $fail[] = ($i + 1) . ': ' . $e->getMessage(); }
    }
    db()->prepare('INSERT INTO campaigns (workspace_id, name, service, start_at, end_at, lp_slug, coupon, codes, post_ids, mode, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wid, $name, mb_substr($svc['name'], 0, 190), $start, $endD->format('Y-m-d'), $slug, $cp ?: null, json_encode($codes), json_encode(array_values(array_filter($made))), $mode, (int)(current_user()['id'] ?? 0) ?: null]);
    log_activity('campaign.create', $name . ' ' . count($made) . ' posts');
    $J(['ok' => true, 'made' => count($made), 'fail' => $fail, 'skipped' => $skipped, 'lp' => lp_url($slug), 'link' => $link, 'wa' => $waLink, 'coupon' => $cp, 'id' => (int)db()->lastInsertId()]);
}

$camps = mcp_list();
render(t('حملة متكاملة'), 'campaign', function () use ($svcs, $accs, $hour, $camps, $err, $kb) {
    $tz = new DateTimeZone(site_timezone()); $tomorrow = (new DateTime('tomorrow', $tz))->format('Y-m-d');
    // ممكن تتملي من رابط (start, days, count, name, offer)
    $g = function ($k, $max) { return mb_substr(trim((string)($_GET[$k] ?? '')), 0, $max); };
    $pStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $g('start', 10)) && $g('start', 10) >= $tomorrow ? $g('start', 10) : $tomorrow;
    $pDays = max(1, min(60, (int)($g('days', 3) ?: 14))); $pCount = max(1, min(20, (int)($g('count', 3) ?: 5)));
    $pName = $g('name', 190); $pOffer = $g('offer', 120);
    ?>
  <?php if (!$svcs): ?>
    <div class="card p-8 text-center"><div class="mx-auto mb-3 w-12 h-12 rounded-2xl bg-ink-100 text-ink-500 grid place-items-center"><?= ui_icon('layers', 'w-6 h-6') ?></div>
      <b class="block text-ink-900"><?= e(t('محتاجين خدماتك في ملف الشركة الأول')) ?></b><p class="text-sm text-ink-500 mt-1"><?= e(t('الحملة بتتبني من خدمة وسعرها. املأ الملف من موقعك في دقيقة.')) ?></p>
      <a href="<?= e(url('knowledge')) ?>" class="btn-primary mt-4 inline-flex"><?= ui_icon('link', 'w-4 h-4') ?><?= e(t('املأ ملف الشركة')) ?></a></div>
  <?php else: ?>
  <div class="grid xl:grid-cols-[380px_minmax(0,1fr)] gap-4 items-start">
    <form id="cmp-f" class="card p-4 space-y-3 xl:sticky xl:top-20" data-no-ajax>
      <b class="text-ink-900 inline-flex items-center gap-1.5"><?= ui_icon('megaphone', 'w-4 h-4 text-brand-600') ?><?= e(t('الحملة')) ?></b>
      <label class="block text-xs text-ink-600"><?= e(t('الخدمة')) ?><select name="svc" class="input text-sm"><?php foreach ($svcs as $i => $s): ?><option value="<?= $i ?>"><?= e($s['name'] . (!empty($s['price']) ? ' · ' . $s['price'] : '')) ?></option><?php endforeach; ?></select></label>
      <label class="block text-xs text-ink-600"><?= e(t('اسم الحملة (اختياري)')) ?><input name="name" maxlength="190" class="input text-sm" dir="auto" value="<?= e($pName) ?>"></label>
      <div class="grid grid-cols-3 gap-2"><label class="block text-xs text-ink-600 col-span-1"><?= e(t('تبدأ')) ?><input type="date" name="start" value="<?= e($pStart) ?>" class="input text-sm"></label>
        <label class="block text-xs text-ink-600"><?= e(t('كام يوم')) ?><input type="number" name="days" value="<?= $pDays ?>" min="1" max="60" class="input text-sm"></label>
        <label class="block text-xs text-ink-600"><?= e(t('كام منشور')) ?><input type="number" name="count" value="<?= $pCount ?>" min="1" max="20" class="input text-sm"></label></div>
      <div class="rounded-xl border border-ink-200 p-2.5 space-y-2"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="use_coupon" value="1" checked> <?= e(t('كوبون خصم متتبع')) ?></label>
        <div class="grid grid-cols-2 gap-2"><input name="coupon" maxlength="24" class="input !mt-0 text-sm font-mono" dir="ltr" placeholder="<?= e(t('الكود (اختياري)')) ?>"><input name="offer" maxlength="120" class="input !mt-0 text-sm" dir="auto" value="<?= e($pOffer) ?>" placeholder="<?= e(t('العرض (مثلًا: خصم 15%)')) ?>"></div></div>
      <label class="block text-xs text-ink-600"><?= e(t('رابط صورة (https، اختياري — لازم لإنستجرام)')) ?><input name="img" class="input text-sm" dir="ltr" placeholder="https://"></label>
      <div><span class="text-xs text-ink-600"><?= e(t('الحسابات')) ?></span>
        <?php if (!$accs): ?><p class="text-xs text-amber-700 mt-1"><?= e($err ?: t('مفيش حسابات مربوطة تنفع للحملة (فيسبوك، لينكدإن، X، ثريدز، جوجل، إنستجرام).')) ?></p><?php endif; ?>
        <div class="mt-1 grid gap-1 max-h-44 overflow-y-auto"><?php foreach ($accs as $a): ?><label class="flex items-center gap-2 rounded-lg border border-ink-200 px-2 py-1.5 text-sm cursor-pointer has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50"><input type="checkbox" name="accounts[]" value="<?= e($a['_id']) ?>" checked><?= platform_icon((string)$a['platform'], 'w-4 h-4') ?><span class="truncate"><?= e($a['displayName'] ?? $a['username'] ?? '') ?></span></label><?php endforeach; ?></div></div>
      <div class="grid grid-cols-2 gap-1 rounded-xl bg-ink-100 p-1 text-sm"><label class="text-center rounded-lg py-1.5 cursor-pointer has-[:checked]:bg-white has-[:checked]:shadow-sm has-[:checked]:font-semibold"><input type="radio" name="mode" value="draft" checked class="sr-only"><?= e(t('مسودات أراجعها')) ?></label><label class="text-center rounded-lg py-1.5 cursor-pointer has-[:checked]:bg-white has-[:checked]:shadow-sm has-[:checked]:font-semibold"><input type="radio" name="mode" value="schedule" class="sr-only"><?= e(t('جدولة مباشرة')) ?></label></div>
      <p class="text-[11px] text-ink-500"><?= e(t('المواعيد موزعة على الفترة الساعة %s (أحسن وقت للنشر عندك).', fmt_hour($hour))) ?></p>
    </form>
    <div class="space-y-3 min-w-0">
      <div class="card p-4"><div class="flex flex-wrap items-center gap-2 mb-2"><b class="text-ink-900"><?= e(t('هيتعمل إيه')) ?></b><span class="text-xs text-ink-500"><?= e(t('عدّل أي نص قبل الإنشاء. {link} = لينك صفحة الحملة المتتبع، و{wa} = لينك واتساب المتتبع.')) ?></span></div>
        <div class="grid sm:grid-cols-4 gap-2 text-xs">
          <?php foreach ([['file', t('صفحة هبوط للخدمة')], ['tag', t('كوبون متتبع')], ['chat', t('لينك واتساب متتبع')], ['chart', t('تقرير واحد للحملة')]] as [$ic, $tx]): ?><div class="rounded-xl bg-ink-50 p-2.5 flex items-center gap-2"><span class="text-brand-600"><?= ui_icon($ic, 'w-4 h-4') ?></span><?= e($tx) ?></div><?php endforeach; ?>
        </div></div>
      <div id="cmp-posts" class="space-y-2"></div>
      <div class="card p-3 flex flex-wrap items-center gap-2 sticky bottom-3 shadow-lg"><span id="cmp-sum" class="text-sm text-ink-600"></span><button type="button" id="cmp-go" class="btn-primary ms-auto" disabled><?= ui_icon('megaphone', 'w-4 h-4') ?><?= e(t('أنشئ الحملة')) ?></button></div>
      <div id="cmp-done"></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($camps): ?>
  <section class="card p-4 mt-5" id="cmp-list"><b class="text-ink-900 inline-flex items-center gap-1.5 mb-3"><?= ui_icon('chart', 'w-4 h-4 text-brand-600') ?><?= e(t('حملاتك')) ?></b>
    <div class="overflow-x-auto"><table class="w-full text-sm min-w-[720px]"><thead><tr class="text-xs text-ink-500 border-b border-ink-100"><th class="text-start py-1.5"><?= e(t('الحملة')) ?></th><th><?= e(t('الفترة')) ?></th><th><?= e(t('منشورات')) ?></th><th><?= e(t('ضغطات')) ?></th><th><?= e(t('زيارات الصفحة')) ?></th><th><?= e(t('عملاء')) ?></th><th><?= e(t('بقوا عملاء')) ?></th><th><?= e(t('المبيعات')) ?></th></tr></thead><tbody>
    <?php foreach ($camps as $c): $s = mcp_stats($c); ?><tr class="border-b border-ink-50"><td class="py-2"><b class="text-ink-900" dir="auto"><?= e($c['name']) ?></b><div class="text-[11px] text-ink-400"><a class="link" target="_blank" href="<?= e(lp_url($c['lp_slug'])) ?>"><?= e(t('صفحة الهبوط')) ?></a><?= $c['coupon'] ? ' · <span class="font-mono">' . e($c['coupon']) . '</span>' : '' ?> · <?= e($c['mode'] === 'schedule' ? t('مجدولة') : t('مسودات')) ?></div></td>
      <td class="text-center text-xs whitespace-nowrap"><?= e(fmt_date($c['start_at'], 'd/m')) ?> – <?= e(fmt_date($c['end_at'], 'd/m')) ?></td><td class="text-center"><?= $s['posts'] ?></td><td class="text-center"><?= $s['clicks'] ?></td><td class="text-center"><?= $s['views'] ?></td>
      <td class="text-center font-semibold"><?= $s['leads'] ?></td><td class="text-center text-lime-700 font-semibold"><?= $s['won'] ?></td><td class="text-center tabular-nums"><?= e(number_format($s['value'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
  <?php endif; ?>
  <script>window.CMP = <?= json_encode(['url' => url('campaign'), 'csrf' => csrf_token(), 'angles' => ['offer' => t('العرض'), 'detail' => t('التفاصيل'), 'faq' => t('سؤال شائع'), 'about' => t('عن الشركة'), 'last' => t('آخر فرصة')],
    'sum' => t('%s منشور على %s حساب'), 'busy' => t('بننشئ الحملة…'), 'done' => t('اتعملت الحملة: %s منشور'), 'fail' => t('%s منشور ماتعملش'), 'skip' => t('اتشال من غير صورة: %s'), 'lp' => t('صفحة الهبوط'), 'link' => t('اللينك المتتبع'), 'wa' => t('لينك واتساب'), 'coupon' => t('الكوبون'), 'cal' => t('افتح التقويم'), 'calUrl' => url('posts', ['view' => 'cal']), 'err' => t('حصلت مشكلة في الاتصال. جرب تاني.'), 'del' => t('شيل')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/campaign.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
