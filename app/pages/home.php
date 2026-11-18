<?php
/* الصفحة الرئيسية العامة (لاندنج): عربي/إنجليزي، فاتح/داكن */
if (current_user()) redirect(url('dashboard'));
$siteName = setting('site_name', t('لوحة النشر'));
$canReg = setting('allow_signup', '1') === '1';
$ic = function (string $d, string $c = 'w-6 h-6') { return '<svg class="' . $c . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>'; };
$features = [
    ['M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z', t('منافسات اعتماد المطابقة لك'), t('نقرأ المنافسات الجديدة يوميًا ونطابقها مع خدمات شركتك، ونرسل لك ما يناسبك فقط قبل آخر موعد للتقديم.')],
    ['M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0zM7 6H4a3 3 0 003 4M17 6h3a3 3 0 01-3 4', t('من كسب وبكم'), t('ترسيات اعتماد: الفائز وقيمة العقد وكل المتقدمين وعروضهم، ثم بيانات التواصل مع الفائز لتعرض عليه خدماتك.')],
    ['M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0z', t('عملاء وصفقات في مكان واحد'), t('إدارة العملاء والمتابعات والصفقات وعروض الأسعار، مع صنّاع القرار وبياناتهم من Apollo.')],
    ['M15 12a3 3 0 11-6 0 3 3 0 016 0zM2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z', t('عين على المنافسين'), t('أسعارهم وإعلاناتهم ومواقعهم وتقييماتهم وحساباتهم، مع تنبيه عند أي تغيير مهم.')],
    ['M12 3l2.1 5.4L19.5 10l-5.4 2.1L12 17.5l-2.1-5.4L4.5 10l5.4-1.6z', t('محتوى ونشر بالذكاء الاصطناعي'), t('اكتب ونسّق وجدول على كل المنصات من شاشة واحدة، بلغة شركتك ومن ملفها، دون مبالغات.')],
    ['M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z', t('الرسائل والمكالمات'), t('صندوق واحد لرسائل وتعليقات كل الحسابات وواتساب، ومكالمات من رقم الشركة تُسجّل في ملف العميل.')],
];
$steps = [[t('عرّفنا بشركتك'), t('ارفع ملفاتك أو أعطنا رابط موقعك؛ نقرأه كله ونبني ملف الشركة دون ذكاء اصطناعي.')], [t('اختر مجالك'), t('كلمات وأنشطة تطابق خدماتك، وتُقترح لك تلقائيًا من الملف.')],
    [t('استقبل الفرص'), t('منافسات جديدة وترسيات وعملاء محتملون، كلٌّ في وقته مع تنبيه.')], [t('حوّلها إلى صفقات'), t('بيانات التواصل، ومطابقة خدماتك بالمطلوب، وعرض سعر جاهز بضغطة.')]];
$faq = [[t('من أين تأتي بيانات المنافسات؟'), t('من منصة اعتماد الحكومية عبر أدوات بيانات متخصصة، وتُحدّث يوميًا.')],
    [t('هل أحتاج حسابًا في اعتماد؟'), t('لمتابعة المنافسات والترسيات لا. التقديم نفسه وشراء كراسة الشروط يتمان من حسابك في اعتماد.')],
    [t('هل تستخدمون الذكاء الاصطناعي في كل شيء؟'), t('لا. قراءة موقعك وملفاتك ومطابقة المنافسات تتم دون ذكاء اصطناعي. نستخدمه فقط عند الكتابة والتحليل، وبحد استهلاك تضعه أنت.')],
    [t('هل تدعم العربية والإنجليزية؟'), t('نعم، الواجهة كاملة بالعربية الفصحى والإنجليزية، مع وضع فاتح وداكن.')],
    [t('هل بياناتي آمنة؟'), t('كل شركة لها مساحة منفصلة، والصلاحيات لكل موظف حسب دوره، والمفاتيح محفوظة مشفّرة.')]];
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($siteName . ' · ' . t('المنافسات والمبيعات والتسويق في منصة واحدة')); ?>
<meta name="description" content="<?= e(t('تابع منافسات اعتماد وترسياتها، واعرف من كسب وبكم، وحوّل الفرص إلى عملاء وعروض أسعار. مع إدارة العملاء والمحتوى والرسائل في منصة واحدة.')) ?>">
<style>
.lp-grid{background-image:radial-gradient(circle at 1px 1px,rgb(148 163 184/.18) 1px,transparent 0);background-size:22px 22px}
.lp-glow{background:radial-gradient(60% 60% at 70% 20%,rgb(37 99 235/.18),transparent 70%),radial-gradient(50% 50% at 20% 80%,rgb(6 182 212/.14),transparent 70%)}
.dark .lp-glow{background:radial-gradient(60% 60% at 70% 20%,rgb(37 99 235/.28),transparent 70%),radial-gradient(50% 50% at 20% 80%,rgb(6 182 212/.18),transparent 70%)}
.lp-in{animation:lpIn .7s ease-out both}@keyframes lpIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
details.lp-q summary::-webkit-details-marker{display:none}details.lp-q[open] .lp-chev{transform:rotate(180deg)}
</style>
</head>
<body class="bg-white text-ink-800 antialiased">
<header class="sticky top-0 z-30 backdrop-blur bg-white/80 border-b border-ink-100">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-4">
    <a href="<?= e(url('home')) ?>" class="flex items-center gap-2 font-bold text-ink-900"><span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-cyan-500 text-white grid place-items-center"><?= e(mb_substr($siteName, 0, 1)) ?></span><?= e($siteName) ?></a>
    <nav class="hidden md:flex items-center gap-5 text-sm text-ink-600 ms-6"><a href="#features" class="hover:text-brand-600"><?= e(t('المزايا')) ?></a><a href="#how" class="hover:text-brand-600"><?= e(t('كيف يعمل')) ?></a><a href="#faq" class="hover:text-brand-600"><?= e(t('الأسئلة الشائعة')) ?></a></nav>
    <div class="ms-auto flex items-center gap-2"><div class="hidden sm:flex gap-2"><?= pref_switch() ?></div>
      <a href="<?= e(url('login')) ?>" class="btn-ghost !py-2"><?= e(t('دخول')) ?></a>
      <?php if ($canReg): ?><a href="<?= e(url('register')) ?>" class="btn-primary !py-2 hidden sm:inline-flex"><?= e(t('ابدأ الآن')) ?></a><?php endif; ?></div>
  </div>
</header>

<section class="relative overflow-hidden">
  <div class="absolute inset-0 lp-grid opacity-60"></div><div class="absolute inset-0 lp-glow"></div>
  <div class="relative max-w-6xl mx-auto px-4 pt-14 pb-20 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <div class="lp-in min-w-0">
      <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 text-brand-700 text-xs font-semibold px-3 py-1"><span class="w-1.5 h-1.5 rounded-full bg-lime-500"></span><?= e(t('منافسات اعتماد · الترسيات · العملاء · المحتوى')) ?></span>
      <h1 class="mt-5 text-3xl sm:text-5xl break-words font-extrabold leading-tight text-ink-900"><?= e(t('اعرف المنافسة قبل منافسيك')) ?><br><span class="bg-gradient-to-l from-brand-600 to-cyan-500 bg-clip-text text-transparent"><?= e(t('وحوّلها إلى صفقة')) ?></span></h1>
      <p class="mt-5 text-lg text-ink-600 leading-relaxed max-w-xl"><?= e(t('نطابق منافسات اعتماد اليومية مع خدمات شركتك، ونريك من كسب المنافسات السابقة وبكم، ونجهّز لك بيانات التواصل وعرض السعر. ومعها إدارة العملاء والمحتوى والرسائل في منصة واحدة.')) ?></p>
      <div class="mt-8 flex flex-wrap gap-3"><a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="btn-primary !px-6 !py-3 text-base"><?= e($canReg ? t('ابدأ الآن') : t('دخول')) ?></a><a href="#how" class="btn-ghost !px-6 !py-3 text-base"><?= e(t('كيف يعمل؟')) ?></a></div>
      <p class="mt-4 text-xs text-ink-500"><?= e(t('عربي وإنجليزي · فاتح وداكن · بيانات كل شركة منفصلة')) ?></p>
    </div>
    <div class="lp-in min-w-0 [animation-delay:.15s]">
      <div class="rounded-3xl border border-ink-200 bg-white shadow-2xl shadow-brand-900/10 p-5">
        <div class="flex items-center gap-2 mb-4"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span><span class="w-2.5 h-2.5 rounded-full bg-lime-400"></span><b class="ms-3 text-sm text-ink-900"><?= e(t('نظرة عامة')) ?></b></div>
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-2xl bg-brand-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('نسبة المطابقة')) ?></div><div class="text-2xl font-extrabold text-brand-700">86%</div></div>
          <div class="rounded-2xl bg-lime-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('منافسات جديدة')) ?></div><div class="text-2xl font-extrabold text-lime-700">24</div></div>
          <div class="rounded-2xl bg-amber-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('تنتهي هذا الأسبوع')) ?></div><div class="text-2xl font-extrabold text-amber-700">5</div></div>
        </div>
        <div class="mt-4 space-y-2">
          <?php foreach ([[t('توريد وتركيب مطابخ تجارية'), t('وزارة الصحة'), '92%', 'new'], [t('صيانة وتشغيل أنظمة التكييف'), t('أمانة منطقة الرياض'), '81%', ''], [t('تجهيز قاعات تدريب ذكية'), t('جامعة الملك سعود'), '74%', '']] as $r): ?>
            <div class="flex items-center gap-3 rounded-2xl border border-ink-100 p-3"><span class="w-9 h-9 rounded-xl bg-ink-100 grid place-items-center text-ink-500"><?= $ic('M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z', 'w-4 h-4') ?></span>
              <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-ink-900 truncate"><?= e($r[0]) ?></span><span class="block text-[11px] text-ink-500"><?= e($r[1]) ?></span></span>
              <?php if ($r[3]): ?><span class="rounded-full bg-lime-100 text-lime-700 text-[10px] font-bold px-2 py-0.5"><?= e(t('جديد')) ?></span><?php endif; ?><b class="text-sm text-brand-700"><?= $r[2] ?></b></div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4 rounded-2xl bg-ink-900 text-white p-3 text-xs flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-amber-400 text-ink-900 grid place-items-center">!</span><?= e(t('ترسية جديدة في مجالك: 330,000 ريال · 3 متقدمين')) ?></div>
      </div>
    </div>
  </div>
