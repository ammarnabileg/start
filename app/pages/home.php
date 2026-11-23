<?php
/* الصفحة الرئيسية العامة (لاندنج): عربي/إنجليزي، فاتح/داكن */
if (current_user()) redirect(url('dashboard'));
$siteName = setting('site_name', t('لوحة النشر'));
$canReg = setting('allow_signup', '1') === '1';
$ic = function (string $d, string $c = 'w-6 h-6') { return '<svg class="' . $c . '" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>'; };
$groups = [
    ['M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z', t('السوق والفرص'), [
        ['M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z', t('منافسات اعتماد'), t('نقرأ المنافسات يوميًا ونطابقها مع خدماتك، ونرسل لك ما يناسبك قبل آخر موعد للتقديم.')],
        ['M8 21h8M12 17v4M7 4h10v5a5 5 0 01-10 0zM7 6H4a3 3 0 003 4M17 6h3a3 3 0 01-3 4', t('المنافسات المرساة'), t('من كسب وبكم ومن تقدّم، ثم بيانات التواصل مع الفائز وعرض سعر من خدماتك المطابقة.')],
        ['M4 21V8l8-5 8 5v13M9 21v-6h6v6', t('الشركات المستهدفة'), t('ابحث عن شركات بالمجال والحجم والمدينة، واعرف صنّاع القرار وبياناتهم من Apollo.')],
        ['M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0z', t('إدارة العملاء'), t('مراحل ومتابعات وصفقات ومهام وعروض أسعار وفواتير، مع سجل كامل لكل تواصل.')],
        ['M4 4h16v6H4zM4 14h7v6H4zM15 14h5v6h-5z', t('مصادر العملاء'), t('نماذج وصفحات هبوط وروابط واتساب متتبَّعة وحجز مواعيد وبرنامج إحالة، وكلها تصب في العملاء.')],
        ['M15 12a3 3 0 11-6 0 3 3 0 016 0zM2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z', t('مراقبة المنافسين'), t('أسعارهم وإعلاناتهم ومواقعهم وتقييماتهم وحساباتهم، مع تنبيه عند كل تغيير مهم.')],
    ]],
    ['M12 3l2.1 5.4L19.5 10l-5.4 2.1L12 17.5l-2.1-5.4L4.5 10l5.4-1.6z', t('إنشاء المحتوى'), [
        ['M12 3l2.1 5.4L19.5 10l-5.4 2.1L12 17.5l-2.1-5.4L4.5 10l5.4-1.6z', t('الكتابة بالذكاء الاصطناعي'), t('منشورات وإعلانات وردود بلغة شركتك ومن ملفها، دون مبالغات أو معلومات مختلقة.')],
        ['M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M15 9h.01', t('الصور والفيديو'), t('توليد صور وفيديوهات قصيرة لمنشوراتك وإعلاناتك من وصف بسيط.')],
        ['M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 15.6 7.1 18.2l.9-5.5-4-3.9 5.5-.8z', t('المصمم'), t('قوالب جاهزة بهوية شركتك لكل المقاسات، تعدّلها بالسحب وتنشرها مباشرة.')],
        ['M4 6h16M4 12h16M4 18h10', t('مكتبة الوسائط'), t('كل صورك وفيديوهاتك في مجلدات، مع بحث وروابط مشاركة وسلة محذوفات.')],
    ]],
    ['M4 12l16-8-6 16-2-6z', t('النشر والإعلانات'), [
        ['M4 5h16v14H4zM8 3v4M16 3v4M4 10h16', t('جدولة على كل المنصات'), t('فيسبوك وإنستجرام وتيك توك ولينكدإن وإكس ويوتيوب وجوجل بيزنس من شاشة واحدة، مع تقويم وطابور نشر.')],
        ['M9 12l2 2 4-4M4 5h16v14H4z', t('موافقات النشر'), t('الموظف يكتب والمدير يوافق قبل النشر، مع سجل لكل تعديل.')],
        ['M3 11l18-6-6 18-3-8z', t('الحملات الإعلانية'), t('أنشئ حملات ميتا وتابع نتائجها وميزانياتها، مع اقتراحات لتحسين الأداء.')],
        ['M17 20h5v-2a4 4 0 00-5-3.87M12 12a4 4 0 100-8 4 4 0 000 8zM3 20v-2a6 6 0 0112 0v2', t('الجماهير'), t('جماهير مخصصة ومشابهة من عملائك وزوار موقعك.')],
    ]],
    ['M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z', t('التواصل مع العملاء'), [
        ['M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z', t('صندوق رسائل موحّد'), t('رسائل كل الحسابات في مكان واحد، مع توزيع على الفريق وملاحظات داخلية وردود محفوظة.')],
        ['M7 8h10M7 12h6M5 20l2-4h12V4H5z', t('التعليقات'), t('رد على تعليقات كل منشوراتك، وصنّفها، وأخفِ المسيء منها بضغطة.')],
        ['M12 2a7 7 0 017 7c0 5-7 13-7 13S5 14 5 9a7 7 0 017-7z', t('الوكيل الذكي والردود التلقائية'), t('يرد على العملاء من ملف شركتك فقط، ويحوّل للموظف عند الحاجة.')],
        ['M3 21l1.6-5A9 9 0 1112 21a9 9 0 01-4.6-1.3z', t('واتساب للأعمال'), t('رسائل جماعية وقوالب معتمدة وتسلسلات متابعة تلقائية وجهات اتصال.')],
        ['M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a1 1 0 01-1 1A16 16 0 014 5a1 1 0 011-1z', t('الهاتف والرسائل النصية'), t('أرقام للشركة ومكالمات تصل جوالك وتُسجّل في ملف العميل، ورسائل SMS.')],
        ['M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z', t('تقييمات جوجل'), t('رد على تقييمات فروعك في جوجل بيزنس وحدّث بياناتها وصورها.')],
    ]],
    ['M4 20V10M10 20V4M16 20v-6M22 20H2', t('النتائج'), [
        ['M4 20V10M10 20V4M16 20v-6M22 20H2', t('التحليلات'), t('أداء المنشورات والحسابات والإعلانات والرسائل في لوحة واحدة.')],
        ['M9 17v-6M13 17V7M17 17v-3M3 21h18', t('تقارير المبيعات'), t('مصادر العملاء ونسب التحويل وعائد الإعلانات وأداء كل موظف.')],
        ['M12 22a10 10 0 110-20 10 10 0 010 20zM12 6v6l4 2', t('الأهداف'), t('أهداف شهرية للفريق ومتابعة التقدم يومًا بيوم.')],
        ['M4 4h16v16H4zM8 9h8M8 13h5', t('تقارير جاهزة'), t('تقارير دورية تُرسل للمديرين بالبريد، قابلة للطباعة.')],
    ]],
    ['M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', t('الأساس'), [
        ['M4 19.5A2.5 2.5 0 016.5 17H20V3H6.5A2.5 2.5 0 004 5.5zM4 19.5A2.5 2.5 0 006.5 22H20v-5', t('قاعدة المعرفة'), t('نقرأ موقعك كله (حتى 10,000 صفحة) وملفاتك مهما كبر حجمها، والنص المكتوب على الصور، دون ذكاء اصطناعي.')],
        ['M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0z', t('الفريق والصلاحيات'), t('دعوة الموظفين وأدوار بصلاحيات دقيقة لكل صفحة، وسجل نشاط كامل.')],
        ['M4 7h16v13H4zM9 7V4h6v3', t('عدة شركات'), t('أدر أكثر من شركة أو عميل من حساب واحد، ولكل منها بياناتها المنفصلة.')],
        ['M10 13a5 5 0 007 0l3-3a5 5 0 00-7-7l-1 1M14 11a5 5 0 00-7 0l-3 3a5 5 0 007 7l1-1', t('التكاملات'), t('Apollo وApify وZernio ومزوّدو الذكاء الاصطناعي، مع حد استهلاك شهري تضعه أنت.')],
    ]],
];
$steps = [[t('عرّفنا بشركتك'), t('ارفع ملفاتك أو أعطنا رابط موقعك؛ نقرأه كله ونبني ملف الشركة دون ذكاء اصطناعي.')], [t('اربط حساباتك'), t('حسابات التواصل وواتساب والإعلانات وApollo، بضغطة لكل منها.')],
    [t('اعمل من مكان واحد'), t('فرص وعملاء ومحتوى ورسائل في شاشة واحدة، مع تنبيه لكل جديد.')], [t('تابع النتائج'), t('ما الذي جاء بالعملاء، وأي منشور نجح، وأين تذهب الميزانية.')]];
