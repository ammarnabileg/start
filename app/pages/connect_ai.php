<?php
/** ربط المساعد الخارجي: ChatGPT / Claude بالمنصة عبر MCP (توكنات وتعليمات). */
$wid = (int)ws('id');
// التوكن الجديد بيتخزّن في الجلسة لمرة واحدة ويُقرأ بعد الـredirect (PRG)، فبيظهر مع رسالة النجاح بثبات
// (قبل كده كان متغير محلي بيضيع لو حصل أي redirect/تحديث، فالرسالة تظهر من غير التوكن)
$newToken = $_SESSION['mcp_new_token'] ?? null; unset($_SESSION['mcp_new_token']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'create') {
        $sc = in_array($_POST['scope'] ?? '', ['read', 'write', 'publish', 'admin'], true) ? $_POST['scope'] : 'read';
        $_SESSION['mcp_new_token'] = mcp_token_create($wid, (int)(current_user()['id'] ?? 0), (string)($_POST['name'] ?? ''), $sc);
        flash('success', t('أُنشئ توكن جديد. انسخه الآن، فلن يظهر مرة أخرى.'));
        redirect(url('connect_ai'));
    } elseif ($a === 'delete') {
        mcp_token_delete($wid, (int)($_POST['id'] ?? 0));
        flash('success', t('حُذف التوكن.'));
        redirect(url('connect_ai'));
    } elseif ($a === 'delete_all') {
        $n = mcp_tokens_delete_all($wid);
        flash('success', t('حُذفت كل التوكنات (%s). سيتوقف أي اتصال قائم فورًا.', $n));
        redirect(url('connect_ai'));
    }
}
$recent = mcp_actions_recent($wid, 12);
$tokens = mcp_tokens_list($wid);
$mcpUrl = base_url() . '/index.php?p=mcp';

