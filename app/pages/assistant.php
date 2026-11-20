<?php
/**
 * المساعد الذكي: تسأله عن أرقام الشركة ويرد من بياناتها الحقيقية.
 * عشان الرصيد: بنبعت ملخص صغير للبيانات (بيتحفظ 10 دقايق)، وآخر كام رسالة بس من المحادثة.
 */
$wid = (int)ws('id');
$hist = &$_SESSION['ai_chat'][$wid];
if (!is_array($hist)) $hist = [];
$connected = ai_connection() !== null;
$tabs = ['models' => t('الموديلات والمهام'), 'agent' => t('الرد الذكي'), 'features' => t('الميزات'), 'usage' => t('الاستهلاك والإعدادات')];
require_once __DIR__ . '/../views/ai_agent.php';
if ($tab_req = (string)($_GET['ajax'] ?? '')) { if (strpos($tab_req, 'agent_') === 0) { if ($tab_req !== 'agent_state' && $_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Content-Type: application/json; charset=utf-8'); exit('{"ok":false,"error":"POST required"}'); } ai_agent_ajax($tab_req); } }
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'models';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'ask');
    if (strpos($action, 'agent_') === 0) ai_agent_ajax($action);
    // إعدادات الذكاء الاصطناعي
    if ($action === 'ai_feature') ai_feature_post();
    if (strpos($action, 'ai_') === 0) { ai_settings_post($action); redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models'])); }
    if (!ai_feature_on('assistant')) { flash('error', t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')); redirect(url('assistant', ['tab' => 'features'])); }
    if ($action === 'clear') {
        $hist = [];
        foreach (['', 'x', 'n', 'xn'] as $sfx) unset($_SESSION['ai_ctx'][$wid . $sfx]);
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

render(t('الذكاء الاصطناعي'), 'assistant', function () use ($connected) {
    $wid = (int)ws('id');
    $reg = connector_registry();
    $money = function ($v) { return '$' . ($v > 0 && $v < 0.01 ? number_format($v, 4) : number_format($v, 2)); };
    $spend = ai_month_spend($wid);
    $ftTotal = count(ai_presets()) + count(ai_insights()) + count(ai_special_features());
    $ftOn = max(0, $ftTotal - count(ai_disabled($wid)));
    $replyOn = (function_exists('ai_reply_cfg') ? (ai_reply_cfg($wid)['when'] ?? '') : '') === 'always';
    $conn = $connected ? ai_connection() : null;
    $provName = $conn ? ($reg[$conn['provider']]['name'] ?? $conn['provider']) : '';
    $spaces = ['overview' => t('نظرة عامة'), 'models' => t('الموديلات والتوجيه'), 'features' => t('الميزات'), 'agent' => t('الرد الذكي'), 'usage' => t('الاستهلاك والتكاليف')];
    ?>
    <div class="grid lg:grid-cols-[15rem_1fr] gap-5 items-start">
      <!-- تنقّل داخلي -->
      <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:sticky lg:top-20 -mx-1 px-1" aria-label="<?= e(t('أقسام الذكاء الاصطناعي')) ?>">
        <?php foreach ($spaces as $k => $lbl): ?>
          <button type="button" data-space="<?= $k ?>" class="ai-navbtn shrink-0 flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-start text-ink-600 hover:bg-ink-100 transition whitespace-nowrap">
            <span class="ai-navdot w-1.5 h-1.5 rounded-full bg-ink-300 shrink-0"></span><span class="flex-1"><?= e($lbl) ?></span>
          </button>
        <?php endforeach; ?>
        <a href="<?= e(url('connectors')) ?>" class="shrink-0 flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm text-ink-500 hover:bg-ink-100 transition whitespace-nowrap"><?= ui_icon('plug', 'w-4 h-4') ?><span class="lg:flex-1"><?= e(t('ربط المزودين')) ?></span></a>
      </nav>

      <div class="min-w-0">
        <!-- نظرة عامة -->
        <section data-space-panel="overview" class="space-y-5">
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('المزود')) ?></div><div class="mt-1 font-semibold text-ink-900 truncate"><?= $connected ? e($provName) : '<span class="text-amber-700">' . e(t('غير مربوط')) . '</span>' ?></div><?php if ($connected): ?><div class="text-[11px] text-lime-700 mt-0.5"><?= e(ai_mode() === 'manual' ? t('توجيه يدوي') : t('توجيه تلقائي')) ?></div><?php endif; ?></div>
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('استهلاك الشهر')) ?></div><div class="mt-1 font-semibold text-ink-900" dir="ltr"><?= e($money($spend)) ?></div><button type="button" data-space="usage" class="text-[11px] link mt-0.5"><?= e(t('التفاصيل')) ?> ←</button></div>
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('الميزات المفعّلة')) ?></div><div class="mt-1 font-semibold text-ink-900"><?= (int)$ftOn ?> <span class="text-ink-400 text-sm font-normal">/ <?= (int)$ftTotal ?></span></div><button type="button" data-space="features" class="text-[11px] link mt-0.5"><?= e(t('إدارة')) ?> ←</button></div>
            <div class="card p-4"><div class="text-xs text-ink-500"><?= e(t('الرد الذكي')) ?></div><div class="mt-1 font-semibold <?= $replyOn ? 'text-lime-700' : 'text-ink-900' ?>"><?= e($replyOn ? t('يعمل') : t('متوقف')) ?></div><button type="button" data-space="agent" class="text-[11px] link mt-0.5"><?= e(t('فتح') ) ?> ←</button></div>
          </div>
          <?php if (!$connected): ?>
          <div class="card p-6 flex flex-col sm:flex-row items-center gap-4 text-center sm:text-start border-amber-200 bg-amber-50/50">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center shrink-0"><?= ai_icon('spark', 'w-6 h-6') ?></div>
            <div class="flex-1"><h3 class="font-semibold text-ink-900"><?= e(t('ابدأ بربط مزود ذكاء اصطناعي')) ?></h3><p class="text-sm text-ink-600 mt-0.5"><?= e(t('OpenRouter و Gemini فيهما استخدام مجاني. تقدّر تضبط باقي الإعدادات من هنا دلوقتي.')) ?></p></div>
            <?php if (can('integrations.manage')): ?><a href="<?= e(url('connectors')) ?>" class="btn-primary shrink-0"><?= e(t('اربط الآن')) ?></a><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="card p-5">
            <h3 class="font-semibold text-ink-900 mb-1"><?= e(t('إجراءات سريعة')) ?></h3>
            <p class="text-xs text-ink-500 mb-3"><?= e(t('أكثر ما تحتاجه، على بُعد نقرة.')) ?></p>
            <div class="flex flex-wrap gap-2">
              <?php if ($connected): ?><button type="button" data-cp-toggle class="btn-primary !py-1.5 !text-sm"><?= ai_icon('spark', 'w-4 h-4') ?><?= e(t('افتح المحادثة')) ?></button><?php endif; ?>
              <button type="button" data-space="models" class="btn-ghost !py-1.5 !text-sm"><?= e(t('اختيار الموديلات والتوجيه')) ?></button>
              <button type="button" data-space="features" class="btn-ghost !py-1.5 !text-sm"><?= e(t('تشغيل/إيقاف الميزات')) ?></button>
              <button type="button" data-space="agent" class="btn-ghost !py-1.5 !text-sm"><?= e(t('إعداد الرد الذكي')) ?></button>
              <a href="<?= e(url('knowledge')) ?>" class="btn-ghost !py-1.5 !text-sm"><?= ui_icon('bulb', 'w-4 h-4') ?><?= e(t('قاعدة المعرفة')) ?></a>
            </div>
          </div>
        </section>

        <!-- الموديلات والتوجيه -->
        <section data-space-panel="models" class="space-y-5" hidden><?php ai_settings_render('models'); ?></section>
        <!-- الميزات -->
        <section data-space-panel="features" hidden><?php ai_features_render(); ?></section>
        <!-- الرد الذكي -->
        <section data-space-panel="agent" hidden><?php ai_agent_render(); ?></section>
        <!-- الاستهلاك والتكاليف -->
        <section data-space-panel="usage" hidden><?php ai_settings_render('usage'); ?></section>
      </div>
    </div>
    <script>
    (function () {
      var panels = [].slice.call(document.querySelectorAll('[data-space-panel]'));
      var navs = [].slice.call(document.querySelectorAll('.ai-navbtn'));
      var valid = { overview: 1, models: 1, features: 1, agent: 1, usage: 1 };
      function show(key, push) {
        if (!valid[key]) key = 'overview';
        panels.forEach(function (p) { p.hidden = p.dataset.spacePanel !== key; });
        navs.forEach(function (n) {
          var on = n.dataset.space === key;
          n.classList.toggle('bg-brand-50', on); n.classList.toggle('text-brand-700', on); n.classList.toggle('font-semibold', on);
          var d = n.querySelector('.ai-navdot'); if (d) { d.classList.toggle('bg-brand-500', on); d.classList.toggle('bg-ink-300', !on); }
          n.setAttribute('aria-current', on ? 'page' : 'false');
        });
        if (push) { try { var u = new URL(location.href); if (key === 'overview') u.searchParams.delete('tab'); else u.searchParams.set('tab', key); history.replaceState(null, '', u); } catch (e) {} }
      }
      document.addEventListener('click', function (e) { var n = e.target.closest('[data-space]'); if (n) { e.preventDefault(); show(n.dataset.space, true); window.scrollTo({ top: 0 }); } });
      show(new URLSearchParams(location.search).get('tab') || 'overview', false);
    })();
    </script>
    <?php
});
