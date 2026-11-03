<?php
/**
 * التقارير (أسبوعي/شهري للطباعة والإيميل)، الأهداف الشهرية، التنبيهات، ومقارنة الشركات.
 * كل نداءات Zernio هنا قراءة بس (GET). البيانات من نفس دوال app/modules/analytics.php.
 */

/* ---------- الاتصالات ---------- */

/** كل اتصالات خدمة النشر لشركة معينة: [['z' => Zernio, 'pid' => profileId|null, 'id' => connId], ...] */
function rp_clients(int $wid): array
{
    static $cache = [];
    if (!isset($cache[$wid])) {
        $cache[$wid] = [];
        foreach (connections('zernio', $wid) as $c) {
            $key = (string)($c['credentials']['api_key'] ?? '');
            if ($key === '') continue;
            $cache[$wid][] = ['z' => new Zernio($key), 'pid' => ($c['settings']['profile_id'] ?? null) ?: null, 'id' => (int)$c['id']];
        }
    }
    return $cache[$wid];
}

/** حسابات الشركة من كل الاتصالات، وكل حساب عليه رقم الاتصال (_ci) */
function rp_accounts(int $wid, ?string &$error = null): array
{
    $out = [];
    foreach (rp_clients($wid) as $i => $c) {
        [$r, $err] = an_try(function () use ($c) {
            return $c['z']->request('GET', '/v1/accounts', array_filter(['profileId' => $c['pid']]))['accounts'] ?? [];
        });
        if ($err) { $error = $error ?: $err; continue; }
        foreach ((array)$r as $a) { $a['_ci'] = $i; $out[] = $a; }
    }
    return $out;
}

function rp_acc_name(array $a): string
{
    return trim((string)($a['displayName'] ?? $a['username'] ?? ''));
}

function rp_tz(int $wid): string
{
    static $c = [];
    if (!isset($c[$wid])) {
        $st = db()->prepare("SELECT timezone FROM workspaces WHERE id = ?");
        $st->execute([$wid]);
        $tz = (string)$st->fetchColumn();
        $c[$wid] = in_array($tz, timezone_identifiers_list(), true) ? $tz : 'Africa/Cairo';
    }
    return $c[$wid];
}

function rp_ws_name(int $wid): string
{
    $st = db()->prepare("SELECT name FROM workspaces WHERE id = ?");
    $st->execute([$wid]);
    return (string)$st->fetchColumn();
}

/** تاريخ ISO بتوقيت شركة معينة (بيشتغل كمان من غير شركة مفتوحة زي في الـ cron) */
function rp_fmt(?string $iso, string $tz, string $format = 'd/m/Y'): string
{
    if (!$iso) return '—';
    try {
        $d = new DateTime($iso);
        $d->setTimezone(new DateTimeZone($tz));
        return $d->format($format);
    } catch (Throwable $t) {
        return (string)$iso;
    }
}

/* ---------- الفترات ---------- */

/** الفترة: last_week = الاتنين للحد اللي فاتوا، last_month = الشهر اللي فات، custom = من/إلى */
function rp_period(string $kind, string $tz, string $from = '', string $to = ''): array
{
    $now = new DateTime('now', new DateTimeZone($tz));
    $ok = function ($s) { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false; };
    if ($kind === 'last_month') {
        $f = (clone $now)->modify('first day of last month')->format('Y-m-d');
        $t = (clone $now)->modify('last day of last month')->format('Y-m-d');
    } elseif ($kind === 'custom' && $ok($from) && $ok($to)) {
        $today = $now->format('Y-m-d');
        $f = $from; $t = min($to, $today);
        if ($f > $t) [$f, $t] = [$t, $f];
        if ((strtotime($t) - strtotime($f)) / 86400 > 365) $f = gmdate('Y-m-d', strtotime($t) - 365 * 86400);
    } else {
        $kind = 'last_week';
        $mon = (clone $now)->modify('monday this week')->modify('-7 days');
        $f = $mon->format('Y-m-d');
        $t = $mon->modify('+6 days')->format('Y-m-d');
    }
    $len = (int)round((strtotime($t) - strtotime($f)) / 86400) + 1;
    $pto = gmdate('Y-m-d', strtotime($f) - 86400);
    $pfrom = gmdate('Y-m-d', strtotime($pto) - ($len - 1) * 86400);
    return ['kind' => $kind, 'from' => $f, 'to' => $t, 'pfrom' => $pfrom, 'pto' => $pto, 'len' => $len];
}

/* ---------- جمع بيانات التقرير ---------- */

/**
 * يجمع بيانات فترة من كل اتصالات الشركة.
 * $parts: daily, followers, top, inbox, response
 */
