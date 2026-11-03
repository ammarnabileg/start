<?php
/**
 * المساعد الذكي: تسأله عن أرقام الشركة ويرد من بياناتها الحقيقية.
 * عشان الرصيد: بنبعت ملخص صغير للبيانات (بيتحفظ 10 دقايق)، وآخر كام رسالة بس من المحادثة.
 */
$wid = (int)ws('id');
$hist = &$_SESSION['ai_chat'][$wid];
if (!is_array($hist)) $hist = [];
$connected = ai_connection() !== null;

/** ملخص بيانات الشركة اللي المساعد بيرد منه */
function assistant_context(): string
{
    $wid = (int)ws('id');
    $cache = $_SESSION['ai_ctx'][$wid] ?? null;
    if ($cache && $cache['t'] > time() - 600) return $cache['v'];

    $z = zernio_or_null($err);
    $pid = zernio_profile();
    $ctx = ['company' => ws('name'), 'today' => (new DateTime('now', new DateTimeZone(site_timezone())))->format('Y-m-d l'), 'timezone' => site_timezone()];
    if ($z) {
        $g = function (callable $f) { try { return $f(); } catch (Throwable $t) { return null; } };
        $acc = $g(function () use ($z, $pid) { return $z->allAccounts($pid); }) ?: [];
        $ctx['accounts'] = array_map(function ($a) {
            return ['platform' => $a['platform'] ?? '', 'name' => trim($a['displayName'] ?? $a['username'] ?? ''), 'followers' => $a['followersCount'] ?? null,
                'needs_reconnect' => !empty($a['needsReconnection'])];
        }, $acc);
        $from = gmdate('Y-m-d', time() - 29 * 86400);
        $to = gmdate('Y-m-d');
        $daily = $g(function () use ($z, $pid, $from, $to) { return an_daily_metrics($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to]); }) ?: [];
        $sum = function ($rows) {
            $t = [];
            foreach ($rows as $r) foreach ((array)($r['metrics'] ?? []) as $k => $v) if (is_numeric($v)) $t[$k] = ($t[$k] ?? 0) + $v;
            return $t;
        };
        usort($daily, function ($a, $b) { return strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? '')); });
        $ctx['last_30_days_totals'] = $sum($daily);
        $ctx['last_7_days_totals'] = $sum(array_slice($daily, -7));
        $ctx['previous_7_days_totals'] = $sum(array_slice($daily, -14, 7));
        $ctx['daily_impressions_last_14'] = array_map(function ($r) { return [$r['date'] ?? '', (int)($r['metrics']['impressions'] ?? 0)]; }, array_slice($daily, -14));
        $posts = $g(function () use ($z, $pid, $from, $to) { return an_posts($z, ['profileId' => $pid, 'fromDate' => $from, 'toDate' => $to, 'limit' => 8, 'sortBy' => 'engagement', 'order' => 'desc'])['posts'] ?? []; }) ?: [];
        $ctx['top_posts_last_30_days'] = array_map(function ($p) {
            return ['platform' => $p['platform'] ?? '', 'date' => substr((string)($p['publishedAt'] ?? ''), 0, 10), 'text' => mb_substr(trim((string)($p['content'] ?? '')), 0, 120),
                'metrics' => array_intersect_key((array)($p['analytics'] ?? $p['metrics'] ?? []), array_flip(['impressions', 'reach', 'likes', 'comments', 'shares', 'clicks', 'engagementRate']))];
        }, $posts);
        $best = $g(function () use ($z, $pid) { return an_best_time($z, ['profileId' => $pid]); }) ?: [];
        $ctx['best_times_utc'] = array_slice($best, 0, 6);
        $sched = $g(function () use ($z, $pid) { return $z->posts(['status' => 'scheduled', 'limit' => 1, 'profileId' => $pid]); });
        $ctx['scheduled_posts'] = (int)($sched['pagination']['total'] ?? 0);
        $conv = $g(function () use ($z, $pid) { return $z->conversations(['limit' => 30, 'profileId' => $pid])['data'] ?? []; }) ?: [];
        $ctx['inbox'] = ['conversations_loaded' => count($conv), 'unread_messages' => array_sum(array_map(function ($c) { return (int)($c['unreadCount'] ?? 0); }, $conv)),
            'by_platform' => array_count_values(array_map(function ($c) { return (string)($c['platform'] ?? ''); }, $conv))];
    }
    $v = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $_SESSION['ai_ctx'][$wid] = ['t' => time(), 'v' => $v];
    return $v;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'ask');
    if ($action === 'clear') {
        $hist = [];
        unset($_SESSION['ai_ctx'][$wid]);
        redirect(url('assistant'));
    }
    $q = trim((string)($_POST['message'] ?? ''));
    if ($q === '') {
        flash('error', t('اكتب سؤالك الأول.'));
        redirect(url('assistant'));
    }
    $q = mb_substr($q, 0, 1500);
    $lang = lang() === 'ar' ? 'Arabic (Modern Standard Arabic)' : 'English';
    $system = "You are the analytics assistant inside a social media management app for the company \"" . ws('name') . "\". "
        . "Answer ONLY from the JSON data below; if the data doesn't contain the answer, say so briefly and suggest what to check. "
        . "Be concise and practical: short paragraphs or bullet lists, concrete numbers, and 1-3 actionable recommendations when useful. "
        . "Best-time hours are UTC; convert to the company timezone. Reply in $lang.\n\nDATA:\n" . assistant_context();
    $msgs = [['role' => 'system', 'content' => $system]];
    foreach (array_slice($hist, -6) as $h) $msgs[] = ['role' => $h['role'], 'content' => $h['content']];
    $msgs[] = ['role' => 'user', 'content' => $q];
    $hist[] = ['role' => 'user', 'content' => $q, 't' => time()];
    try {
        $r = ai_chat($msgs);
        $hist[] = ['role' => 'assistant', 'content' => $r['text'], 'model' => $r['model'], 'tokens' => (int)($r['usage']['total_tokens'] ?? 0), 't' => time()];
        log_activity('ai.ask', $r['model'] . ' · ' . (int)($r['usage']['total_tokens'] ?? 0) . ' tokens');
    } catch (AiException $ex) {
        array_pop($hist);
        flash('error', $ex->getMessage());
    }
    $hist = array_slice($hist, -20);
    redirect(url('assistant'));
}

