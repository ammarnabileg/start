<?php
/**
 * الـ <head> المشترك: الخط (Alexandria)، الألوان، والمكونات.
 * الألوان متعرّفة كمتغيرات CSS، فالوضع الفاتح والداكن بيستخدموا نفس الكلاسات.
 * الفاتح هو الأساسي. الداكن بيتفعل بكلاس dark على <html>.
 */
function page_head(string $title): void
{
    ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="<?= theme() ?>">
<meta name="csrf-token" content="<?= e(session_status() === PHP_SESSION_ACTIVE ? csrf_token() : '') ?>">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--ink-50:245 247 251;--ink-100:237 241 248;--ink-200:221 227 238;--ink-300:195 205 223;--ink-400:138 151 176;--ink-500:95 109 136;--ink-600:69 82 109;--ink-700:49 61 87;--ink-800:29 40 66;--ink-900:13 22 48;--brand-50:238 244 255;--brand-100:219 231 255;--brand-200:188 211 255;--brand-300:143 181 255;--brand-400:91 142 249;--brand-500:59 111 240;--brand-600:37 87 224;--brand-700:29 69 189;--brand-800:28 58 150;--brand-900:27 52 119;--cyan-50:236 254 255;--cyan-100:207 250 254;--cyan-200:165 243 252;--cyan-300:103 232 249;--cyan-400:34 211 238;--cyan-500:6 182 212;--cyan-600:8 145 178;--cyan-700:14 116 144;--cyan-800:21 94 117;--cyan-900:22 78 99;--lime-50:242 253 228;--lime-100:226 251 196;--lime-200:198 244 143;--lime-300:163 232 79;--lime-400:132 216 31;--lime-500:101 184 15;--lime-600:76 146 10;--lime-700:60 112 12;--lime-800:51 90 16;--lime-900:44 76 18;--red-50:254 242 242;--red-100:254 226 226;--red-200:254 202 202;--red-300:252 165 165;--red-400:248 113 113;--red-500:239 68 68;--red-600:220 38 38;--red-700:185 28 28;--red-800:153 27 27;--red-900:127 29 29;--amber-50:255 251 235;--amber-100:254 243 199;--amber-200:253 230 138;--amber-300:252 211 77;--amber-400:251 191 36;--amber-500:245 158 11;--amber-600:217 119 6;--amber-700:180 83 9;--amber-800:146 64 14;--amber-900:120 53 15;--surface:255 255 255;}
html.dark{--ink-50:10 16 32;--ink-100:16 26 51;--ink-200:28 40 70;--ink-300:43 58 94;--ink-400:93 109 144;--ink-500:133 148 178;--ink-600:167 179 204;--ink-700:198 207 225;--ink-800:223 229 241;--ink-900:242 245 251;--brand-50:18 34 74;--brand-100:23 43 92;--brand-200:33 57 122;--brand-300:47 79 163;--brand-400:75 116 224;--brand-500:107 144 245;--brand-600:59 111 240;--brand-700:157 184 255;--brand-800:188 208 255;--brand-900:219 230 255;--cyan-50:8 47 58;--cyan-100:11 60 74;--cyan-200:15 84 102;--cyan-300:18 122 146;--cyan-400:15 165 196;--cyan-500:34 211 238;--cyan-600:34 211 238;--cyan-700:103 232 249;--cyan-800:165 243 252;--cyan-900:207 250 254;--lime-50:22 38 11;--lime-100:29 51 13;--lime-200:44 77 19;--lime-300:63 110 23;--lime-400:101 184 15;--lime-500:132 216 31;--lime-600:163 232 79;--lime-700:190 242 100;--lime-800:217 249 157;--lime-900:236 252 203;--red-50:42 18 22;--red-100:56 22 27;--red-200:90 31 39;--red-300:127 41 51;--red-400:194 65 76;--red-500:239 91 102;--red-600:248 113 113;--red-700:252 165 165;--red-800:254 202 202;--red-900:254 226 226;--amber-50:42 33 16;--amber-100:56 43 18;--amber-200:90 68 22;--amber-300:127 95 27;--amber-400:194 142 34;--amber-500:245 181 50;--amber-600:251 191 36;--amber-700:252 211 77;--amber-800:253 230 138;--amber-900:254 243 199;--surface:16 26 51;}
</style>
<link rel="stylesheet" href="assets/app.css?v=<?= e(APP_VERSION) ?>">
<script>window.T = <?= json_encode([
    'working' => t('جاري التنفيذ...'), 'comment' => t('تعليق'), 'copied' => t('اتنسخ ✓'),
    'schedule' => t('جدولة المنشور'), 'now' => t('انشر دلوقتي'), 'draft' => t('حفظ المسودة'), 'queue' => t('ضيف للطابور'), 'sending' => t('جاري الإرسال...'), 'notSent' => t('ما اتبعتتش · اتأكد من النت وجرب تاني'), 'liveFailed' => t('حصلت مشكلة في الاتصال. جرب تاني.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php if (session_status() === PHP_SESSION_ACTIVE && current_user() && current_workspace()): ?>
<script>window.MEDIA = <?= json_encode(['list' => url('media', ['ajax' => 'list']), 'post' => url('media'), 'page' => url('media'), 'csrf' => csrf_token(), 'canEdit' => can('posts.create'), 'max' => function_exists('media_max_bytes') ? media_max_bytes() : 2097152, 'lang' => lang(), 's' => [
  'title' => t('مكتبة الوسائط'), 'pick' => t('اختار من المكتبة'), 'upload' => t('ارفع ملفات'), 'drop' => t('اسحب الملفات هنا أو اضغط للرفع'), 'search' => t('ابحث بالاسم أو الوسم...'), 'all' => t('الكل'),
  'images' => t('صور'), 'videos' => t('فيديو'), 'docs' => t('ملفات'), 'audio' => t('صوت'), 'allFolders' => t('كل الملفات'), 'noFolder' => t('من غير فولدر'), 'newFolder' => t('فولدر جديد'), 'folderQ' => t('اسم الفولدر:'),
  'empty' => t('مفيش ملفات هنا لسه. ارفع أول ملف.'), 'uploading' => t('بيرفع %s...'), 'uploaded' => t('اترفع %s ملف'), 'select' => t('اختار'), 'selected' => t('%s مختار'), 'useSel' => t('استخدم المختار'),
  'rename' => t('غيّر الاسم'), 'renameQ' => t('الاسم الجديد:'), 'move' => t('انقل لفولدر'), 'moveQ' => t('اسم الفولدر (فاضي = من غير فولدر):'), 'del' => t('امسح'), 'delQ' => t('تمسح %s ملف نهائيًا؟ لو مستخدمين في منشورات أو إعلانات اتنشرت مش هيتأثروا.'),
  'copyLink' => t('انسخ الرابط العام'), 'copied' => t('اتنسخ'), 'download' => t('تحميل'), 'tags' => t('وسوم (افصل بفاصلة)'), 'details' => t('التفاصيل'), 'size' => t('الحجم'), 'dims' => t('المقاس'), 'type' => t('النوع'), 'date' => t('اترفع'),
  'preparing' => t('بيجهز رابط عام للمنصات...'), 'publicErr' => t('ما قدرناش نجهز رابط عام'), 'maxSize' => t('أقصى حجم للملف على السيرفر: %s'), 'usage' => t('المستخدم: %s'), 'notAllowed' => t('النوع ده مش مناسب هنا'), 'close' => t('اقفل'),
  'sortNew' => t('الأحدث'), 'sortName' => t('الاسم'), 'sortSize' => t('الحجم'), 'tooBig' => t('%s أكبر من المسموح (%s)'), 'open' => t('افتح المكتبة كاملة'),
  'small' => t('صغيرة'), 'smallHint' => t('أقل من 600 بكسل: هتبان مش واضحة في الإعلانات. الأنسب 1080 أو أكتر.'), 'goodFor' => t('مقاسها %s ومناسبة للإعلانات'), 'view' => t('عرض'), 'crop' => t('قص لمقاس إعلان'),
  'dups' => t('%s ملف كان موجود قبل كده، ما اتكررش'), 'moved' => t('اتنقلوا'), 'zoom' => t('تكبير'), 'saveCopy' => t('احفظ نسخة'), 'saving' => t('بيحفظ...'), 'cropped' => t('اتحفظت نسخة مقصوصة جنب الأصل'),
  'cropHint' => t('اسحب الصورة لتحريكها. الأصل مش بيتغير، بتتحفظ نسخة جديدة بعرض لحد 1080.'), 'rFeed' => t('فيد'), 'rFeedTall' => t('فيد طولي'), 'rStory' => t('ستوري وريلز'), 'rLink' => t('رابط'), 'rWide' => t('عريض'),
  'back' => t('رجوع'), 'fwd' => t('تقدم'), 'up' => t('للفولدر الأعلى'), 'refresh' => t('تحديث'), 'vBig' => t('أيقونات كبيرة'), 'vIcons' => t('أيقونات'), 'vList' => t('تفاصيل'), 'pane' => t('لوحة المعاينة'),
  'quick' => t('وصول سريع'), 'recent' => t('الأحدث'), 'foldersT' => t('الفولدرات'), 'dropHere' => t('سيب الملفات هنا عشان ترفعها'), 'noRes' => t('مفيش نتايج'), 'name' => t('الاسم'), 'folderT' => t('فولدر'), 'itemsW' => t('ملف'),
  'tImage' => t('صورة'), 'tVideo' => t('فيديو'), 'tAudio' => t('ملف صوت'), 'tFile' => t('ملف'), 'where' => t('المكان'), 'paneHint' => t('اختار ملف عشان تشوف تفاصيله'), 'nItems' => t('%s عنصر'),
  'kbdHint' => t('F2 تسمية · Del للسلة · Ctrl+X/V قص ولزق · Space معاينة · اكتب حرف للانتقال'), 'fileName' => t('الملف:'), 'allTypes' => t('كل الأنواع'), 'cancel' => t('إلغاء'), 'choose' => t('اختيار'),
  'open' => t('فتح'), 'selAll' => t('تحديد الكل'), 'starred' => t('المفضلة'), 'trash' => t('سلة المحذوفات'), 'undo' => t('تراجع'), 'trashed' => t('%s ملف اتنقل للسلة'), 'restored' => t('اترجعوا'), 'restore' => t('استرجاع'),
  'purge' => t('حذف نهائي'), 'purgeQ' => t('تمسح %s ملف نهائيًا؟ مش هتقدر ترجعهم.'), 'emptyTrash' => t('إفراغ السلة'), 'zipN' => t('تحميل %s ملف (ZIP)'), 'star' => t('إضافة للمفضلة'), 'unstar' => t('شيل من المفضلة'),
  'cut' => t('قص'), 'cutN' => t('%s ملف مقصوص — روح للفولدر واعمل لزق'), 'pasteN' => t('لزق %s ملف هنا'), 'moveN' => t('نقل %s ملف إلى...'), 'moveHere' => t('انقل هنا'), 'newFolderName' => t('فولدر جديد'),
  'dupShort' => t('موجود قبل كده'), 'canceled' => t('اتلغى'), 'zoomT' => t('حجم الأيقونات (Ctrl + عجلة الماوس)'), 'delMixed' => t('تمسح %s ملف و%s فولدر؟ ملفات الفولدرات هتطلع للفولدر الأعلى ومش هتتمسح.')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
window.mediaLoad = function (cb) { if (window.Media) return cb(); (window.__mq = window.__mq || []).push(cb); if (window.__ml) return; window.__ml = 1; var s = document.createElement('script'); s.src = 'assets/media.js?v=<?= e(APP_VERSION) ?>'; s.onload = function () { (window.__mq || []).forEach(function (f) { f(); }); }; document.head.appendChild(s); };
window.mediaPick = function (o) { return new Promise(function (res) { window.mediaLoad(function () { window.Media.pick(o || {}).then(res); }); }); };
(function () { // أي input فيه data-media="image|video|any" بياخد زرار "من المكتبة"
  var scan = function () { [].forEach.call(document.querySelectorAll('input[data-media]:not([data-media-on])'), function (inp) {
    inp.setAttribute('data-media-on', '1'); var b = document.createElement('button'); b.type = 'button'; b.className = 'media-btn mt-1.5 inline-flex items-center gap-1 rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:bg-ink-50'; b.textContent = '📁 ' + window.MEDIA.s.pick;
    b.onclick = function () { if (inp.disabled) return; window.mediaPick({ accept: inp.getAttribute('data-media') || 'image', public: true }).then(function (f) { if (!f || !f[0]) return; inp.value = f[0].public || f[0].url; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); }); };
    inp.insertAdjacentElement('afterend', b); }); };
  var scanFiles = function () { [].forEach.call(document.querySelectorAll('input[type=file][data-lib]:not([data-lib-on])'), function (inp) { // خانة رفع ملف: + اختيار من المكتبة
    inp.setAttribute('data-lib-on', '1'); var form = inp.form, req = inp.required, inline = inp.hasAttribute('data-lib-inline');
    var b = document.createElement('button'); b.type = 'button'; b.title = window.MEDIA.s.pick; b.setAttribute('aria-label', window.MEDIA.s.pick);
    b.className = inline ? 'media-btn inline-grid place-items-center w-9 h-9 rounded-xl text-ink-500 hover:bg-ink-100' : 'media-btn mt-1.5 inline-flex items-center gap-1 rounded-lg border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:bg-ink-50';
    b.textContent = inline ? '📁' : '📁 ' + window.MEDIA.s.pick;
    var chip = document.createElement('span'); chip.className = 'media-chip hidden ms-2 inline-flex items-center gap-2 rounded-lg bg-brand-50 text-brand-800 text-xs px-2 py-1 align-middle';
    b.onclick = function () { window.mediaPick({ accept: inp.getAttribute('data-lib') || 'image', public: false }).then(function (f) {
      if (!f || !f[0]) return; f = f[0]; var h = form && form.querySelector('input[name="lib_' + inp.name + '"]');
      if (!h && form) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'lib_' + inp.name; form.appendChild(h); } if (h) h.value = f.id;
      inp.value = ''; inp.required = false;
      chip.innerHTML = (f.kind === 'image' ? '<img alt="" class="w-7 h-7 rounded object-cover">' : '') + '<span class="max-w-[12rem] truncate"></span><button type="button" class="opacity-70 hover:opacity-100" aria-label="×">×</button>';
      if (chip.querySelector('img')) chip.querySelector('img').src = f.thumb || f.url; chip.querySelector('span').textContent = f.name; chip.classList.remove('hidden');
      chip.querySelector('button').onclick = function () { if (h) h.remove(); inp.required = req; chip.classList.add('hidden'); };
      if (inline && inp.parentNode) inp.parentNode.classList.add('bg-brand-100');
    }); };
    inp.addEventListener('change', function () { var h = form && form.querySelector('input[name="lib_' + inp.name + '"]'); if (inp.files.length && h) { h.remove(); chip.classList.add('hidden'); inp.required = req; } });
    var anchor = inline ? inp.parentNode : inp; anchor.insertAdjacentElement('afterend', b); b.insertAdjacentElement('afterend', chip); }); };
  document.addEventListener('DOMContentLoaded', function () { scan(); scanFiles(); var t; new MutationObserver(function () { clearTimeout(t); t = setTimeout(function () { scan(); scanFiles(); }, 120); }).observe(document.body, { childList: true, subtree: true }); });
})();
</script>
<?php endif; ?>
<script>window.PHONE = <?= json_encode(['cc' => function_exists('ws_country') && session_status() === PHP_SESSION_ACTIVE && current_workspace() ? ws_country() : 'EG', 'example' => function_exists('cx_phone') && session_status() === PHP_SESSION_ACTIVE && current_workspace() ? cx_phone() : '',
    'names' => array_map(function ($c) { return lang() === 'ar' ? $c['ar'] : $c['en']; }, countries()), 'flags' => array_map(function ($c) { return $c['flag']; }, countries()),
    't' => ['bad' => t('ده مش شكل رقم تليفون'), 'invalidFor' => t('الرقم مش صحيح لـ %s، اتأكد من عدد الأرقام'), 'mobile' => t('موبايل'), 'fixed' => t('أرضي'), 'either' => t('موبايل أو أرضي'), 'tollFree' => t('رقم مجاني'),
      'notMobile' => t('%s رقم أرضي، والخدمة دي محتاجة رقم موبايل'), 'fix' => t('فيه رقم تليفون غلط، صلّحه الأول')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
document.addEventListener('DOMContentLoaded', function () {
  var go = function () { if (window.__phoneLoad || !document.querySelector('input[data-phone]')) return false; window.__phoneLoad = 1;
    var a = document.createElement('script'); a.src = 'assets/vendor/libphonenumber-max.js?v=1.11.12';
    a.onload = function () { var b = document.createElement('script'); b.src = 'assets/phone.js?v=<?= e(APP_VERSION) ?>'; document.head.appendChild(b); }; document.head.appendChild(a); return true; };
  if (!go()) { var mo = new MutationObserver(function () { if (go()) mo.disconnect(); }); mo.observe(document.body, { childList: true, subtree: true }); }
});
</script>
<?php if (function_exists('ai_presets') && session_status() === PHP_SESSION_ACTIVE && current_user() && current_workspace() && can('ai.use') && ai_connection()): ?>
<script>window.AI_PRESETS = <?= json_encode(['items' => array_map(function ($p) { return $p['label']; }, array_filter(ai_presets(), function ($k) { return ai_feature_on($k); }, ARRAY_FILTER_USE_KEY)), 'modes' => array_map(function ($p) { return $p['mode']; }, ai_presets()), 'title' => t('أوامر الذكاء الاصطناعي'), 'use' => t('استخدم هذا النص'), 'copy' => t('نسخ'), 'insights' => ai_insights_js(), 'run' => t('تشغيل'), 'csvBtn' => t('تحميل CSV للنشر الجماعي'), 'rowsTxt' => t('منشور'), 'thinking' => t('جاري التحليل من بياناتك...'), 'aiTitle' => t('تحليلات الذكاء الاصطناعي')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>

    <?php
}

/** وسم <html> باللغة والاتجاه والوضع */
function html_open(): string
{
    return '<html lang="' . lang() . '" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '" class="' . (theme() === 'dark' ? 'dark' : '') . '">';
}

/** زرارين اللغة والوضع: تبديل الوضع فوري من غير تحميل، واللغة بمؤشر بيتحرك */
function pref_switch(): string
{
    $l = lang(); $dark = theme() === 'dark';
    $sun = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';
    $moon = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z"/></svg>';
    $lang = '<div class="lang-seg tb relative p-[3px] text-xs font-semibold" role="radiogroup" aria-label="Language">'
        . '<span class="lang-pill absolute top-[3px] bottom-[3px] w-[38px] rounded-lg bg-brand-600 shadow-sm shadow-brand-600/30" data-on="' . $l . '"></span>'
        . '<a href="' . e(url('pref', ['lang' => 'en'])) . '" data-lang="en" role="radio" aria-checked="' . ($l === 'en' ? 'true' : 'false') . '" class="relative z-10 w-[38px] h-8 flex items-center justify-center rounded-lg transition-colors ' . ($l === 'en' ? 'text-[#fff]' : 'text-ink-500 hover:text-ink-900') . '">EN</a>'
        . '<a href="' . e(url('pref', ['lang' => 'ar'])) . '" data-lang="ar" role="radio" aria-checked="' . ($l === 'ar' ? 'true' : 'false') . '" class="relative z-10 w-[38px] h-8 flex items-center justify-center rounded-lg transition-colors text-sm ' . ($l === 'ar' ? 'text-[#fff]' : 'text-ink-500 hover:text-ink-900') . '">ع</a>'
        . '</div>';
    $theme = '<button type="button" data-theme-toggle role="switch" aria-checked="' . ($dark ? 'true' : 'false') . '" aria-label="Dark mode" title="' . e(t('الوضع الداكن')) . '"'
        . ' data-url-light="' . e(url('pref', ['theme' => 'light'])) . '" data-url-dark="' . e(url('pref', ['theme' => 'dark'])) . '"'
        . ' class="tt tb relative w-[68px] focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-500/20">'
        . '<span class="absolute inset-y-0 start-[11px] flex items-center text-ink-400">' . $sun . '</span>'
        . '<span class="absolute inset-y-0 end-[11px] flex items-center text-ink-400">' . $moon . '</span>'
        . '<span class="tt-knob absolute top-[3px] start-[3px] w-8 h-8 rounded-lg bg-brand-600 text-[#fff] shadow-sm shadow-brand-600/30 flex items-center justify-center">'
        . '<span class="tt-sun absolute">' . $sun . '</span><span class="tt-moon absolute">' . $moon . '</span></span>'
        . '</button>';
    return $lang . $theme;
}
