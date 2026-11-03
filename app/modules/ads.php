<?php
/**
 * الإعلانات المدفوعة: حسابات الإعلانات، الحملات، الجماهير، وفورمات الـ Leads.
 * كل الدوال بتاخد Zernio $z وبتنادي $z->request مباشرة.
 * أي حاجة ممكن تصرف فلوس (تشغيل حملة، رفع ميزانية، إنشاء حملة شغالة) لازم تكون لصاحب الشركة بس + تأكيد.
 */

/* ---------- المنصات ---------- */

/** منصات الحسابات (في /v1/accounts) اللي ممكن يبقى ليها حسابات إعلانات */
function ads_connection_platforms(): array
{
    return ['facebook', 'instagram', 'metaads', 'linkedin', 'linkedinads', 'tiktok', 'tiktokads', 'twitter', 'xads',
        'pinterest', 'pinterestads', 'googleads', 'redditads', 'microsoftads'];
}

/** منصات الربط اللي بيقبلها GET /v1/connect/{platform}/ads */
function ads_connect_platforms(): array
{
    return ['facebook' => 'Meta (Facebook / Instagram)', 'googleads' => 'Google Ads', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn',
        'pinterest' => 'Pinterest', 'twitter' => 'X', 'redditads' => 'Reddit', 'microsoftads' => 'Microsoft Ads'];
}

/** المنصات اللي بترجع في الحملات (فلتر platform) */
function ads_campaign_platforms(): array
{
    return ['facebook', 'instagram', 'google', 'tiktok', 'linkedin', 'pinterest', 'twitter', 'reddit', 'microsoft'];
}

function ads_platform_label(string $p): string
{
    $map = ['metaads' => 'Meta Ads', 'linkedinads' => 'LinkedIn Ads', 'tiktokads' => 'TikTok Ads', 'xads' => 'X Ads',
        'pinterestads' => 'Pinterest Ads', 'googleads' => 'Google Ads', 'google' => 'Google Ads', 'redditads' => 'Reddit Ads',
        'reddit' => 'Reddit', 'microsoftads' => 'Microsoft Ads', 'microsoft' => 'Microsoft Ads', 'openai' => 'ChatGPT Ads'];
    return $map[$p] ?? platform_label($p);
}

/** هل الحساب ده من عيلة Meta (فيسبوك/انستجرام/Meta Ads)؟ */
function ads_is_meta(string $platform): bool
{
    return in_array($platform, ['facebook', 'instagram', 'metaads'], true);
}

/* ---------- الحسابات ---------- */

/** الحسابات المربوطة اللي ممكن تعمل إعلانات (فيسبوك، لينكدإن، Google Ads ...) */
function ads_connections(Zernio $z): array
{
    $r = $z->request('GET', '/v1/accounts', ['profileId' => zernio_profile()]);
    $out = [];
    foreach ($r['accounts'] ?? [] as $a) {
        if (in_array($a['platform'] ?? '', ads_connection_platforms(), true) && ($a['enabled'] ?? true) !== false) {
            $out[] = $a;
        }
    }
    return $out;
}

/** حسابات الإعلانات اللي حساب مربوط يقدر يديرها (متخزنة في الجلسة 10 دقايق عشان السرعة) */
function ads_ad_accounts(Zernio $z, string $accountId, bool $fresh = false): array
{
    $key = 'ads_acc_' . $accountId;
    $c = $_SESSION[$key] ?? null;
    if (!$fresh && is_array($c) && ($c['t'] ?? 0) > time() - 600) {
        return $c['d'];
    }
    $r = $z->request('GET', '/v1/ads/accounts', ['accountId' => $accountId]);
    $list = $r['accounts'] ?? [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION[$key] = ['t' => time(), 'd' => $list];
    }
    return $list;
}