</section>

<section class="border-y border-ink-100 bg-ink-50/60">
  <div class="max-w-6xl mx-auto px-4 py-6 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-ink-500"><span class="font-semibold text-ink-700"><?= e(t('يعمل مع')) ?></span>
    <?php foreach ([t('منصة اعتماد'), 'Apollo', 'Google Maps', t('واتساب'), 'Meta', 'TikTok', 'LinkedIn', 'Google Business'] as $s): ?><span><?= e($s) ?></span><?php endforeach; ?></div>
</section>

<section id="features" class="max-w-6xl mx-auto px-4 py-20 scroll-mt-20">
  <div class="text-center max-w-2xl mx-auto"><h2 class="text-3xl font-extrabold text-ink-900"><?= e(t('كل ما تحتاجه لتكسب عقودًا أكثر')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('من أول منافسة تظهر إلى عرض السعر والمتابعة، دون التنقل بين أدوات متفرقة.')) ?></p></div>
  <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php foreach ($features as $f): ?>
      <div class="group rounded-3xl border border-ink-200 bg-white p-6 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-900/5 transition">
        <span class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 grid place-items-center group-hover:scale-105 transition"><?= $ic($f[0]) ?></span>
        <h3 class="mt-4 font-bold text-lg text-ink-900"><?= e($f[1]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($f[2]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section id="how" class="bg-ink-50/60 border-y border-ink-100 scroll-mt-20">
  <div class="max-w-6xl mx-auto px-4 py-20">
    <div class="text-center max-w-2xl mx-auto"><h2 class="text-3xl font-extrabold text-ink-900"><?= e(t('كيف يعمل')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('أربع خطوات من التسجيل إلى أول عرض سعر.')) ?></p></div>
    <ol class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php foreach ($steps as $i => $s): ?>
        <li class="relative rounded-3xl bg-white border border-ink-200 p-6"><span class="w-10 h-10 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500 text-white font-bold grid place-items-center"><?= $i + 1 ?></span>
          <h3 class="mt-4 font-bold text-ink-900"><?= e($s[0]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($s[1]) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section id="faq" class="max-w-3xl mx-auto px-4 py-20 scroll-mt-20">
  <h2 class="text-3xl font-extrabold text-center text-ink-900"><?= e(t('الأسئلة الشائعة')) ?></h2>
  <div class="mt-10 space-y-3">
    <?php foreach ($faq as $q): ?>
      <details class="lp-q rounded-2xl border border-ink-200 bg-white px-5 py-4"><summary class="cursor-pointer list-none flex items-center gap-3 font-semibold text-ink-900"><span class="flex-1"><?= e($q[0]) ?></span><span class="lp-chev transition text-ink-400"><?= $ic('M6 9l6 6 6-6', 'w-5 h-5') ?></span></summary><p class="mt-3 text-sm text-ink-600 leading-relaxed"><?= e($q[1]) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 pb-20">
  <div class="rounded-[2rem] p-10 sm:p-14 text-center relative overflow-hidden" style="background:linear-gradient(135deg,#1d4ed8,#2563eb 55%,#06b6d4);color:#fff"><div class="absolute inset-0 lp-grid opacity-20"></div>
    <h2 class="relative text-3xl sm:text-4xl font-extrabold"><?= e(t('المنافسة القادمة في مجالك تُطرح الآن')) ?></h2><p class="relative mt-3 max-w-xl mx-auto" style="color:rgba(255,255,255,.85)"><?= e(t('سجّل، وعرّفنا بشركتك، واستقبل أول المنافسات المطابقة اليوم.')) ?></p>
    <a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="relative mt-8 inline-flex rounded-2xl font-bold px-8 py-3 transition hover:opacity-90" style="background:#fff;color:#1d4ed8"><?= e($canReg ? t('ابدأ الآن') : t('دخول')) ?></a></div>
</section>

<footer class="border-t border-ink-100">
  <div class="max-w-6xl mx-auto px-4 py-8 flex flex-wrap items-center gap-4 text-sm text-ink-500"><span class="font-semibold text-ink-700"><?= e($siteName) ?></span><span>© <?= date('Y') ?></span>
    <span class="sm:hidden flex gap-2"><?= pref_switch() ?></span><a href="<?= e(url('login')) ?>" class="ms-auto hover:text-brand-600"><?= e(t('دخول')) ?></a></div>
</footer>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
</body>
</html>
