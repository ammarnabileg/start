<?php
/** متابعة المنافسين: مواقعهم بتتقري كل يوم وأي تغيير في الأسعار أو الخدمات أو العروض بيتسجل */
$wid = (int)ws('id');
$AJ = !empty($_POST['ajax']);
$back = function (string $h = '') use (&$AJ, &$payload) { if ($AJ) { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => true, 'flash' => $m, 'focus' => $h, 'd' => $payload()], JSON_UNESCAPED_UNICODE)); } redirect(url('competitors') . ($h !== '' ? '#' . $h : '')); };
$kinds = ['price_up' => t('رفع سعر'), 'price_down' => t('نزّل سعر'), 'price' => t('غيّر سعر'), 'offer_new' => t('عرض جديد'), 'offer_gone' => t('عرض خلص'), 'svc_new' => t('خدمة جديدة'),
    'svc_gone' => t('خدمة اتشالت'), 'hours' => t('المواعيد'), 'down' => t('الموقع وقع'), 'up' => t('الموقع رجع'), 'first' => t('أول قراءة')];
$host = function ($u) { return preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST)); };
/** كل بيانات الصفحة في JSON واحد (الكروت، التغييرات، المقارنة، الأرقام) */
$payload = function () use ($host) {
    $comps = comp_list(); $events = comp_events(null, 300); $prices = $comps ? comp_price_table($comps) : []; $since = strtotime('-30 days');
    $ago = function ($d) { if (!$d) return ''; $s = time() - strtotime($d); return $s < 3600 ? t('من %s دقيقة', max(1, (int)($s / 60))) : ($s < 86400 ? t('من %s ساعة', (int)($s / 3600)) : t('من %s يوم', (int)($s / 86400))); };
    $C = []; foreach ($comps as $c) { $sn = json_decode((string)$c['snap'], true) ?: [];
        $C[] = ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'url' => (string)$c['url'], 'host' => $host($c['url']), 'note' => (string)$c['note'], 'links' => array_values(array_filter(explode("\n", (string)$c['links']))),
            'checked' => $c['last_check'] ? fmt_date($c['last_check'], 'd/m h:i A') : '', 'ago' => $ago($c['last_check']), 'ok' => (int)$c['last_ok'],
            'services' => array_values(array_map(function ($x) { return ['name' => (string)$x['name'], 'price' => (string)$x['price']]; }, (array)($sn['services'] ?? []))), 'offers' => array_values((array)($sn['offers'] ?? []))]; }
    $E = array_map(function ($e) use ($since) { return ['cid' => (int)$e['competitor_id'], 'cname' => (string)$e['cname'], 'kind' => (string)$e['kind'], 'title' => comp_ev_title($e['kind'], (string)$e['title']), 'detail' => comp_ev_detail($e['kind'], $e['detail']),
        'day' => substr((string)$e['created_at'], 0, 10), 'date' => fmt_date($e['created_at'], 'd/m'), 'recent' => strtotime($e['created_at']) >= $since ? 1 : 0]; }, $events);
    $P = []; $cheaper = 0;
    foreach ($prices as $r) { $mn = comp_price_num((string)$r['mine']); $th = [];
        foreach ($r['them'] as $cid => $h) { $n = comp_price_num((string)$h['price']); $th[(string)$cid] = ['price' => (string)$h['price'], 'n' => $n, 'd' => ($mn && $n !== null) ? (int)round(($n - $mn) / $mn * 100) : null]; if ($mn && $n !== null && $n < $mn) $cheaper++; }
        $P[] = ['name' => (string)$r['name'], 'mine' => (string)$r['mine'], 'mn' => $mn, 'them' => (object)$th]; }
    $rec = array_filter($E, function ($e) { return $e['recent'] && $e['kind'] !== 'first'; });
    return ['comps' => $C, 'events' => array_values(array_filter($E, function ($e) { return $e['kind'] !== 'first'; })), 'prices' => $P,
        'k' => ['n' => count($C), 'changes' => count($rec), 'up' => count(array_filter($rec, function ($e) { return $e['kind'] === 'price_up'; })), 'down' => count(array_filter($rec, function ($e) { return $e['kind'] === 'price_down'; })), 'cheaper' => $cheaper]];
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'add') {
        $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
        $u = trim((string)($_POST['url'] ?? '')); if ($u !== '' && !preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
        if ($name === '' || !filter_var($u, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $u)) { flash('error', t('اكتب اسم المنافس ورابط موقعه.')); $back(); }
        if (count(comp_list()) >= 15) { flash('error', t('الحد الأقصى 15 منافس.')); $back(); }
        $links = implode("\n", array_slice(array_values(array_filter(array_map('trim', preg_split('/\s+/', (string)($_POST['links'] ?? ''))), function ($x) { return preg_match('~^https?://~i', $x); })), 0, 6));
        db()->prepare('INSERT INTO competitors (workspace_id, name, url, links, note) VALUES (?, ?, ?, ?, ?)')->execute([$wid, $name, mb_substr($u, 0, 500), $links ?: null, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500) ?: null]);
        $c = comp_get((int)db()->lastInsertId());
        comp_check($c); $c = comp_get((int)$c['id']);
        log_activity('competitor.add', $name);
        if ($c['last_ok']) flash('success', t('اتضاف %s واتقرا موقعه ✓', $name)); else flash('error', t('اتضاف %s، بس موقعه مش بيفتح دلوقتي. هنحاول تاني بكرة.', $name));
        $back('c' . (int)$c['id']);
    }
    $c = comp_get((int)($_POST['id'] ?? 0));
    if (!$c) { flash('error', t('المنافس ده مش موجود.')); $back(); }
    if ($a === 'check') {
        $n = comp_check($c); $c = comp_get((int)$c['id']);
        if (!$c['last_ok']) flash('error', t('موقع %s مش بيفتح دلوقتي.', $c['name']));
        else flash('success', $n ? t('اتسجل %s تغيير ✓', $n) : t('مفيش تغيير من آخر مرة.'));
        $back('c' . (int)$c['id']);
    }
    if ($a === 'del') {
        db()->prepare('DELETE FROM competitors WHERE id = ? AND workspace_id = ?')->execute([(int)$c['id'], $wid]);
        log_activity('competitor.del', (string)$c['name']); flash('success', t('اتشال ✓')); $back();
    }
    $back();
}

