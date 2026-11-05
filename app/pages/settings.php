<?php
/** إعدادات الشركة المفتوحة حالياً (لصاحب الشركة). المفاتيح في صفحة Connectors. */
$w = current_workspace();
$zones = array_values(array_unique(array_merge(array_column(countries(), 'tz'), ['UTC'])));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['ws_name'] ?? ''));
    $tz = (string)($_POST['timezone'] ?? '');
    $cc = strtoupper((string)($_POST['country'] ?? ''));
    if (isset(countries()[$cc])) save_setting('country_' . $w['id'], $cc);
    $on = (array)($_POST['modules'] ?? []);
    $off = array_values(array_diff(array_keys(module_catalog()), $on));
    db()->prepare("UPDATE workspaces SET name = ?, timezone = ?, disabled_modules = ? WHERE id = ?")->execute([
        $name !== '' ? mb_substr($name, 0, 120) : $w['name'],
        in_array($tz, timezone_identifiers_list(), true) ? $tz : $w['timezone'],
        json_encode($off),
        $w['id'],
    ]);
    log_activity('settings.update');
    flash('success', t('اتحفظ.'));
    redirect(url('settings'));
}

render(t('الإعدادات العامة'), 'settings', function () use ($w, $zones) {
    $cur = site_timezone();
    if (!in_array($cur, $zones, true)) $zones[] = $cur;
    ?>
    <form method="post" id="st-form" class="max-w-3xl space-y-6 pb-24">
      <?= csrf_field() ?>
      <section class="card p-6">
        <h2 class="font-bold text-ink-900 mb-1"><?= e(t('هوية الشركة')) ?></h2>
        <p class="text-xs text-ink-500 mb-4"><?= e(t('الاسم بيظهر للفريق في القائمة وفي التقارير.')) ?></p>
        <div class="flex items-center gap-4">
          <span id="st-av" class="w-14 h-14 rounded-2xl bg-gradient-to-br from-brand-500 to-cyan-500 text-white text-xl font-bold flex items-center justify-center shrink-0"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?></span>
          <label class="block flex-1"><span class="label"><?= e(t('اسم الشركة')) ?></span>
            <input name="ws_name" value="<?= e($w['name']) ?>" required maxlength="120" class="input"></label>
        </div>
      </section>
      <section class="card p-6">
        <h2 class="font-bold text-ink-900 mb-4"><?= e(t('الدولة والتوقيت')) ?></h2>
      <?php $cur_c = ws_country(); $cs = countries(); ?>
      <div id="cty" class="space-y-3">
        <div class="flex items-end justify-between gap-3">
          <div><span class="label"><?= e(t('دولة الشركة')) ?></span><p class="text-xs text-ink-500 mt-0.5"><?= e(t('كل الأمثلة في المنصة (العملة، الأرقام، المدن، طرق الدفع، الإجازة، اللهجة) والذكاء الاصطناعي بيتبنوا على الدولة دي.')) ?></p></div>
          <?php if (!ws_country_set()): ?><span class="shrink-0 rounded-full bg-amber-100 text-amber-800 text-[11px] px-2 py-0.5"><?= e(t('لم تُحدد بعد')) ?></span><?php endif; ?>
        </div>
        <input type="hidden" name="country" value="<?= e(ws_country_set() ? $cur_c : '') ?>">
        <input type="search" id="cty-q" class="input !mt-0 text-sm" placeholder="<?= e(t('ابحث عن دولة...')) ?>" autocomplete="off">
        <div id="cty-list" class="max-h-72 overflow-y-auto rounded-2xl border border-ink-200 p-2 space-y-3">
          <?php foreach (country_regions() as $rk => $rl): ?>
            <div class="cty-grp"><div class="px-1 pb-1.5 text-[11px] font-semibold text-ink-400"><?= e($rl) ?></div>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">
                <?php foreach ($cs as $code => $c): if ($c['region'] !== $rk) continue; ?>
                  <button type="button" data-cc="<?= $code ?>" data-q="<?= e(mb_strtolower($c['ar'] . ' ' . $c['en'] . ' ' . $code)) ?>" class="cty flex items-center gap-2 rounded-xl border px-2.5 py-2 text-sm text-start transition <?= $code === $cur_c ? 'border-brand-500 bg-brand-50 text-brand-800 font-medium ring-2 ring-brand-500/20' : 'border-ink-200 hover:border-brand-300 hover:bg-ink-50' ?>">
                    <span class="text-lg leading-none"><?= $c['flag'] ?></span><span class="flex-1 truncate"><?= e(lang() === 'ar' ? $c['ar'] : $c['en']) ?></span><span class="text-[10px] text-ink-400" dir="ltr"><?= e($c['cur']) ?></span>
                  </button>
                <?php endforeach; ?>
              </div></div>
          <?php endforeach; ?>
        </div>
        <div id="cty-prev" class="rounded-2xl bg-gradient-to-br from-brand-50 to-cyan-50 border border-brand-100 p-4 text-sm"></div>
      </div>
      <script>
      (function () {
        var C = <?= json_encode(array_map(function ($c) { return ['name' => lang() === 'ar' ? $c['ar'] : $c['en'], 'flag' => $c['flag'], 'tz' => $c['tz'], 'cur' => $c['cur'] . ' · ' . (lang() === 'ar' ? $c['cur_ar'] : $c['cur_en']), 'phone' => $c['phone'], 'city' => (lang() === 'ar' ? $c['city_ar'] . '، ' . $c['area_ar'] : $c['city_en'] . ', ' . $c['area_en']), 'pay' => lang() === 'ar' ? $c['pay_ar'] : $c['pay_en'], 'wk' => $c['weekend'], 'dia' => lang() === 'ar' ? $c['dialect_ar'] : $c['dialect_en']]; }, $cs), JSON_UNESCAPED_UNICODE) ?>;
        var L = <?= json_encode(['cur' => t('العملة'), 'phone' => t('صيغة الأرقام'), 'city' => t('مثال للمدينة'), 'pay' => t('طرق الدفع الشائعة'), 'wk' => t('الإجازة الأسبوعية'), 'dia' => t('اللهجة المحلية'), 'tz' => t('المنطقة الزمنية'), 'title' => t('كده هتبان الأمثلة'), 'days' => ['sat' => t('السبت'), 'sun' => t('الأحد'), 'mon' => t('الاثنين'), 'tue' => t('الثلاثاء'), 'wed' => t('الأربعاء'), 'thu' => t('الخميس'), 'fri' => t('الجمعة')]], JSON_UNESCAPED_UNICODE) ?>;
        var box = document.getElementById('cty'), hid = box.querySelector('[name=country]'), prev = document.getElementById('cty-prev');
        var esc = function (t) { return String(t).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
        function show(cc) {
          var c = C[cc]; if (!c) return;
          var row = function (k, v, ltr) { return v ? '<div class="flex gap-3 py-1"><span class="w-32 shrink-0 text-ink-500 text-xs pt-0.5">' + L[k] + '</span><span class="text-ink-900"' + (ltr ? ' dir="ltr"' : '') + '>' + esc(v) + '</span></div>' : ''; };
          prev.innerHTML = '<div class="flex items-center gap-2 mb-2"><span class="text-2xl">' + c.flag + '</span><b class="text-ink-900">' + esc(c.name) + '</b><span class="text-xs text-ink-500">· ' + L.title + '</span></div>'
            + row('cur', c.cur) + row('phone', c.phone, true) + row('city', c.city) + row('pay', c.pay) + row('wk', c.wk.map(function (d) { return L.days[d]; }).join(' + ')) + row('dia', c.dia) + row('tz', c.tz, true);
          prev.classList.remove('kb-in'); void prev.offsetWidth; prev.style.animation = 'none'; prev.offsetHeight; prev.style.animation = '';
        }
        box.addEventListener('click', function (e) {
          var b = e.target.closest('.cty'); if (!b) return; var cc = b.dataset.cc; hid.value = cc;
          box.querySelectorAll('.cty').forEach(function (x) { var on = x === b; x.className = x.className.replace(/border-brand-500 bg-brand-50 text-brand-800 font-medium ring-2 ring-brand-500\/20|border-ink-200 hover:border-brand-300 hover:bg-ink-50/, on ? 'border-brand-500 bg-brand-50 text-brand-800 font-medium ring-2 ring-brand-500/20' : 'border-ink-200 hover:border-brand-300 hover:bg-ink-50'); });
          var tz = document.querySelector('select[name=timezone]');
          if (tz && C[cc].tz) { if (![].some.call(tz.options, function (o) { return o.value === C[cc].tz; })) tz.add(new Option(C[cc].tz, C[cc].tz)); tz.value = C[cc].tz; tz.classList.add('ring-4', 'ring-brand-500/20'); setTimeout(function () { tz.classList.remove('ring-4', 'ring-brand-500/20'); }, 1200); }
          show(cc);
        });
        document.getElementById('cty-q').addEventListener('input', function () {
          var q = this.value.trim().toLowerCase();
          box.querySelectorAll('.cty').forEach(function (b) { b.classList.toggle('hidden', !!q && b.dataset.q.indexOf(q) < 0); });
          box.querySelectorAll('.cty-grp').forEach(function (g) { g.classList.toggle('hidden', !g.querySelector('.cty:not(.hidden)')); });
        });
        show(hid.value || <?= json_encode($cur_c) ?>);
      })();
      </script>
      <div class="grid sm:grid-cols-[minmax(0,1fr)_auto] gap-4 items-end mt-5 pt-5 border-t border-ink-100">
        <label class="block"><span class="label"><?= e(t('المنطقة الزمنية')) ?></span>
          <select name="timezone" class="input" dir="ltr">
            <?php foreach ($zones as $z): ?><option <?= $z === $cur ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?>
          </select>
          <span class="text-xs text-ink-400"><?= e(t('مواعيد الجدولة والتواريخ بتظهر بالتوقيت ده.')) ?></span></label>
        <div class="rounded-2xl bg-ink-900 text-white px-5 py-3 text-center min-w-[10rem]">
          <div class="text-[11px] opacity-70"><?= e(t('الساعة دلوقتي عندكم')) ?></div>
          <div id="st-clock" class="text-2xl font-bold tabular-nums" dir="ltr">--:--</div>
          <div id="st-date" class="text-[11px] opacity-70"></div>
        </div>
      </div>
      </section>
      <section class="card p-6">
        <div class="flex flex-wrap items-center gap-2 mb-1"><h2 class="font-bold text-ink-900 me-auto"><?= e(t('الأقسام')) ?></h2>
          <span id="st-mn" class="text-xs text-ink-500"></span>
          <button type="button" id="st-all" class="text-xs link"><?= e(t('شغّل الكل')) ?></button></div>
        <p class="text-xs text-ink-500 mb-4"><?= e(t('اقفل الأقسام اللي الشركة دي مش بتستخدمها، عشان القائمة تبقى أبسط للفريق. مفيش بيانات بتتمسح.')) ?></p>
        <div class="grid sm:grid-cols-2 gap-2">
          <?php foreach (module_catalog() as $mk => $ml): ?>
            <label class="st-mod flex items-center gap-3 rounded-xl border border-ink-200 px-3 py-3 cursor-pointer hover:bg-ink-50 has-[:checked]:border-brand-200 has-[:checked]:bg-brand-50/40 transition">
              <input type="checkbox" name="modules[]" value="<?= e($mk) ?>" <?= module_enabled($mk) ? 'checked' : '' ?> class="peer sr-only">
              <span class="relative w-10 h-6 shrink-0 rounded-full bg-ink-200 peer-checked:bg-brand-600 transition-colors after:content-[''] after:absolute after:top-1 after:start-1 after:w-4 after:h-4 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:start-5"></span>
              <span class="text-sm font-medium text-ink-800 flex-1"><?= e($ml) ?></span>
              <span class="text-[11px] text-ink-400 peer-checked:hidden"><?= e(t('مقفول')) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
      <div id="st-bar" class="fixed bottom-5 inset-x-0 z-50 flex justify-center pointer-events-none transition-all" style="opacity:0;transform:translateY(1rem)">
        <div class="pointer-events-auto flex items-center gap-3 rounded-full bg-ink-900 text-white shadow-2xl ps-5 pe-2 py-2 text-sm">
          <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span><span><?= e(t('فيه تعديلات مش محفوظة')) ?></span>
          <button type="button" id="st-undo" class="rounded-full bg-white/10 hover:bg-white/20 px-3 py-1"><?= e(t('تراجع')) ?></button>
          <button class="rounded-full bg-brand-500 hover:bg-brand-400 px-4 py-1 font-medium"><?= e(t('حفظ')) ?></button>
        </div>
      </div>
      <noscript><button class="btn-primary"><?= e(t('حفظ')) ?></button></noscript>
    </form>
    <script>
    (function () {
      var f = document.getElementById('st-form'), bar = document.getElementById('st-bar'), init = new FormData(f), sending = false;
      var ser = function (fd) { var a = []; fd.forEach(function (v, k) { if (k !== '_csrf') a.push(k + '=' + v); }); return a.sort().join('&'); };
      var base = ser(init), mn = document.getElementById('st-mn'), mods = f.querySelectorAll('input[name="modules[]"]');
      var LBL = <?= json_encode(t('%s من %s شغالين'), JSON_UNESCAPED_UNICODE) ?>, LEAVE = <?= json_encode(t('فيه تعديلات مش محفوظة. تخرج؟'), JSON_UNESCAPED_UNICODE) ?>;
      function dirty() { return ser(new FormData(f)) !== base; }
      function paint() {
        var d = dirty(); bar.style.opacity = d ? '1' : '0'; bar.style.transform = d ? 'none' : 'translateY(1rem)'; bar.style.pointerEvents = d ? '' : 'none';
        var on = [].filter.call(mods, function (i) { return i.checked; }).length; mn.textContent = LBL.replace('%s', on).replace('%s', mods.length);
        var n = f.querySelector('[name=ws_name]').value.trim(); document.getElementById('st-av').textContent = (n[0] || '?').toUpperCase();
      }
      f.addEventListener('input', paint); f.addEventListener('change', paint); f.addEventListener('click', function () { setTimeout(function () { paint(); tick(); }, 0); });
      document.getElementById('st-all').onclick = function () { mods.forEach(function (i) { i.checked = true; }); paint(); };
      document.getElementById('st-undo').onclick = function () { location.reload(); };
      f.addEventListener('submit', function () { sending = true; });
      window.addEventListener('beforeunload', function (e) { if (!sending && dirty()) { e.preventDefault(); e.returnValue = LEAVE; return LEAVE; } });
      // ساعة حية بالمنطقة الزمنية المختارة
      var tz = f.querySelector('select[name=timezone]'), ck = document.getElementById('st-clock'), dt = document.getElementById('st-date');
      function tick() {
        try {
          var now = new Date(), o = { timeZone: tz.value };
          ck.textContent = now.toLocaleTimeString('<?= lang() === 'ar' ? 'ar-EG' : 'en-GB' ?>', Object.assign({ hour: '2-digit', minute: '2-digit' }, o));
          dt.textContent = now.toLocaleDateString('<?= lang() === 'ar' ? 'ar-EG' : 'en-GB' ?>', Object.assign({ weekday: 'long', day: 'numeric', month: 'long' }, o));
        } catch (e) { ck.textContent = '--:--'; dt.textContent = ''; }
      }
      tz.addEventListener('change', tick); setInterval(tick, 15000); tick(); paint();
    })();
    </script>
    <?php
});
