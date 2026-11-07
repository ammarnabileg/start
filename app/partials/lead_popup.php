<?php
/** نافذة العميل المشتركة: نفس النافذة في العملاء المستهدفين والرسائل والتعليقات وأي صفحة، والتعديل بيتحفظ على نفس العميل */
function lead_popup_L(): array
{
    return ['pick' => t('اختار الملف أو اسحبه هنا'), 'rows' => t('%s صف في الملف'), 'sheets' => t('الشيتات'), 'sheetRows' => t('%s صف'), 'noSheet' => t('اختار شيت واحد على الأقل.'), 'go' => t('استورد %s عميل'), 'busy' => t('بنستورد…'), 'done' => t('اتضاف %s جديد، واتدمج %s، واتشال %s'),
        'cols' => ['name' => t('الاسم'), 'phone' => t('الموبايل'), 'email' => t('الإيميل'), 'company' => t('الشركة'), 'title' => t('المسمى'), 'country' => t('الدولة'), 'tags' => t('تاجات'), 'note' => t('ملاحظة')], 'skip' => t('— تجاهل —'),
        'noRows' => t('الملف فاضي أو مش CSV ولا Excel.'), 'reading' => t('بنقرا الملف…'), 'searching' => t('بندوّر…'), 'found' => t('%s نتيجة'), 'nothing' => t('مفيش نتايج. جرّب كلمات تانية.'), 'have' => t('عندك'), 'revealing' => t('بنكشف…'), 'revealed' => t('اتضاف %s عميل'),
        'err' => t('حصلت مشكلة.'), 'hasEmail' => t('فيه إيميل'), 'hasPhone' => t('فيه رقم'), 'synced' => t('اتضاف %s شخص جديد اتعامل معاك'), 'saved' => t('اتحفظ ✓'), 'tagAsk' => t('اكتب التاج'), 'done2' => t('اتعمل على %s'),
        'call' => t('اتصال'), 'wa' => t('واتساب'), 'mail' => t('إيميل'), 'src' => t('المصدر'), 'added' => t('اتضاف'), 'last' => t('آخر تواصل'), 'country' => t('الدولة'), 'none' => t('مفيش بيانات تواصل لسه'), 'noTitle' => t('عميل محتمل'), 'loadErr' => t('ماقدرناش نحمّل سجل التواصل.'), 'retry' => t('حاول تاني'), 'now' => t('دلوقتي'), 'minAgo' => t('من %s دقيقة'), 'hrAgo' => t('من %s ساعة'), 'dayAgo' => t('من %s يوم'), 'noNext' => t('مفيش'), 'noActs' => t('لسه مفيش تواصل متسجل.'), 'logged' => t('اتسجل ✓'), 'today' => t('النهارده'), 'pickAt' => t('اختار التاريخ الأول.'), 'copied' => t('اتنسخ ✓'), 'copy' => t('نسخ'), 'all' => t('الكل'), 'untag' => t('شيل التاج'), 'heat' => t('حرارة العميل: %s من 100'), 'hot' => t('ساخن'), 'warm' => t('دافي'), 'cold' => t('بارد'), 'undo' => t('تراجع'), 'won' => t('مبروك! صفقة جديدة 🎉'),
        'waTpl' => crm_templates(), 'book' => setting('crm_book_on_' . (int)ws('id'), '0') === '1' ? crm_book_url() : '', 'company' => '', 'editTpl' => (can('crm.manage') || is_ws_owner()) ? url('crm_leads') . '#lead-' : '', 'editTplL' => t('عدّل القوالب'), 'why' => t('الأهمية'), 'me' => (string)(current_user()['name'] ?? '')];
}
/** أدوات النافذة (من صفحة العملاء القديمة): نتيجة المكالمة، الصفقات، الإيميل، الخطط، المهام، البيانات */
function lead_popup_X(): array
{
    $canManage = can('crm.manage') || is_ws_owner(); $members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name'];
    return ['api' => url('crm_leads'), 'voice' => url('crm_leads') . '&voice=', 'settings' => url('crm_plans'), 'oc' => crm_call_outcomes(), 'lost' => crm_lost_reasons(), 'dealSt' => crm_deal_statuses(),
        'plans' => array_map(function ($p) { return ['id' => $p['id'], 'name' => $p['name'], 'steps' => array_map(function ($x) { return (string)($x['ch'] ?? ''); }, $p['steps'])]; }, crm_plans()),
        'chans' => array_map(function ($c) { return $c[1]; }, crm_plan_channels()), 'pipes' => crm_pipelines(), 'cf' => crm_custom_fields(), 'apollo' => crm_has_key() && can('crm.reveal'), 'manage' => $canManage, 'members' => $members, 'me' => (int)(current_user()['id'] ?? 0),
        'T' => ['how' => t('المكالمة كانت عاملة إزاي؟'), 'deals' => t('الصفقات'), 'addDeal' => t('صفقة جديدة'), 'dealName' => t('اسم الصفقة (مثلًا: باقة سنوية)'), 'value' => t('القيمة'), 'closeAt' => t('هتتقفل إمتى؟'), 'add' => t('أضف'), 'noDeals' => t('مفيش صفقات لسه. ضيف أول صفقة وتابع قيمتها هنا.'),
          'total' => t('إجمالي المفتوح والمكسوب'), 'sure' => t('متأكد؟'), 'inDays' => t('بعد %s يوم'), 'late' => t('متأخرة %s يوم'), 'today' => t('النهارده'),
          'plan' => t('خطة المتابعة'), 'noPlan' => t('مفيش خطة شغالة. اختار خطة تمشي بيها خطوة بخطوة:'), 'noPlans' => t('مفيش خطط متعملة. اعمل خطة من الإعدادات.'), 'start' => t('ابدأ'), 'step' => t('خطوة %s من %s'), 'done' => t('نفذتها ✓'), 'skip' => t('تخطّي'), 'stop' => t('وقّف الخطة'), 'sendWa' => t('ابعتها واتساب'), 'dueAt' => t('ميعادها'),
          'tasks' => t('المتابعة والمهام'), 'follow' => t('متابعة'), 'stageL' => t('الحالة'), 'noteQ' => t('إيه اللي حصل؟ (اختياري)'), 'saveUpd' => t('حفظ التحديث'), 'updEmpty' => t('غيّر الحالة أو اكتب ملاحظة أو الخطوة الجاية'), 'whenL' => t('إمتى؟'), 'pick' => t('تاريخ'), 'week' => t('الأسبوع الجاي'), 'mkPlan' => t('اعمل خطة'), 'taskPh' => t('الخطوة الجاية (اختياري)… مثلًا: ابعتله عرض السعر'), 'noTasks' => t('مفيش مهام مفتوحة.'), 'tomorrow' => t('بكرة'), 'd3' => t('بعد 3 أيام'), 'noDue' => t('من غير ميعاد'), 'me' => t('أنا'),
          'subject' => t('عنوان الإيميل'), 'body' => t('اكتب الرسالة… استخدم المتغيرات تحت'), 'send' => t('ابعت الإيميل'), 'sending' => t('بيتبعت…'), 'sent' => t('اتبعت ✓'), 'preview' => t('معاينة'), 'noEmail' => t('العميل ده مالوش إيميل. ضيفه من فوق الأول.'), 'unsub' => t('العميل ده لغى الاشتراك في الإيميلات.'),
          'opened' => t('اتفتح %s مرة'), 'clicked' => t('ضغط على رابط'), 'notOpened' => t('لسه ما اتفتحش'), 'history' => t('اللي اتبعت'), 'smtp' => t('بيتبعت من SMTP بتاعك، وبتعرف لو اتفتح أو حد ضغط على رابط.'),
          'pipe' => t('الـ Pipeline'), 'byDeals' => t('محسوبة من الصفقات'), 'saved' => t('اتحفظ ✓'), 'noFields' => t('ممكن تضيف حقول مخصصة من الإعدادات.'), 'fields' => t('حقول مخصصة'),
          'intel' => t('ملخص سريع'), 'best' => t('أحسن وقت تكلّمه'), 'first' => t('رسالة مقترحة'), 'copy' => t('نسخ'), 'copied' => t('اتنسخ ✓'), 'useWa' => t('ابعتها واتساب'),
          'enriching' => t('بنكشف…'), 'got' => t('لقينا بيانات جديدة ✓'), 'pending' => t('الرقم هيوصل خلال دقايق'), 'nothing' => t('مالقيناش بيانات جديدة'), 'deleted' => t('اتحذف'), 'delSure' => t('اضغط تاني للتأكيد'),
          'rec' => t('بيسجّل…'), 'noMic' => t('المتصفح مش سامح بالمايك.'), 'vSaved' => t('اتحفظت الملاحظة الصوتية ✓'), 'lang' => is_rtl() ? 'ar-EG' : 'en-US',
          'queue' => t('طابور الاتصال'), 'qOf' => t('%s من %s'), 'qNext' => t('التالي'), 'qEnd' => t('خلصت الطابور 👏'), 'qAuto' => t('هننقل للي بعده خلال %s…'), 'qStay' => t('خليني هنا'), 'qExit' => t('اخرج من الطابور'), 'err' => t('حصلت مشكلة.')]];
}
function lead_popup(): string
{
    static $done = false; if ($done) return ''; $done = true;
    $stages = crm_stages(); $kinds = crm_act_kinds(); $canManage = can('crm.manage') || is_ws_owner();
    $members = []; foreach (crm_members() as $m) $members[(int)$m['id']] = $m['name'];
    $nexts = ['today' => t('النهارده'), '1d' => t('بكرة'), '3d' => t('بعد 3 أيام'), 'week' => t('الأسبوع الجاي'), 'none' => t('من غير متابعة')];
    $st = db()->prepare("SELECT DISTINCT tags FROM crm_leads WHERE workspace_id = ? AND tags IS NOT NULL AND tags <> '' LIMIT 2000"); $st->execute([(int)ws('id')]);
    $rows = array_map(function ($t) { return ['tags' => $t]; }, $st->fetchAll(PDO::FETCH_COLUMN));
    ob_start(); ?>
    <dialog id="pr-dlg" class="mk-dlg pd-dlg !w-[min(980px,96vw)] !p-0">
      <div id="pd-queue" class="pd-qbar hidden"></div>
      <div class="pd-hero">
        <div class="flex items-start gap-4">
          <div class="relative shrink-0"><span id="pd-av" class="pd-av"></span><span id="pd-heat" class="pd-heat" title=""></span></div>
          <div class="flex-1 min-w-0 pt-1"><div id="pd-idv" class="group flex items-center gap-1.5 min-w-0"><b id="pd-name" class="block text-xl text-[#fff] truncate"></b><button type="button" id="pd-edit" class="pd-hb !w-7 !h-7 opacity-70 group-hover:opacity-100" title="<?= e(t('تعديل')) ?> (R)"><?= ui_icon('pencil', 'w-3.5 h-3.5') ?></button></div><span id="pd-sub" class="block text-sm text-[#fff]/80 truncate"></span><form id="pd-idf" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-1.5 mt-1" data-no-ajax><input name="name" class="pd-in" dir="auto" required placeholder="<?= e(t('الاسم')) ?>"><input name="title" class="pd-in" dir="auto" placeholder="<?= e(t('المسمى')) ?>"><input name="company" class="pd-in" dir="auto" placeholder="<?= e(t('الشركة')) ?>"><div class="sm:col-span-3 flex gap-1.5"><button class="pd-chip !bg-[#fff] !text-[#1d4ed8] !py-1 !px-3"><?= e(t('حفظ')) ?></button><button type="button" data-idx class="pd-chip !py-1 !px-3"><?= e(t('إلغاء')) ?></button></div></form>
            <div class="flex flex-wrap items-center gap-1.5 mt-2"><span id="pd-stage-pill" class="pd-chip"></span><span id="pd-src-pill" class="pd-chip !bg-[#fff]/15"></span><span id="pd-due-pill" class="pd-chip hidden"></span><button type="button" id="pd-enrich" class="pd-chip !bg-[#fff] !text-[#6d28d9] hidden hover:scale-105 transition"><?= ui_icon('spark', 'w-3.5 h-3.5') ?><?= e(t('اكشف بياناته')) ?></button></div></div>
          <div class="flex items-center gap-1 shrink-0"><span id="pd-pos" class="text-[11px] text-[#fff]/70 tabular-nums me-1" dir="ltr"></span>
            <button type="button" class="pd-hb" data-nav="-1" title="<?= e(t('السابق')) ?> (→)"><?= ui_icon('chevron-right', 'w-5 h-5') ?></button><button type="button" class="pd-hb" data-nav="1" title="<?= e(t('التالي')) ?> (←)"><?= ui_icon('chevron-left', 'w-5 h-5') ?></button>
            <button type="button" data-close class="pd-hb" aria-label="<?= e(t('إغلاق')) ?>"><?= ui_icon('x', 'w-5 h-5') ?></button></div>
        </div>
        <div id="pd-contact" class="pd-cts"></div>
        <form id="pd-addc" class="hidden mt-3 flex flex-wrap gap-2" data-no-ajax><input name="phone" class="input !mt-0 !py-1.5 text-sm flex-1 min-w-[140px]" dir="ltr" placeholder="<?= e(t('الرقم')) ?>"><input name="email" type="email" class="input !mt-0 !py-1.5 text-sm flex-1 min-w-[160px]" dir="ltr" placeholder="<?= e(t('الإيميل')) ?>"><button class="btn-primary !py-1.5 !text-sm"><?= e(t('حفظ')) ?></button></form>
      </div>
      <div class="pd-stages" id="pd-stages"><?php foreach ($stages as $k => $l): ?><button type="button" data-stg="<?= e($k) ?>"><?= e($l) ?></button><?php endforeach; ?></div>
      <select id="pd-stage" class="hidden" aria-hidden="true"><?php foreach ($stages as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <div class="pd-body">
        <div class="pd-side">
          <section class="pd-card hidden" aria-hidden="true"><div class="flex items-center gap-2 text-sm mb-2"><?= ui_icon('calendar', 'w-4 h-4 text-brand-600') ?><b class="text-ink-900"><?= e(t('المتابعة الجاية')) ?></b><span id="pd-next" class="ms-auto text-ink-600 tabular-nums text-xs"></span></div>
            <div class="pd-nb" id="pd-next-b"><?php foreach ($nexts as $k => $l): ?><button type="button" class="pd-q" data-when="<?= e($k) ?>"><?= e($l) ?></button><?php endforeach; ?><input type="datetime-local" id="pd-next-at" class="input !mt-0 !py-1 !h-8 text-xs pd-nat"></div>
            <input id="pd-next-note" class="input !mt-2 !py-1.5 text-xs" dir="auto" maxlength="255" placeholder="<?= e(t('هتكلمه في إيه؟ (اختياري)')) ?>"></section>
          <?php if ($canManage): ?><section class="pd-card"><label class="flex items-center gap-2 text-sm"><?= ui_icon('user', 'w-4 h-4 text-brand-600') ?><b class="text-ink-900"><?= e(t('المسؤول')) ?></b>
            <select id="pd-owner" class="input !mt-0 !py-1 !h-9 text-sm ms-auto !w-auto max-w-[60%]"><option value="0"><?= e(t('من غير مسؤول')) ?></option><?php foreach ($members as $k => $l): ?><option value="<?= (int)$k ?>"><?= e($l) ?></option><?php endforeach; ?></select></label></section><?php endif; ?>
          <dl id="pd-meta" class="pd-meta"></dl>
          <div class="pd-card"><span class="text-xs font-semibold text-ink-500"><?= e(t('التاجات')) ?></span><div class="flex flex-wrap items-center gap-1.5 mt-2"><span id="pd-tags" class="contents"></span><form id="pd-tag-f" class="inline-flex m-0" data-no-ajax><input name="t" list="pd-tagl" autocomplete="off" class="input !mt-0 !py-1 !w-28 text-xs" dir="auto" placeholder="<?= e(t('+ تاج')) ?>"><datalist id="pd-tagl"><?php $allT = []; foreach ($rows as $r) foreach (explode(',', (string)$r['tags']) as $x) { $x = trim($x); if ($x !== '') $allT[$x] = 1; } foreach (array_slice(array_keys($allT), 0, 200) as $x): ?><option value="<?= e($x) ?>"><?php endforeach; ?></datalist></form></div></div>
        </div>
        <div class="flex flex-col min-h-0">
          <div id="pd-tabs" class="pd-tabs" role="tablist"><?php foreach (['ct' => ['chat', t('التواصل')], 'dl' => ['wallet', t('الصفقات')], 'tk' => ['calendar', t('المتابعة')], 'em' => ['mail', t('الإيميل')], 'dt' => ['layers', t('البيانات')]] as $k => $v): ?><button type="button" role="tab" data-pt="<?= $k ?>"<?= $k === 'ct' ? ' class="on"' : '' ?>><?= ui_icon($v[0], 'w-4 h-4') ?><span><?= e($v[1]) ?></span><i data-ptn="<?= $k ?>"></i></button><?php endforeach; ?><span class="pd-tabs-ink" aria-hidden="true"></span></div>
          <div class="p-5 flex flex-col min-h-0 pd-pane" data-pane="ct">
          <div id="pd-intel"></div>
          <div id="pd-oc" class="pd-oc hidden"></div>
          <div class="flex items-center gap-2 mb-2"><b class="text-sm text-ink-900"><?= e(t('سجّل تواصل')) ?></b><span class="text-[11px] text-ink-400"><?= e(t('بضغطة')) ?></span></div>
          <div class="grid grid-cols-4 gap-1.5 mb-2"><?php foreach (['call' => 'phone', 'wa' => 'chat', 'email' => 'mail', 'meet' => 'users'] as $k => $ic): ?><button type="button" class="pd-kind pd-k-<?= $k ?>" data-kind="<?= $k ?>"><?= ui_icon($ic, 'w-5 h-5') ?><span><?= e($kinds[$k][1]) ?></span></button><?php endforeach; ?></div>
          <div id="pd-quick" class="flex flex-wrap gap-1.5 mb-2"><?php foreach ([t('مردّش'), t('مهتم 🔥'), t('طلب عرض سعر'), t('كلّمه تاني بعدين'), t('مش مهتم')] as $q): ?><button type="button" class="pd-qr" data-qr="<?= e($q) ?>"><?= e($q) ?></button><?php endforeach; ?></div><form id="pd-note-f" class="relative m-0" data-no-ajax><textarea name="b" rows="2" class="input !mt-0 text-sm !pe-36 resize-none" dir="auto" placeholder="<?= e(t('اكتب ملاحظة أو نتيجة التواصل…')) ?>"></textarea><span class="absolute bottom-2 end-2 flex items-center gap-1"><button type="button" id="pd-dict" class="pd-mic hidden" title="<?= e(t('إملاء بالصوت')) ?>"><?= ui_icon('type', 'w-4 h-4') ?></button><button type="button" id="pd-rec" class="pd-mic" title="<?= e(t('سجّل ملاحظة صوتية')) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/></svg></button><button class="btn-primary !py-1 !px-3 !text-xs"><?= e(t('حفظ')) ?></button></span><span id="pd-rec-st" class="pd-recst hidden"><i></i><b id="pd-rec-t">0:00</b><button type="button" id="pd-rec-stop"><?= e(t('وقّف واحفظ')) ?></button><button type="button" id="pd-rec-x">✕</button></span></form>
          <div class="flex items-center gap-2 mt-4 mb-2"><b class="text-sm text-ink-900"><?= e(t('سجل التواصل')) ?></b><span id="pd-acts-n" class="text-[11px] text-ink-400"></span><div id="pd-tf" class="ms-auto flex gap-1"></div></div>
          <ol id="pd-acts" class="pd-tl flex-1 overflow-y-auto max-h-[46vh]"></ol>
          </div>
          <?php foreach (['dl', 'tk', 'em', 'dt'] as $k): ?><div class="p-5 pd-pane hidden overflow-y-auto max-h-[62vh]" data-pane="<?= $k ?>" id="pd-p-<?= $k ?>"></div><?php endforeach; ?>
        </div>
      </div>
      <div class="flex items-center gap-2 px-5 py-3 border-t border-ink-100 bg-ink-50/60"><button type="button" id="pd-keys-b" class="text-[11px] text-ink-500 hidden sm:inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-ink-100"><kbd class="pd-kbd">?</kbd> <?= e(t('اختصارات الكيبورد')) ?></button><span class="ms-auto"></span>
        <a id="pd-co" href="#" class="btn-ghost !py-1.5 !text-sm hidden"><?= ui_icon('building', 'w-4 h-4') ?><?= e(t('الشركة')) ?></a><a id="pd-open" href="#" class="hidden"></a><?php if ($canManage): ?><button type="button" id="pd-del" class="pd-del"><?= ui_icon('trash', 'w-4 h-4') ?><span><?= e(t('حذف العميل')) ?></span></button><?php endif; ?></div>
      <div id="pd-wa" class="pd-pop hidden" role="menu"><b class="block text-xs text-ink-500 px-2 pb-1"><?= e(t('رسالة واتساب جاهزة')) ?></b><div id="pd-wa-l"></div>
        <textarea id="pd-wa-t" rows="3" class="input !mt-1 text-sm" dir="auto"></textarea><div class="flex gap-1.5 mt-1.5"><button type="button" id="pd-wa-go" class="btn-primary !py-1.5 !text-xs flex-1"><?= ui_icon('chat', 'w-4 h-4') ?><?= e(t('افتح واتساب')) ?></button><button type="button" id="pd-wa-x" class="btn-ghost !py-1.5 !text-xs"><?= e(t('إلغاء')) ?></button></div></div>
      <div id="pd-keys" class="pd-pop hidden !w-72"><b class="block text-sm text-ink-900 mb-2"><?= e(t('اختصارات الكيبورد')) ?></b><ul class="space-y-1.5 text-xs text-ink-700">
        <?php foreach ([['← →', t('العميل اللي بعده / قبله')], ['1 – 9', t('غيّر المرحلة')], ['N', t('اكتب ملاحظة')], ['C', t('اتصال')], ['W', t('واتساب')], ['E', t('إيميل')], ['R', t('تعديل الاسم والشركة')], ['T', t('ضيف تاج')], ['F', t('حدد المتابعة بكرة')], ['Ctrl + Enter', t('احفظ الملاحظة')], ['Esc', t('إغلاق')]] as $k): ?><li class="flex items-center gap-2"><kbd class="pd-kbd"><?= e($k[0]) ?></kbd><span><?= e($k[1]) ?></span></li><?php endforeach; ?></ul></div>
      <div id="pd-lost" class="pd-pop hidden !top-[132px] !bottom-auto"><b class="block text-sm text-ink-900"><?= e(t('ليه خسرناه؟')) ?></b><p class="text-[11px] text-ink-500 mb-2"><?= e(t('السبب بيظهر في التقارير وبيساعدك تعرف بتخسر فين.')) ?></p><div class="flex flex-wrap gap-1.5"><?php foreach (crm_lost_reasons() as $k => $v): ?><button type="button" class="pd-qr" data-lr="<?= e($k) ?>"><?= e($v) ?></button><?php endforeach; ?></div><button type="button" class="link text-xs mt-2" data-lr-x><?= e(t('إلغاء')) ?></button></div>
      <div id="pd-fx" class="pointer-events-none absolute inset-0 overflow-hidden"></div>
    </dialog>

    <script>window.LP = <?= json_encode(['csrf' => csrf_token(), 'importUrl' => url('crm_leads'), 'selfUrl' => url('prospects'), 'leadUrl' => url('crm_leads'), 'stages' => $stages, 'members' => $members, 'L' => lead_popup_L(), 'X' => lead_popup_X()], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
    <script src="assets/lead-tools.js?v=<?= e(APP_VERSION) ?>" defer></script>
<?php return ob_get_clean();
}