render(t('المنافسين'), 'competitors', function () use ($payload, $kinds) { ?>
  <div id="cx"></div>
  <script>window.CX = <?= json_encode(['d' => $payload(), 'kinds' => $kinds, 'csrf' => csrf_token(), 'self' => url('competitors'), 'kb' => url('knowledge'),
    'T' => ['add' => t('إضافة منافس'), 'name' => t('اسم المنافس'), 'url' => t('رابط موقعه'), 'links' => t('روابط السوشيال'), 'linksH' => t('(اختياري، للوصول السريع بس)'), 'note' => t('ملاحظة'), 'opt' => t('(اختياري)'),
      'addGo' => t('أضف واقرا الموقع'), 'reading' => t('بنقرا الموقع… بتاخد لحد دقيقة'), 'emptyT' => t('اعرف كل تغيير عند منافسينك'), 'emptyD' => t('ضيف موقع منافس، وكل يوم هتعرف لو غيّر أسعاره أو نزّل عرض أو ضاف خدمة، وتشوف أسعارك قدام أسعاره.'), 'first' => t('إضافة أول منافس'),
      'kN' => t('منافس بنتابعه'), 'kCh' => t('تغيير آخر 30 يوم'), 'kUp' => t('رفع سعر'), 'kDown' => t('نزّل سعر'), 'kCheap' => t('خدمة هما أرخص فيها'),
      'svc' => t('خدمة بسعر'), 'offers' => t('عرض'), 'check' => t('اقرا دلوقتي'), 'checking' => t('بيقرا…'), 'del' => t('شيل'), 'delQ' => t('متأكد؟ اضغط تاني'), 'down' => t('الموقع مش بيفتح'), 'never' => t('لسه'),
      'noCh' => t('مفيش تغييرات لسه. أول تغيير هيظهر هنا.'), 'feed' => t('سجل التغييرات'), 'all' => t('الكل'), 'allC' => t('كل المنافسين'), 'feedNone' => t('لسه مفيش تغييرات. المواقع بتتقري كل يوم، وأول ما حاجة تتغير هتظهر هنا ويوصلك تنبيه.'),
      'cmp' => t('أسعارك قدام أسعارهم'), 'cmpD' => t('خدماتك من ملف الشركة، وقدامها الخدمة اللي بنفس الاسم أو اسم قريب عند كل منافس.'), 'cmpNone' => t('ضيف خدماتك وأسعارها في ملف الشركة عشان تظهر المقارنة.'), 'kbL' => t('ملف الشركة'),
      'svcCol' => t('الخدمة'), 'mine' => t('سعرك'), 'onlyCheap' => t('اللي هما أرخص فيها بس'), 'cheap' => t('أرخص منك'), 'exp' => t('أغلى منك'), 'pos' => t('مكانك'), 'lone' => t('%s خدمة مالهاش مقابل عند المنافسين'),
      'tCh' => t('التغييرات'), 'tSvc' => t('الخدمات والأسعار'), 'tOff' => t('العروض'), 'find' => t('دوّر على خدمة…'), 'noSvc' => t('مالقيناش أسعار على موقعه.'), 'noOff' => t('مفيش عروض على موقعه دلوقتي.'),
      'vs' => t('قدامك'), 'today' => t('النهارده'), 'yday' => t('امبارح'), 'open' => t('افتح الموقع'), 'cheaperIn' => t('أرخص منك في %s'), 'pricierIn' => t('أغلى منك في %s'), 'err' => t('حصلت مشكلة. جرّب تاني.')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
  <script src="assets/comp-ux.js?v=<?= e(APP_VERSION) ?>"></script>
<?php });
