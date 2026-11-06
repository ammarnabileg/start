<?php
/**
 * المساعد الذكي: تسأله عن أرقام الشركة ويرد من بياناتها الحقيقية.
 * عشان الرصيد: بنبعت ملخص صغير للبيانات (بيتحفظ 10 دقايق)، وآخر كام رسالة بس من المحادثة.
 */
$wid = (int)ws('id');
$hist = &$_SESSION['ai_chat'][$wid];
if (!is_array($hist)) $hist = [];
$connected = ai_connection() !== null;
$tabs = ['models' => t('الموديلات والمهام'), 'features' => t('الميزات'), 'usage' => t('الاستهلاك والإعدادات')];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'models';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'ask');
    // إعدادات الذكاء الاصطناعي
    if ($action === 'ai_feature') ai_feature_post();
    if (strpos($action, 'ai_') === 0) { ai_settings_post($action); redirect(url('assistant', ['tab' => $_POST['back'] ?? 'models'])); }
    if (!ai_feature_on('assistant')) { flash('error', t('هذه الميزة متوقفة في الشركة. يمكن تفعيلها من إعدادات الذكاء الاصطناعي.')); redirect(url('assistant', ['tab' => 'features'])); }
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

render(t('الذكاء الاصطناعي'), 'assistant', function () use ($connected, $tabs, $tab) {
    ?>
    <div class="flex items-center gap-1 border-b border-ink-200 mb-6 overflow-x-auto">
      <?php foreach ($tabs as $tk => $tl): ?>
        <a href="<?= e(url('assistant', $tk === 'models' ? [] : ['tab' => $tk])) ?>" class="relative -mb-px px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition <?= $tab === $tk ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-900' ?>"><?= e($tl) ?></a>
      <?php endforeach; ?>
      <a href="<?= e(url('connectors')) ?>" class="ms-auto px-3 py-2 text-xs link whitespace-nowrap"><?= e(t('ربط المزودين ←')) ?></a>
    </div>
    <?php
    if ($tab === 'features') { ai_features_render(); return; }
    if ($connected) echo '<div class="card p-4 mb-5 flex items-center gap-3 text-sm"><span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-cyan-500 text-[#fff] flex items-center justify-center">' . ai_icon('spark', 'w-4 h-4') . '</span><span class="flex-1 text-ink-600">' . e(t('المحادثة مع الذكاء الاصطناعي أصبحت في اللوحة الجانبية، متاحة من أي صفحة. اضغط ✨ أو Ctrl+J.')) . '</span><button type="button" data-cp-toggle class="btn-primary !py-1.5 !text-xs">' . e(t('افتح المحادثة')) . '</button></div>';
    // شركة ملهاش أي مزود: كارت واضح بدل قائمة الموديلات (باقي التبويبات شغالة عادي)
    if (!$connected && $tab === 'models') {
        ?>
        <div class="card p-10 text-center max-w-xl mx-auto">
          <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center mb-4"><?= ai_icon('spark', 'w-7 h-7') ?></div>
          <h2 class="text-lg font-semibold text-ink-900"><?= e(t('المساعد يحتاج مزود ذكاء اصطناعي')) ?></h2>
          <p class="text-sm text-ink-500 mt-1 mb-5"><?= e(t('اربط مفتاح OpenRouter (فيه موديلات مجانية) أو Anthropic أو OpenAI أو Gemini من صفحة التكاملات.')) ?></p>
          <?php if (can('integrations.manage')): ?><a href="<?= e(url('connectors')) ?>" class="btn-primary"><?= e(t('فتح التكاملات')) ?></a><?php else: ?><p class="text-xs text-ink-400"><?= e(t('اطلب من صاحب الشركة يربطه.')) ?></p><?php endif; ?>
        </div>
        <?php
        return;
    }
    echo '<div class="space-y-5">'; ai_settings_render($tab); echo '</div>';
});