/** هل حساب الإعلانات شغال؟ (Meta: accountStatus=1، لينكدإن: ACTIVE) */
function ads_account_usable(array $a): bool
{
    if (array_key_exists('selectable', $a)) {
        return (bool)$a['selectable'];
    }
    if (isset($a['accountStatus'])) {
        return (int)$a['accountStatus'] === 1;
    }
    if (isset($a['status'])) {
        return strtoupper((string)$a['status']) === 'ACTIVE';
    }
    return true;
}

/** كل حسابات الإعلانات الشغالة (مع الحساب المربوط بتاعها) — للاختيار في الفورمات */
function ads_usable_accounts(Zernio $z, array $connections, bool $metaOnly = false): array
{
    $out = [];
    foreach ($connections as $c) {
        if ($metaOnly && !ads_is_meta($c['platform'] ?? '')) continue;
        try {
            foreach (ads_ad_accounts($z, $c['_id']) as $a) {
                if (ads_account_usable($a)) {
                    $out[] = ['conn' => $c, 'acc' => $a];
                }
            }
        } catch (ZernioException $e) {
            // حساب مش متاح له إعلانات: نتجاهله هنا
        }
    }
    return $out;
}

/** رابط ربط حساب إعلانات (Zernio بيرجع authUrl) */
function ads_connect_url(Zernio $z, string $platform, string $redirect): array
{
    return $z->request('GET', '/v1/connect/' . rawurlencode($platform) . '/ads', [
        'profileId' => current_profile_id($z),
        'redirect_url' => $redirect,
    ]);
}

/* ---------- الحملات ---------- */

/** فترات جاهزة للتاريخ → [from, to] بصيغة Y-m-d */
function ads_date_presets(): array
{
    return ['7' => t('آخر 7 أيام'), '30' => t('آخر 30 يوم'), '90' => t('آخر 90 يوم'), 'month' => t('الشهر ده'), 'last_month' => t('الشهر اللي فات')];
}

function ads_date_range(string $preset): array
{
    $tz = new DateTimeZone(site_timezone());
    $today = new DateTime('now', $tz);
    switch ($preset) {
        case 'month':
            return [$today->format('Y-m-01'), $today->format('Y-m-d')];
        case 'last_month':
            $f = (clone $today)->modify('first day of last month');
            $l = (clone $today)->modify('last day of last month');
            return [$f->format('Y-m-d'), $l->format('Y-m-d')];
        default:
            $days = in_array($preset, ['7', '30', '90'], true) ? (int)$preset : 30;
            $f = (clone $today)->modify('-' . ($days - 1) . ' days');
            return [$f->format('Y-m-d'), $today->format('Y-m-d')];
    }
}

function ads_campaigns(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/ads/campaigns', $filters + ['profileId' => zernio_profile()]);
}

/** حملة واحدة بمجموعاتها وإعلاناتها، ومعاها أرقام يوم بيوم */
function ads_campaign_tree(Zernio $z, string $campaignId, string $from, string $to, ?string $platform = null): ?array
{
    $r = $z->request('GET', '/v1/ads/tree', [
        'campaignId' => $campaignId, 'platform' => $platform, 'fromDate' => $from, 'toDate' => $to,
        'timeIncrement' => 1, 'dailyLevel' => 'campaign', 'limit' => 1, 'profileId' => zernio_profile(),
    ]);
    foreach ($r['campaigns'] ?? [] as $c) {
        if ((string)($c['platformCampaignId'] ?? '') === $campaignId) {
            return $c;
        }
    }
    return $r['campaigns'][0] ?? null;
}

function ads_set_campaign_status(Zernio $z, string $campaignId, string $platform, string $status): array
{
    return $z->request('PUT', '/v1/ads/campaigns/' . rawurlencode($campaignId) . '/status', [], ['status' => $status, 'platform' => $platform]);
}

function ads_set_adset_status(Zernio $z, string $adSetId, string $platform, string $status): array
{
    return $z->request('PUT', '/v1/ads/ad-sets/' . rawurlencode($adSetId) . '/status', [], ['status' => $status, 'platform' => $platform]);
}

