<?php
/**
 * تقويم الفرص (المرحلة 2: السوق): المواسم والمناسبات اللي الطلب بيزيد فيها، حسب دولة الشركة.
 * من غير ذكاء اصطناعي: التواريخ الهجرية من تقويم أم القرى (intl)، والمناسبات الثابتة من جدول معروف،
 * ومناسبات الشركة نفسها بيضيفها المستخدم. كل مناسبة ليها "وقت تحضير" وزرار يفتح حملة متكاملة جاهزة.
 */

/** تاريخ للعرض: اليوم، الشهر (اسم عربي في الواجهة العربية)، والسنة. $f من j و M و F و Y */
function opp_fmt(string $ymd, string $f): string
{
    $t = strtotime($ymd . ' 12:00'); $m = (int)date('n', $t);
    $ar = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    $full = lang() === 'ar' ? $ar[$m - 1] : date('F', $t); $short = lang() === 'ar' ? $ar[$m - 1] : date('M', $t);
    $out = '';
    foreach (str_split($f) as $ch) $out .= ['j' => date('j', $t), 'Y' => date('Y', $t), 'F' => $full, 'M' => $short][$ch] ?? $ch;
    return $out;
}

/** الدول العربية (للمناسبات اللي بتخص المنطقة) */
function opp_arab(string $cc): bool { return in_array($cc, ['SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'SD', 'JO', 'LB', 'SY', 'PS', 'IQ', 'YE', 'MA', 'DZ', 'TN', 'LY'], true); }
function opp_gulf(string $cc): bool { return in_array($cc, ['SA', 'AE', 'KW', 'QA', 'BH', 'OM'], true); }

/** تاريخ ميلادي لكل يوم هجري (شهر-يوم) في الفترة. تقويم أم القرى، والفرق مع رؤية الهلال ممكن يوم */
function opp_hijri_dates(DateTime $from, DateTime $to): array
{
    if (!class_exists('IntlDateFormatter')) return [];
    $f = new IntlDateFormatter('en_US@calendar=islamic-umalqura', IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', IntlDateFormatter::TRADITIONAL, 'M-d');
    $out = []; $d = (clone $from)->setTime(12, 0);
    while ($d <= $to) { $k = $f->format($d); if (!isset($out[$k])) $out[$k] = []; $out[$k][] = $d->format('Y-m-d'); $d->modify('+1 day'); }
    return $out;
}

/** الجمعة البيضاء: اليوم اللي بعد رابع خميس في نوفمبر */
function opp_white_friday(int $y): string { $d = new DateTime("fourth thursday of november $y"); return $d->modify('+1 day')->format('Y-m-d'); }

/** عيد القيامة الأرثوذكسي (لشم النسيم في مصر = الاثنين اللي بعده) */
function opp_orthodox_easter(int $y): string
{
    $a = $y % 4; $b = $y % 7; $c = $y % 19; $d = (19 * $c + 15) % 30; $e = (2 * $a + 4 * $b - $d + 34) % 7;
    $month = intdiv($d + $e + 114, 31); $day = (($d + $e + 114) % 31) + 1;
    $jd = gregoriantojd($month, $day, $y) + 13; // يولياني → ميلادي (صالح من 1900 لـ 2099)
    $g = cal_from_jd($jd, CAL_GREGORIAN); return sprintf('%04d-%02d-%02d', $g['year'], $g['month'], $g['day']);
}

/**
 * كل الفرص في الفترة. كل واحدة:
 * key, name, date, end (لو أكتر من يوم), kind (religious|national|commercial|payday|custom), lead (أيام التحضير), offer (عرض مقترح), angle (فكرة المحتوى), approx
 */
function opp_list(int $days = 120, ?string $cc = null): array
{
    $cc = $cc ?? ws_country(); $tz = new DateTimeZone(site_timezone());
    $from = new DateTime('today', $tz); $to = (clone $from)->modify('+' . $days . ' days');
    $arab = opp_arab($cc); $o = [];
    $add = function (string $key, string $name, string $date, string $kind, int $lead, string $offer, string $angle, array $x = []) use (&$o, $from, $to) {
        $end = $x['end'] ?? $date; if ($end < $from->format('Y-m-d') || $date > $to->format('Y-m-d')) return;
        $o[$key . '@' . $date] = ['key' => $key, 'name' => $name, 'date' => $date, 'end' => $end, 'kind' => $kind, 'lead' => $lead, 'offer' => $offer, 'angle' => $angle, 'approx' => !empty($x['approx'])];
    };
    // المناسبات الإسلامية (أم القرى)
    if ($arab || in_array($cc, ['TR', 'ID', 'MY', 'PK', 'BD'], true)) {
        $h = opp_hijri_dates((clone $from)->modify('-35 days'), (clone $to)->modify('+3 days'));
        foreach ($h['9-1'] ?? [] as $d) $add('ramadan', t('رمضان'), $d, 'religious', 21, t('عرض رمضان'), t('عروض السحور والفطار، والطلبات الكبيرة للعزايم، والتوصيل بالليل.'), ['end' => date('Y-m-d', strtotime($d . ' +28 days')), 'approx' => true]);
        foreach ($h['9-21'] ?? [] as $d) $add('ramadan_last10', t('العشر الأواخر من رمضان'), $d, 'religious', 7, t('عرض العشر الأواخر'), t('تجهيزات العيد وهدايا آخر الشهر. الناس بتشتري قبل الزحمة.'), ['end' => date('Y-m-d', strtotime($d . ' +8 days')), 'approx' => true]);
        foreach ($h['10-1'] ?? [] as $d) $add('eid_fitr', t('عيد الفطر'), $d, 'religious', 14, t('عرض العيد'), t('هدايا العيد، وتهنئة العملاء، وعروض أيام العيد والإجازة.'), ['end' => date('Y-m-d', strtotime($d . ' +3 days')), 'approx' => true]);
        foreach ($h['12-9'] ?? [] as $d) $add('arafah', t('يوم عرفة'), $d, 'religious', 5, '', t('محتوى تهنئة وروحاني، ومش وقت عروض بيع مباشرة.'), ['approx' => true]);
        foreach ($h['12-10'] ?? [] as $d) $add('eid_adha', t('عيد الأضحى'), $d, 'religious', 14, t('عرض عيد الأضحى'), t('العزايم والإجازة الطويلة والسفر. اعرض الطلبات الكبيرة والحجز المسبق.'), ['end' => date('Y-m-d', strtotime($d . ' +3 days')), 'approx' => true]);
        foreach ($h['1-1'] ?? [] as $d) $add('hijri_new_year', t('رأس السنة الهجرية'), $d, 'religious', 5, '', t('تهنئة وبداية جديدة.'), ['approx' => true]);
        if ($cc !== 'SA') foreach ($h['3-12'] ?? [] as $d) $add('mawlid', t('المولد النبوي'), $d, 'religious', 7, t('عرض المولد'), $cc === 'EG' ? t('حلاوة المولد والهدايا العائلية.') : t('تهنئة ومحتوى عائلي.'), ['approx' => true]);
    }
    for ($y = (int)$from->format('Y'); $y <= (int)$to->format('Y'); $y++) {
        // تجارية
        $add('white_friday', $arab ? t('الجمعة البيضاء') : 'Black Friday', opp_white_friday($y), 'commercial', 21, t('خصومات الجمعة البيضاء'), t('أكبر موسم خصومات أونلاين. ابدأ بدري قبل ما الناس تصرف ميزانيتها عند غيرك.'), ['end' => date('Y-m-d', strtotime(opp_white_friday($y) . ' +3 days'))]);
        $add('11_11', t('عروض 11.11'), "$y-11-11", 'commercial', 10, t('عرض 11.11'), t('موسم تسوق أونلاين قوي في الخليج. عرض ليوم واحد بس بيشد.'));
        $add('new_year', t('رأس السنة الميلادية'), "$y-01-01", 'commercial', 10, t('عرض بداية السنة'), t('بداية جديدة: اشتراكات وباقات سنوية وأهداف السنة.'));
        if ($cc !== 'SA') $add('valentine', t('عيد الحب'), "$y-02-14", 'commercial', 10, t('عرض عيد الحب'), t('هدايا للشريك، وعشا لاتنين، وباقات مخصوصة.'));
        if ($arab) $add('mothers_day', t('عيد الأم'), "$y-03-21", 'commercial', 14, t('عرض عيد الأم'), t('هدايا الأم. ابدأ بدري لأن الناس بتشتري قبلها بأسبوع.'));
        // أيام وطنية (اللي تواريخها ثابتة ومعروفة بس)
        $nat = ['SA' => [['sa_national', t('اليوم الوطني السعودي'), '09-23'], ['sa_founding', t('يوم التأسيس'), '02-22']], 'AE' => [['ae_national', t('عيد الاتحاد'), '12-02']],
            'KW' => [['kw_national', t('العيد الوطني الكويتي'), '02-25']], 'QA' => [['qa_national', t('اليوم الوطني القطري'), '12-18']], 'BH' => [['bh_national', t('العيد الوطني البحريني'), '12-16']],
            'EG' => [['eg_oct6', t('عيد 6 أكتوبر'), '10-06'], ['eg_jul23', t('عيد ثورة 23 يوليو'), '07-23']], 'JO' => [['jo_independence', t('عيد الاستقلال الأردني'), '05-25']],
            'MA' => [['ma_throne', t('عيد العرش'), '07-30']], 'DZ' => [['dz_independence', t('عيد الاستقلال الجزائري'), '07-05']], 'TN' => [['tn_independence', t('عيد الاستقلال التونسي'), '03-20']]];
        foreach ($nat[$cc] ?? [] as $n) $add($n[0], $n[1], "$y-" . $n[2], 'national', 14, t('عرض %s', $n[1]), opp_gulf($cc) ? t('عروض بالرقم (زي خصم 93% لليوم الوطني 93) وألوان العلم بتشد جدًا.') : t('تهنئة وعرض بمناسبة اليوم.'));
        if ($cc === 'EG') { $e = opp_orthodox_easter($y); $add('sham_nessim', t('شم النسيم'), date('Y-m-d', strtotime($e . ' +1 day')), 'national', 10, t('عرض شم النسيم'), t('الخروجات والفسح والأكل البيتي. الطلبات بتبدأ قبلها بيومين.'));
            $add('coptic_xmas', t('عيد الميلاد المجيد'), "$y-01-07", 'religious', 7, '', t('تهنئة لكل العملاء.')); }
    }
    // يوم الرواتب في السعودية: 27 كل شهر
    if ($cc === 'SA') { $m = (clone $from)->modify('first day of this month'); while ($m <= $to) { $add('payday', t('نزول الرواتب'), $m->format('Y-m-27'), 'payday', 3, t('عرض نزول الراتب'), t('الصرف بيعلى في الأيام اللي بعد الراتب. أحسن وقت لعرض قصير.')); $m->modify('+1 month'); } }
    // مناسبات الشركة نفسها
    foreach (opp_custom() as $c) {
        $d = $c['date']; if (!empty($c['yearly'])) { $md = substr($d, 5); $d = $from->format('Y') . '-' . $md; if ($d < $from->format('Y-m-d')) $d = ((int)$from->format('Y') + 1) . '-' . $md; }
        $add('custom_' . $c['id'], $c['name'], $d, 'custom', (int)($c['lead'] ?? 10), (string)($c['offer'] ?? ''), (string)($c['note'] ?? ''));
    }
    $hidden = opp_hidden(); $o = array_filter($o, function ($x) use ($hidden) { return !isset($hidden[$x['key'] . '@' . $x['date']]); });
    usort($o, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    return array_values($o);
}

function opp_custom(): array { $v = json_decode((string)setting('opp_custom_' . (int)ws('id'), ''), true); return is_array($v) ? $v : []; }
function opp_hidden(): array { $v = json_decode((string)setting('opp_hidden_' . (int)ws('id'), ''), true); return is_array($v) ? $v : []; }

/** حالة الفرصة النهارده: soon (وقت التحضير)، live (شغالة)، later. وأول يوم تحضير */
function opp_state(array $x): array
{
    $today = (new DateTime('today', new DateTimeZone(site_timezone())))->format('Y-m-d');
    $prep = date('Y-m-d', strtotime($x['date'] . ' -' . (int)$x['lead'] . ' days'));
    $days = (int)round((strtotime($x['date']) - strtotime($today)) / 86400);
    $st = $today >= $x['date'] && $today <= $x['end'] ? 'live' : ($today >= $prep ? 'soon' : 'later');
    return ['state' => $st, 'prep' => $prep, 'days' => $days];
}

/** الحملة اللي بتغطي الفرصة دي (من صفحة حملة متكاملة) لو موجودة */
function opp_campaign_for(array $x, array $camps): ?array
{
    // الحملة لازم تكون شغالة لحد قرب المناسبة (مش حملة خلصت قبلها بأسبوع)، وتبدأ قبل ما المناسبة تخلص
    $near = date('Y-m-d', strtotime($x['date'] . ' -2 days'));
    foreach ($camps as $c) if ($c['start_at'] && $c['end_at'] && $c['start_at'] <= $x['end'] && $c['end_at'] >= $near) return $c;
    return null;
}

/** رابط حملة متكاملة جاهزة للمناسبة: تبدأ أول يوم تحضير (أو بكرة) وتخلص آخر يوم في المناسبة */
function opp_campaign_url(array $x): string
{
    $tz = new DateTimeZone(site_timezone()); $tomorrow = (new DateTime('tomorrow', $tz))->format('Y-m-d');
    $start = max($tomorrow, date('Y-m-d', strtotime($x['date'] . ' -' . min(10, (int)$x['lead']) . ' days')));
    $days = max(1, min(60, (int)round((strtotime($x['end']) - strtotime($start)) / 86400) + 1));
    return url('campaign', ['start' => $start, 'days' => $days, 'count' => max(2, min(8, intdiv($days, 3) + 1)), 'name' => $x['name'], 'offer' => $x['offer'], 'occ' => $x['key']]);
}

/** التشغيل الدوري: تنبيه المديرين لما فرصة تدخل وقت التحضير ومفيش حملة ليها. مرة واحدة لكل فرصة */
function opp_tick(): int
{
    if (!function_exists('crm_notify') || !module_enabled('crm')) return 0;
    $wid = (int)ws('id'); $sent = json_decode((string)setting('opp_notified_' . $wid, ''), true) ?: [];
    $camps = function_exists('mcp_list') ? mcp_list() : []; $n = 0;
    foreach (opp_list(60) as $x) {
        $id = $x['key'] . '@' . $x['date']; if (isset($sent[$id]) || $x['kind'] === 'payday') continue;
        $s = opp_state($x); if ($s['state'] !== 'soon' || opp_campaign_for($x, $camps)) continue;
        crm_notify(null, null, 'opportunity', '📅 ' . t('%s بعد %s يوم ولسه مفيش حملة ليه. ابدأ التحضير.', $x['name'], $s['days']));
        $sent[$id] = date('Y-m-d'); $n++;
    }
    if ($n) { $cut = date('Y-m-d', strtotime('-400 days')); $sent = array_filter($sent, function ($d) use ($cut) { return $d >= $cut; }); save_setting('opp_notified_' . $wid, json_encode($sent)); }
    return $n;
}