render(t('المساعد الخارجي'), 'connect_ai', function () use ($tokens, $mcpUrl, $newToken) {
    $step = function (int $n, string $title) { return '<div class="flex items-center gap-2 mb-3"><span class="w-7 h-7 rounded-full bg-brand-600 text-[#fff] grid place-items-center text-sm font-bold shrink-0">' . $n . '</span><b class="text-ink-900">' . e($title) . '</b></div>'; };
    ?>
    <p class="text-sm text-ink-500 mb-5"><?= e(t('اربط ChatGPT أو Claude بمنصتك فيدير العمل من داخل المحادثة: يقرأ حساباتك ومنشوراتك ومخزون المحتوى، ويبدأ خطط محتوى جديدة. ثلاث خطوات:')) ?></p>

    <div class="space-y-4">
      <div class="card p-5">
        <?= $step(1, t('أنشئ توكنًا')) ?>
        <?php if ($newToken): ?>
        <div class="rounded-xl border border-brand-300 bg-brand-50/60 p-3 mb-4">
          <b class="text-ink-900 block mb-1 text-sm"><?= e(t('هذا توكنك. انسخه الآن، فلن يظهر مرة أخرى:')) ?></b>
          <div class="flex gap-2 items-center"><code id="newtok" class="flex-1 text-xs bg-white border border-ink-200 rounded-lg p-2 overflow-x-auto" dir="ltr"><?= e($newToken) ?></code>
            <button type="button" class="btn-ghost !py-1.5" onclick="navigator.clipboard.writeText(document.getElementById('newtok').textContent);this.textContent='✓'"><?= e(t('نسخ')) ?></button></div>
        </div>
        <?php endif; ?>
        <form method="post" class="grid sm:grid-cols-2 gap-3 items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="create">
          <label class="block"><span class="label"><?= e(t('اسم للتمييز')) ?></span><input name="name" class="input" placeholder="<?= e(t('مثال: ChatGPT الخاص بي')) ?>" maxlength="120"></label>
          <label class="block"><span class="label"><?= e(t('الصلاحية')) ?></span>
            <select name="scope" class="input" id="ca-scope">
              <option value="read"><?= e(t('قراءة فقط (الأكثر أمانًا)')) ?></option>
              <option value="write"><?= e(t('قراءة وبدء خطط المحتوى')) ?></option>
              <option value="publish"><?= e(t('نشر: قراءة وخطط ونشر وجدولة مباشرة')) ?></option>
              <option value="admin"><?= e(t('مدير (خطر): كل ما سبق مع صرف الإعلانات والحذف')) ?></option>
            </select></label>
          <p id="ca-warn" class="sm:col-span-2 text-[11px] text-red-700 hidden"><?= e(t('تنبيه: توكن «مدير» يطلق حملات إعلانية بمصروف فعلي، ويحذف منشورات وخططًا، مباشرةً ودون موافقة. أنشئه عند الحاجة فقط، واحذفه إذا تسرّب. تُسجَّل كل عملية في الأسفل.')) ?></p>
          <div class="sm:col-span-2"><button class="btn-primary"><?= e(t('أنشئ التوكن')) ?></button></div>
        </form>
        <script>(function(){var s=document.getElementById('ca-scope'),w=document.getElementById('ca-warn');if(s&&w)s.addEventListener('change',function(){w.classList.toggle('hidden',s.value!=='admin');});})();</script>
      </div>

      <div class="card p-5">
        <?= $step(2, t('أضِف الرابط في تطبيقك')) ?>
        <div class="flex gap-2 items-center mb-2"><code id="mcpurl" class="flex-1 text-xs bg-ink-50 border border-ink-200 rounded-lg p-2 overflow-x-auto" dir="ltr"><?= e($mcpUrl) ?></code>
          <button type="button" class="btn-ghost !py-1.5" onclick="navigator.clipboard.writeText(document.getElementById('mcpurl').textContent);this.textContent='✓'"><?= e(t('نسخ')) ?></button></div>
        <p class="text-[11px] text-ink-400 mb-3"><?= e(t('القاعدة واحدة في كل التطبيقات: الصق الرابط أعلاه، وأضِف ترويسة مصادقة: Authorization بقيمة «Bearer» ثم مسافة ثم التوكن. التطبيقات التي تدعم MCP عبر رابط (Streamable HTTP):')) ?></p>
        <div class="divide-y divide-ink-100 text-sm">
          <?php foreach ([
            ['ChatGPT', t('الإعدادات ← Connectors ← أضف موصّلًا (MCP)، الصق الرابط، وفي المصادقة: Bearer ثم التوكن.')],
            ['Claude', t('الإعدادات ← Connectors ← Add custom connector، الصق الرابط، والتوكن بصيغة Bearer.')],
            [t('Claude Code (الطرفية)'), t('نفّذ: claude mcp add --transport http watad «الرابط» --header "Authorization: Bearer «التوكن»".')],
            ['Cursor', t('Settings ← MCP ← Add new server، النوع HTTP، الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».')],
            ['Windsurf', t('Settings ← Cascade ← MCP Servers ← أضف خادمًا بالرابط وترويسة Authorization: Bearer «التوكن».')],
            [t('VS Code (Copilot)'), t('لوحة الأوامر ← «MCP: Add Server» ← HTTP ← الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».')],
            [t('Cline (إضافة VS Code)'), t('MCP Servers ← Remote ← الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».')],
            ['Zed', t('في settings.json ضمن context_servers أضِف: {"source":"custom","url":"الرابط","headers":{"Authorization":"Bearer التوكن"}}.')],
            ['Raycast', t('Settings ← AI ← Model Context Protocol ← أضف خادمًا بالرابط والتوكن (Bearer).')],
            ['n8n', t('عقدة MCP Client ← نوع الاتصال Streamable HTTP ← ضع الرابط وترويسة Authorization: Bearer «التوكن».')],
          ] as $row): ?>
          <div class="py-2 flex gap-3"><b class="text-ink-900 shrink-0 min-w-[7.5rem]"><?= e($row[0]) ?></b><span class="text-ink-600 min-w-0" dir="auto"><?= e($row[1]) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card p-5">
        <?= $step(3, t('استخدمه من داخل المحادثة')) ?>
        <p class="text-sm text-ink-600 mb-2"><?= e(t('اكتب للمساعد بالعربي أو الإنجليزي، مثلًا:')) ?></p>
        <ul class="text-sm text-ink-700 space-y-1.5 list-disc ps-5">
          <li><?= e(t('«ابدأ خطة محتوى عن عروض رمضان، 10 منشورات».')) ?></li>
          <li><?= e(t('«اعرض مخزون المحتوى الجاهز».')) ?></li>
          <li><?= e(t('«اعرض آخر منشوراتي وحالتها».')) ?></li>
        </ul>
      </div>
    </div>

    <div class="card p-5 mt-5">
      <div class="flex items-center gap-3 mb-3"><b class="text-ink-900 flex-1"><?= e(t('التوكنات')) ?></b>
        <?php if ($tokens): ?><form method="post" onsubmit="return confirm('<?= e(t('حذف كل التوكنات؟ سيتوقف أي اتصال مع ChatGPT أو Claude فورًا، ولا يمكن التراجع.')) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_all"><button class="btn-ghost !py-1.5 text-red-600"><?= e(t('امسح كل التوكنات')) ?></button></form><?php endif; ?>
      </div>
      <?php if (!$tokens): ?><p class="text-sm text-ink-500"><?= e(t('لا توجد توكنات بعد.')) ?></p><?php else: ?>
      <div class="divide-y divide-ink-100">
        <?php foreach ($tokens as $tk): ?>
        <div class="flex items-center gap-3 py-2 text-sm">
          <div class="flex-1 min-w-0"><b class="text-ink-900"><?= e($tk['name']) ?></b>
            <span class="text-xs text-ink-400"><?= e($tk['scopes'] === 'write' ? t('قراءة + كتابة') : t('قراءة فقط')) ?> · <?= e($tk['last_used_at'] ? t('آخر استخدام %s', $tk['last_used_at']) : t('لم يُستخدم')) ?></span></div>
          <form method="post" onsubmit="return confirm('<?= e(t('حذف التوكن؟ سيتوقف أي اتصال يستخدمه.')) ?>')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$tk['id'] ?>"><button class="btn-ghost !py-1 text-red-600"><?= e(t('حذف')) ?></button></form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card p-5 mt-5">
      <b class="text-ink-900 block mb-3"><?= e(t('سجل النشر عبر المساعد')) ?></b>
      <?php if (!$recent): ?><p class="text-sm text-ink-500"><?= e(t('لا توجد عمليات بعد.')) ?></p><?php else: ?>
      <div class="divide-y divide-ink-100 text-sm">
        <?php foreach ($recent as $ac): $okA = $ac['status'] === 'done'; ?>
        <div class="flex items-center gap-3 py-2">
          <span class="<?= $okA ? 'text-lime-600' : 'text-red-600' ?>"><?= $okA ? '✓' : '✕' ?></span>
          <div class="flex-1 min-w-0"><div class="truncate text-ink-800" dir="auto"><?= e($ac['summary']) ?></div>
            <span class="text-xs text-ink-400"><?= e((string)$ac['resolved_at']) ?><?= $ac['result'] ? ' · ' . e($ac['result']) : '' ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
<?php });