function ads_set_ad_status(Zernio $z, string $adId, string $status): array
{
    return $z->request('PUT', '/v1/ads/' . rawurlencode($adId) . '/status', [], ['status' => $status]);
}

function ads_update_campaign_budget(Zernio $z, string $campaignId, string $platform, float $amount, string $type): array
{
    return $z->request('PUT', '/v1/ads/campaigns/' . rawurlencode($campaignId), [], ['platform' => $platform, 'budget' => ['amount' => $amount, 'type' => $type]]);
}

function ads_update_adset_budget(Zernio $z, string $adSetId, string $platform, float $amount, string $type): array
{
    return $z->request('PUT', '/v1/ads/ad-sets/' . rawurlencode($adSetId), [], ['platform' => $platform, 'budget' => ['amount' => $amount, 'type' => $type]]);
}

/* ---------- إنشاء ---------- */

function ads_boost(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/ads/boost', [], $payload);
}

function ads_create(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/ads/create', [], $payload);
}

/** بحث في خيارات الاستهداف (اهتمامات، أماكن) */
function ads_targeting_search(Zernio $z, string $accountId, string $q, string $dimension = 'interest', ?string $adAccountId = null): array
{
    $query = ['accountId' => $accountId, 'q' => $q, 'dimension' => $dimension, 'adAccountId' => $adAccountId, 'limit' => 15];
    if ($dimension === 'geo') {
        $query['geoType'] = 'all';
    }
    return $z->request('GET', '/v1/ads/targeting/search', $query)['results'] ?? [];
}

/* ---------- الجماهير ---------- */

function ads_audiences(Zernio $z, string $accountId, string $adAccountId): array
{
    return $z->request('GET', '/v1/ads/audiences', ['accountId' => $accountId, 'adAccountId' => $adAccountId])['audiences'] ?? [];
}

function ads_create_audience(Zernio $z, array $payload): array
{
    return $z->request('POST', '/v1/ads/audiences', [], $payload);
}

function ads_delete_audience(Zernio $z, string $id): void
{
    $z->request('DELETE', '/v1/ads/audiences/' . rawurlencode($id));
}

/* ---------- Lead Gen ---------- */

function ads_lead_forms(Zernio $z, string $accountId, ?string $adAccountId = null): array
{
    return $z->request('GET', '/v1/ads/lead-forms', ['accountId' => $accountId, 'adAccountId' => $adAccountId, 'limit' => 100]);
}

function ads_leads(Zernio $z, array $filters): array
{
    return $z->request('GET', '/v1/ads/leads', $filters);
}

/* ---------- عرض ---------- */

function ads_money($v, ?string $cur = null): string
{
    $n = (float)$v;
    $s = number_format($n, $n >= 1000 ? 0 : 2);
    return $cur ? $s . ' ' . $cur : $s;
}

function ads_num($v): string
{
    return number_format((float)$v);
}

/** شارة حالة الحملة/الإعلان */
function ads_status_badge(string $s): string
{
    $map = [
        'active' => [t('شغالة'), 'bg-lime-100 text-lime-700'],
        'paused' => [t('متوقفة'), 'bg-amber-100 text-amber-800'],
        'pending_review' => [t('تحت المراجعة'), 'bg-brand-100 text-brand-700'],
        'rejected' => [t('مرفوضة'), 'bg-red-100 text-red-700'],
        'completed' => [t('خلصت'), 'bg-ink-100 text-ink-600'],
        'cancelled' => [t('ملغية'), 'bg-ink-100 text-ink-600'],
        'error' => [t('فيها مشكلة'), 'bg-red-100 text-red-700'],
    ];
    $m = $map[strtolower($s)] ?? [$s ?: '—', 'bg-ink-100 text-ink-600'];
    return '<span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-medium ' . $m[1] . '">' . e($m[0]) . '</span>';
}

/** "النتايج": التحويلات لو موجودة، وإلا التفاعل */
function ads_results(array $m): float
{
    return (float)($m['conversions'] ?? 0) ?: (float)($m['engagement'] ?? 0);
}

