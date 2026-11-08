<?php
/** متابعة المنافسين: مواقعهم بتتقري كل يوم وأي تغيير في الأسعار أو الخدمات أو العروض بيتسجل */
$wid = (int)ws('id');
$AJ = !empty($_POST['ajax']);
$back = function (string $h = '') use (&$AJ, &$payload) { if ($AJ) { $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); header('Content-Type: application/json; charset=utf-8'); exit(json_encode(['ok' => true, 'flash' => $m, 'focus' => $h, 'd' => $payload()], JSON_UNESCAPED_UNICODE)); } redirect(url('competitors') . ($h !== '' ? '#' . $h : '')); };
$kinds = ['price_up' => t('رفع سعر'), 'price_down' => t('نزّل سعر'), 'price' => t('غيّر سعر'), 'offer_new' => t('عرض جديد'), 'offer_gone' => t('عرض خلص'), 'svc_new' => t('خدمة جديدة'),
    'svc_gone' => t('خدمة اتشالت'), 'hours' => t('المواعيد'), 'down' => t('الموقع وقع'), 'up' => t('الموقع رجع'), 'first' => t('أول قراءة'), 'soc_bio' => t('وصف الحساب'), 'soc_name' => t('اسم الحساب'), 'soc_link' => t('رابط الحساب'), 'ad_new' => t('إعلان جديد'), 'ad_stop' => t('أوقف إعلانًا')];
