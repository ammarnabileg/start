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
$svcCount = array_sum(array_map(function ($g) { return count($g[2]); }, $groups));
$stats = [
    [count($groups), '', t('أقسام متكاملة')],
    [$svcCount, '+', t('خدمة في منصة واحدة')],
    [9, '', t('منصات نشر وتواصل')],
    [10000, '', t('صفحة من موقعك تُقرأ')],
];
$replaces = [t('أداة جدولة'), t('أداة تصميم'), t('نظام CRM'), t('صندوق رسائل'), t('أداة إعلانات'), t('مراقبة منافسين'), t('قراءة منافسات'), t('لوحة تحليلات'), t('واتساب API'), t('صفحات هبوط'), t('ردود تلقائية'), t('قاعدة معرفة')];
$rotate = [t('عملاء جُدد'), t('منافسات مطابقة'), t('محتوى ينشر نفسه'), t('ردود فورية'), t('نتائج تُقاس')];
// مواضع عُقد المخطط الدائري (بيضاوي حول المركز)
$cx = 300; $cy = 205; $rx = 212; $ry = 150; $diag = [];
foreach ($groups as $gi => $g) {
    $ang = deg2rad(-90 + 60 * $gi);
    $diag[] = [round($cx + $rx * cos($ang), 1), round($cy + $ry * sin($ang), 1), $g[0], $g[1]];
}
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($siteName . ' · ' . t('الفرص والمبيعات والتسويق والتواصل في منصة واحدة')); ?>
<meta name="description" content="<?= e(t('منصة واحدة للمنافسات والعملاء والمحتوى والنشر والإعلانات والرسائل وواتساب والهاتف والتحليلات.')) ?>">
<style>
/* =========================================================
   محرّك البهجة والحركة — ~50 تقنية (hero داكن جريء + أقسام حيّة)
   يحترم prefers-reduced-motion بالكامل في آخر الملف
   ========================================================= */