/** نص الميزانية */
function ads_budget_text(?array $b, ?string $cur = null): string
{
    if (!$b || !isset($b['amount'])) {
        return '—';
    }
    return ($b['type'] ?? 'daily') === 'lifetime'
        ? t('%s إجمالي', ads_money($b['amount'], $cur))
        : t('%s يومياً', ads_money($b['amount'], $cur));
}

/** صندوق "مفيش حساب إعلانات" مع زرار الربط لصاحب الشركة */
function ads_empty_state(): void
{
    ?>
    <div class="card p-6 text-center">
      <div class="text-4xl mb-2">📣</div>
      <h2 class="font-bold text-ink-900 text-lg"><?= e(t('مفيش حساب إعلانات مربوط لسه')) ?></h2>
      <p class="text-sm text-ink-600 mt-2 max-w-xl mx-auto"><?= e(t('عشان تدير إعلاناتك من هنا، لازم تربط حساب إعلانات (Meta، Google، TikTok، LinkedIn ...). الربط بيفتح صفحة المنصة نفسها عشان توافق، وبعدها هترجع هنا تلقائياً.')) ?></p>
      <?php ads_connect_buttons(); ?>
    </div>
    <?php
}

function ads_connect_buttons(): void
{
    if (!can('ads.spend')) {
        echo '<p class="text-sm text-ink-500 mt-4">' . e(t('اطلب من صاحب الشركة يربط حساب الإعلانات.')) . '</p>';
        return;
    }
    ?>
    <form method="post" action="<?= e(url('ads')) ?>" class="mt-4 flex flex-wrap items-center justify-center gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="connect">
      <select name="platform" class="input !mt-0 !w-auto">
        <?php foreach (ads_connect_platforms() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
      </select>
      <button class="btn-primary"><?= e(t('ربط حساب إعلانات')) ?></button>
    </form>
    <?php
}

/** صندوق التأكيد قبل أي حاجة بتصرف فلوس */
function ads_spend_confirm(string $text): string
{
    return '<label class="flex items-start gap-2 text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl p-3">'
        . '<input type="checkbox" name="confirm_spend" value="1" required class="mt-0.5">'
        . '<span>' . e($text) . '</span></label>';
}

/** رسم أعمدة صغير بالـ SVG — $points: [['label'=>..., 'value'=>...], ...] و $fmt بيكتب الرقم */
function ads_bar_chart(array $points, string $title, callable $fmt): string
{
    if (!$points) {
        return '<p class="text-sm text-ink-500">' . e(t('مفيش أرقام في الفترة دي.')) . '</p>';
    }
    $max = max(array_map(function ($p) { return (float)$p['value']; }, $points)) ?: 1;
    $n = count($points);
    $w = 600; $h = 140; $gap = 2;
    $bw = max(1, ($w - $gap * $n) / $n);
    $svg = '<svg viewBox="0 0 ' . $w . ' ' . ($h + 4) . '" class="w-full h-36" preserveAspectRatio="none" role="img" aria-label="' . e($title) . '">';
    foreach ($points as $i => $p) {
        $bh = max(1, ((float)$p['value'] / $max) * $h);
        $x = $i * ($bw + $gap);
        $svg .= '<rect x="' . round($x, 2) . '" y="' . round($h - $bh, 2) . '" width="' . round($bw, 2) . '" height="' . round($bh, 2) . '" rx="2" class="fill-brand-500"><title>' . e($p['label'] . ': ' . $fmt($p['value'])) . '</title></rect>';
    }
    $svg .= '</svg>';
    $first = $points[0]['label'];
    $last = $points[$n - 1]['label'];
    return '<div><div class="text-xs text-ink-500 mb-1">' . e($title) . ' · ' . e(t('الأعلى: %s', $fmt($max))) . '</div>' . $svg
        . '<div class="flex justify-between text-[11px] text-ink-400 mt-1" dir="ltr"><span>' . e($first) . '</span><span>' . e($last) . '</span></div></div>';
}
