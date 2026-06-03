<?php
$pageTitle = 'شروط الاستخدام - PioneerIcons';
require_once 'includes/config.php';
include 'includes/header.php';
?>

<section class="hero-bg py-16 text-white">
  <div class="hero-glow"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="w-14 h-14 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center mx-auto mb-5">
      <i class="fa-solid fa-file-contract text-white text-xl"></i>
    </div>
    <h1 class="text-4xl font-black mb-3">شروط الاستخدام</h1>
    <p class="text-purple-200 font-medium">آخر تحديث: يناير 2026</p>
  </div>
</section>

<div class="max-w-3xl mx-auto px-4 py-16">
  <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-10">
    <p class="text-amber-800 font-semibold text-sm leading-7">
      <i class="fa-solid fa-triangle-exclamation text-amber-600 ml-2"></i>
      يُرجى قراءة هذه الشروط بعناية قبل استخدام المنصة. باستخدامك لـ <?= pi_setting('site_name') ?>، فإنك توافق صراحةً على الالتزام بجميع ما يرد فيها.
    </p>
  </div>

  <div class="space-y-8" style="font-family:'Cairo',sans-serif;">

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-circle-info text-white text-xs"></i></span>
        ١. طبيعة المنصة وهويتها
      </h2>
      <div class="text-gray-600 text-sm leading-8">
        <p class="mb-3"><?= pi_setting('site_name') ?> منصة رقمية مرجعية متخصصة في توثيق الشخصيات والمؤسسات العربية. نعمل وفق نموذج مفتوح يُتيح للعموم اقتراح الإضافات، مع خضوع كل اقتراح لمراجعة تحريرية دقيقة قبل النشر.</p>
        <p>المنصة ليست شبكة اجتماعية ولا منصة إعلانية عشوائية — نحن سجل موثق للريادة والتأثير العربي، وكل محتوى يُنشر يحمل ثقل مسؤوليتنا المهنية.</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-user-check text-white text-xs"></i></span>
        ٢. شروط الاستخدام المقبول
      </h2>
      <div class="text-gray-600 text-sm leading-8">
        <p class="mb-3">بقبولك لهذه الشروط، تتعهد بما يلي:</p>
        <ul class="space-y-2 mr-4">
          <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-green-500 mt-1 flex-shrink-0 text-xs"></i><span>تقديم معلومات صحيحة ودقيقة ومحدّثة عند أي اقتراح أو طلب</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-green-500 mt-1 flex-shrink-0 text-xs"></i><span>عدم تقديم معلومات مضللة أو مزورة أو منقوصة بقصد</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-green-500 mt-1 flex-shrink-0 text-xs"></i><span>احترام خصوصية الأفراد والمؤسسات المدرجة على المنصة</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-green-500 mt-1 flex-shrink-0 text-xs"></i><span>عدم استخدام المنصة لأغراض تشهيرية أو انتقامية أو مضايقة الآخرين</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-circle-check text-green-500 mt-1 flex-shrink-0 text-xs"></i><span>الامتناع عن أي محاولة للنفاذ غير المصرح به إلى الأنظمة أو قواعد البيانات</span></li>
        </ul>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-ban text-white text-xs"></i></span>
        ٣. الاستخدامات المحظورة
      </h2>
      <div class="text-gray-600 text-sm leading-8">
        <p class="mb-3">يُحظر صراحةً ما يلي:</p>
        <ul class="space-y-2 mr-4">
          <li class="flex items-start gap-2"><i class="fa-solid fa-xmark text-red-500 mt-1 flex-shrink-0 text-xs font-black"></i><span>اقتراح إضافة شخصية أو مؤسسة بهدف الإضرار بها أو تشويه سمعتها</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-xmark text-red-500 mt-1 flex-shrink-0 text-xs font-black"></i><span>انتزاع بيانات المنصة بشكل آلي (Scraping) دون إذن كتابي مسبق</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-xmark text-red-500 mt-1 flex-shrink-0 text-xs font-black"></i><span>إعادة نشر محتوى المنصة تجارياً دون ترخيص رسمي</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-xmark text-red-500 mt-1 flex-shrink-0 text-xs font-black"></i><span>انتحال صفة شخص أو مؤسسة دون تفويض صريح منها</span></li>
          <li class="flex items-start gap-2"><i class="fa-solid fa-xmark text-red-500 mt-1 flex-shrink-0 text-xs font-black"></i><span>أي نشاط يُخل بأمن المنصة أو يُعيق وصول المستخدمين الآخرين إليها</span></li>
        </ul>
        <p class="mt-4 font-semibold text-gray-700">المخالفة تستوجب الحذف الفوري وقد تستلزم اتخاذ إجراءات قانونية.</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-copyright text-white text-xs"></i></span>
        ٤. الملكية الفكرية
      </h2>
      <div class="text-gray-600 text-sm leading-8 space-y-3">
        <p>جميع عناصر المنصة — من تصميم وكود وهوية بصرية وعلامات تجارية — هي ملك حصري لـ <?= pi_setting('site_name') ?> ومحمية قانونياً. لا يجوز نسخها أو تقليدها أو استخدامها دون إذن كتابي مسبق.</p>
        <p>أما المحتوى الذي تُقدمه بنفسك (نصوص، صور، بيانات)، فأنت تمنحنا ترخيصاً غير حصري لعرضه على المنصة مع احتفاظك بملكيته الكاملة.</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-hand-holding-dollar text-white text-xs"></i></span>
        ٥. العضويات والمدفوعات
      </h2>
      <div class="text-gray-600 text-sm leading-8 space-y-3">
        <p>تُعتبر رسوم العضوية والخدمات المدفوعة غير قابلة للاسترداد بعد تفعيل الخدمة، إلا في حالات موثقة من خللٍ تقني من جانبنا.</p>
        <p>نحتفظ بالحق في تعديل الأسعار مع إشعار مسبق لا يقل عن 30 يوماً. تجديد الاشتراك يعني قبولك للسعر الجديد.</p>
        <p>في حال إخلالك بهذه الشروط، نحتفظ بالحق في إلغاء عضويتك دون استرداد، وذلك بعد إشعارك وإتاحة فرصة للتوضيح.</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-scale-balanced text-white text-xs"></i></span>
        ٦. حدود المسؤولية
      </h2>
      <div class="text-gray-600 text-sm leading-8 space-y-3">
        <p><?= pi_setting('site_name') ?> مسؤولة عن دقة المحتوى الذي تُعدّه وتنشره بنفسها. أما المحتوى المقترح من المستخدمين، فيخضع للمراجعة ولكننا نعتمد على دقة ما يُقدَّم لنا.</p>
        <p>لسنا مسؤولين عن أي قرارات تجارية أو شخصية تُتخذ بناءً على المعلومات الواردة في الصفحات الشخصية أو صفحات المؤسسات.</p>
        <p>لا تضمن المنصة توافراً مستمراً بنسبة 100%، وإن كنا نبذل قصارى جهدنا للوصول إلى أعلى معدلات التشغيل.</p>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
      <h2 class="text-xl font-black text-gray-900 mb-4 flex items-center gap-3">
        <span class="w-8 h-8 rounded-lg pi-gradient flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-gavel text-white text-xs"></i></span>
        ٧. القانون الواجب التطبيق
      </h2>
      <p class="text-gray-600 text-sm leading-8">تخضع هذه الشروط وتُفسَّر وفقاً للقوانين المعمول بها في مقر الشركة. أي نزاع ينشأ عن استخدام المنصة يُفضَّل حله بالتفاوض الودي أولاً، وفي حال تعذّر ذلك يُحال إلى الجهة القضائية المختصة.</p>
    </div>

  </div>

  <div class="mt-10 bg-gray-900 rounded-2xl p-8 text-center text-white">
    <h3 class="text-lg font-black mb-2">استفسار عن الشروط؟</h3>
    <p class="text-gray-400 text-sm mb-4">فريقنا القانوني يرد على استفساراتك خلال يومي عمل</p>
    <a href="advertise.php" class="inline-block px-6 py-3 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition text-sm">
      تواصل معنا
    </a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