function rp_collect(int $wid, string $from, string $to, string $acc = '', array $parts = ['daily', 'followers', 'top', 'inbox', 'response']): array
{
    $R = [
        'daily' => [], 'totals' => ['impressions' => 0, 'reach' => 0, 'likes' => 0, 'comments' => 0, 'shares' => 0, 'saves' => 0, 'clicks' => 0, 'views' => 0, 'postCount' => 0],
        'fol' => ['gain' => 0.0, 'accounts' => []], 'top' => [],
        'inbox' => ['received' => 0, 'sent' => 0, 'conversations' => 0], 'resp' => ['sample' => 0, 'median' => null, 'mean' => null],
        'errors' => [],
    ];
    $clients = rp_clients($wid);
    if (!$clients) { $R['errors']['all'] = t('مش مربوط'); return $R; }
    $accCi = null;
    if ($acc !== '') {
        foreach (rp_accounts($wid) as $a) if ((string)($a['_id'] ?? '') === $acc) $accCi = $a['_ci'];
    }
    $medians = [];
    foreach ($clients as $i => $c) {
        if ($accCi !== null && $accCi !== $i) continue;
        $z = $c['z'];
        $base = ['profileId' => $c['pid'], 'accountId' => $acc ?: null, 'fromDate' => $from, 'toDate' => $to];
        if (in_array('daily', $parts, true)) {
            [$d, $err] = an_try(function () use ($z, $base) { return an_daily_full($z, $base); });
            if ($err) $R['errors']['daily'] = $err;
            foreach ((array)($d['dailyData'] ?? []) as $row) {
                $day = substr((string)($row['date'] ?? ''), 0, 10);
                $m = (array)($row['metrics'] ?? []);
                foreach ($R['totals'] as $k => $_) {
                    $v = $k === 'postCount' ? (int)($row['postCount'] ?? 0) : (float)($m[$k] ?? 0);
                    $R['totals'][$k] += $v;
                    $R['daily'][$day][$k] = ($R['daily'][$day][$k] ?? 0) + $v;
                }
            }
        }
        if (in_array('followers', $parts, true)) {
            $q = ['profileId' => $c['pid'], 'accountIds' => $acc ?: null, 'fromDate' => $from, 'toDate' => $to, 'granularity' => 'daily'];
            [$f, $err] = an_try(function () use ($z, $q) { return an_follower_stats($z, $q); });
            if ($err) $R['errors']['followers'] = $err;
            foreach ((array)($f['accounts'] ?? []) as $a) {
                $R['fol']['gain'] += (float)($a['growth'] ?? 0);
                $R['fol']['accounts'][] = $a;
            }
        }
        if (in_array('top', $parts, true)) {
            [$p, $err] = an_try(function () use ($z, $base) { return an_posts($z, $base + ['limit' => 5, 'page' => 1, 'sortBy' => 'engagement', 'order' => 'desc']); });
            if ($err) $R['errors']['top'] = $err;
            foreach ((array)($p['posts'] ?? []) as $post) $R['top'][] = $post;
        }
        $iq = ['fromDate' => $from, 'toDate' => $to, 'profileId' => $c['pid']];
        if (in_array('inbox', $parts, true) && $acc === '') {
            [$v, $err] = an_try(function () use ($z, $iq) { return an_inbox($z, 'volume', $iq); });
            if ($err) $R['errors']['inbox'] = $err;
            $s = (array)($v['summary'] ?? []);
            $R['inbox']['received'] += (int)($s['received'] ?? 0);
            $R['inbox']['sent'] += (int)($s['sent'] ?? 0);
            $R['inbox']['conversations'] += (int)($s['uniqueConversations'] ?? 0);
        }
        if (in_array('response', $parts, true) && $acc === '') {
            [$v, $err] = an_try(function () use ($z, $iq) { return an_inbox($z, 'response-time', $iq); });
            if ($err) $R['errors']['response'] = $err;
            $s = (array)($v['summary'] ?? []);
            if (!empty($s['sampleSize'])) {
                $R['resp']['sample'] += (int)$s['sampleSize'];
                $medians[] = [(float)($s['medianSeconds'] ?? 0), (float)($s['meanSeconds'] ?? 0), (int)$s['sampleSize']];
            }
        }
    }
    if ($medians) {
        $n = array_sum(array_column($medians, 2));
        $R['resp']['median'] = array_sum(array_map(function ($m) { return $m[0] * $m[2]; }, $medians)) / max(1, $n);
        $R['resp']['mean'] = array_sum(array_map(function ($m) { return $m[1] * $m[2]; }, $medians)) / max(1, $n);
    }
    usort($R['top'], function ($a, $b) { return an_engagement((array)($b['analytics'] ?? [])) <=> an_engagement((array)($a['analytics'] ?? [])); });
    $R['top'] = array_slice($R['top'], 0, 5);
    ksort($R['daily']);
    $R['totals']['engagement'] = an_engagement($R['totals']);
    $R['totals']['rate'] = an_rate($R['totals']['engagement'], (float)$R['totals']['impressions']);
    return $R;
}

/** تقرير كامل: الفترة الحالية + السابقة للمقارنة */
function rp_build(int $wid, array $per, string $acc = ''): array
{
    return [
        'ws' => rp_ws_name($wid),
        'tz' => rp_tz($wid),
        'per' => $per,
        'acc' => $acc,
        'cur' => rp_collect($wid, $per['from'], $per['to'], $acc),
        'prev' => rp_collect($wid, $per['pfrom'], $per['pto'], $acc, ['daily', 'followers', 'inbox', 'response']),
        'generated' => gmdate('c'),
    ];
}