:root{--b1:#2563eb;--b2:#06b6d4;--b3:#84cc16;--navy:#070b1a;--navy2:#0c1430}
html{scroll-behavior:smooth}
/* 1) شريط تقدّم التمرير أعلى الصفحة */
.lp-progress{position:fixed;inset-block-start:0;inset-inline-start:0;height:3px;width:0;z-index:60;background:linear-gradient(90deg,var(--b1),var(--b2),var(--b3));box-shadow:0 0 12px rgba(37,99,235,.6);transition:width .1s linear}
/* 2) الهيدر الزجاجي اللي بيصغّر عند النزول */
.lp-head{position:sticky;inset-block-start:0;z-index:50;transition:background .35s,box-shadow .35s,backdrop-filter .35s,border-color .35s}
.lp-head.shrunk{background:rgba(255,255,255,.82);backdrop-filter:blur(14px);box-shadow:0 8px 30px -18px rgba(15,23,42,.35);border-bottom:1px solid rgba(226,232,240,.9)}
.dark .lp-head.shrunk{background:rgba(10,15,30,.72);border-bottom-color:rgba(255,255,255,.08)}
.lp-navlink{position:relative}
.lp-navlink::after{content:"";position:absolute;inset-inline-start:0;inset-block-end:-4px;height:2px;width:100%;background:linear-gradient(90deg,var(--b1),var(--b2));transform:scaleX(0);transform-origin:inline-start;transition:transform .3s cubic-bezier(.22,1,.36,1)}
.lp-navlink:hover::after{transform:scaleX(1)}

/* 3) الهيرو الداكن + توهّجات مموّهة + شبكة + حبوب ضوضاء */
.hero{position:relative;overflow:hidden;background:
  radial-gradient(60% 55% at 78% 8%,rgba(37,99,235,.42),transparent 60%),
  radial-gradient(55% 55% at 15% 85%,rgba(6,182,212,.30),transparent 60%),
  radial-gradient(40% 40% at 55% 60%,rgba(132,204,22,.14),transparent 60%),
  linear-gradient(180deg,var(--navy) 0%,var(--navy2) 70%,#0e1a3f 100%)}
.hero::after{content:"";position:absolute;inset:0;pointer-events:none;opacity:.5;background-image:radial-gradient(circle at 1px 1px,rgba(148,163,184,.14) 1px,transparent 0);background-size:26px 26px;mask-image:radial-gradient(120% 90% at 50% 10%,#000,transparent 75%)}
.hero-fade{position:absolute;inset-inline:0;inset-block-end:-1px;height:120px;background:linear-gradient(180deg,transparent,var(--navy2) 60%);pointer-events:none}
/* 4) توهّجات طايرة بعمق مختلف (parallax) */
.glow{position:absolute;border-radius:9999px;filter:blur(80px);pointer-events:none;will-change:transform}
.glow.g1{width:460px;height:460px;background:radial-gradient(circle,rgba(37,99,235,.6),transparent 70%);top:-120px;inset-inline-end:-80px}
.glow.g2{width:380px;height:380px;background:radial-gradient(circle,rgba(6,182,212,.5),transparent 70%);bottom:-120px;inset-inline-start:-60px}
.glow.g3{width:300px;height:300px;background:radial-gradient(circle,rgba(132,204,22,.38),transparent 70%);top:35%;inset-inline-start:40%}
.lp-net{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;opacity:.6}
/* توهّج يتبع المؤشر */
.hero-spot{position:absolute;inset:0;pointer-events:none;background:radial-gradient(240px 240px at var(--mx,50%) var(--my,30%),rgba(99,179,237,.16),transparent 70%);transition:background .2s}

/* 5) تايبوغرافي الهيرو */
.hero h1{letter-spacing:-.02em}
.shine{background:linear-gradient(100deg,#fff 10%,#7dd3fc 30%,#a3e635 50%,#7dd3fc 70%,#fff 90%);background-size:200% auto;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shine 6s linear infinite}
@keyframes shine{to{background-position:-200% center}}
/* 6) rotator الكلمات */
.rot{display:inline-grid;vertical-align:bottom;overflow:hidden;height:1.15em}
.rot>span{grid-area:1/1;opacity:0;transform:translateY(100%);transition:opacity .5s,transform .5s cubic-bezier(.22,1,.36,1);background:linear-gradient(90deg,#60a5fa,#22d3ee);-webkit-background-clip:text;background-clip:text;color:transparent;white-space:nowrap}
.rot>span.on{opacity:1;transform:none}
.rot>span.off{opacity:0;transform:translateY(-100%)}

/* 7) أزرار */
.btn-shine{position:relative;overflow:hidden}
.btn-shine::before{content:"";position:absolute;top:0;inset-inline-start:-120%;width:60%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);transform:skewX(-18deg);transition:none}
.btn-shine:hover::before{animation:sweep .8s ease}
@keyframes sweep{to{inset-inline-start:140%}}
.mag{will-change:transform;transition:transform .25s cubic-bezier(.22,1,.36,1)}

/* 8) كارت الهيرو الزجاجي + 3D tilt */
.tilt{transform-style:preserve-3d;transition:transform .2s ease}
.glass{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(14px);box-shadow:0 30px 80px -30px rgba(2,6,23,.8)}
.glass-row{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08)}
.depth{transform:translateZ(40px)}
.depth-sm{transform:translateZ(22px)}

/* 9) مخطّطات حيّة صغيرة */
.bar{width:100%;background:linear-gradient(180deg,#60a5fa,#22d3ee);border-radius:6px 6px 2px 2px;height:0;transition:height 1s cubic-bezier(.22,1,.36,1)}
.ring-track{stroke:rgba(148,163,184,.25)}
.ring-val{stroke:url(#ringg);stroke-linecap:round;transition:stroke-dashoffset 1.3s cubic-bezier(.22,1,.36,1)}
/* شريط فعاليات يجري تلقائيًا */
.ticker{height:112px;overflow:hidden;position:relative;-webkit-mask-image:linear-gradient(180deg,transparent,#000 18%,#000 82%,transparent);mask-image:linear-gradient(180deg,transparent,#000 18%,#000 82%,transparent)}
.ticker-track{display:flex;flex-direction:column;gap:8px;animation:tick 9s linear infinite}
@keyframes tick{to{transform:translateY(-50%)}}
.ticker:hover .ticker-track{animation-play-state:paused}
/* فقاعة كتابة */
.typing span{display:inline-block;width:6px;height:6px;border-radius:9999px;background:#94a3b8;animation:typing 1.2s infinite}
.typing span:nth-child(2){animation-delay:.2s}.typing span:nth-child(3){animation-delay:.4s}
@keyframes typing{0%,60%,100%{opacity:.25;transform:translateY(0)}30%{opacity:1;transform:translateY(-3px)}}

/* 10) ظهور عند التمرير (عدة اتجاهات) */
[data-reveal]{opacity:0;transform:translateY(30px);transition:opacity .8s cubic-bezier(.22,1,.36,1),transform .8s cubic-bezier(.22,1,.36,1);will-change:opacity,transform}
[data-reveal="left"]{transform:translateX(-36px)}
[data-reveal="right"]{transform:translateX(36px)}
[data-reveal="scale"]{transform:scale(.9)}
[data-reveal].in{opacity:1;transform:none}

/* 11) ماركي الشعارات */
.marq{display:flex;gap:2.75rem;width:max-content;animation:marq 28s linear infinite}
.marq:hover{animation-play-state:paused}
@keyframes marq{to{transform:translateX(50%)}}
[dir=ltr] .marq{animation-name:marqL}@keyframes marqL{to{transform:translateX(-50%)}}

/* 12) كروت المزايا: tilt + spotlight + حافة متوهجة */
.card{position:relative;transition:transform .35s cubic-bezier(.22,1,.36,1),box-shadow .35s,border-color .35s;transform-style:preserve-3d}
.card:hover{transform:translateY(-6px)}
.card::before{content:"";position:absolute;inset:0;border-radius:inherit;opacity:0;transition:opacity .35s;background:radial-gradient(260px 260px at var(--mx,50%) var(--my,50%),rgba(37,99,235,.12),transparent 65%);pointer-events:none}
.card:hover::before{opacity:1}
.card .cic{transition:transform .4s cubic-bezier(.34,1.56,.64,1)}
.card:hover .cic{transform:translateY(-3px) rotate(-6deg) scale(1.1)}
.pulse{animation:pls 1.8s ease-in-out infinite}@keyframes pls{0%,100%{box-shadow:0 0 0 0 rgba(132,204,22,.6)}50%{box-shadow:0 0 0 7px rgba(132,204,22,0)}}

/* 13) تبويبات بمؤشّر منزلق */
.tabs{position:relative}
.tab-ind{position:absolute;inset-block-start:0;height:100%;border-radius:9999px;background:linear-gradient(90deg,var(--b1),var(--b2));box-shadow:0 10px 24px -8px rgba(37,99,235,.6);transition:transform .4s cubic-bezier(.22,1,.36,1),width .4s cubic-bezier(.22,1,.36,1);z-index:0}

/* 14) المخطّط الدائري SVG */
.diag .spoke{stroke-dashoffset:1;transition:stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)}
.diag.in .spoke{stroke-dashoffset:0}
.diag .node{opacity:0;transform:translateY(10px) scale(.7);transform-box:fill-box;transform-origin:center;transition:opacity .55s,transform .55s cubic-bezier(.34,1.56,.64,1)}
.diag.in .node{opacity:1;transform:none}
.diag .node:hover{transform:scale(1.08)}
.nlabel{fill:#334155;font-size:13px;font-weight:700}.dark .nlabel{fill:#cbd5e1}
.hub{animation:hub 3s ease-in-out infinite;transform-box:fill-box;transform-origin:center}@keyframes hub{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
[dir=rtl] .arrow svg{transform:scaleX(-1)}

/* 15) شرائح الأدوات المستبدلة */
.strike{position:relative;transition:color .4s,border-color .4s,background .4s}
.strike::after{content:"";position:absolute;inset-inline:12%;top:50%;height:2px;background:currentColor;transform:scaleX(0);transform-origin:inline-start;transition:transform .5s cubic-bezier(.22,1,.36,1)}
[data-reveal].in .strike::after{transform:scaleX(1)}[data-reveal].in .strike{color:#94a3b8}

/* 16) خطوات "كيف يعمل" بخط تقدّم مربوط بالتمرير */
.steps-line{position:absolute;inset-inline-start:0;inset-block-start:0;width:100%;height:3px;background:rgba(148,163,184,.25);border-radius:9999px;overflow:hidden}
.steps-line i{display:block;height:100%;width:0;background:linear-gradient(90deg,var(--b1),var(--b2),var(--b3))}
.step-dot{transition:transform .4s,background .4s,color .4s,box-shadow .4s}
.step.on .step-dot{transform:scale(1.12);box-shadow:0 12px 26px -8px rgba(37,99,235,.6)}

/* 17) FAQ أكورديون ناعم */
.faq{overflow:hidden}
.faq .ans{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s cubic-bezier(.22,1,.36,1)}
.faq[open] .ans{grid-template-rows:1fr}
.faq .ans>div{overflow:hidden}
.faq summary::-webkit-details-marker{display:none}
.faq[open] .chev{transform:rotate(180deg)}

/* 18) CTA بتدرّج متحرك + لمعة */
.cta{position:relative;overflow:hidden;background:linear-gradient(120deg,#1d4ed8,#2563eb,#06b6d4,#1d4ed8);background-size:220% 220%;animation:mesh 10s ease infinite}
@keyframes mesh{0%,100%{background-position:0 50%}50%{background-position:100% 50%}}

/* 19) زر العودة للأعلى */
.totop{position:fixed;inset-block-end:22px;inset-inline-end:22px;z-index:40;opacity:0;transform:translateY(16px) scale(.9);pointer-events:none;transition:opacity .3s,transform .3s}
.totop.on{opacity:1;transform:none;pointer-events:auto}

/* sparkle على الـCTA */
.spark{position:absolute;width:6px;height:6px;border-radius:9999px;background:#fff;opacity:0;pointer-events:none}

@media (prefers-reduced-motion:reduce){
  *{animation:none!important}
  [data-reveal],.diag .node{opacity:1!important;transform:none!important;transition:none!important}
  .diag .spoke{stroke-dashoffset:0!important}
  .lp-net,.hero-spot{display:none}
  .bar{height:var(--rh,40%)!important;transition:none}
  html{scroll-behavior:auto}
}
</style>
</head>
<body class="bg-white text-ink-800 antialiased overflow-x-hidden">
<div class="lp-progress" id="lpProg"></div>

<header class="lp-head border-b border-transparent">
  <div class="max-w-6xl mx-auto px-4 h-16 flex items-center gap-4">
    <a href="<?= e(url('home')) ?>" class="flex items-center gap-2 font-bold text-ink-900"><span class="w-9 h-9 rounded-xl bg-gradient-to-br from-brand-600 to-cyan-500 text-white grid place-items-center shadow-lg shadow-brand-600/30"><?= e(mb_substr($siteName, 0, 1)) ?></span><?= e($siteName) ?></a>
    <nav class="hidden md:flex items-center gap-6 text-sm text-ink-600 ms-6"><a href="#live" class="lp-navlink hover:text-brand-600"><?= e(t('المنصة')) ?></a><a href="#features" class="lp-navlink hover:text-brand-600"><?= e(t('المزايا')) ?></a><a href="#how" class="lp-navlink hover:text-brand-600"><?= e(t('كيف يعمل')) ?></a><a href="#faq" class="lp-navlink hover:text-brand-600"><?= e(t('الأسئلة')) ?></a></nav>
    <div class="ms-auto flex items-center gap-2"><div class="hidden sm:flex gap-2"><?= pref_switch() ?></div>
      <a href="<?= e(url('login')) ?>" class="btn-ghost !py-2"><?= e(t('دخول')) ?></a>
      <?php if ($canReg): ?><a href="<?= e(url('register')) ?>" class="btn-primary btn-shine mag !py-2 hidden sm:inline-flex"><?= e(t('ابدأ الآن')) ?></a><?php endif; ?></div>
  </div>
</header>

<!-- ============ HERO (داكن جريء) ============ -->
<section class="hero" id="hero">
  <div class="glow g1" data-parallax="24"></div><div class="glow g2" data-parallax="-18"></div><div class="glow g3" data-parallax="30"></div>
  <canvas class="lp-net" aria-hidden="true"></canvas>
  <div class="hero-spot" id="heroSpot"></div>
  <div class="relative max-w-6xl mx-auto px-4 pt-20 pb-28 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <div data-reveal="right" class="min-w-0">
      <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 text-white/90 text-xs font-semibold px-3 py-1.5 backdrop-blur"><span class="w-1.5 h-1.5 rounded-full bg-lime-400 pulse"></span><?= e(t('منصة واحدة لكل تسويق ومبيعات شركتك')) ?></span>
      <h1 class="mt-6 text-white font-extrabold leading-[1.1]" style="font-size:clamp(2.2rem,5.2vw,3.9rem)">
        <?= e(t('شركتك تكسب')) ?> <span class="rot" data-rotate='<?= e(json_encode($rotate, JSON_UNESCAPED_UNICODE)) ?>'></span><br>
        <span class="shine"><?= e(t('من منصة واحدة فقط')) ?></span>
      </h1>
      <p class="mt-6 text-lg leading-relaxed max-w-xl" style="color:rgba(226,232,240,.78)"><?= e(t('منافسات اعتماد وترسياتها، والعملاء والصفقات، والمحتوى والنشر على كل المنصات، والإعلانات، وصندوق رسائل موحّد مع واتساب والهاتف، والتحليلات — كلها من ملف شركتك وبفريقك وصلاحياتهم.')) ?></p>
      <div class="mt-9 flex flex-wrap gap-3">
        <a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="btn-shine mag inline-flex items-center gap-2 rounded-2xl font-bold px-7 py-3.5 text-base text-white shadow-2xl shadow-brand-900/40" style="background:linear-gradient(90deg,#2563eb,#06b6d4)"><?= e($canReg ? t('ابدأ مجانًا') : t('دخول')) ?><?= $ic('M5 12h14M13 6l6 6-6 6', 'w-5 h-5') ?></a>
        <a href="#live" class="mag inline-flex items-center gap-2 rounded-2xl font-semibold px-6 py-3.5 text-base text-white border border-white/20 bg-white/5 backdrop-blur hover:bg-white/10 transition"><?= e(t('شوف المنصة شغالة')) ?></a>
      </div>
      <div class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs" style="color:rgba(226,232,240,.6)">
        <span class="inline-flex items-center gap-1.5"><?= $ic('M20 6L9 17l-5-5', 'w-4 h-4') ?><?= e(t('عربي وإنجليزي')) ?></span>
        <span class="inline-flex items-center gap-1.5"><?= $ic('M20 6L9 17l-5-5', 'w-4 h-4') ?><?= e(t('فاتح وداكن')) ?></span>
        <span class="inline-flex items-center gap-1.5"><?= $ic('M20 6L9 17l-5-5', 'w-4 h-4') ?><?= e(t('بيانات كل شركة منفصلة')) ?></span>
      </div>
    </div>

    <!-- كارت زجاجي تفاعلي 3D بواجهة حقيقية -->
    <div data-reveal="left" class="min-w-0 [perspective:1200px]">
      <div class="tilt glass rounded-3xl p-5" data-tilt>
        <div class="depth flex items-center gap-2 mb-4"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span><span class="w-2.5 h-2.5 rounded-full bg-lime-400"></span><b class="ms-3 text-sm text-white"><?= e(t('لوحة شركتك')) ?></b><span class="ms-auto inline-flex items-center gap-1 text-[10px] text-lime-300"><span class="w-1.5 h-1.5 rounded-full bg-lime-400 pulse"></span><?= e(t('مباشر')) ?></span></div>
        <div class="depth-sm grid grid-cols-3 gap-3">
          <div class="glass-row rounded-2xl p-3"><div class="text-[11px]" style="color:rgba(226,232,240,.6)"><?= e(t('عملاء جدد')) ?></div><div class="text-2xl font-extrabold text-white lp-count" data-to="38">0</div></div>
          <div class="glass-row rounded-2xl p-3"><div class="text-[11px]" style="color:rgba(226,232,240,.6)"><?= e(t('منافسات')) ?></div><div class="text-2xl font-extrabold text-cyan-300 lp-count" data-to="24">0</div></div>
          <div class="glass-row rounded-2xl p-3"><div class="text-[11px]" style="color:rgba(226,232,240,.6)"><?= e(t('منشورات')) ?></div><div class="text-2xl font-extrabold text-lime-300 lp-count" data-to="12">0</div></div>
        </div>
        <!-- مخطّط أعمدة حيّ -->
        <div class="depth-sm glass-row rounded-2xl p-3 mt-3">
          <div class="flex items-center justify-between mb-2"><span class="text-[11px]" style="color:rgba(226,232,240,.7)"><?= e(t('أداء الأسبوع')) ?></span><span class="text-[11px] text-lime-300">+18%</span></div>
          <div class="flex items-end gap-1.5" style="height:56px">
            <?php foreach ([38,52,44,67,58,80,72] as $h): ?><div class="bar" data-h="<?= $h ?>" style="--rh:<?= $h ?>%"></div><?php endforeach; ?>
          </div>
        </div>
        <!-- شريط فعاليات حيّ + كتابة -->
        <div class="depth-sm ticker mt-3">
          <div class="ticker-track">
            <?php $feed = [[t('منافسة مطابقة · وزارة الصحة'),'92%'],[t('رسالة واتساب · أحمد'),t('الآن')],[t('منشور مجدول · إنستجرام'),'18:00'],[t('صفقة جديدة · 48,000 ﷼'),'✓']]; for($r=0;$r<2;$r++) foreach($feed as $f): ?>
              <div class="glass-row rounded-xl px-3 py-2 flex items-center gap-2"><span class="w-7 h-7 rounded-lg bg-white/10 grid place-items-center text-cyan-300"><?= $ic('M3 21h18M5 21V10m14 11V10M9 21v-6h6v6M2 10l10-6 10 6z','w-3.5 h-3.5') ?></span><span class="flex-1 text-[12px] text-white truncate"><?= e($f[0]) ?></span><b class="text-[11px] text-lime-300"><?= e($f[1]) ?></b></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="depth-sm glass-row rounded-xl px-3 py-2 mt-2 flex items-center gap-2"><span class="w-7 h-7 rounded-lg bg-white/10 grid place-items-center text-white"><?= $ic('M21 12a8 8 0 01-11.6 7.1L3 21l1.9-6.4A8 8 0 1121 12z','w-3.5 h-3.5') ?></span><span class="text-[12px]" style="color:rgba(226,232,240,.8)"><?= e(t('الوكيل الذكي يكتب ردًّا')) ?></span><span class="typing ms-auto inline-flex gap-1"><span></span><span></span><span></span></span></div>
      </div>
    </div>
  </div>
  <div class="hero-fade"></div>
</section>

<!-- شعارات متحركة -->
<section style="background:var(--navy2)">
  <div class="max-w-6xl mx-auto px-4 py-7 flex items-center gap-4">
    <span class="shrink-0 font-semibold text-sm" style="color:rgba(226,232,240,.55)"><?= e(t('يعمل مع')) ?></span>
    <div class="relative flex-1 overflow-hidden" style="-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)">
      <div class="marq py-1 text-sm" style="color:rgba(226,232,240,.62)">
        <?php for ($k = 0; $k < 2; $k++): foreach ([t('منصة اعتماد'), 'Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'X', 'YouTube', t('واتساب'), 'Google Business', 'Apollo', 'Meta Ads'] as $s): ?><span class="whitespace-nowrap font-medium transition-colors hover:text-white"><?= e($s) ?></span><?php endforeach; endfor; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ أرقام المنصة ============ -->
<section class="max-w-6xl mx-auto px-4 pt-20" id="live">
  <div data-reveal class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center">
    <?php foreach ($stats as $si => $st): ?>
      <div data-reveal data-reveal-delay="<?= $si * 90 ?>" class="rounded-3xl border border-ink-100 bg-ink-50/60 p-6">
        <div class="text-4xl sm:text-5xl font-extrabold tracking-tight bg-gradient-to-l from-brand-600 to-cyan-500 bg-clip-text text-transparent"><span class="lp-count" data-to="<?= $st[0] ?>"<?= $st[0] >= 1000 ? ' data-format="comma"' : '' ?>>0</span><?= $st[1] ?></div>
        <div class="mt-2 text-sm text-ink-600"><?= e($st[2]) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============ المزايا (تبويبات + كروت tilt/spotlight) ============ -->
<section id="features" class="max-w-6xl mx-auto px-4 py-20 scroll-mt-20">
  <div data-reveal class="text-center max-w-2xl mx-auto"><h2 class="text-3xl sm:text-4xl font-extrabold text-ink-900"><?= e(t('كل خدمات شركتك في منصة واحدة')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('من أول فرصة إلى المحتوى والنشر والتواصل والنتائج، دون التنقل بين أدوات متفرقة.')) ?></p></div>
  <div data-reveal class="tabs mt-10 flex flex-wrap justify-center gap-2" role="tablist" id="tabWrap">
    <span class="tab-ind" id="tabInd" style="width:0"></span>
    <?php foreach ($groups as $gi => $g): ?><button type="button" role="tab" data-lpt="<?= $gi ?>" aria-selected="<?= $gi ? 'false' : 'true' ?>" class="lp-tab relative z-10 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition"><?= $ic($g[0], 'w-4 h-4') ?><?= e($g[1]) ?> <span class="text-[11px] opacity-70"><?= count($g[2]) ?></span></button><?php endforeach; ?>
  </div>
  <?php foreach ($groups as $gi => $g): ?>
  <div data-lpp="<?= $gi ?>" class="lp-pane mt-9 grid sm:grid-cols-2 lg:grid-cols-3 gap-5<?= $gi ? ' hidden' : '' ?>">
    <?php foreach ($g[2] as $fx => $f): ?>
      <div class="card group rounded-3xl border border-ink-200 bg-white p-6 hover:border-brand-300 hover:shadow-xl hover:shadow-brand-900/5" data-tilt data-spotlight<?= $gi ? '' : ' data-reveal data-reveal-delay="' . ($fx * 70) . '"' ?>>
        <span class="cic w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-50 to-cyan-50 text-brand-600 grid place-items-center"><?= $ic($f[0]) ?></span>
        <h3 class="mt-4 font-bold text-lg text-ink-900"><?= e($f[1]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($f[2]) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</section>

<!-- ============ المخطّط الدائري ============ -->
<section class="relative overflow-hidden border-y border-ink-100 bg-white">
  <div class="absolute inset-0 opacity-50" style="background-image:radial-gradient(circle at 1px 1px,rgba(148,163,184,.18) 1px,transparent 0);background-size:22px 22px"></div>
  <div class="relative max-w-6xl mx-auto px-4 py-20 grid lg:grid-cols-2 gap-12 items-center">
    <div data-reveal="right">
      <span class="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 text-brand-700 text-xs font-semibold px-3 py-1"><?= e(t('منظومة واحدة')) ?></span>
      <h2 class="mt-5 text-3xl font-extrabold text-ink-900"><?= e(t('ستة أقسام تدور حول مركز واحد: شركتك')) ?></h2>
      <p class="mt-4 text-ink-600 leading-relaxed"><?= e(t('كل قسم يغذّي الباقي من نفس ملف الشركة ونفس البيانات: الفرص تتحوّل لعملاء، والعملاء لمحتوى ونشر، والنشر لرسائل ونتائج — بلا نسخ ولصق بين أدوات متفرقة.')) ?></p>
      <ul class="mt-6 grid sm:grid-cols-2 gap-3">
        <?php foreach ($groups as $g): ?>
          <li class="flex items-center gap-3 text-sm text-ink-700"><span class="w-8 h-8 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><?= $ic($g[0], 'w-4 h-4') ?></span><b class="font-semibold"><?= e($g[1]) ?></b></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div data-reveal="left" class="diag min-w-0">
      <svg viewBox="0 0 600 410" class="w-full h-auto" role="img" aria-label="<?= e(t('مخطط الأقسام حول المنصة')) ?>">
        <defs>
          <linearGradient id="lpHub" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2563eb"/><stop offset="1" stop-color="#06b6d4"/></linearGradient>
          <radialGradient id="lpNode" cx=".5" cy=".4" r=".7"><stop offset="0" stop-color="#eff6ff"/><stop offset="1" stop-color="#dbeafe"/></radialGradient>
        </defs>
        <?php foreach ($diag as $di => $d): ?>
          <line class="spoke" x1="<?= $cx ?>" y1="<?= $cy ?>" x2="<?= $d[0] ?>" y2="<?= $d[1] ?>" pathLength="1" stroke-dasharray="1" stroke="#93c5fd" stroke-width="2" stroke-linecap="round" style="transition-delay:<?= $di * 90 ?>ms"></line>
        <?php endforeach; ?>
        <g class="hub"><circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="50" fill="url(#lpHub)"/><circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="50" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="2"/><?php $hubW = preg_split('/\s+/', trim(t('شركتك'))); ?><text x="<?= $cx ?>" y="<?= $cy + (count($hubW) > 1 ? -2 : 6) ?>" text-anchor="middle" fill="#fff" font-size="<?= count($hubW) > 1 ? 14 : 17 ?>" font-weight="800"><?php foreach ($hubW as $wi => $w): ?><tspan x="<?= $cx ?>"<?= $wi ? ' dy="16"' : '' ?>><?= e($w) ?></tspan><?php endforeach; ?></text></g>
        <?php foreach ($diag as $di => $d): $ly = $d[1] <= $cy ? $d[1] + 52 : $d[1] - 44; ?>
          <g class="node" style="transition-delay:<?= $di * 90 + 420 ?>ms">
            <circle cx="<?= $d[0] ?>" cy="<?= $d[1] ?>" r="34" fill="url(#lpNode)" stroke="#bfdbfe" stroke-width="1.5"/>
            <svg x="<?= $d[0] - 15 ?>" y="<?= $d[1] - 15 ?>" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $d[2] ?>"/></svg>
            <text class="nlabel" x="<?= $d[0] ?>" y="<?= $ly ?>" text-anchor="middle"><?= e($d[3]) ?></text>
          </g>
        <?php endforeach; ?>
      </svg>
    </div>
  </div>
</section>

<!-- ============ منصة واحدة تغني عن أدوات ============ -->
<section class="max-w-6xl mx-auto px-4 py-20">
  <div data-reveal class="text-center max-w-2xl mx-auto">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-ink-900"><?= e(t('منصّة واحدة تغني عن أدوات كثيرة')) ?></h2>
    <p class="mt-3 text-ink-600"><?= e(t('بدل اشتراكات متفرّقة لا تتكلّم مع بعضها — كل شيء في مكان واحد، بنفس البيانات والفريق.')) ?></p>
  </div>
  <div class="mt-10 flex flex-col lg:flex-row gap-6 items-center">
    <div data-reveal class="flex flex-wrap gap-2.5 justify-center lg:justify-start lg:flex-1">
      <?php foreach ($replaces as $ri => $rp): ?>
        <span data-reveal data-reveal-delay="<?= $ri * 45 ?>" class="strike inline-flex items-center rounded-xl border border-ink-200 bg-ink-50 px-3.5 py-2 text-sm font-medium text-ink-600"><?= e($rp) ?></span>
      <?php endforeach; ?>
    </div>
    <div data-reveal data-reveal-delay="200" class="arrow hidden lg:flex items-center justify-center text-brand-400 shrink-0"><?= $ic('M5 12h14M13 6l6 6-6 6', 'w-9 h-9') ?></div>
    <div data-reveal="scale" data-reveal-delay="300" class="card rounded-3xl p-6 text-center text-white shadow-2xl shadow-brand-900/20 mx-auto" data-tilt style="background:linear-gradient(135deg,#1d4ed8,#06b6d4)">
      <span class="cic inline-grid w-14 h-14 rounded-2xl bg-white/15 place-items-center mx-auto mb-3"><?= $ic('M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z', 'w-7 h-7') ?></span>
      <div class="text-xl font-extrabold"><?= e($siteName) ?></div>
      <div class="mt-1 text-sm" style="color:rgba(255,255,255,.85)"><?= e(t('اشتراك واحد · حساب واحد')) ?></div>
    </div>
  </div>
</section>

<!-- ============ كيف يعمل (خط تقدّم مربوط بالتمرير) ============ -->
<section id="how" class="bg-ink-50/60 border-y border-ink-100 scroll-mt-20">
  <div class="max-w-6xl mx-auto px-4 py-20">
    <div data-reveal class="text-center max-w-2xl mx-auto"><h2 class="text-3xl sm:text-4xl font-extrabold text-ink-900"><?= e(t('كيف يعمل')) ?></h2><p class="mt-3 text-ink-600"><?= e(t('أربع خطوات من التسجيل إلى أول نتيجة.')) ?></p></div>
    <div class="relative mt-14" id="stepsBox">
      <div class="steps-line hidden lg:block"><i id="stepsFill"></i></div>
      <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 lg:pt-8">
        <?php foreach ($steps as $i => $s): ?>
          <li data-reveal data-reveal-delay="<?= $i * 90 ?>" class="step card relative rounded-3xl bg-white border border-ink-200 p-6" data-tilt><span class="step-dot w-11 h-11 rounded-full bg-gradient-to-br from-brand-600 to-cyan-500 text-white font-bold grid place-items-center"><?= $i + 1 ?></span>
            <h3 class="mt-4 font-bold text-ink-900"><?= e($s[0]) ?></h3><p class="mt-2 text-sm text-ink-600 leading-relaxed"><?= e($s[1]) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section id="faq" class="max-w-3xl mx-auto px-4 py-20 scroll-mt-20">
  <h2 data-reveal class="text-3xl sm:text-4xl font-extrabold text-center text-ink-900"><?= e(t('الأسئلة الشائعة')) ?></h2>
  <div class="mt-10 space-y-3">
    <?php foreach ($faq as $qi => $q): ?>
      <details data-reveal data-reveal-delay="<?= $qi * 55 ?>" class="faq rounded-2xl border border-ink-200 bg-white px-5 py-4 transition-colors hover:border-brand-200"><summary class="cursor-pointer list-none flex items-center gap-3 font-semibold text-ink-900"><span class="flex-1"><?= e($q[0]) ?></span><span class="chev transition text-ink-400"><?= $ic('M6 9l6 6 6-6', 'w-5 h-5') ?></span></summary><div class="ans"><div><p class="mt-3 text-sm text-ink-600 leading-relaxed"><?= e($q[1]) ?></p></div></div></details>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="max-w-6xl mx-auto px-4 pb-20">
  <div data-reveal class="cta rounded-[2rem] p-10 sm:p-16 text-center relative" id="ctaBox" style="color:#fff">
    <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 1px 1px,rgba(255,255,255,.4) 1px,transparent 0);background-size:22px 22px"></div>
    <h2 class="relative text-3xl sm:text-5xl font-extrabold"><?= e(t('ابدأ اليوم وأدر كل شيء من مكان واحد')) ?></h2>
    <p class="relative mt-4 max-w-xl mx-auto text-lg" style="color:rgba(255,255,255,.88)"><?= e(t('سجّل، وعرّفنا بشركتك، واربط حساباتك في دقائق.')) ?></p>
    <a href="<?= e(url($canReg ? 'register' : 'login')) ?>" class="btn-shine mag relative mt-9 inline-flex items-center gap-2 rounded-2xl font-bold px-9 py-4 text-base transition hover:scale-[1.02]" style="background:#fff;color:#1d4ed8"><?= e($canReg ? t('ابدأ الآن مجانًا') : t('دخول')) ?><?= $ic('M5 12h14M13 6l6 6-6 6', 'w-5 h-5') ?></a>
  </div>
</section>

<footer class="border-t border-ink-100">
  <div class="max-w-6xl mx-auto px-4 py-8 flex flex-wrap items-center gap-4 text-sm text-ink-500"><span class="font-semibold text-ink-700"><?= e($siteName) ?></span><span>© <?= date('Y') ?></span>
    <span class="sm:hidden flex gap-2"><?= pref_switch() ?></span><a href="<?= e(url('login')) ?>" class="ms-auto hover:text-brand-600"><?= e(t('دخول')) ?></a></div>
</footer>

<button class="totop w-11 h-11 rounded-full text-white grid place-items-center shadow-xl" id="toTop" aria-label="<?= e(t('للأعلى')) ?>" style="background:linear-gradient(135deg,#2563eb,#06b6d4)"><?= $ic('M12 19V5M5 12l7-7 7 7', 'w-5 h-5') ?></button>

<script>
(function () {
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  var raf = requestAnimationFrame, doc = document;
  function $all(s, r) { return Array.prototype.slice.call((r || doc).querySelectorAll(s)); }

  /* ---- 1) ظهور عند التمرير + تتابع ---- */
  var reveals = $all('[data-reveal]');
  if (reduce || !('IntersectionObserver' in window)) { reveals.forEach(function (el) { el.classList.add('in'); }); }
  else {
    var ro = new IntersectionObserver(function (es) {
      es.forEach(function (en) { if (!en.isIntersecting) return; var el = en.target, d = +(el.getAttribute('data-reveal-delay') || 0); setTimeout(function () { el.classList.add('in'); }, d); ro.unobserve(el); });
    }, { threshold: 0.12, rootMargin: '0px 0px -7% 0px' });
    reveals.forEach(function (el) { ro.observe(el); });
  }

  /* ---- 2) عدّادات ---- */
  function fmt(el, n) { return el.getAttribute('data-format') === 'comma' ? n.toLocaleString('en-US') : String(n); }
  function count(el) { var to = +(el.getAttribute('data-to') || 0), dur = 1300, t0 = null; if (reduce) { el.textContent = fmt(el, to); return; } function tk(ts) { if (t0 === null) t0 = ts; var p = Math.min((ts - t0) / dur, 1), e = 1 - Math.pow(1 - p, 3); el.textContent = fmt(el, Math.round(to * e)); if (p < 1) raf(tk); } raf(tk); }
  /* ---- 3) أعمدة المخطط ---- */
  function bars(root) { $all('.bar', root).forEach(function (b) { b.style.height = (b.getAttribute('data-h') || 40) + '%'; }); }
  var once = new WeakSet();
  if ('IntersectionObserver' in window) {
    var io2 = new IntersectionObserver(function (es) {
      es.forEach(function (en) { if (!en.isIntersecting || once.has(en.target)) return; once.add(en.target); var el = en.target; if (el.classList.contains('lp-count')) count(el); else bars(el); });
    }, { threshold: 0.5 });
    $all('.lp-count').forEach(function (el) { io2.observe(el); });
    $all('.tilt, .glass').forEach(function (el) { if ($all('.bar', el).length) io2.observe(el); });
    $all('.card').forEach(function (el) { if ($all('.bar', el).length) io2.observe(el); });
  } else { $all('.lp-count').forEach(count); bars(doc); }

  /* ---- 4) rotator الكلمات ---- */
  (function () {
    var host = doc.querySelector('.rot'); if (!host) return;
    var words = []; try { words = JSON.parse(host.getAttribute('data-rotate') || '[]'); } catch (e) {}
    if (!words.length) return;
    words.forEach(function (w, i) { var s = doc.createElement('span'); s.textContent = w; if (i === 0) s.className = 'on'; host.appendChild(s); });
    if (reduce) return;
    var items = $all('span', host), i = 0;
    setInterval(function () { var cur = items[i], nx = items[(i + 1) % items.length]; cur.className = 'off'; nx.className = 'on'; i = (i + 1) % items.length; }, 2300);
  })();

  /* ---- 5) شريط التقدّم + هيدر يصغّر + زر للأعلى ---- */
  var prog = doc.getElementById('lpProg'), head = doc.querySelector('.lp-head'), toTop = doc.getElementById('toTop');
  function onScroll() {
    var h = doc.documentElement, max = h.scrollHeight - h.clientHeight, y = h.scrollTop || doc.body.scrollTop;
    if (prog) prog.style.width = (max > 0 ? (y / max) * 100 : 0) + '%';
    if (head) head.classList.toggle('shrunk', y > 24);
    if (toTop) toTop.classList.toggle('on', y > 600);
    stepsProgress(y);
  }
  if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  /* ---- 6) خط تقدّم خطوات "كيف يعمل" مربوط بالتمرير ---- */
  var stepsBox = doc.getElementById('stepsBox'), stepsFill = doc.getElementById('stepsFill'), stepEls = $all('.step');
  function stepsProgress() {
    if (!stepsBox || !stepsFill) return;
    var r = stepsBox.getBoundingClientRect(), vh = window.innerHeight;
    var p = Math.max(0, Math.min(1, (vh * 0.75 - r.top) / (r.height * 0.7)));
    stepsFill.style.width = (p * 100) + '%';
    stepEls.forEach(function (s, i) { s.classList.toggle('on', p >= (i + 0.5) / stepEls.length); });
  }

  /* ---- 7) 3D tilt ---- */
  if (!reduce) $all('[data-tilt]').forEach(function (el) {
    el.addEventListener('mousemove', function (e) {
      var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      el.style.transform = 'perspective(1000px) rotateX(' + (-y * 7) + 'deg) rotateY(' + (x * 9) + 'deg)';
    });
    el.addEventListener('mouseleave', function () { el.style.transform = ''; });
  });
  /* ---- 8) spotlight داخل الكروت ---- */
  if (!reduce) $all('[data-spotlight], .card').forEach(function (el) {
    el.addEventListener('mousemove', function (e) { var r = el.getBoundingClientRect(); el.style.setProperty('--mx', (e.clientX - r.left) + 'px'); el.style.setProperty('--my', (e.clientY - r.top) + 'px'); });
  });
  /* ---- 9) أزرار مغناطيسية ---- */
  if (!reduce) $all('.mag').forEach(function (el) {
    el.addEventListener('mousemove', function (e) { var r = el.getBoundingClientRect(); el.style.transform = 'translate(' + ((e.clientX - r.left) / r.width - .5) * 10 + 'px,' + ((e.clientY - r.top) / r.height - .5) * 10 + 'px)'; });
    el.addEventListener('mouseleave', function () { el.style.transform = ''; });
  });

  /* ---- 10) parallax بالماوس (الهيرو) + توهّج يتبع المؤشر ---- */
  var hero = doc.getElementById('hero'), spot = doc.getElementById('heroSpot'), players = $all('[data-parallax]');
  if (hero && !reduce) hero.addEventListener('mousemove', function (e) {
    var r = hero.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
    if (spot) { spot.style.setProperty('--mx', (e.clientX - r.left) + 'px'); spot.style.setProperty('--my', (e.clientY - r.top) + 'px'); }
    players.forEach(function (p) { var d = +(p.getAttribute('data-parallax') || 0); p.style.transform = 'translate(' + (x * d) + 'px,' + (y * d) + 'px)'; });
  });

  /* ---- 11) تبويبات بمؤشّر منزلق ---- */
  (function () {
    var tabs = $all('[data-lpt]'), panes = $all('[data-lpp]'), ind = doc.getElementById('tabInd');
    function moveInd(btn) { if (!ind || !btn) return; ind.style.width = btn.offsetWidth + 'px'; ind.style.transform = 'translateX(' + (btn.offsetLeft) + 'px)'; }
    function on(k, btn) {
      tabs.forEach(function (t) { var a = t.dataset.lpt === k; t.setAttribute('aria-selected', a); t.className = 'lp-tab relative z-10 inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition ' + (a ? 'text-[#fff]' : 'text-ink-700 hover:text-brand-600'); });
      panes.forEach(function (p) { p.classList.toggle('hidden', p.dataset.lpp !== k); });
      moveInd(btn);
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { on(t.dataset.lpt, t); }); });
    if (tabs[0]) { on('0', tabs[0]); setTimeout(function () { moveInd(tabs[0]); }, 60); }
    window.addEventListener('resize', function () { var a = tabs.filter(function (t) { return t.getAttribute('aria-selected') === 'true'; })[0]; moveInd(a); });
  })();

  /* ---- 12) sparkles على الـCTA عند الـhover ---- */
  var cta = doc.getElementById('ctaBox');
  if (cta && !reduce) cta.addEventListener('mouseenter', function () {
    for (var i = 0; i < 10; i++) (function (i) {
      var s = doc.createElement('span'); s.className = 'spark'; s.style.left = (20 + Math.random() * 60) + '%'; s.style.top = (20 + Math.random() * 60) + '%';
      cta.appendChild(s);
      var dx = (Math.random() - .5) * 80, dy = -30 - Math.random() * 60;
      s.animate([{ opacity: 1, transform: 'translate(0,0) scale(1)' }, { opacity: 0, transform: 'translate(' + dx + 'px,' + dy + 'px) scale(0)' }], { duration: 700 + Math.random() * 300, easing: 'cubic-bezier(.22,1,.36,1)' }).onfinish = function () { s.remove(); };
    })(i);
  });

  /* ---- 13) كانفاس شبكة النقاط التفاعلية ---- */
  var cv = doc.querySelector('.lp-net');
  if (cv && !reduce && cv.getContext) {
    var ctx = cv.getContext('2d'), DPR = Math.min(window.devicePixelRatio || 1, 2), W = 0, H = 0, pts = [], mx = -999, my = -999, rid = 0;
    function resize() { var r = cv.getBoundingClientRect(); W = r.width; H = r.height; cv.width = W * DPR; cv.height = H * DPR; ctx.setTransform(DPR, 0, 0, DPR, 0, 0); var n = Math.max(22, Math.min(60, Math.round(W * H / 16000))); pts = []; for (var i = 0; i < n; i++) pts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35 }); }
    cv.parentNode.addEventListener('mousemove', function (e) { var r = cv.getBoundingClientRect(); mx = e.clientX - r.left; my = e.clientY - r.top; });
    cv.parentNode.addEventListener('mouseleave', function () { mx = my = -999; });
    function frame() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < pts.length; i++) { var a = pts[i]; a.x += a.vx; a.y += a.vy; if (a.x < 0 || a.x > W) a.vx *= -1; if (a.y < 0 || a.y > H) a.vy *= -1; var ddx = a.x - mx, ddy = a.y - my, dm = ddx * ddx + ddy * ddy; if (dm < 14000) { a.x += ddx / dm * 120; a.y += ddy / dm * 120; }
        for (var j = i + 1; j < pts.length; j++) { var b = pts[j], dx = a.x - b.x, dy = a.y - b.y, d2 = dx * dx + dy * dy; if (d2 < 16000) { ctx.globalAlpha = (1 - d2 / 16000) * .5; ctx.strokeStyle = '#60a5fa'; ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke(); } } }
      ctx.globalAlpha = .9; ctx.fillStyle = '#7dd3fc'; for (var k = 0; k < pts.length; k++) { ctx.beginPath(); ctx.arc(pts[k].x, pts[k].y, 1.7, 0, 6.283); ctx.fill(); }
      ctx.globalAlpha = 1; rid = raf(frame);
    }
    doc.addEventListener('visibilitychange', function () { if (doc.hidden) cancelAnimationFrame(rid); else rid = raf(frame); });
    var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(resize, 200); });
    resize(); frame();
  }

  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
})();
</script>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>" defer></script>
</body>
</html>
