<?php
$pageTitle = 'من نحن - PioneerIcons';
require_once 'includes/config.php';
include 'includes/header.php';
?>

<section class="hero-bg py-20 text-white" style="position:relative;overflow:hidden;">
  <div class="hero-glow"></div>
  <div class="absolute inset-0 opacity-10 pointer-events-none" style="background:radial-gradient(circle at 80% 20%,#a855f7 0%,transparent 50%),radial-gradient(circle at 10% 80%,#7c3aed 0%,transparent 40%);"></div>
  <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
    <div class="hero-animate-1 inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-purple-200">
      <i class="fa-solid fa-star text-yellow-300 text-xs"></i> السجل العربي الأول للشخصيات والمؤسسات المؤثرة
    </div>
    <h1 class="hero-animate-2 text-4xl md:text-6xl font-black mb-6 leading-tight">قصتنا ورسالتنا</h1>
    <p class="hero-animate-3 text-purple-200 text-xl font-medium max-w-2xl mx-auto leading-relaxed">أُسسنا لأن العالم يحتاج إلى مرجع عربي موثوق — مكان واحد يجد فيه الإعلام والباحثون وأصحاب القرار ما يحتاجونه عن كل من يصنع التأثير في المنطقة العربية.</p>
  </div>
</section>

<!-- Mission -->
<section class="py-20 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center">
      <div>
        <p class="pi-reveal text-xs font-black text-purple-600 tracking-widest uppercase mb-3">رسالتنا</p>
        <h2 class="pi-reveal pi-delay-1 text-3xl font-black text-gray-900 mb-5 leading-tight">نبني السجل الموثق<br>لمن يصنع التأثير في العالم العربي</h2>
        <p class="pi-reveal pi-delay-2 text-gray-600 text-base leading-8 mb-5">
          <?= pi_setting('site_name') ?> ليست مجرد دليل — نحن قاعدة البيانات المرجعية الأولى التي يعتمد عليها الإعلام والمؤسسات الحكومية ولجان الجوائز والباحثون للتحقق من هوية الشخصيات والمؤسسات العربية المؤثرة.
        </p>
        <p class="pi-reveal pi-delay-3 text-gray-600 text-base leading-8">
          في عالم تتزاحم فيه المعلومات المغلوطة، نوفر مصدراً واحداً موثوقاً — يتحكم فيه صاحبه بدقة، ويُقتبس منه بثقة، ويظل مرجعاً عبر الزمن.
        </p>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div class="bg-purple-50 rounded-2xl p-6 text-center">
          <p class="text-4xl font-black text-purple-700 mb-1">+<?= number_format(pi_count_personalities()) ?></p>
          <p class="text-gray-600 text-sm font-semibold">شخصية موثقة</p>
        </div>
        <div class="bg-blue-50 rounded-2xl p-6 text-center">
          <p class="text-4xl font-black text-blue-700 mb-1">+<?= number_format(pi_count_institutions()) ?></p>
          <p class="text-gray-600 text-sm font-semibold">مؤسسة وشركة</p>
        </div>
        <div class="bg-amber-50 rounded-2xl p-6 text-center">
          <p class="text-4xl font-black text-amber-700 mb-1">17+</p>
          <p class="text-gray-600 text-sm font-semibold">دولة عربية</p>
        </div>
        <div class="bg-green-50 rounded-2xl p-6 text-center">
          <p class="text-4xl font-black text-green-700 mb-1">100%</p>
          <p class="text-gray-600 text-sm font-semibold">محتوى محقّق</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Values -->
<section class="py-20 bg-gray-50">
  <div class="max-w-5xl mx-auto px-4">
    <p class="pi-reveal text-xs font-black text-purple-600 tracking-widest uppercase text-center mb-3">ما يميزنا</p>
    <h2 class="pi-reveal pi-delay-1 text-3xl font-black text-gray-900 text-center mb-14">ثلاثة مبادئ تجعلنا المرجع الأول<br>لا مجرد موقع آخر</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div class="pi-reveal bg-white rounded-2xl p-8 shadow-sm">
        <div class="w-14 h-14 rounded-2xl pi-gradient flex items-center justify-center mb-5">
          <i class="fa-solid fa-shield-halved text-white text-xl"></i>
        </div>
        <h3 class="text-xl font-black text-gray-800 mb-3">التوثيق الذي يُوثق به</h3>
        <p class="text-gray-500 text-sm leading-7">كل ملف يمر بمنظومة تحقق صارمة قبل النشر. لهذا تلجأ إلينا المؤسسات الإعلامية وجهات التحقق الدولية — لأن سمعتنا مبنية على دقة كل كلمة ننشرها.</p>
      </div>
      <div class="pi-reveal pi-delay-1 bg-white rounded-2xl p-8 shadow-sm">
        <div class="w-14 h-14 rounded-2xl bg-blue-600 flex items-center justify-center mb-5">
          <i class="fa-solid fa-lock text-white text-xl"></i>
        </div>
        <h3 class="text-xl font-black text-gray-800 mb-3">صاحبه يتحكم فيه</h3>
        <p class="text-gray-500 text-sm leading-7">صاحب الصفحة هو الوحيد الذي يكتب قصته. نحن نوثق، لا نفرض روايات. هذا ما يجعل ملفاتنا مصدراً للحقيقة، لا للتسويق.</p>
      </div>
      <div class="pi-reveal pi-delay-2 bg-white rounded-2xl p-8 shadow-sm">
        <div class="w-14 h-14 rounded-2xl bg-amber-500 flex items-center justify-center mb-5">
          <i class="fa-solid fa-globe text-white text-xl"></i>
        </div>
        <h3 class="text-xl font-black text-gray-800 mb-3">عربي في جوهره</h3>
        <p class="text-gray-500 text-sm leading-7">المنصة الوحيدة التي بُنيت للمشهد العربي، بكل تعقيداته وخصوصياته. لا ترجمة، لا اقتباس — منصة عربية أصيلة تفهم سياقها وتحترم هويتها.</p>
      </div>
    </div>
  </div>
</section>

<!-- Vision -->
<section class="py-20" style="background:linear-gradient(135deg,#0B0B1F 0%,#1A0D35 100%);">
  <div class="max-w-3xl mx-auto px-4 text-center">
    <p class="pi-reveal text-xs font-black text-purple-400 tracking-widest uppercase mb-4">رؤيتنا</p>
    <h2 class="pi-reveal pi-delay-1 text-3xl md:text-4xl font-black text-white mb-6 leading-tight">أن يُقتبس منا في كل قرار<br>يُتخذ بشأن عربي مؤثر</h2>
    <p class="pi-reveal pi-delay-2 text-purple-300 text-lg leading-8 mb-10">نطمح إلى أن تكون منصتنا المحطة الأولى لكل صحفي يريد التحقق، وكل مستثمر يريد معرفة شريكه، وكل جهة تريد اختيار مرشحيها — بمعلومات عربية، موثقة، في ثوانٍ.</p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="add_personality.php" class="px-8 py-4 pi-primary-bg text-white font-black rounded-2xl hover:opacity-90 transition">
        <i class="fa-solid fa-user-plus ml-2"></i> سجّل شخصيتك
      </a>
      <a href="advertise.php" class="px-8 py-4 bg-white/10 border border-white/20 text-white font-black rounded-2xl hover:bg-white/20 transition">
        <i class="fa-solid fa-handshake ml-2"></i> شاركنا كراعٍ
      </a>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