/* ---------- عرض التقرير (للطباعة وللإيميل) ---------- */

function rp_pct_badge(float $cur, float $prev, bool $lowerIsBetter = false): string
{
    $c = an_change($cur, $prev);
    if ($c === null) return '<span style="color:#64748b">' . e($cur > 0 ? t('جديد') : '—') . '</span>';
    if (abs($c) < 0.05) return '<span style="color:#64748b" dir="ltr">0%</span>';
    $good = $lowerIsBetter ? $c < 0 : $c > 0;
    return '<span style="color:' . ($good ? '#15803d' : '#dc2626') . ';font-weight:600" dir="ltr">' . ($c > 0 ? '▲ +' : '▼ ') . e(number_format($c, abs($c) >= 100 ? 0 : 1)) . '%</span>';
}

/** رسم يومي: SVG للطباعة، أو جدول أعمدة للإيميل (برامج الإيميل كتير منها بيمنع SVG) */
function rp_chart(array $dates, array $vals, bool $email): string
{
    $max = max(1, max($vals ?: [0]));
    $n = count($vals);
    if ($n === 0) return '';
    if ($email) {
        $out = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;height:120px" dir="ltr"><tr>';
        foreach ($vals as $i => $v) {
            $h = (int)round($v / $max * 110);
            $out .= '<td valign="bottom" style="padding:0 1px;height:120px" title="' . e($dates[$i] . ': ' . an_num($v)) . '">'
                . '<div style="background:#3b6ff0;height:' . max(1, $h) . 'px;line-height:1px;font-size:1px;border-radius:2px 2px 0 0">&nbsp;</div></td>';
        }
        $out .= '</tr></table>';
        $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:10px;color:#64748b" dir="ltr"><tr><td width="50%" style="text-align:left">' . e(date('d/m', strtotime($dates[0]))) . '</td><td width="50%" style="text-align:right">' . e(date('d/m', strtotime($dates[$n - 1]))) . '</td></tr></table>';
        return $out;
    }
    $W = 680; $H = 170; $pad = 24;
    $bw = ($W - $pad) / $n;
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . ($H + 18) . '" width="100%" role="img" aria-label="' . e(t('مرات الظهور يوم بيوم')) . '" style="display:block;direction:ltr;unicode-bidi:isolate">';
    foreach ([0, 0.5, 1] as $g) {
        $y = $H - $g * ($H - 10);
        $svg .= '<line x1="' . $pad . '" x2="' . $W . '" y1="' . $y . '" y2="' . $y . '" stroke="#e2e8f0" stroke-width="1"/>';
        $svg .= '<text x="0" y="' . ($y + 3) . '" font-size="9" fill="#64748b">' . e(an_num($max * $g)) . '</text>';
    }
    foreach ($vals as $i => $v) {
        $h = $v / $max * ($H - 10);
        $x = $pad + $i * $bw + $bw * 0.15;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($H - $h, 1) . '" width="' . round(max(1, $bw * 0.7), 1) . '" height="' . round($h, 1) . '" rx="2" fill="#3b6ff0"><title>' . e(date('d/m', strtotime($dates[$i])) . ': ' . an_num($v)) . '</title></rect>';
    }
    $step = max(1, (int)ceil($n / 8));
    for ($i = 0; $i < $n; $i += $step) {
        $svg .= '<text x="' . round($pad + $i * $bw + $bw / 2, 1) . '" y="' . ($H + 13) . '" font-size="9" fill="#64748b" text-anchor="middle">' . e(date('d/m', strtotime($dates[$i]))) . '</text>';
    }
    return $svg . '</svg>';
}