render(t('المساعد الذكي'), 'assistant', function () use ($hist, $connected) {
    $suggest = [t('إيه أحسن منشور الشهر ده وليه؟'), t('الأداء الأسبوع ده أحسن ولا أسوأ من اللي قبله؟'), t('إمتى أحسن وقت أنشر فيه؟'), t('اقترح عليا 3 أفكار منشورات بناءً على اللي نجح'), t('فيه حسابات محتاجة اهتمام؟'), t('لخصلي الوضع في 5 نقاط')];
    ?>
    <?php if (!$connected): ?>
      <div class="card p-10 text-center max-w-xl mx-auto">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center text-xl font-bold mb-4">AI</div>
        <h2 class="text-lg font-semibold text-ink-900"><?= e(t('المساعد محتاج OpenRouter')) ?></h2>
        <p class="text-sm text-ink-500 mt-1 mb-5"><?= e(t('اربط مفتاح OpenRouter من صفحة التكاملات. بيبدأ بالموديلات المجانية، فممكن تستخدمه من غير أي تكلفة.')) ?></p>
        <?php if (can('integrations.manage')): ?><a href="<?= e(url('connectors')) ?>" class="btn-primary"><?= e(t('ربط OpenRouter')) ?></a><?php else: ?><p class="text-xs text-ink-400"><?= e(t('اطلب من صاحب الشركة يربطه.')) ?></p><?php endif; ?>
      </div>
    <?php return; endif; ?>

    <div data-live-root>
    <div class="card flex flex-col h-[calc(100vh-14rem)] min-h-[520px] max-w-4xl">
      <div class="px-5 py-3 border-b border-ink-200 flex items-center gap-3">
        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center text-xs font-bold">AI</span>
        <div class="flex-1"><b class="text-ink-900"><?= e(t('اسأل عن أرقام %s', ws('name'))) ?></b>
          <p class="text-xs text-ink-500"><?= e(t('بيرد من بيانات آخر 30 يوم. البيانات بتتحدث كل 10 دقايق.')) ?></p></div>
        <?php if ($hist): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn-ghost text-xs"><?= e(t('محادثة جديدة')) ?></button></form>
        <?php endif; ?>
      </div>

      <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-4 bg-ink-50" id="thread">
        <?php if (!$hist): ?>
          <div class="text-center py-6">
            <p class="text-sm text-ink-500 mb-4"><?= e(t('جرب تسأل:')) ?></p>
            <div class="flex flex-wrap justify-center gap-2">
              <?php foreach ($suggest as $s): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="message" value="<?= e($s) ?>">
                  <button class="chip"><?= e($s) ?></button></form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php foreach ($hist as $h): $me = $h['role'] === 'user'; ?>
          <div class="flex <?= $me ? 'justify-start' : 'justify-end' ?>">
            <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed <?= $me ? 'bg-brand-600 text-white' : 'bg-white border border-ink-200 text-ink-800' ?>">
              <?= $me ? '<p class="whitespace-pre-line">' . e($h['content']) . '</p>' : ai_render($h['content']) ?>
              <?php if (!$me && !empty($h['model'])): ?><div class="text-[10px] text-ink-400 mt-2" dir="ltr" style="text-align:start"><?= e($h['model']) ?><?= !empty($h['tokens']) ? ' · ' . (int)$h['tokens'] . ' tokens' : '' ?></div><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div id="bottom"></div>
      </div>

      <form method="post" data-optimistic class="p-3 border-t border-ink-200 flex gap-2 items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="ask">
        <textarea name="message" rows="2" maxlength="1500" class="input !mt-0 flex-1 text-sm" placeholder="<?= e(t('اكتب سؤالك...')) ?>" data-enter-submit></textarea>
        <button class="btn-primary"><?= e(t('اسأل')) ?></button>
      </form>
    </div>
    </div>
    <?php
});
