<?php
/** حملة متكاملة في خطوة واحدة (#5): خدمة من ملف الشركة ← صفحة هبوط + كوبون + لينك واتساب متتبع + منشورات موزعة + تقرير واحد.
 *  النصوص من قوالب ثابتة (من غير ذكاء اصطناعي)، والمستخدم بيراجع ويعدّل قبل الإنشاء. */

function mcp_platforms(): array { return ['facebook', 'linkedin', 'twitter', 'threads', 'googlebusiness', 'instagram']; }

/** الحملات المحفوظة للشركة */
function mcp_list(): array
{
    try { $st = db()->prepare('SELECT * FROM campaigns WHERE workspace_id = ? ORDER BY id DESC LIMIT 30'); $st->execute([(int)ws('id')]); return $st->fetchAll(); } catch (Throwable $e) { return []; }
}

/** نصوص المنشورات من زوايا مختلفة بالتناوب */
function mcp_build_posts(array $svc, array $kb, array $o): array
{
    $c = (array)($kb['company'] ?? []); $faq = array_values(array_filter((array)($kb['faq']['rows'] ?? []), function ($r) { return trim((string)($r['q'] ?? '')) !== '' && trim((string)($r['a'] ?? '')) !== ''; }));
    $name = trim((string)($svc['name'] ?? '')); $price = trim((string)($svc['price'] ?? '')); $det = trim((string)($svc['details'] ?? ''));
    $link = (string)$o['link']; $wa = (string)($o['wa'] ?? ''); $tags = implode(' ', array_slice((array)($o['tags'] ?? []), 0, 4));
    $cpl = $o['coupon'] !== '' ? t('استخدم كود %s للخصم', $o['coupon']) . ($o['offer'] !== '' ? ' (' . $o['offer'] . ')' : '') : ($o['offer'] !== '' ? $o['offer'] : '');
    $about = trim((string)($c['tagline'] ?? '')) ?: mb_substr(trim((string)($c['about'] ?? '')), 0, 180);
    $end = (string)$o['end_label']; $cta = $wa !== '' ? t('للطلب على واتساب: %s', $wa) : t('للطلب: %s', $link);
    $ang = [
        'offer' => trim($name . ($price !== '' ? ' — ' . $price : '') . "\n" . ($about !== '' ? $about . "\n" : '') . ($cpl !== '' ? $cpl . "\n" : '') . "\n" . t('اعرف أكتر واطلب: %s', $link)),
        'detail' => trim(t('ليه %s؟', $name) . "\n" . ($det !== '' ? $det : $about) . "\n\n" . $cta),
        'faq' => '', 'about' => trim(($about !== '' ? $about . "\n\n" : '') . t('جرّب %s', $name) . ($price !== '' ? ' ' . t('بـ %s بس', $price) : '') . "\n" . t('التفاصيل: %s', $link)),
        'last' => trim(t('آخر فرصة! عرض %s بيخلص %s.', $name, $end) . ($cpl !== '' ? "\n" . $cpl : '') . "\n" . $cta),
    ];
    $order = ['offer', 'detail', 'faq', 'about'];
    $n = max(1, min(20, (int)$o['count'])); $out = [];
    for ($i = 0; $i < $n; $i++) {
        $k = $i === $n - 1 && $n > 1 ? 'last' : $order[$i % count($order)];
        if ($k === 'faq') { if ($faq) { $q = $faq[intdiv($i, count($order)) % count($faq)]; $txt = trim(t('سؤال بيوصلنا كتير: %s', trim((string)$q['q'])) . "\n" . trim((string)$q['a']) . "\n\n" . $cta); } else { $k = 'offer'; $txt = $ang['offer']; } }
        else $txt = $ang[$k];
        $out[] = ['angle' => $k, 'text' => mb_substr($txt . ($tags !== '' ? "\n\n" . $tags : ''), 0, 2000)];
    }
    return $out;
}

/** مواعيد موزعة بالتساوي على الفترة في أحسن ساعة (بتوقيت الشركة) */
function mcp_schedule(string $start, int $days, int $n, int $hour): array
{
    $tz = new DateTimeZone(site_timezone()); $d0 = new DateTime($start . ' ' . sprintf('%02d:00', $hour), $tz); $now = new DateTime('now', $tz);
    if ($d0 < $now) { $d0 = (clone $now)->modify('+1 hour'); $d0->setTime((int)$d0->format('G'), 0); }
    $out = []; $step = $n > 1 ? max(0, ($days - 1)) / ($n - 1) : 0;
    for ($i = 0; $i < $n; $i++) { $d = (clone $d0)->modify('+' . (int)round($i * $step) . ' days'); if ($i && $d->format('Y-m-d') === substr($out[$i - 1], 0, 10)) $d->modify('+3 hours'); $out[] = $d->format('Y-m-d\TH:i'); }
    return $out;
}

/** أرقام الحملة: ضغطات، زيارات الصفحة، عملاء، بقوا عملاء، مبيعات */
function mcp_stats(array $c): array
{
    $wid = (int)ws('id'); $codes = array_values(array_filter(json_decode((string)$c['codes'], true) ?: []));
    $clicks = 0; if ($codes) { $st = db()->prepare('SELECT COALESCE(SUM(clicks), 0) FROM crm_links WHERE workspace_id = ? AND code IN (' . implode(',', array_fill(0, count($codes), '?')) . ')'); $st->execute(array_merge([$wid], $codes)); $clicks = (int)$st->fetchColumn(); }
    $views = 0; foreach (function_exists('lp_list') ? lp_list() : [] as $x) if (($x['slug'] ?? '') === $c['lp_slug']) $views = (int)($x['views'] ?? 0);
    $keys = array_merge($codes, [preg_replace('/[^A-Za-z0-9]/', '', (string)$c['lp_slug'])]); $keys = array_values(array_unique(array_filter($keys)));
    $q = 'SELECT COUNT(*) n, SUM(stage = \'won\') w, COALESCE(SUM(CASE WHEN stage = \'won\' THEN deal_value END), 0) v FROM crm_leads WHERE workspace_id = ? AND (' . ($keys ? 'link_code IN (' . implode(',', array_fill(0, count($keys), '?')) . ')' : '0') . ($c['coupon'] !== '' && $c['coupon'] !== null ? ' OR coupon = ?' : '') . ')';
    $args = array_merge([$wid], $keys); if ($c['coupon'] !== '' && $c['coupon'] !== null) $args[] = $c['coupon'];
    $st = db()->prepare($q); $st->execute($args); $r = $st->fetch();
    return ['posts' => count(json_decode((string)$c['post_ids'], true) ?: []), 'clicks' => $clicks, 'views' => $views, 'leads' => (int)$r['n'], 'won' => (int)$r['w'], 'value' => (float)$r['v']];
}