/** التقرير كـ HTML بستايل inline (نفس الشكل في صفحة الطباعة والإيميل) */
function rp_render(array $R, bool $email = false): string
{
    $rtl = is_rtl();
    $al = $rtl ? 'right' : 'left';
    $ar = $rtl ? 'left' : 'right';
    $cur = $R['cur']; $prev = $R['prev']; $per = $R['per'];
    $dmy = function ($d) { return date('d/m/Y', strtotime($d)); };
    $font = $rtl ? "'Segoe UI',Tahoma,Arial,sans-serif" : "-apple-system,'Segoe UI',Roboto,Arial,sans-serif";
    $card = 'border:1px solid #e2e8f0;border-radius:10px;padding:12px;';
    $h2 = 'font-size:15px;font-weight:700;color:#0f172a;margin:22px 0 8px;';
    $o = '<div dir="' . ($rtl ? 'rtl' : 'ltr') . '" style="font-family:' . $font . ';color:#0f172a;font-size:13px;line-height:1.5;text-align:' . $al . '">';

    // الرأس: اسم الشركة والفترة بس (من غير شعارات)
    $title = $per['kind'] === 'last_month' ? t('التقرير الشهري') : ($per['kind'] === 'last_week' ? t('التقرير الأسبوعي') : t('تقرير الأداء'));
    $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #0f172a;margin-bottom:14px"><tr>'
        . '<td style="padding-bottom:10px;text-align:' . $al . '"><div style="font-size:22px;font-weight:800">' . e($R['ws']) . '</div>'
        . '<div style="font-size:14px;color:#334155">' . e($title) . '</div></td>'
        . '<td style="padding-bottom:10px;text-align:' . $ar . ';font-size:12px;color:#475569;vertical-align:bottom">'
        . '<div><bdi dir="ltr">' . e($dmy($per['from']) . ' – ' . $dmy($per['to'])) . '</bdi></div>'
        . '<div>' . e(t('مقارنة بـ')) . ' <bdi dir="ltr">' . e($dmy($per['pfrom']) . ' – ' . $dmy($per['pto'])) . '</bdi></div>'
        . (!empty($R['accName']) ? '<div>' . e(t('الحساب: %s', $R['accName'])) . '</div>' : '')
        . '</td></tr></table>';

    if (!empty($cur['errors']['all'])) {
        return $o . '<p>' . e(t('الشركة دي لسه مش مربوطة بخدمة النشر.')) . '</p></div>';
    }

    // ملخص الأرقام
    $ct = $cur['totals']; $pt = $prev['totals'];
    $kpis = [
        [t('مرات الظهور'), an_num($ct['impressions']), rp_pct_badge($ct['impressions'], $pt['impressions']), !empty($cur['errors']['daily'])],
        [t('الوصول'), an_num($ct['reach']), rp_pct_badge($ct['reach'], $pt['reach']), !empty($cur['errors']['daily'])],
        [t('التفاعل'), an_num($ct['engagement']), rp_pct_badge($ct['engagement'], $pt['engagement']), !empty($cur['errors']['daily'])],
        [t('معدل التفاعل'), number_format($ct['rate'], 2) . '%', rp_pct_badge($ct['rate'], $pt['rate']), !empty($cur['errors']['daily'])],
        [t('متابعين جداد'), ($cur['fol']['gain'] > 0 ? '+' : '') . an_num($cur['fol']['gain']), rp_pct_badge($cur['fol']['gain'], $prev['fol']['gain']), !empty($cur['errors']['followers'])],
        [t('منشورات اتنشرت'), an_num($ct['postCount']), rp_pct_badge($ct['postCount'], $pt['postCount']), !empty($cur['errors']['daily'])],
    ];
    $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="border-collapse:separate"><tr>';
    foreach ($kpis as $i => $k) {
        if ($i === 3) $o .= '</tr><tr>';
        $o .= '<td width="33%" style="' . $card . 'vertical-align:top;text-align:' . $al . '">'
            . '<div style="font-size:11px;color:#64748b">' . e($k[0]) . '</div>'
            . ($k[3] ? '<div style="font-size:13px;color:#94a3b8;margin-top:4px">' . e(t('مش متاح')) . '</div>'
                : '<div style="font-size:22px;font-weight:800" dir="ltr"><bdi>' . e($k[1]) . '</bdi></div><div style="font-size:11px">' . $k[2] . ' <span style="color:#94a3b8">' . e(t('عن الفترة السابقة')) . '</span></div>')
            . '</td>';
    }
    $o .= '</tr></table>';

    // الرسم اليومي
    $dates = an_date_list($per['from'], $per['to']);
    $vals = array_map(function ($d) use ($cur) { return (float)($cur['daily'][$d]['impressions'] ?? 0); }, $dates);
    $o .= '<div style="' . $h2 . '">' . e(t('مرات الظهور يوم بيوم')) . '</div><div style="' . $card . '">';
    $o .= array_sum($vals) > 0 ? rp_chart($dates, $vals, $email) : '<div style="color:#64748b;padding:16px 0;text-align:center">' . e(t('مفيش بيانات في الفترة دي.')) . '</div>';
    $o .= '</div>';

    // أفضل المنشورات
    $o .= '<div style="' . $h2 . '">' . e(t('أفضل المنشورات')) . '</div>';
    if (!$cur['top']) {
        $o .= '<div style="' . $card . 'color:#64748b">' . e(!empty($cur['errors']['top']) ? $cur['errors']['top'] : t('مفيش منشورات اتنشرت في الفترة دي.')) . '</div>';
    } else {
        $th = 'font-size:11px;color:#64748b;font-weight:600;padding:6px;border-bottom:1px solid #e2e8f0;text-align:';
        $o .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px"><tr>'
            . '<th style="' . $th . $al . '">' . e(t('المنشور')) . '</th><th style="' . $th . 'center">' . e(t('مرات الظهور')) . '</th><th style="' . $th . 'center">' . e(t('التفاعل')) . '</th><th style="' . $th . 'center">' . e(t('معدل التفاعل')) . '</th></tr>';
        foreach ($cur['top'] as $p) {
            $an = (array)($p['analytics'] ?? []);
            $eng = an_engagement($an);
            $text = trim(preg_replace('/\{hashtag\|\\\\?#\|([^}]*)\}/u', '#$1', (string)($p['content'] ?? '')));
            $text = mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 110);
            $td = 'padding:7px 6px;border-bottom:1px solid #f1f5f9;vertical-align:top;';
            $o .= '<tr><td style="' . $td . 'text-align:' . $al . '"><div>' . e($text !== '' ? $text : t('(منشور بدون نص)')) . '</div>'
                . '<div style="font-size:10px;color:#64748b">' . e(platform_label((string)($p['platform'] ?? ''))) . ' · <bdi dir="ltr">' . e(rp_fmt($p['publishedAt'] ?? null, $R['tz'])) . '</bdi></div></td>'
                . '<td style="' . $td . 'text-align:center" dir="ltr">' . e(an_num($an['impressions'] ?? 0)) . '</td>'
                . '<td style="' . $td . 'text-align:center" dir="ltr">' . e(an_num($eng)) . '</td>'
                . '<td style="' . $td . 'text-align:center" dir="ltr">' . e(number_format(an_rate($eng, (float)($an['impressions'] ?? 0)), 2)) . '%</td></tr>';
        }
        $o .= '</table>';
    }

    // المتابعين
    $o .= '<div style="' . $h2 . '">' . e(t('المتابعين')) . '</div>';
    if (!$cur['fol']['accounts']) {
        $o .= '<div style="' . $card . 'color:#64748b">' . e(!empty($cur['errors']['followers']) ? $cur['errors']['followers'] : t('مفيش بيانات متابعين.')) . '</div>';
    } else {
        $o .= '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12px">';
        foreach ($cur['fol']['accounts'] as $a) {
            $g = (float)($a['growth'] ?? 0);
            $o .= '<tr><td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:' . $al . '"><b>' . e(rp_acc_name($a)) . '</b> <span style="color:#64748b;font-size:11px">' . e(platform_label((string)($a['platform'] ?? ''))) . '</span></td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:' . $ar . '">' . e(t('%s متابع', an_num($a['currentFollowers'] ?? 0))) . '</td>'
                . '<td style="padding:6px;border-bottom:1px solid #f1f5f9;text-align:' . $ar . ';color:' . ($g > 0 ? '#15803d' : ($g < 0 ? '#dc2626' : '#64748b')) . '" dir="ltr">' . e(($g > 0 ? '+' : '') . an_num($g)) . '</td></tr>';
        }
        $o .= '</table>';
    }

    // الرسائل
    if ($R['acc'] === '') {
        $o .= '<div style="' . $h2 . '">' . e(t('ملخص الرسائل')) . '</div>';
        if (!empty($cur['errors']['inbox'])) {
            $o .= '<div style="' . $card . 'color:#64748b">' . e($cur['errors']['inbox']) . '</div>';
        } else {
            $ib = $cur['inbox']; $pb = $prev['inbox'];
            $cells = [
                [t('رسائل وصلت'), an_num($ib['received']), rp_pct_badge($ib['received'], $pb['received'])],
                [t('رسائل اتبعتت'), an_num($ib['sent']), rp_pct_badge($ib['sent'], $pb['sent'])],
                [t('محادثات'), an_num($ib['conversations']), rp_pct_badge($ib['conversations'], $pb['conversations'])],
                [t('متوسط وقت الرد'), an_duration($cur['resp']['median']), $cur['resp']['median'] !== null && $prev['resp']['median'] !== null ? rp_pct_badge($cur['resp']['median'], $prev['resp']['median'], true) : ''],
            ];
            $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="border-collapse:separate"><tr>';
            foreach ($cells as $c) {
                $o .= '<td width="25%" style="' . $card . 'text-align:' . $al . ';vertical-align:top"><div style="font-size:11px;color:#64748b">' . e($c[0]) . '</div><div style="font-size:18px;font-weight:800">' . e($c[1]) . '</div><div style="font-size:11px">' . $c[2] . '</div></td>';
            }
            $o .= '</tr></table>';
        }
    }

    $o .= '<div style="margin-top:22px;padding-top:8px;border-top:1px solid #e2e8f0;font-size:10px;color:#94a3b8">'
        . e(t('اتعمل في %s · التواريخ بتوقيت %s', rp_fmt($R['generated'], $R['tz'], 'd/m/Y h:i A'), $R['tz'])) . '</div>';
    return $o . '</div>';
}