$host = function ($u) { return preg_replace('/^www\./', '', (string)parse_url($u, PHP_URL_HOST)); };
/** كل بيانات الصفحة في JSON واحد (الكروت، التغييرات، المقارنة، الأرقام) */
$payload = function () use ($host) {
    $comps = comp_list(); $events = comp_events(null, 300); $prices = $comps ? comp_price_table($comps) : []; $since = strtotime('-30 days');
    $ago = function ($d) { if (!$d) return ''; $s = time() - strtotime($d); return $s < 3600 ? t('من %s دقيقة', max(1, (int)($s / 60))) : ($s < 86400 ? t('من %s ساعة', (int)($s / 3600)) : t('من %s يوم', (int)($s / 86400))); };
    $C = []; foreach ($comps as $c) { $sn = json_decode((string)$c['snap'], true) ?: [];
        $C[] = ['id' => (int)$c['id'], 'name' => (string)$c['name'], 'url' => (string)$c['url'], 'host' => $host($c['url']), 'note' => (string)$c['note'], 'links' => array_values(array_filter(explode("\n", (string)$c['links']))),
            'checked' => $c['last_check'] ? fmt_date($c['last_check'], 'd/m h:i A') : '', 'ago' => $ago($c['last_check']), 'ok' => (int)$c['last_ok'],
            'services' => array_values(array_map(function ($x) { return ['name' => (string)$x['name'], 'price' => (string)$x['price']]; }, (array)($sn['services'] ?? []))), 'offers' => array_values((array)($sn['offers'] ?? [])), 'soc' => cs_card((int)$c['id']), 'ads' => (int)db()->query('SELECT COUNT(*) FROM competitor_ads WHERE active = 1 AND competitor_id = ' . (int)$c['id'])->fetchColumn()]; }
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
        try { cs_sync($c); if (cs_on()) { cs_refresh($c); ca_refresh($c); } } catch (Throwable $e) {}
        log_activity('competitor.add', $name);
        if ($c['last_ok']) flash('success', t('اتضاف %s واتقرا موقعه ✓', $name)); else flash('error', t('اتضاف %s، بس موقعه مش بيفتح دلوقتي. هنحاول تاني بكرة.', $name));
        $back('c' . (int)$c['id']);
    }
    $c = comp_get((int)($_POST['id'] ?? 0));
    if (!$c) { flash('error', t('المنافس ده مش موجود.')); $back(); }
    // السوشيال: كل البيانات بتتجاب عند فتح التبويب بس
    $SJ = function ($d) { header('Content-Type: application/json; charset=utf-8'); exit(json_encode($d, JSON_UNESCAPED_UNICODE)); };
    if ($a === 'ads') $SJ(['ok' => true, 'r' => ca_report($c)]);
    if ($a === 'ads_refresh') {
        if (!cs_on()) $SJ(['ok' => false, 'error' => t('اربط Apify من صفحة التكاملات أولًا لقراءة مكتبة الإعلانات.')]);
        if (!ca_page($c)) $SJ(['ok' => false, 'error' => t('أضف رابط صفحة فيسبوك المنافس في تبويب «السوشيال» أولًا: مكتبة الإعلانات تُقرأ من صفحته.')]);
        try { ca_refresh($c); } catch (Throwable $e) { $SJ(['ok' => false, 'error' => $e->getMessage()]); }
        $SJ(['ok' => true, 'r' => ca_report(comp_get((int)$c['id']))]);
    }
    if ($a === 'ads_poll') { $more = ca_poll($c); $SJ(['ok' => true, 'left' => $more ? 1 : 0, 'r' => ca_report(comp_get((int)$c['id']))]); }
    if ($a === 'social') { cs_sync($c); $SJ(['ok' => true, 'r' => cs_report((int)$c['id'])]); }
    if ($a === 'social_refresh') {
        if (!cs_on()) $SJ(['ok' => false, 'error' => t('اربط Apify من صفحة التكاملات أولًا لقراءة حسابات السوشيال.')]);
        $n = cs_refresh($c); $SJ(['ok' => true, 'started' => $n, 'r' => cs_report((int)$c['id'])]);
    }
    if ($a === 'social_poll') { $left = cs_poll((int)$c['id']); $SJ(['ok' => true, 'left' => $left, 'r' => cs_report((int)$c['id'])]); }
    if ($a === 'links') {
        $links = implode("\n", array_slice(array_values(array_filter(array_map('trim', preg_split('/\s+/', (string)($_POST['links'] ?? ''))), function ($x) { return preg_match('~^https?://~i', $x) || preg_match('~^(www\.)?(instagram|tiktok|facebook|fb|youtube|x|twitter|linkedin)\.com/~i', $x); })), 0, 8));
        $links = implode("\n", array_map(function ($l) { return preg_match('~^https?://~i', $l) ? $l : 'https://' . $l; }, array_filter(explode("\n", $links))));
        db()->prepare('UPDATE competitors SET links = ? WHERE id = ? AND workspace_id = ?')->execute([$links ?: null, (int)$c['id'], $wid]);
        $c = comp_get((int)$c['id']); cs_sync($c); $started = cs_on() ? cs_refresh($c) : 0; if (cs_on()) { try { ca_refresh($c); } catch (Throwable $e) {} }
        $SJ(['ok' => true, 'started' => $started, 'links' => array_values(array_filter(explode("\n", (string)$c['links']))), 'r' => cs_report((int)$c['id'])]);
    }
    if ($a === 'check') {
        $n = comp_check($c); $c = comp_get((int)$c['id']);
        if (!$c['last_ok']) flash('error', t('موقع %s مش بيفتح دلوقتي.', $c['name']));
        else flash('success', $n ? t('اتسجل %s تغيير ✓', $n) : t('مفيش تغيير من آخر مرة.'));
        $back('c' . (int)$c['id']);
    }
    if ($a === 'del') {
        cs_forget_comp((int)$c['id']); foreach (['ca_run_', 'ca_err_', 'ca_empty_'] as $k) { try { db()->prepare('DELETE FROM settings WHERE name = ?')->execute([$k . (int)$c['id']]); } catch (Throwable $e) {} }
        db()->prepare('DELETE FROM competitors WHERE id = ? AND workspace_id = ?')->execute([(int)$c['id'], $wid]);
        log_activity('competitor.del', (string)$c['name']); flash('success', t('اتشال ✓')); $back();
    }
    $back();
}

