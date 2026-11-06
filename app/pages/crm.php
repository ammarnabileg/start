<?php
/** إدارة العملاء ← الشركات المستهدفة: العميل المثالي + تحديث + 5 صناع قرار لكل شركة + كشف بياناتهم */
$hasKey = crm_has_key();
// مزايا Apollo بتظهر بس لو مربوط؛ المدير بيفضل يقدر يفتح الصفحة عشان يربطه
if (!$hasKey && !can('crm.manage')) redirect(url('crm_leads'));
$prefs = crm_prefs();
$uid = (int)(current_user()['id'] ?? 0);
$canReveal = can('crm.reveal');
$canManage = can('crm.manage');
$wid = (int)ws('id');
$json = function (array $a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
$getCo = function (int $id) use ($wid) {
    $st = db()->prepare('SELECT * FROM crm_companies WHERE id = ? AND workspace_id = ?');
    $st->execute([$id, $wid]);
    return $st->fetch() ?: null;
};
$leadOut = function (array $l) {
    return ['id' => (int)$l['id'], 'ext' => (string)$l['ext_id'], 'name' => $l['name'], 'title' => (string)$l['title'], 'email' => (string)$l['email'],
        'phone' => (string)$l['phone'], 'pending' => (int)$l['phone_pending'] === 1, 'wa' => crm_wa_digits($l['phone']), 'linkedin' => (string)$l['linkedin']];
};

// الأشخاص في الشركة (للدرج الجانبي)
if (($_GET['ajax'] ?? '') === 'people') {
    $co = $getCo((int)($_GET['id'] ?? 0));
    if (!$co) $json(['ok' => false, 'error' => t('الشركة مش موجودة')]);
    $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? AND company_id = ? ORDER BY id');
    $st->execute([$wid, (int)$co['id']]);
    $known = [];
    foreach ($st->fetchAll() as $l) $known[$l['ext_id'] ?: ('l' . $l['id'])] = $leadOut($l);
    try {
        $ppl = crm_company_people($co, (string)($prefs['icp']['titles'] ?? ''));
    } catch (CrmPlanException $ex) {
        save_setting('crm_plan_' . $wid, 'free');
        $json(['ok' => false, 'plan' => true, 'error' => t('خطة Apollo المجانية مش بتسمح بالبحث عن الأشخاص ولا كشف أرقامهم. لو تعرف حد في الشركة دي ضيفه بإيدك تحت، أو رقّي خطة Apollo.'), 'known' => array_values($known)]);
    } catch (CrmException $ex) {
        $json(['ok' => false, 'error' => $ex->getMessage(), 'known' => array_values($known)]);
    }
    foreach ($ppl as &$p) { if (isset($known[$p['id']])) { $p['lead'] = $known[$p['id']]; unset($known[$p['id']]); } }
    unset($p);
    $json(['ok' => true, 'people' => $ppl, 'known' => array_values($known), 'left' => max(0, (int)$prefs['daily_limit'] - crm_reveals_today($uid))]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'reveal') {
        if (!$canReveal) $json(['ok' => false, 'error' => t('مالكش صلاحية الكشف.')]);
        $co = $getCo((int)($_POST['company'] ?? 0));
        $pid = mb_substr((string)($_POST['person'] ?? ''), 0, 64);
        if (!$co || $pid === '') $json(['ok' => false, 'error' => t('بيانات ناقصة')]);
        // اتكشف قبل كده؟ مانصرفش كريديت تاني
        $st = db()->prepare('SELECT * FROM crm_leads WHERE workspace_id = ? AND ext_id = ? LIMIT 1');
        $st->execute([$wid, $pid]);
        if ($l = $st->fetch()) $json(['ok' => true, 'lead' => $leadOut($l), 'again' => true]);
        $left = (int)$prefs['daily_limit'] - crm_reveals_today($uid);
        if ($left <= 0) $json(['ok' => false, 'error' => t('خلصت حد الكشف بتاعك النهارده (%s). بيتجدد بكرة.', (int)$prefs['daily_limit'])]);
        try {
            $l = crm_reveal($co, $pid, $prefs);
        } catch (CrmException $ex) {
            $json(['ok' => false, 'error' => $ex->getMessage()]);
        }
        $json(['ok' => true, 'lead' => $leadOut($l), 'merged' => !empty($l['merged']), 'left' => $left - 1]);
    }
    if ($action === 'manual_lead') {
        $co = $getCo((int)($_POST['company'] ?? 0));
        $name = trim((string)($_POST['name'] ?? ''));
        if (!$co || $name === '' || (trim((string)($_POST['email'] ?? '')) === '' && trim((string)($_POST['phone'] ?? '')) === '')) $json(['ok' => false, 'error' => t('اكتب الاسم ورقم أو إيميل على الأقل.')]);
        $l = crm_lead_upsert(['source' => 'manual', 'company_id' => (int)$co['id'], 'company' => $co['name'], 'country' => (string)$co['country'], 'name' => $name,
            'title' => (string)($_POST['title'] ?? ''), 'email' => (string)($_POST['email'] ?? ''), 'phone' => (string)($_POST['phone'] ?? ''), 'owner_id' => $uid ?: null]);
        log_activity('crm.add', $name . ' @ ' . $co['name']);
        $json(['ok' => true, 'lead' => $leadOut($l), 'merged' => !empty($l['merged'])]);
    }
    if ($action === 'hide' || $action === 'unhide') {
        db()->prepare('UPDATE crm_companies SET hidden = ? WHERE id = ? AND workspace_id = ?')->execute([$action === 'hide' ? 1 : 0, (int)($_POST['id'] ?? 0), $wid]);
        $json(['ok' => true]);
    }
    if ($action === 'refresh') {
        try {
            [$new, $old, $wrapped] = crm_refresh($prefs);
            if ($new + $old === 0) flash('error', t('مفيش شركات بالفلاتر دي. وسّع البحث (دولة أو مجال تاني).'));
            elseif ($new === 0) flash('success', t('مفيش شركات جديدة المرة دي، نفس الـ %s شركة اتحدّثت.', $old));
            else flash('success', t('%s شركة جديدة (متعلّم عليها) و%s زي ما هي.', $new, $old) . ($wrapped ? ' ' . t('خلصنا كل النتايج ورجعنا للأول.') : ''));
        } catch (CrmException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect(url('crm'));
    }
    if ($action === 'add_domains') {
        $list = preg_split('/[\s,،]+/u', (string)($_POST['domains'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        try {
            [$added, $dup, $bad] = crm_add_domains($list);
            flash($added ? 'success' : 'error', t('اتضاف %s شركة', $added) . ($dup ? ' · ' . t('%s موجودين قبل كده', $dup) : '') . ($bad ? ' · ' . t('دومين مش صحيح: %s', implode(', ', array_slice($bad, 0, 5))) : ''));
            if ($added) log_activity('crm.add_domains', (string)$added);
        } catch (CrmException $ex) {
            flash('error', $ex->getMessage());
        }
        redirect(url('crm'));
    }
    if ($action === 'icp' && $canManage) {
        $icp = [
            'locations' => array_slice(array_values(array_filter(array_map(function ($s) { return mb_substr(trim($s), 0, 80); }, explode(',', (string)($_POST['locations'] ?? ''))))), 0, 10),
            'keywords' => mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 300),
            'sizes' => array_values(array_intersect((array)($_POST['sizes'] ?? []), array_keys(crm_sizes()))),
            'titles' => mb_substr(trim((string)($_POST['titles'] ?? '')), 0, 300),
        ];
        crm_save_prefs(['icp' => $icp, 'next_page' => 1, 'daily_limit' => max(0, min(500, (int)($_POST['daily_limit'] ?? 20))), 'auto_assign' => !empty($_POST['auto_assign']) ? 1 : 0]);
        log_activity('crm.icp', json_encode($icp, JSON_UNESCAPED_UNICODE));
        flash('success', t('اتحفظ العميل المثالي. دوس "تحديث" عشان تجيب شركات بيه.'));
        redirect(url('crm'));
    }
    redirect(url('crm'));
}

$st = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM crm_leads l WHERE l.workspace_id = c.workspace_id AND l.company_id = c.id) AS revealed
    FROM crm_companies c WHERE c.workspace_id = ? ORDER BY c.hidden, c.last_batch DESC, (c.first_batch = c.last_batch) DESC, c.name LIMIT 400');
$st->execute([$wid]);
$companies = $st->fetchAll();
$batch = (int)$prefs['batch'];
$st = db()->prepare('SELECT COUNT(*) FROM crm_leads WHERE workspace_id = ?');
$st->execute([$wid]);
$leadCount = (int)$st->fetchColumn();
$today = crm_reveals_today($uid);

render(t('الشركات المستهدفة'), 'crm', function () use ($hasKey, $prefs, $companies, $batch, $leadCount, $today, $canReveal, $canManage) {
    $icp = $prefs['icp'];
    $visible = array_filter($companies, function ($c) { return !$c['hidden']; });
    $newOnes = array_filter($visible, function ($c) use ($batch) { return (int)$c['first_batch'] === $batch && $batch > 0; });
    $limit = (int)$prefs['daily_limit'];
    $icpEmpty = !$icp['locations'] && $icp['keywords'] === '' && !$icp['sizes'];
    $sizes = crm_sizes();
    ?>
    <?php if (!$hasKey): ?>
      <div class="card p-5 mb-5 border-brand-200 bg-brand-50/40">
        <div class="font-semibold text-ink-900"><?= e(t('اربط Apollo.io عشان تبدأ')) ?></div>
        <p class="text-sm text-ink-600 mt-1"><?= e(t('الشركات والأشخاص جايين من قاعدة بيانات Apollo.io. اعمل حساب هناك وحط الـ API Key في التكاملات.')) ?></p>
        <?php if (can('integrations.manage')): ?><a class="btn-primary mt-3 inline-flex" href="<?= e(url('connectors')) ?>"><?= e(t('افتح التكاملات')) ?></a><?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('الشركات')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><?= count($visible) ?></div></div>
      <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4"><div class="text-xs text-ink-500"><?= e(t('جديد في آخر تحديث')) ?></div><div class="text-2xl font-bold text-amber-700 mt-1"><?= count($newOnes) ?></div></div>
      <div class="rounded-2xl border border-ink-200 bg-white p-4"><div class="text-xs text-ink-500"><?= e(t('كشفت النهارده')) ?></div><div class="text-2xl font-bold text-ink-900 mt-1"><span id="crm-used"><?= $today ?></span><span class="text-sm text-ink-400 font-medium"> / <?= $limit ?></span></div>
        <div class="h-1.5 rounded-full bg-ink-100 mt-2 overflow-hidden"><div id="crm-bar" class="h-full bg-brand-500 rounded-full" style="width:<?= $limit ? min(100, round($today / $limit * 100)) : 100 ?>%"></div></div></div>
      <a href="<?= e(url('crm_leads')) ?>" class="rounded-2xl border border-lime-200 bg-lime-50/60 p-4 hover:border-lime-400 transition"><div class="text-xs text-ink-500"><?= e(t('في الـ Leads')) ?></div><div class="text-2xl font-bold text-lime-700 mt-1" id="crm-leads"><?= $leadCount ?></div></a>
    </div>

    <div class="grid lg:grid-cols-3 gap-5">
      <div class="lg:col-span-2 order-2 lg:order-1">
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <form method="post" id="crm-refresh"><?= csrf_field() ?><input type="hidden" name="action" value="refresh">
            <button class="btn-primary inline-flex items-center gap-2" <?= $hasKey ? '' : 'disabled' ?>><span id="crm-spin" aria-hidden="true">↻</span><?= e(t('تحديث')) ?></button>
          </form>
          <div id="crm-chips" class="flex flex-wrap gap-1.5" role="group">
            <button type="button" data-f="" class="chip on"><?= e(t('الكل')) ?></button>
            <button type="button" data-f="new" class="chip"><?= e(t('الجديد')) ?> <span class="opacity-60"><?= count($newOnes) ?></span></button>
            <button type="button" data-f="rev" class="chip"><?= e(t('كشفت منها')) ?></button>
            <button type="button" data-f="hid" class="chip"><?= e(t('المخفية')) ?></button>
          </div>
          <input id="crm-q" type="search" class="input ms-auto w-full sm:w-56" placeholder="<?= e(t('دوّر على شركة…')) ?>">
        </div>
        <?php if (!$companies): ?>
          <?= an_empty(t('لسه مفيش شركات'), $icpEmpty ? t('حدد العميل المثالي على الجنب، وبعدين دوس "تحديث".') : t('دوس "تحديث" يجيبلك شركات بالعميل المثالي بتاعك.'), '🏢') ?>
        <?php endif; ?>
        <div id="crm-grid" class="grid sm:grid-cols-2 gap-3">
          <?php foreach ($companies as $c): $isNew = $batch > 0 && (int)$c['first_batch'] === $batch && !$c['hidden'];
            $loc = trim(implode('، ', array_filter([$c['city'], $c['country']]))); ?>
            <div class="crm-co group relative rounded-2xl border p-4 cursor-pointer transition hover:shadow-md <?= $isNew ? 'border-amber-300 bg-gradient-to-br from-amber-50 via-orange-50 to-white ring-1 ring-amber-200' : 'border-ink-200 bg-white hover:border-brand-300' ?>"
                 data-id="<?= (int)$c['id'] ?>" data-new="<?= $isNew ? 1 : 0 ?>" data-rev="<?= (int)$c['revealed'] ?>" data-hid="<?= (int)$c['hidden'] ?>"
                 data-q="<?= e(mb_strtolower($c['name'] . ' ' . $c['industry'] . ' ' . $loc . ' ' . $c['domain'])) ?>" style="<?= $c['hidden'] ? 'display:none' : '' ?>" tabindex="0" role="button">
              <div class="flex items-start gap-3">
                <?php if ($c['logo']): ?><img src="<?= e($c['logo']) ?>" alt="" class="w-11 h-11 rounded-xl object-contain bg-white border border-ink-100 shrink-0" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-11 h-11 rounded-xl bg-brand-100 text-brand-700 font-bold grid place-items-center shrink-0',textContent:<?= e(json_encode(mb_substr($c['name'], 0, 1))) ?>}))">
                <?php else: ?><div class="w-11 h-11 rounded-xl bg-brand-100 text-brand-700 font-bold grid place-items-center shrink-0"><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></div><?php endif; ?>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-2">
                    <div class="crm-name font-semibold text-ink-900 truncate"><?= e($c['name']) ?></div>
                    <?php if ($isNew): ?><span class="shrink-0 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-md bg-amber-500 text-white"><?= e(t('جديد')) ?></span><?php endif; ?>
                  </div>
                  <div class="text-xs text-ink-500 truncate mt-0.5"><?= e($c['industry'] ?: '—') ?></div>
                </div>
                <button type="button" class="crm-hide opacity-0 group-hover:opacity-100 focus:opacity-100 text-ink-400 hover:text-red-600 text-xs px-1.5 py-1 rounded-lg hover:bg-red-50 transition" title="<?= e($c['hidden'] ? t('رجّعها') : t('مش مهتم، اخفيها')) ?>" data-act="<?= $c['hidden'] ? 'unhide' : 'hide' ?>"><?= $c['hidden'] ? '↺' : '✕' ?></button>
              </div>
              <div class="flex flex-wrap gap-x-3 gap-y-1 mt-3 text-xs text-ink-500">
                <?php if ($loc): ?><span>📍 <?= e($loc) ?></span><?php endif; ?>
                <?php if ($c['employees']): ?><span>👥 <?= e(t('%s موظف', number_format((int)$c['employees']))) ?></span><?php endif; ?>
                <?php if ($c['domain']): ?><span dir="ltr">🌐 <?= e($c['domain']) ?></span><?php endif; ?>
                <?php if ((int)$c['revealed']): ?><span class="text-lime-700 font-medium">✓ <?= e(t('كشفت %s', (int)$c['revealed'])) ?></span><?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div id="crm-none" class="hidden text-center text-sm text-ink-500 py-10"><?= e(t('مفيش شركات تطابق البحث')) ?></div>
      </div>

      <aside class="order-1 lg:order-2">
        <?php if (setting('crm_plan_' . (int)ws('id')) === 'free'): ?>
          <div class="card p-4 mb-4 border-amber-200 bg-amber-50/70 text-sm">
            <div class="font-semibold text-amber-900"><?= e(t('حسابك على خطة Apollo المجانية')) ?></div>
            <p class="text-xs text-amber-800 mt-1"><?= e(t('البحث عن الشركات والأشخاص وكشف الأرقام والإيميلات مقفولين فيها. اللي شغال: إضافة الشركات بالدومين، وإضافة الأشخاص اللي تعرفهم بإيدك. البحث والكشف هيشتغلوا لوحدهم لو رقّيت الخطة.')) ?></p>
            <a class="link text-xs mt-1 inline-block" href="https://www.apollo.io/pricing" target="_blank" rel="noopener"><?= e(t('خطط Apollo')) ?> ↗</a>
          </div>
        <?php endif; ?>
        <form method="post" class="card p-5 mb-4 space-y-2">
          <?= csrf_field() ?><input type="hidden" name="action" value="add_domains">
          <h3 class="font-semibold text-ink-900">＋ <?= e(t('ضيف شركات بالدومين')) ?></h3>
          <p class="text-xs text-ink-500"><?= e(t('اكتب مواقع الشركات اللي عاوز تستهدفها (سطر لكل واحدة، لحد 25). بتشتغل مع أي خطة.')) ?></p>
          <textarea name="domains" rows="3" class="input font-mono text-xs" dir="ltr" placeholder="talabat.com&#10;elmenus.com" <?= $hasKey ? '' : 'disabled' ?>></textarea>
          <button class="btn-ghost w-full !text-sm" <?= $hasKey ? '' : 'disabled' ?>><?= e(t('ضيف')) ?></button>
        </form>
        <form method="post" class="card p-5 space-y-4">
          <?= csrf_field() ?><input type="hidden" name="action" value="icp">
          <div>
            <h3 class="font-semibold text-ink-900">🎯 <?= e(t('العميل المثالي')) ?></h3>
            <p class="text-xs text-ink-500 mt-1"><?= e(t('"تحديث" بيجيب شركات بالمواصفات دي. لو غيرتها البحث بيبدأ من الأول.')) ?></p>
          </div>
          <?php $dis = $canManage ? '' : 'disabled'; ?>
          <label class="block"><span class="label"><?= e(t('الدول أو المدن (بالإنجليزي، افصل بفاصلة)')) ?></span>
            <input class="input" name="locations" dir="ltr" value="<?= e(implode(', ', $icp['locations'])) ?>" placeholder="Egypt, Riyadh" <?= $dis ?>></label>
          <label class="block"><span class="label"><?= e(t('المجال (كلمات بالإنجليزي)')) ?></span>
            <input class="input" name="keywords" dir="ltr" value="<?= e($icp['keywords']) ?>" placeholder="restaurants, real estate" <?= $dis ?>></label>
          <div><span class="label"><?= e(t('عدد الموظفين')) ?></span>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach ($sizes as $k => $lbl): ?>
                <label class="cursor-pointer"><input type="checkbox" name="sizes[]" value="<?= e($k) ?>" class="peer sr-only" <?= in_array($k, $icp['sizes'], true) ? 'checked' : '' ?> <?= $dis ?>>
                  <span class="inline-block px-2.5 py-1 rounded-lg border border-ink-200 text-xs text-ink-600 peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600 peer-focus-visible:ring-2 ring-brand-300" dir="ltr"><?= e($lbl) ?></span></label>
              <?php endforeach; ?>
            </div></div>
          <label class="block"><span class="label"><?= e(t('المسمى الوظيفي اللي بتدور عليه (اختياري)')) ?></span>
            <input class="input" name="titles" dir="ltr" value="<?= e($icp['titles']) ?>" placeholder="CEO, Marketing Manager" <?= $dis ?>>
            <span class="text-xs text-ink-400"><?= e(t('لو فاضي بنجيب أعلى 5 مناصب (المالك، المدير التنفيذي، المدراء).')) ?></span></label>
          <div class="grid grid-cols-2 gap-3 items-end">
            <label class="block"><span class="label"><?= e(t('حد الكشف اليومي لكل موظف')) ?></span>
              <input class="input" type="number" min="0" max="500" name="daily_limit" value="<?= (int)$prefs['daily_limit'] ?>" <?= $dis ?>></label>
            <label class="flex items-center gap-2 text-sm text-ink-700 pb-2 cursor-pointer"><input type="checkbox" name="auto_assign" value="1" class="rounded" <?= (int)$prefs['auto_assign'] ? 'checked' : '' ?> <?= $dis ?>><?= e(t('وزّع الـ Leads الجديدة على الفريق بالدور')) ?></label>
          </div>
          <?php if ($canManage): ?><button class="btn-primary w-full"><?= e(t('احفظ')) ?></button>
          <?php else: ?><p class="text-xs text-ink-500"><?= e(t('المدير بس يقدر يغيّر الإعدادات دي.')) ?></p><?php endif; ?>
          <p class="text-[11px] text-ink-400 leading-relaxed"><?= e(t('كل تحديث بيخصم 1 كريديت من Apollo، وكل كشف شخص بيخصم كريديت. الأرقام بتوصل بعد دقايق، وتغطيتها في الشرق الأوسط ضعيفة.')) ?></p>
        </form>
      </aside>
    </div>

    <div id="crm-drawer" class="fixed inset-0 z-[60] hidden" aria-hidden="true">
      <div class="absolute inset-0 bg-ink-900/40" data-close></div>
      <div class="absolute top-0 bottom-0 end-0 w-full sm:w-[440px] bg-white shadow-2xl flex flex-col" role="dialog" aria-modal="true" aria-labelledby="crm-d-title">
        <div class="flex items-center gap-3 p-4 border-b border-ink-100">
          <div class="min-w-0 flex-1"><div id="crm-d-title" class="font-semibold text-ink-900 truncate"></div><div id="crm-d-sub" class="text-xs text-ink-500 truncate"></div></div>
          <button type="button" class="btn-ghost" data-close aria-label="<?= e(t('اقفل')) ?>">✕</button>
        </div>
        <div class="px-4 pt-3 text-xs text-ink-500 flex items-center gap-2"><span><?= e(t('متبقي لك النهارده:')) ?></span><b id="crm-left" class="text-ink-900"></b></div>
        <div id="crm-d-body" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
      </div>
    </div>
    <script>window.CRM = <?= json_encode([
        'canReveal' => $canReveal, 'url' => url('crm'), 'csrf' => csrf_token(), 'leadsUrl' => url('crm_leads'),
        'tr' => [
            'loading' => t('بنجيب صناع القرار…'), 'reveal' => t('اكشف الرقم والإيميل'), 'revealing' => t('بنكشف…'), 'none' => t('Apollo مالقاش أشخاص في الشركة دي.'),
            'added' => t('اتضاف للـ Leads ✓'), 'merged' => t('كان موجود في الـ Leads، كمّلنا بياناته ✓'), 'pending' => t('الرقم جاي في السكة (دقايق)…'), 'nophone' => t('مفيش رقم'),
            'noemail' => t('مفيش إيميل'), 'copy' => t('انسخ'), 'copied' => t('اتنسخ'), 'noperm' => t('مالكش صلاحية الكشف'), 'hasE' => t('عنده إيميل'), 'hasP' => t('عنده رقم'),
            'other' => t('اتكشفوا قبل كده'), 'manualH' => t('ضيف شخص من الشركة دي بإيدك'), 'mName' => t('الاسم'), 'mTitle' => t('المسمى الوظيفي'), 'mPhone' => t('الرقم'), 'mEmail' => t('الإيميل'), 'mAdd' => t('ضيف للـ Leads'), 'plans' => t('خطط Apollo'), 'open' => t('افتح في الـ Leads'), 'err' => t('حصل خطأ، جرب تاني'), 'site' => t('الموقع'),
        ],
    ], JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="assets/ui-crm.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php
});