/* ---------- الإيميل المجدول ---------- */

function rp_schedule(int $wid): array
{
    $d = ['workspace_id' => $wid, 'recipients' => '', 'frequency' => 'off', 'lang' => 'en', 'last_sent' => null, 'last_result' => null];
    try {
        $st = db()->prepare("SELECT * FROM report_schedules WHERE workspace_id = ?");
        $st->execute([$wid]);
        $r = $st->fetch();
        return $r ? $r + $d : $d;
    } catch (Throwable $t) {
        return $d;
    }
}

function rp_save_schedule(int $wid, string $recipients, string $freq, string $lang): void
{
    db()->prepare("INSERT INTO report_schedules (workspace_id, recipients, frequency, lang, updated_at) VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE recipients = VALUES(recipients), frequency = VALUES(frequency), lang = VALUES(lang), updated_at = NOW()")
        ->execute([$wid, $recipients, $freq, $lang]);
}

/** يفصل ويتأكد من الإيميلات. يرجع [صحيحة, غلط] */
function rp_parse_emails(string $s): array
{
    $ok = []; $bad = [];
    foreach (preg_split('/[\s,;]+/', $s) as $x) {
        $x = strtolower(trim($x));
        if ($x === '') continue;
        if (filter_var($x, FILTER_VALIDATE_EMAIL)) $ok[$x] = $x; else $bad[] = $x;
    }
    return [array_values(array_slice($ok, 0, 20)), $bad];
}