render(t('المنافسين'), 'competitors', function () use ($payload, $kinds) { ?>
  <div id="cx"></div>
  <script>window.CX = <?= json_encode(['d' => $payload(), 'kinds' => $kinds, 'csrf' => csrf_token(), 'self' => url('competitors'), 'kb' => url('knowledge'),
    'T' => ['tAds' => t('الإعلانات'), 'aLoading' => t('جارٍ تحميل الإعلانات…'), 'aDays' => t('شغّال منذ %s يوم'), 'aRan' => t('استمر %s يوم'), 'aNewB' => t('جديد'), 'aVar' => t('%s نسخ'), 'aLib' => t('في مكتبة الإعلانات'), 'aChecked' => t('آخر قراءة:'), 'aRefresh' => t('حدّث الإعلانات'), 'aNoApify' => t('اربط Apify من صفحة التكاملات لقراءة مكتبة إعلانات ميتا.'), 'aNoPage' => t('أضف رابط صفحة فيسبوك المنافس في تبويب «السوشيال»: مكتبة الإعلانات تُقرأ من صفحته.'), 'aBusy' => t('جارٍ قراءة مكتبة الإعلانات… تستغرق دقيقة إلى 3 دقائق.'), 'aActive' => t('إعلان شغّال'), 'aWin' => t('شغّال 30 يومًا أو أكثر'), 'aWinH' => t('غالبًا إعلانات رابحة'), 'aNew' => t('جديد هذا الأسبوع'), 'aNewH' => t('آخر 7 أيام'), 'aAvg' => t('متوسط مدة الإعلان'), 'aAvgH' => t('يوم'), 'aNever' => t('لم تُقرأ الإعلانات بعد. اضغط «حدّث الإعلانات».'), 'aNone' => t('لا توجد إعلانات شغّالة لهذا المنافس الآن.'), 'aPl' => t('المنصات التي يعلن عليها'), 'aFmt' => t('شكل الإعلانات'), 'aCtas' => t('أزرار الإجراء التي يستخدمها:'), 'aWinT' => t('إعلاناته الرابحة'), 'aWinD' => t('المعلن لا يترك إعلانًا خاسرًا يعمل شهرًا: الإعلان الذي يستمر طويلًا غالبًا يحقق نتائج. ادرس عرضه وصياغته.'), 'aNewT' => t('إعلانات جديدة هذا الأسبوع'), 'aLand' => t('صفحات الهبوط'), 'aLpN' => t('%s إعلان'), 'aLpWait' => t('تُقرأ مع التحديث القادم'), 'aLpBad' => t('تعذّر فتح الصفحة'), 'aAll' => t('كل إعلاناته الشغّالة'), 'aFind' => t('ابحث في نص الإعلانات…'), 'aStopped' => t('إعلانات أوقفها (%s)'), 'aStarted' => t('بدأت قراءة مكتبة الإعلانات. تستغرق دقيقة إلى 3 دقائق.'), 'f_image' => t('صورة'), 'f_video' => t('فيديو'), 'f_carousel' => t('ألبوم/كتالوج'), 'sReach' => t('الوصول (مشاهدات الفيديو ÷ المتابعين)'), 'sV7' => t('مشاهدات أول 7 أيام'), 'sV7n' => t('متوسط %s فيديو'), 'sMin' => t('%s دقيقة'), 'sHr' => t('%s ساعة'), 'sHrs' => t('%s ساعات'), 'sDay' => t('%s يوم'), 'sReplied' => t('ردّ عليه'), 'sNoRep' => t('بلا رد'), 'sCust' => t('تعامله مع العملاء في التعليقات'), 'sCustN' => t('%s تعليق من عملاء في آخر 30 يومًا'), 'sRate' => t('نسبة الرد'), 'sRepOf' => t('%s من %s'), 'sSpeed' => t('سرعة الرد'), 'sMedian' => t('الوسيط'), 'sNeg' => t('شكاوى العملاء عنده'), 'sNegNone' => t('لا توجد شكاوى واضحة في التعليقات المقروءة.'), 'sQs' => t('أسئلة عملائه'), 'sQsH' => t('أسئلة لم يجاوبها في محتواه: جاوبها أنت في منشوراتك.'), 'qPrice' => t('السعر'), 'qPlace' => t('المكان'), 'qTime' => t('المواعيد'), 'qDel' => t('التوصيل'), 'qStock' => t('التوفر'), 'qOther' => t('أخرى'), 'sCmtSoon' => t('تُقرأ التعليقات على آخر 5 منشورات مع القراءة القادمة (كل 3 أيام).'), 'sPaidT' => t('الشراكات المدفوعة'), 's90n' => t('%s في آخر 90 يومًا'), 'sPartners' => t('الحسابات التي تعاون معها:'), 'sMusic' => t('الأصوات التي يستخدمها (تيك توك)'), 'tSoc' => t('السوشيال'), 'sFol' => t('متابع'), 'sWeek' => t('هذا الأسبوع'), 'sLoading' => t('جارٍ تحميل بيانات السوشيال…'), 'tImage' => t('صورة'), 'tCarousel' => t('ألبوم'), 'tVideo' => t('فيديو'), 'tText' => t('نص'), 'sSoon' => t('يظهر الرسم بعد يومين من المتابعة'), 'sPaid' => t('شراكة مدفوعة'), 'sEr' => t('تفاعل'), 'sFolAll' => t('متابع على كل المنصات'), 'sNoAcc' => t('لا توجد حسابات سوشيال مدعومة لهذا المنافس بعد.'), 'sReading' => t('جارٍ القراءة…'), 'sRefresh' => t('حدّث السوشيال'), 'sNoApify' => t('اربط Apify من صفحة التكاملات لقراءة حسابات السوشيال.'), 'sFast' => t('الأسرع نموًا: %s بنسبة %s هذا الأسبوع'), 's7' => t('آخر 7 أيام'), 's30' => t('آخر 30 يومًا'), 'sPw' => t('منشور/أسبوع'), 'sErAvg' => t('متوسط التفاعل'), 'sSince' => t('نتابع منذ %s'), 'sNever' => t('لم تتم القراءة بعد. اضغط «حدّث السوشيال».'), 'sLinks' => t('روابط حساباته'), 'sLinksH' => t('رابط في كل سطر: إنستجرام، تيك توك، فيسبوك، يوتيوب. (X ولينكدإن غير مدعومين حاليًا)'), 'sSave' => t('احفظ وابدأ القراءة'), 'sNoPosts' => t('لا توجد منشورات بعد. تظهر بعد أول قراءة.'), 'sTop' => t('أفضل 10 منشورات في آخر 30 يومًا'), 'sMix' => t('نوع المحتوى'), 's90' => t('%s منشور في آخر 90 يومًا'), 'sDays' => t('أحد,اثنين,ثلاثاء,أربعاء,خميس,جمعة,سبت'), 'sHeat' => t('أوقات النشر'), 'sBest' => t('أكثر أوقاته نشرًا'), 'am' => t('ص'), 'pm' => t('م'), 'sTags' => t('الهاشتاجات التي يستخدمها'), 'sMents' => t('الحسابات التي يذكرها'), 'sAll' => t('كل المنشورات'), 'sAllPl' => t('كل المنصات'), 'sNew' => t('الأحدث'), 'sMost' => t('الأعلى تفاعلًا'), 'sFind' => t('ابحث في المنشورات…'), 'sMore' => t('عرض المزيد'), 'sStarted' => t('بدأت قراءة %s حساب. تستغرق دقيقة إلى 3 دقائق.'), 'sSaved' => t('تم الحفظ ✓'), 'add' => t('إضافة منافس'), 'name' => t('اسم المنافس'), 'url' => t('رابط موقعه'), 'links' => t('روابط السوشيال'), 'linksH' => t('(اختياري، للوصول السريع بس)'), 'note' => t('ملاحظة'), 'opt' => t('(اختياري)'),
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
