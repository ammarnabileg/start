<?php
/** ربط المساعد الخارجي: ChatGPT / Claude بالمنصة عبر MCP (توكنات وتعليمات). */
$wid = (int)ws('id');
$newToken = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'create') {
        $newToken = mcp_token_create($wid, (int)(current_user()['id'] ?? 0), (string)($_POST['name'] ?? ''), ($_POST['scope'] ?? '') === 'write' ? 'write' : 'read');
        flash('success', t('اتعمل توكن جديد. انسخه دلوقتي — مش هيظهر تاني.'));
    } elseif ($a === 'delete') {
        mcp_token_delete($wid, (int)($_POST['id'] ?? 0));
        flash('success', t('اتمسح التوكن.'));
        redirect(url('connect_ai'));
    }
}
$tokens = mcp_tokens_list($wid);
$mcpUrl = base_url() . '/index.php?p=mcp';

render(t('ربط ChatGPT / Claude'), 'connect_ai', function () use ($tokens, $mcpUrl, $newToken) { ?>
    <p class="text-sm text-ink-500 mb-5"><?= e(t('اربط تطبيق ChatGPT أو Claude بمنصتك، وخلي المساعد يشغّل الشغل بدالك: يقرا حساباتك ومنشوراتك ومخزون المحتوى، ويبدأ خطط محتوى جديدة بالذكاء الاصطناعي — كل ده من داخل الشات.')) ?></p>

    <?php if ($newToken): ?>
    <div class="card p-4 mb-5 border-brand-300 bg-brand-50/50">
      <b class="text-ink-900 block mb-1"><?= e(t('التوكن الجديد (انسخه الآن — لن يظهر مرة أخرى):')) ?></b>
      <div class="flex gap-2 items-center"><code id="newtok" class="flex-1 text-xs bg-white border border-ink-200 rounded-lg p-2 overflow-x-auto" dir="ltr"><?= e($newToken) ?></code>
        <button type="button" class="btn-ghost !py-1.5" onclick="navigator.clipboard.writeText(document.getElementById('newtok').textContent);this.textContent='✓'"><?= e(t('نسخ')) ?></button></div>
    </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-5">
      <div class="card p-5">
        <b class="text-ink-900 block mb-3"><?= e(t('أنشئ توكن ربط')) ?></b>
        <form method="post" class="space-y-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <label class="block"><span class="label"><?= e(t('اسم (للتمييز)')) ?></span><input name="name" class="input" placeholder="<?= e(t('مثلاً: ChatGPT بتاعي')) ?>" maxlength="120"></label>
          <label class="block"><span class="label"><?= e(t('الصلاحية')) ?></span>
            <select name="scope" class="input">
              <option value="read"><?= e(t('قراءة فقط (آمن: يقرا بس)')) ?></option>
              <option value="write"><?= e(t('قراءة + بدء خطط محتوى')) ?></option>
            </select></label>
          <p class="text-[11px] text-ink-400"><?= e(t('ملاحظة أمان: التوكنات دي لا تنشر منشورات ولا تصرف ميزانية إعلانات ولا تحذف أي حاجة. النشر يظل من داخل المنصة.')) ?></p>
          <button class="btn-primary"><?= e(t('أنشئ التوكن')) ?></button>
        </form>
      </div>

      <div class="card p-5">
        <b class="text-ink-900 block mb-3"><?= e(t('رابط الخادم (MCP)')) ?></b>
        <div class="flex gap-2 items-center mb-3"><code id="mcpurl" class="flex-1 text-xs bg-ink-50 border border-ink-200 rounded-lg p-2 overflow-x-auto" dir="ltr"><?= e($mcpUrl) ?></code>
          <button type="button" class="btn-ghost !py-1.5" onclick="navigator.clipboard.writeText(document.getElementById('mcpurl').textContent);this.textContent='✓'"><?= e(t('نسخ')) ?></button></div>
        <div class="text-sm text-ink-600 space-y-2">
          <p><b>ChatGPT:</b> <?= e(t('Settings ← Connectors ← Add (MCP)، وحطّ الرابط فوق، وفي خانة الـ Authorization اكتب: Bearer ثم مسافة ثم التوكن.')) ?></p>
          <p><b>Claude:</b> <?= e(t('Settings ← Connectors ← Add custom connector، وحطّ نفس الرابط، والتوكن كـ Bearer.')) ?></p>
          <p class="text-[11px] text-ink-400"><?= e(t('بعد الربط اكتب في الشات مثلاً: «ابدأ خطة محتوى عن كذا» أو «وريني مخزون المحتوى».')) ?></p>
        </div>
      </div>
    </div>

    <div class="card p-5 mt-5">
      <b class="text-ink-900 block mb-3"><?= e(t('التوكنات')) ?></b>
      <?php if (!$tokens): ?><p class="text-sm text-ink-500"><?= e(t('لا توجد توكنات بعد.')) ?></p><?php else: ?>
      <div class="divide-y divide-ink-100">
        <?php foreach ($tokens as $tk): ?>
        <div class="flex items-center gap-3 py-2 text-sm">
          <div class="flex-1 min-w-0"><b class="text-ink-900"><?= e($tk['name']) ?></b>
            <span class="text-xs text-ink-400"><?= e($tk['scopes'] === 'write' ? t('قراءة + كتابة') : t('قراءة فقط')) ?> · <?= e($tk['last_used_at'] ? t('آخر استخدام %s', $tk['last_used_at']) : t('لم يُستخدم')) ?></span></div>
          <form method="post" onsubmit="return confirm('<?= e(t('حذف التوكن؟ أي ربط يستخدمه هيتوقف.')) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$tk['id'] ?>"><button class="btn-ghost !py-1 text-red-600"><?= e(t('حذف')) ?></button></form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
<?php });