/** لو التقرير مستحق دلوقتي يرجع نوع الفترة، وإلا null */
function rp_due(array $s, string $tz): ?string
{
    if (!in_array($s['frequency'], ['weekly', 'monthly'], true) || trim((string)$s['recipients']) === '') return null;
    $now = new DateTime('now', new DateTimeZone($tz));
    $boundary = $s['frequency'] === 'weekly' ? (clone $now)->modify('monday this week')->setTime(0, 0) : (clone $now)->modify('first day of this month')->setTime(0, 0);
    if (!$s['last_sent']) return $s['frequency'] === 'weekly' ? 'last_week' : 'last_month';
    $last = new DateTime((string)$s['last_sent'], new DateTimeZone('UTC'));
    return $last < $boundary ? ($s['frequency'] === 'weekly' ? 'last_week' : 'last_month') : null;
}

/** يبعت التقرير لكل المستلمين. بيرجع [عدد اللي اتبعتلهم, عدد اللي فشل] */
function rp_send_report(int $wid, array $recipients, string $kind, string $lang): array
{
    $oldLang = $_COOKIE['lang'] ?? null;
    $_COOKIE['lang'] = $lang === 'ar' ? 'ar' : 'en';
    try {
        $per = rp_period($kind, rp_tz($wid));
        $R = rp_build($wid, $per);
        $subject = t('%s: تقرير الأداء %s', $R['ws'], date('d/m', strtotime($per['from'])) . ' - ' . date('d/m/Y', strtotime($per['to'])));
        $html = '<!doctype html><html lang="' . lang() . '" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:16px;background:#f1f5f9"><div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:12px;padding:20px">'
            . rp_render($R, true) . '</div></body></html>';
    } finally {
        if ($oldLang === null) unset($_COOKIE['lang']); else $_COOKIE['lang'] = $oldLang;
    }
    // SMTP لو متظبط من إعدادات المنصة، وإلا mail() بتاعة الاستضافة
    $ok = 0; $fail = 0;
    foreach ($recipients as $to) {
        try {
            send_mail($to, $subject, $html, null, $wid);
            $ok++;
        } catch (MailException $e) {
            $fail++;
            $GLOBALS['rp_last_mail_error'] = $e->getMessage();
        }
    }
    return [$ok, $fail];
}