$faq = [[t('من أين تأتي بيانات المنافسات؟'), t('من منصة اعتماد الحكومية عبر أدوات بيانات متخصصة، وتُحدّث يوميًا.')],
    [t('هل أحتاج حسابًا في اعتماد؟'), t('لمتابعة المنافسات والترسيات لا. التقديم نفسه وشراء كراسة الشروط يتمان من حسابك في اعتماد.')],
    [t('هل تستخدمون الذكاء الاصطناعي في كل شيء؟'), t('لا. قراءة موقعك وملفاتك ومطابقة المنافسات تتم دون ذكاء اصطناعي. نستخدمه فقط عند الكتابة والتحليل، وبحد استهلاك تضعه أنت.')],
    [t('هل أحتاج كل الخدمات؟'), t('لا. فعّل ما تحتاجه فقط، وتختفي باقي الأقسام من القائمة.')],
    [t('هل تدعم العربية والإنجليزية؟'), t('نعم، الواجهة كاملة بالعربية الفصحى والإنجليزية، مع وضع فاتح وداكن.')],
    [t('هل بياناتي آمنة؟'), t('كل شركة لها مساحة منفصلة، والصلاحيات لكل موظف حسب دوره، والمفاتيح محفوظة مشفّرة.')]];
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($siteName . ' · ' . t('الفرص والمبيعات والتسويق والتواصل في منصة واحدة')); ?>
<meta name="description" content="<?= e(t('منصة واحدة للمنافسات والعملاء والمحتوى والنشر والإعلانات والرسائل وواتساب والهاتف والتحليلات.')) ?>">
<style>
.lp-grid{background-image:radial-gradient(circle at 1px 1px,rgb(148 163 184/.18) 1px,transparent 0);background-size:22px 22px}
.lp-glow{background:radial-gradient(60% 60% at 70% 20%,rgb(37 99 235/.18),transparent 70%),radial-gradient(50% 50% at 20% 80%,rgb(6 182 212/.14),transparent 70%)}
.dark .lp-glow{background:radial-gradient(60% 60% at 70% 20%,rgb(37 99 235/.28),transparent 70%),radial-gradient(50% 50% at 20% 80%,rgb(6 182 212/.18),transparent 70%)}
.lp-in{animation:lpIn .7s ease-out both}@keyframes lpIn{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
details.lp-q summary::-webkit-details-marker{display:none}details.lp-q[open] .lp-chev{transform:rotate(180deg)}
/* ====== بهجة وحركة ====== */
/* فقاعات متدرّجة طايرة في الخلفية */
.lp-blob{position:absolute;border-radius:9999px;filter:blur(60px);opacity:.5;pointer-events:none;will-change:transform}
.lp-blob.b1{width:380px;height:380px;background:radial-gradient(circle,rgb(37 99 235/.55),transparent 70%);top:-80px;inset-inline-end:-60px;animation:floatA 14s ease-in-out infinite}
.lp-blob.b2{width:300px;height:300px;background:radial-gradient(circle,rgb(6 182 212/.5),transparent 70%);bottom:-70px;inset-inline-start:-50px;animation:floatB 18s ease-in-out infinite}
.lp-blob.b3{width:220px;height:220px;background:radial-gradient(circle,rgb(132 204 22/.4),transparent 70%);top:40%;inset-inline-start:30%;animation:floatA 20s ease-in-out infinite reverse}
@keyframes floatA{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-24px,26px) scale(1.08)}}
@keyframes floatB{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(28px,-22px) scale(1.1)}}
/* ظهور تدريجي عند التمرير */
[data-reveal]{opacity:0;transform:translateY(26px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
[data-reveal].in{opacity:1;transform:none}
/* لمعان متحرك على العنوان المتدرّج */
.lp-shine{background-size:200% auto;animation:shine 5s linear infinite}
@keyframes shine{to{background-position:200% center}}
/* شريط شعارات متحرك (ماركي) */
.lp-marquee{display:flex;gap:2.5rem;width:max-content;animation:marq 26s linear infinite}
.lp-marquee:hover{animation-play-state:paused}
@keyframes marq{to{transform:translateX(50%)}}
[dir=ltr] .lp-marquee{animation-name:marqL}@keyframes marqL{to{transform:translateX(-50%)}}
/* نبض خفيف على نقطة الشارة */
.lp-pulse{animation:lpPulse 1.8s ease-in-out infinite}@keyframes lpPulse{0%,100%{box-shadow:0 0 0 0 rgb(132 204 22/.6)}50%{box-shadow:0 0 0 6px rgb(132 204 22/0)}}
/* رفعة البطاقة + ميل خفيف للأيقونة */
.lp-card{transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s,border-color .35s,opacity .7s}
.lp-card[data-reveal]:not(.in){transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.lp-card:hover{transform:translateY(-6px)}
.lp-card:hover .lp-ic{transform:translateY(-3px) rotate(-4deg) scale(1.08)}
.lp-ic{transition:transform .35s cubic-bezier(.34,1.56,.64,1)}
/* عوم لطيف لبطاقة الهيرو */
.lp-float{animation:heroFloat 6s ease-in-out infinite}@keyframes heroFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
@media (prefers-reduced-motion:reduce){.lp-blob,.lp-shine,.lp-marquee,.lp-pulse,.lp-float{animation:none!important}[data-reveal]{opacity:1;transform:none;transition:none}}
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
  <span class="lp-blob b1"></span><span class="lp-blob b2"></span><span class="lp-blob b3"></span>
  <div class="relative max-w-6xl mx-auto px-4 pt-14 pb-20 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <div class="lp-in min-w-0">
      <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 text-brand-700 text-xs font-semibold px-3 py-1"><span class="w-1.5 h-1.5 rounded-full bg-lime-500 lp-pulse"></span><?= e(t('الفرص · المحتوى · النشر · التواصل · النتائج')) ?></span>
      <h1 class="mt-5 text-3xl sm:text-5xl break-words font-extrabold leading-tight text-ink-900"><?= e(t('كل ما تحتاجه شركتك لتكسب عملاء أكثر')) ?><br><span class="lp-shine bg-gradient-to-l from-brand-600 via-cyan-500 to-brand-600 bg-clip-text text-transparent"><?= e(t('في منصة واحدة')) ?></span></h1>
      <p class="mt-5 text-lg text-ink-600 leading-relaxed max-w-xl"><?= e(t('منافسات اعتماد وترسياتها، والعملاء والصفقات، والمحتوى والنشر على كل المنصات، والإعلانات، وصندوق رسائل موحّد مع واتساب والهاتف، والتحليلات. كلها من ملف شركتك وبفريقك وصلاحياتهم.')) ?></p>
      <div class="mt-8 flex flex-wrap gap-3"><a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="btn-primary !px-6 !py-3 text-base"><?= e($canReg ? t('ابدأ الآن') : t('دخول')) ?></a><a href="#how" class="btn-ghost !px-6 !py-3 text-base"><?= e(t('كيف يعمل؟')) ?></a></div>
      <p class="mt-4 text-xs text-ink-500"><?= e(t('عربي وإنجليزي · فاتح وداكن · بيانات كل شركة منفصلة')) ?></p>
    </div>
    <div class="lp-in min-w-0 [animation-delay:.15s]">
      <div class="lp-float rounded-3xl border border-ink-200 bg-white shadow-2xl shadow-brand-900/10 p-5">
        <div class="flex items-center gap-2 mb-4"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span><span class="w-2.5 h-2.5 rounded-full bg-lime-400"></span><b class="ms-3 text-sm text-ink-900"><?= e(t('نظرة عامة')) ?></b></div>
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-2xl bg-brand-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('عملاء جدد')) ?></div><div class="text-2xl font-extrabold text-brand-700 lp-count" data-to="38">0</div></div>
          <div class="rounded-2xl bg-lime-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('منافسات مطابقة')) ?></div><div class="text-2xl font-extrabold text-lime-700 lp-count" data-to="24">0</div></div>
          <div class="rounded-2xl bg-amber-50 p-3"><div class="text-[11px] text-ink-500"><?= e(t('منشورات مجدولة')) ?></div><div class="text-2xl font-extrabold text-amber-700 lp-count" data-to="12">0</div></div>
        </div>
        <div class="mt-4 space-y-2">
          <?php foreach ([[t('توريد وتركيب مطابخ تجارية'), t('منافسة مطابقة · وزارة الصحة'), '92%', 'new'], [t('كم سعر الباقة الشهرية؟'), t('رسالة واتساب · أحمد'), '2m', 'new'], [t('عرض نهاية الشهر'), t('منشور مجدول · إنستجرام وتيك توك'), '18:00', '']] as $r): ?>
            <div class="flex items-center gap-3 rounded-2xl border border-ink-100 p-3"><span class="w-9 h-9 rounded-xl bg-ink-100 grid place-items-center text-ink-500"><?= $ic('M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z', 'w-4 h-4') ?></span>
              <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-ink-900 truncate"><?= e($r[0]) ?></span><span class="block text-[11px] text-ink-500"><?= e($r[1]) ?></span></span>
              <?php if ($r[3]): ?><span class="rounded-full bg-lime-100 text-lime-700 text-[10px] font-bold px-2 py-0.5"><?= e(t('جديد')) ?></span><?php endif; ?><b class="text-sm text-brand-700"><?= $r[2] ?></b></div>
          <?php endforeach; ?>
        </div>
        <div class="mt-4 rounded-2xl bg-ink-900 text-white p-3 text-xs flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-amber-400 text-ink-900 grid place-items-center">!</span><?= e(t('صفقة جديدة: عرض سعر بقيمة 48,000 ريال أُرسل للعميل')) ?></div>
      </div>
    </div>
  </div>
</section>

<section class="border-y border-ink-100 bg-ink-50/60">
  <div class="max-w-6xl mx-auto px-4 py-6 flex items-center gap-4">
    <span class="shrink-0 font-semibold text-ink-700 text-sm"><?= e(t('يعمل مع')) ?></span>
    <div class="relative flex-1 overflow-hidden" style="-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)">
      <div class="lp-marquee py-1 text-sm text-ink-500">
        <?php for ($k = 0; $k < 2; $k++): foreach ([t('منصة اعتماد'), 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'X', 'YouTube', t('واتساب'), 'Google Business', 'Apollo', 'Meta Ads'] as $s): ?><span class="whitespace-nowrap font-medium transition-colors hover:text-brand-600"><?= e($s) ?></span><?php endforeach; endfor; ?>
      </div>
    </div>
  </div>
</section>

<section id="features" class="max-w-6xl mx-auto px-4 py-20 scroll-mt-20">
  <div data-reveal class="text-center max-w-2xl mx-auto"><h2 class="text-3xl font-extrabold text-ink-900"><?= e(t('كل خدمات شركتك في منصة واحدة')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('من أول فرصة إلى المحتوى والنشر والتواصل والنتائج، دون التنقل بين أدوات متفرقة.')) ?></p></div>
  <div class="mt-10 flex flex-wrap justify-center gap-2" role="tablist">
    <?php foreach ($groups as $gi => $g): ?><button type="button" role="tab" data-lpt="<?= $gi ?>" aria-selected="<?= $gi ? 'false' : 'true' ?>" class="lp-tab inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"><?= $ic($g[0], 'w-4 h-4') ?><?= e($g[1]) ?> <span class="text-[11px] opacity-70"><?= count($g[2]) ?></span></button><?php endforeach; ?>
  </div>
  <?php foreach ($groups as $gi => $g): ?>
  <div data-lpp="<?= $gi ?>" class="lp-pane mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-5<?= $gi ? ' hidden' : '' ?>">
    <?php foreach ($g[2] as $fx => $f): ?>
      <div class="lp-card group rounded-3xl border border-ink-200 bg-white p-6 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-900/5"<?= $gi ? '' : ' data-reveal data-reveal-delay="' . ($fx * 70) . '"' ?>>
        <span class="lp-ic w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><?= $ic($f[0]) ?></span>
        <h3 class="mt-4 font-bold text-lg text-ink-900"><?= e($f[1]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($f[2]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
  <p class="mt-8 text-center text-sm text-ink-500"><?= e(t('%s خدمة في 6 أقسام، بواجهة واحدة وحساب واحد.', array_sum(array_map(function ($g) { return count($g[2]); }, $groups)))) ?></p>
</section>
<script>
(function () { var tabs = document.querySelectorAll('[data-lpt]'), panes = document.querySelectorAll('[data-lpp]');
  function on(k) { tabs.forEach(function (t) { var a = t.dataset.lpt === k; t.setAttribute('aria-selected', a); t.className = 'lp-tab inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition ' + (a ? 'bg-brand-600 border-brand-600 text-[#fff] shadow-lg shadow-brand-600/20' : 'border-ink-200 text-ink-700 hover:border-brand-300'); });
    panes.forEach(function (p) { p.classList.toggle('hidden', p.dataset.lpp !== k); }); }
  tabs.forEach(function (t) { t.addEventListener('click', function () { on(t.dataset.lpt); }); }); on('0'); })();
</script>

<section id="how" class="bg-ink-50/60 border-y border-ink-100 scroll-mt-20">
  <div class="max-w-6xl mx-auto px-4 py-20">
    <div data-reveal class="text-center max-w-2xl mx-auto"><h2 class="text-3xl font-extrabold text-ink-900"><?= e(t('كيف يعمل')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('أربع خطوات من التسجيل إلى أول نتيجة.')) ?></p></div>
    <ol class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <?php foreach ($steps as $i => $s): ?>
        <li data-reveal data-reveal-delay="<?= $i * 90 ?>" class="lp-card relative rounded-3xl bg-white border border-ink-200 p-6"><span class="lp-ic w-10 h-10 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500 text-white font-bold grid place-items-center"><?= $i + 1 ?></span>
          <h3 class="mt-4 font-bold text-ink-900"><?= e($s[0]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($s[1]) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section id="faq" class="max-w-3xl mx-auto px-4 py-20 scroll-mt-20">
  <h2 data-reveal class="text-3xl font-extrabold text-center text-ink-900"><?= e(t('الأسئلة الشائعة')) ?></h2>
  <div class="mt-10 space-y-3">
    <?php foreach ($faq as $qi => $q): ?>
      <details data-reveal data-reveal-delay="<?= $qi * 60 ?>" class="lp-q rounded-2xl border border-ink-200 bg-white px-5 py-4 transition-colors hover:border-brand-200"><summary class="cursor-pointer list-none flex items-center gap-3 font-semibold text-ink-900"><span class="flex-1"><?= e($q[0]) ?></span><span class="lp-chev transition text-ink-400"><?= $ic('M6 9l6 6 6-6', 'w-5 h-5') ?></span></summary><p class="mt-3 text-sm text-ink-600 leading-relaxed"><?= e($q[1]) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 pb-20">
  <div data-reveal class="rounded-[2rem] p-10 sm:p-14 text-center relative overflow-hidden" style="background:linear-gradient(135deg,#1d4ed8,#2563eb 55%,#06b6d4);color:#fff"><div class="absolute inset-0 lp-grid opacity-20"></div><span class="lp-blob b3" style="opacity:.35"></span>
    <h2 class="relative text-3xl sm:text-4xl font-extrabold"><?= e(t('ابدأ اليوم وأدر كل شيء من مكان واحد')) ?></h2><p class="relative mt-3 max-w-xl mx-auto" style="color:rgba(255,255,255,.85)"><?= e(t('سجّل، وعرّفنا بشركتك، واربط حساباتك في دقائق.')) ?></p>
    <a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="relative mt-8 inline-flex rounded-2xl font-bold px-8 py-3 transition hover:opacity-90" style="background:#fff;color:#1d4ed8"><?= e($canReg ? t('ابدأ الآن') : t('دخول')) ?></a></div>
</section>

<footer class="border-t border-ink-100">
  <div class="max-w-6xl mx-auto px-4 py-8 flex flex-wrap items-center gap-4 text-sm text-ink-500"><span class="font-semibold text-ink-700"><?= e($siteName) ?></span><span>© <?= date('Y') ?></span>
    <span class="sm:hidden flex gap-2"><?= pref_switch() ?></span><a href="<?= e(url('login')) ?>" class="ms-auto hover:text-brand-600"><?= e(t('دخول')) ?></a></div>
</footer>
<script>
(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  var reveals = document.querySelectorAll('[data-reveal]');
  if (reduce || !('IntersectionObserver' in window)) {
    reveals.forEach(function (el) { el.classList.add('in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var el = en.target, d = parseInt(el.getAttribute('data-reveal-delay') || '0', 10);
        setTimeout(function () { el.classList.add('in'); }, d);
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    reveals.forEach(function (el) { io.observe(el); });
  }
  // عدّاد الأرقام في بطاقة الهيرو
  var counts = document.querySelectorAll('.lp-count');
  function run(el) {
    var to = parseInt(el.getAttribute('data-to') || '0', 10), dur = 1100, t0 = null;
    if (reduce) { el.textContent = to; return; }
    function tick(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min((ts - t0) / dur, 1), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(to * eased);
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if (!('IntersectionObserver' in window)) {
    counts.forEach(run);
  } else {
    var co = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { if (en.isIntersecting) { run(en.target); co.unobserve(en.target); } });
    }, { threshold: 0.6 });
    counts.forEach(function (el) { co.observe(el); });
  }
})();
</script>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
</body>
</html>