function rp_mark_sent(int $wid, string $result, bool $sent): void
{
    try {
        db()->prepare("INSERT INTO report_schedules (workspace_id, last_result, last_sent) VALUES (?, ?, " . ($sent ? 'UTC_TIMESTAMP()' : 'NULL') . ")
            ON DUPLICATE KEY UPDATE last_result = VALUES(last_result)" . ($sent ? ', last_sent = VALUES(last_sent)' : ''))
            ->execute([$wid, mb_substr($result, 0, 250)]);
    } catch (Throwable $t) {
    }
}

/** مفتاح الـ cron السري (بيتعمل مرة واحدة لكل تسطيبة) */
function rp_cron_token(): string
{
    $t = (string)setting('cron_token', '');
    if (strlen($t) < 32) {
        $t = bin2hex(random_bytes(20));
        save_setting('cron_token', $t);
    }
    return $t;
}

/* ---------- الأهداف ---------- */

/** المقاييس: lower = الأقل أحسن */
function rp_goal_metrics(): array
{
    return [
        'impressions' => ['label' => t('مرات الظهور'), 'lower' => false],
        'engagement' => ['label' => t('التفاعل'), 'lower' => false],
        'followers' => ['label' => t('متابعين جداد'), 'lower' => false],
        'posts' => ['label' => t('منشورات اتنشرت'), 'lower' => false],
        'response' => ['label' => t('متوسط وقت الرد (بالدقايق)'), 'lower' => true],
    ];
}

function rp_goals(int $wid, string $month): array
{
    $st = db()->prepare("SELECT metric, target, achieved FROM report_goals WHERE workspace_id = ? AND month = ?");
    $st->execute([$wid, $month]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[$r['metric']] = $r;
    return $out;
}

/** الأرقام الفعلية لشهر (Y-m) لحد النهارده */
function rp_month_actuals(int $wid, string $month): array
{
    $tz = rp_tz($wid);
    $from = $month . '-01';
    $to = date('Y-m-t', strtotime($from));
    $today = (new DateTime('now', new DateTimeZone($tz)))->format('Y-m-d');
    if ($to > $today) $to = $today;
    $R = rp_collect($wid, $from, $to, '', ['daily', 'followers', 'response']);
    return [
        'values' => [
            'impressions' => (float)$R['totals']['impressions'],
            'engagement' => (float)$R['totals']['engagement'],
            'followers' => (float)$R['fol']['gain'],
            'posts' => (float)$R['totals']['postCount'],
            'response' => $R['resp']['median'] !== null ? round($R['resp']['median'] / 60, 1) : null,
        ],
        'errors' => $R['errors'],
    ];
}

/* ---------- التنبيهات ---------- */

function rp_alert_rules(): array
{
    return [
        'post_above' => ['label' => t('منشور أداؤه أعلى من المتوسط'), 'unit' => t('% فوق المتوسط'), 'default' => 50, 'hint' => t('منشور اتنشر في آخر 7 أيام ومرات ظهوره أعلى من متوسط منشورات آخر 30 يوم بالنسبة دي.')],
        'impressions_drop' => ['label' => t('نزول مرات الظهور'), 'unit' => t('% نزول عن الأسبوع اللي قبله'), 'default' => 30, 'hint' => t('بنقارن آخر 7 أيام بالـ 7 أيام اللي قبلهم.')],
        'follower_drop' => ['label' => t('نقص المتابعين'), 'unit' => t('متابع أو أكتر في أسبوع'), 'default' => 5, 'hint' => t('حساب خسر العدد ده من المتابعين أو أكتر في آخر 7 أيام.')],
        'reconnect' => ['label' => t('حساب محتاج إعادة ربط'), 'unit' => '', 'default' => 0, 'hint' => t('حساب الصلاحية بتاعته انتهت أو اتوقف.')],
        'unread_old' => ['label' => t('رسائل مستنية رد'), 'unit' => t('ساعة من غير قراءة'), 'default' => 24, 'hint' => t('محادثة فيها رسائل مش مقروءة بقالها أكتر من عدد الساعات ده.')],
    ];
}

function rp_rules_for(int $wid): array
{
    $out = [];
    foreach (rp_alert_rules() as $k => $r) $out[$k] = ['enabled' => 1, 'threshold' => (float)$r['default']];
    try {
        $st = db()->prepare("SELECT rule, enabled, threshold FROM alert_rules WHERE workspace_id = ?");
        $st->execute([$wid]);
        foreach ($st->fetchAll() as $r) {
            if (isset($out[$r['rule']])) $out[$r['rule']] = ['enabled' => (int)$r['enabled'], 'threshold' => (float)$r['threshold']];
        }
    } catch (Throwable $t) {
    }
    return $out;
}

function rp_add_alert(int $wid, string $rule, string $fp, array $data): void
{
    db()->prepare("INSERT IGNORE INTO alerts (workspace_id, rule, fingerprint, data, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())")
        ->execute([$wid, $rule, mb_substr($fp, 0, 190), json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

/**
 * يقيّم قواعد التنبيه لشركة ويسجل الجديد. محدود بمرة كل 15 دقيقة إلا لو $force.
 * بيرجع عدد التنبيهات الجديدة.
 */
function rp_evaluate_alerts(int $wid, bool $force = false): int
{
    $key = 'alerts_eval_' . $wid;
    if (!$force && (int)setting($key, 0) > time() - 900) return 0;
    save_setting($key, (string)time());
    $clients = rp_clients($wid);
    if (!$clients) return 0;
    $rules = rp_rules_for($wid);
    $st = db()->prepare("SELECT COUNT(*) FROM alerts WHERE workspace_id = ?");
    $st->execute([$wid]);
    $before = (int)$st->fetchColumn();
    $today = gmdate('Y-m-d');
    $d7 = gmdate('Y-m-d', time() - 6 * 86400);

    foreach ($clients as $c) {
        $z = $c['z'];
        $pid = $c['pid'];
        if ($rules['post_above']['enabled']) {
            [$r] = an_try(function () use ($z, $pid, $today) { return an_posts($z, ['profileId' => $pid, 'fromDate' => gmdate('Y-m-d', time() - 29 * 86400), 'toDate' => $today, 'limit' => 100, 'page' => 1, 'sortBy' => 'date', 'order' => 'desc']); });
            $posts = (array)($r['posts'] ?? []);
            $imps = array_map(function ($p) { return (float)($p['analytics']['impressions'] ?? 0); }, $posts);
            if (count($imps) >= 3 && ($avg = array_sum($imps) / count($imps)) > 0) {
                foreach ($posts as $p) {
                    $v = (float)($p['analytics']['impressions'] ?? 0);
                    if (substr((string)($p['publishedAt'] ?? ''), 0, 10) < $d7) continue;
                    $pct = ($v - $avg) / $avg * 100;
                    if ($pct >= $rules['post_above']['threshold']) {
                        rp_add_alert($wid, 'post_above', 'post_above:' . ($p['_id'] ?? md5((string)($p['content'] ?? ''))), [
                            'pct' => round($pct), 'imp' => $v, 'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 90), 'url' => (string)($p['platformPostUrl'] ?? ''), 'platform' => (string)($p['platform'] ?? ''),
                        ]);
                    }
                }
            }
        }
        if ($rules['impressions_drop']['enabled']) {
            $sum = function ($from, $to) use ($z, $pid) {
                [$d, $err] = an_try(function () use ($z, $pid, $from, $to) { return an_daily_metrics($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to]); });
                if ($err) return null;
                $s = 0; foreach ((array)$d as $row) $s += (float)($row['metrics']['impressions'] ?? 0);
                return $s;
            };
            $cur = $sum($d7, $today);
            $prev = $sum(gmdate('Y-m-d', time() - 13 * 86400), gmdate('Y-m-d', time() - 7 * 86400));
            if ($cur !== null && $prev !== null && $prev > 0) {
                $drop = ($prev - $cur) / $prev * 100;
                if ($drop >= $rules['impressions_drop']['threshold']) {
                    rp_add_alert($wid, 'impressions_drop', 'impressions_drop:' . $c['id'] . ':' . gmdate('o-W'), ['pct' => round($drop), 'cur' => $cur, 'prev' => $prev]);
                }
            }
        }
        if ($rules['follower_drop']['enabled']) {
            [$f] = an_try(function () use ($z, $pid, $d7, $today) { return an_follower_stats($z, ['profileId' => $pid, 'fromDate' => $d7, 'toDate' => $today, 'granularity' => 'daily']); });
            foreach ((array)($f['accounts'] ?? []) as $a) {
                $g = (float)($a['growth'] ?? 0);
                if ($g < 0 && -$g >= max(1, $rules['follower_drop']['threshold'])) {
                    rp_add_alert($wid, 'follower_drop', 'follower_drop:' . ($a['_id'] ?? '') . ':' . gmdate('o-W'), ['n' => -$g, 'name' => rp_acc_name($a), 'platform' => (string)($a['platform'] ?? '')]);
                }
            }
        }
        if ($rules['reconnect']['enabled']) {
            [$accs] = an_try(function () use ($z, $pid) { return $z->request('GET', '/v1/accounts', array_filter(['profileId' => $pid]))['accounts'] ?? []; });
            foreach ((array)$accs as $a) {
                if (!empty($a['needsReconnection']) || (isset($a['isActive']) && !$a['isActive'])) {
                    rp_add_alert($wid, 'reconnect', 'reconnect:' . ($a['_id'] ?? '') . ':' . gmdate('o-W'), ['name' => rp_acc_name($a), 'platform' => (string)($a['platform'] ?? '')]);
                }
            }
        }
        if ($rules['unread_old']['enabled']) {
            [$cv] = an_try(function () use ($z, $pid) { return $z->conversations(['limit' => 50, 'profileId' => $pid]); });
            $limit = time() - max(1, $rules['unread_old']['threshold']) * 3600;
            foreach ((array)($cv['data'] ?? []) as $conv) {
                $ut = strtotime((string)($conv['updatedTime'] ?? ''));
                if (!empty($conv['unreadCount']) && $ut && $ut < $limit) {
                    rp_add_alert($wid, 'unread_old', 'unread_old:' . ($conv['id'] ?? '') . ':' . ($conv['updatedTime'] ?? ''), [
                        'name' => (string)($conv['participantName'] ?? $conv['participantUsername'] ?? ''), 'n' => (int)$conv['unreadCount'], 'hours' => (int)floor((time() - $ut) / 3600), 'platform' => (string)($conv['platform'] ?? ''),
                    ]);
                }
            }
        }
    }
    $st->execute([$wid]);
    return max(0, (int)$st->fetchColumn() - $before);
}

/** نص التنبيه بلغة المستخدم */
function rp_alert_text(string $rule, array $d): string
{
    switch ($rule) {
        case 'post_above':
            return t('منشور على %s أداؤه أعلى من المتوسط بـ %s%% (%s مرة ظهور): %s', platform_label((string)($d['platform'] ?? '')), (int)($d['pct'] ?? 0), an_num($d['imp'] ?? 0), (string)($d['text'] ?? ''));
        case 'impressions_drop':
            return t('مرات الظهور نزلت %s%% في آخر 7 أيام (%s بدل %s).', (int)($d['pct'] ?? 0), an_num($d['cur'] ?? 0), an_num($d['prev'] ?? 0));
        case 'follower_drop':
            return t('الحساب %s (%s) خسر %s متابع في آخر 7 أيام.', (string)($d['name'] ?? ''), platform_label((string)($d['platform'] ?? '')), an_num($d['n'] ?? 0));
        case 'reconnect':
            return t('الحساب %s (%s) محتاج يتربط تاني.', (string)($d['name'] ?? ''), platform_label((string)($d['platform'] ?? '')));
        case 'unread_old':
            return t('%s رسالة من %s مستنية بقالها %s ساعة.', (int)($d['n'] ?? 0), (string)($d['name'] ?? ''), (int)($d['hours'] ?? 0));
    }
    return $rule;
}

/** عدد التنبيهات اللي لسه ما اتشافتش (لجرس التنبيهات في الهيدر) */
function alerts_unseen_count(int $workspaceId): int
{
    static $cache = [];
    if (!isset($cache[$workspaceId])) {
        try {
            $st = db()->prepare("SELECT COUNT(*) FROM alerts WHERE workspace_id = ? AND seen = 0");
            $st->execute([$workspaceId]);
            $cache[$workspaceId] = (int)$st->fetchColumn();
        } catch (Throwable $t) {
            $cache[$workspaceId] = 0; // الجدول لسه ما اتعملش
        }
    }
    return $cache[$workspaceId];
}
