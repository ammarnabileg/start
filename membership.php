<?php
require_once 'includes/config.php';

$mysqli->query("CREATE TABLE IF NOT EXISTS pi_memberships (
  mem_id INT AUTO_INCREMENT PRIMARY KEY,
  mem_type ENUM('verified','executive') DEFAULT 'verified',
  mem_plan ENUM('monthly','lifetime') NOT NULL,
  mem_name VARCHAR(200) NOT NULL,
  mem_phone VARCHAR(50) NOT NULL,
  mem_email VARCHAR(200) NOT NULL,
  mem_profile_url VARCHAR(500),
  mem_status ENUM('pending','active','cancelled') DEFAULT 'pending',
  mem_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Add mem_type column if missing (old installs)
$r = $mysqli->query("SHOW COLUMNS FROM pi_memberships LIKE 'mem_type'");
if ($r && $r->num_rows === 0) {
    $mysqli->query("ALTER TABLE pi_memberships ADD COLUMN mem_type ENUM('verified','executive') DEFAULT 'verified' AFTER mem_id");
}

$mem_type = ($_GET['type'] ?? '') === 'executive' ? 'executive' : 'verified';
$pageTitle = ($mem_type === 'executive' ? 'عضوية الرؤساء التنفيذيين' : 'العضوية الموثقة') . ' - PioneerIcons';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan    = ($_POST['mem_plan']??'') === 'lifetime' ? 'lifetime' : 'monthly';
    $type    = ($_POST['mem_type']??'') === 'executive' ? 'executive' : 'verified';
    $name    = trim($_POST['mem_name'] ?? '');
    $phone   = trim($_POST['mem_phone'] ?? '');
    $email   = trim($_POST['mem_email'] ?? '');
    $profile = trim($_POST['mem_profile_url'] ?? '');
    if (!$name)  $errors[] = 'الاسم مطلوب';
    if (!$phone) $errors[] = 'رقم الجوال مطلوب';
    if (!$email) $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($errors)) {
        $pl=pi_escape($plan);$tp=pi_escape($type);$n=pi_escape($name);$ph=pi_escape($phone);$e=pi_escape($email);$pr=pi_escape($profile);
        $mysqli->query("INSERT INTO pi_memberships(mem_type,mem_plan,mem_name,mem_phone,mem_email,mem_profile_url)VALUES('$tp','$pl','$n','$ph','$e','$pr')");
        $success = true;
    }
}

// Fetch social proof data
$verified_people = [];
$r = $mysqli->query("SELECT p_id,p_name_ar,p_title,p_photo FROM pi_personalities WHERE p_active=1 AND p_verified=1 ORDER BY p_views DESC LIMIT 12");
if ($r) while ($row=$r->fetch_assoc()) $verified_people[] = $row;

$verified_insts = [];
$r = $mysqli->query("SELECT inst_id,inst_name_ar,inst_logo FROM pi_institutions WHERE inst_active=1 AND inst_verified=1 ORDER BY inst_views DESC LIMIT 8");
if ($r) while ($row=$r->fetch_assoc()) $verified_insts[] = $row;

$total_p = pi_count_personalities();
$total_i = pi_count_institutions();
$rr = $mysqli->query("SELECT SUM(p_views) c FROM pi_personalities WHERE p_active=1");
$total_views = $rr ? (int)$rr->fetch_assoc()['c'] : 0;
$rr = $mysqli->query("SELECT COUNT(DISTINCT p_country_id) c FROM pi_personalities WHERE p_active=1 AND p_country_id > 0");
$total_countries = $rr ? max((int)$rr->fetch_assoc()['c'], 12) : 12;

include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="max-w-xl mx-auto px-4 py-20 text-center">
  <div class="bg-green-50 border border-green-200 rounded-3xl p-14">
    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
      <i class="fa-solid fa-circle-check text-green-500 text-5xl"></i>
    </div>
    <h2 class="text-2xl font-black text-green-800 mb-3">تم استلام طلبك!</h2>
    <p class="text-green-600 mb-6 font-medium">سيراجع فريقنا طلبك ويتواصل معك قريباً لإتمام التوثيق</p>
    <a href="index.php" class="inline-block px-8 py-3 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition">العودة للرئيسية</a>
  </div>
</div>
<?php include 'includes/footer.php'; ?><?php exit; ?>
<?php endif; ?>

<?php if ($mem_type === 'executive'): ?>

<!-- ═══════ EXECUTIVE PAGE ═══════ -->

<!-- HERO -->
<section class="py-20 text-white" style="background:linear-gradient(135deg,#78350f 0%,#92400e 40%,#b45309 100%);position:relative;overflow:hidden;">
  <div class="absolute inset-0 opacity-15" style="background-image:radial-gradient(circle at 15% 60%,#fbbf24 0%,transparent 55%),radial-gradient(circle at 85% 15%,#f59e0b 0%,transparent 50%);pointer-events:none;"></div>
  <div class="absolute inset-0 opacity-5" style="background-image:url('data:image/svg+xml,%3Csvg width=60 height=60 xmlns=http://www.w3.org/2000/svg%3E%3Cpath d=%27M0 0l60 60M0 60L60 0%27 stroke=%23fff stroke-width=.5/%3E%3C/svg%3E');pointer-events:none;"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-yellow-300/30 rounded-full px-5 py-2 text-sm font-bold mb-6 text-yellow-200">
      <i class="fa-solid fa-crown text-yellow-300 text-xs"></i> صوتك القيادي يستحق منصة تليق به
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-5 leading-tight">باقة الرؤساء التنفيذيين</h1>
    <p class="text-amber-200 text-lg font-medium mb-8 max-w-xl mx-auto leading-relaxed">كل يوم يبحث مستثمر أو صحفي أو شريك محتمل عن اسمك — السؤال هو: ماذا سيجد؟ هذه الباقة تضمن أنه سيجد الصورة التي تريدها أنت، لا أي صورة أخرى.</p>
    <div class="flex flex-wrap items-center justify-center gap-4">
      <a href="#pricing-section" onclick="event.preventDefault();document.getElementById('pricing-section').scrollIntoView({behavior:'smooth'})"
        class="inline-flex items-center gap-2 px-8 py-4 font-black rounded-2xl text-amber-900 hover:brightness-110 transition"
        style="background:linear-gradient(135deg,#fde68a,#f59e0b);box-shadow:0 4px 20px rgba(251,191,36,.4)">
        <i class="fa-solid fa-crown"></i> احصل على الباقة
      </a>
      <a href="membership.php?type=verified" class="inline-flex items-center gap-2 px-6 py-4 font-bold rounded-2xl border border-white/20 bg-white/10 hover:bg-white/20 transition text-sm">
        <i class="fa-solid fa-circle-check text-blue-300"></i> التوثيق فقط
      </a>
    </div>
  </div>
</section>

<!-- KEY BENEFITS -->
<section class="py-14 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-2xl font-black text-gray-800 text-center mb-2">ما الذي سيتغير بعد انضمامك؟</h2>
    <p class="text-gray-400 text-center mb-10 font-medium">نتائج ملموسة — لا وعود فارغة</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="rounded-2xl p-6 border-2 border-amber-100 bg-amber-50">
        <div class="w-12 h-12 rounded-2xl bg-amber-500 flex items-center justify-center mb-4">
          <i class="fa-solid fa-crown text-white text-lg"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">يجدك الناس بصورتك التي تريدها</h3>
        <p class="text-gray-500 text-sm leading-relaxed">قصتك القيادية مكتوبة بشكل احترافي — أنت من يحدد ما يعرفه الآخرون عنك، لا محرك البحث</p>
      </div>
      <div class="rounded-2xl p-6 border-2 border-purple-100 bg-purple-50">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-star text-white text-lg"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">تتصدر النتائج قبل أي منافس</h3>
        <p class="text-gray-500 text-sm leading-relaxed">كل باحث عن اسمك أو مجالك يصل إليك أولاً — لأن ملفك مُعطى أولوية في البحث والتصنيفات</p>
      </div>
      <div class="rounded-2xl p-6 border-2 border-blue-100 bg-blue-50">
        <div class="w-12 h-12 rounded-2xl bg-blue-500 flex items-center justify-center mb-4">
          <i class="fa-solid fa-building text-white text-lg"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">تفتح أبواباً لم تكن متاحة</h3>
        <p class="text-gray-500 text-sm leading-relaxed">حضور في منصات إعلامية عربية كبرى — فرص للتعرف عليك من جمهور لم يكن يعلم بوجودك</p>
      </div>
    </div>
  </div>
</section>

<!-- STATS -->
<section class="py-12 bg-gray-50 border-y border-gray-100">
  <div class="max-w-4xl mx-auto px-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
      <div>
        <p class="text-4xl font-black text-amber-700"><?= number_format($total_p) ?>+</p>
        <p class="text-gray-500 text-sm font-semibold mt-1">شخصية موثقة</p>
      </div>
      <div>
        <p class="text-4xl font-black text-amber-700"><?= number_format($total_i) ?>+</p>
        <p class="text-gray-500 text-sm font-semibold mt-1">مؤسسة وشركة</p>
      </div>
      <div>
        <p class="text-4xl font-black text-amber-700"><?= $total_countries ?>+</p>
        <p class="text-gray-500 text-sm font-semibold mt-1">دولة عربية</p>
      </div>
      <div>
        <p class="text-4xl font-black text-amber-700"><?= number_format($total_views) ?>+</p>
        <p class="text-gray-500 text-sm font-semibold mt-1">مشاهدة كل شهر</p>
      </div>
    </div>
  </div>
</section>

<!-- PRICING -->
<div id="pricing-section" class="max-w-4xl mx-auto px-4 py-16">
  <h2 class="text-3xl font-black text-gray-800 text-center mb-3">اختر باقتك</h2>
  <p class="text-gray-400 text-center mb-10 font-medium">باقة متكاملة تشمل التوثيق وخدمات القيادة الحصرية</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
    <!-- Monthly Executive -->
    <div class="bg-white rounded-2xl shadow-sm border-2 border-amber-200 p-8 flex flex-col">
      <div class="mb-6">
        <p class="text-amber-700 font-semibold text-sm mb-1">الباقة الشهرية</p>
        <div class="flex items-end gap-1">
          <span class="text-5xl font-black text-gray-800">$210</span>
          <span class="text-gray-400 font-semibold mb-1">/ شهر</span>
        </div>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-start gap-2 text-sm text-gray-700 font-semibold"><i class="fa-solid fa-circle-check text-blue-500 w-4 mt-0.5"></i> <span>يثق بك الجمهور فوراً <span class="text-xs text-blue-500 font-bold">(شارة موثق مشمولة)</span></span></li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-crown text-yellow-500 w-4 mt-0.5"></i> يعرف الجميع أنك في مستوى القيادة التنفيذية</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> صفحتك تنطق بثقلك — تصميم VIP لا يشبه أي ملف عادي</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> تظهر أولاً لكل من يبحث في مجالك</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> أنت من يتحكم في قصتك — تعدّل وقتما تشاء</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> بطاقتك الرسمية جاهزة للتحميل في أي اجتماع</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> فريقنا بجانبك متى احتجت — لا تنتظر</li>
      </ul>
      <button onclick="choosePlan('monthly','executive')"
        class="w-full py-3.5 font-black rounded-xl hover:opacity-90 transition text-white" style="background:linear-gradient(135deg,#92400e,#b45309)">
        اشترك الآن
      </button>
    </div>

    <!-- Lifetime Executive -->
    <div class="rounded-2xl shadow-xl p-8 flex flex-col relative overflow-hidden text-white" style="background:linear-gradient(135deg,#78350f,#b45309)">
      <div class="absolute top-4 left-4">
        <span class="px-3 py-1 text-xs font-black text-amber-900 rounded-full bg-yellow-300">الأوفر 👑</span>
      </div>
      <div class="mb-6 mt-4">
        <p class="text-amber-300 font-semibold text-sm mb-1">مدى الحياة</p>
        <div class="flex items-end gap-2">
          <span class="text-5xl font-black">$250</span>
          <div class="mb-1">
            <span class="text-amber-300 line-through text-sm font-semibold block">$2,520</span>
            <span class="text-green-300 text-xs font-bold">وفّر 90%</span>
          </div>
        </div>
        <p class="text-amber-200 text-sm mt-1">دفعة واحدة — مدى الحياة</p>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-start gap-2 text-sm font-semibold"><i class="fa-solid fa-circle-check text-blue-300 w-4 mt-0.5"></i> <span>يثق بك الجمهور فوراً <span class="text-xs text-blue-300">(شارة موثق مشمولة)</span></span></li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-crown text-yellow-300 w-4 mt-0.5"></i> يعرف الجميع أنك في مستوى القيادة التنفيذية</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> صفحتك تنطق بثقلك — تصميم VIP لا يشبه أي ملف عادي</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> تظهر أولاً لكل من يبحث في مجالك</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> أنت من يتحكم في قصتك — تعدّل وقتما تشاء</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> بطاقتك الرسمية جاهزة للتحميل في أي اجتماع</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> فريقنا بجانبك متى احتجت — لا تنتظر</li>
        <li class="flex items-start gap-2 text-sm"><i class="fa-solid fa-check text-green-300 w-4 mt-0.5"></i> تجديد تلقائي مجاني — لا تقلق بشأن الانتهاء</li>
      </ul>
      <div class="rounded-2xl mb-4 p-3" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);">
        <p class="text-xs font-bold text-amber-200 text-center mb-2">⏳ سعر الإطلاق ينتهي خلال</p>
        <div class="flex justify-center gap-2">
          <div class="text-center"><div class="text-2xl font-black" id="me-cd-d">00</div><div class="text-xs text-amber-300 font-semibold">يوم</div></div>
          <div class="text-2xl font-black text-amber-300 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black" id="me-cd-h">00</div><div class="text-xs text-amber-300 font-semibold">ساعة</div></div>
          <div class="text-2xl font-black text-amber-300 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black" id="me-cd-m">00</div><div class="text-xs text-amber-300 font-semibold">دقيقة</div></div>
          <div class="text-2xl font-black text-amber-300 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black" id="me-cd-s">00</div><div class="text-xs text-amber-300 font-semibold">ثانية</div></div>
        </div>
      </div>
      <button onclick="choosePlan('lifetime','executive')"
        class="w-full py-3.5 font-black rounded-xl hover:brightness-110 transition text-amber-900 bg-yellow-300">
        اشترك الآن
      </button>
    </div>
  </div>
</div>

<?php else: ?>

<!-- ═══════ VERIFIED PAGE ═══════ -->

<!-- HERO -->
<section class="hero-bg py-20 text-white" style="position:relative;overflow:hidden;">
  <div class="hero-glow"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-bold mb-6 text-purple-200">
      <i class="fa-solid fa-circle-check text-blue-300 text-xs"></i> شارة التوثيق الرسمية
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight">ماذا يتغير في حياتك<br>بعد التوثيق؟</h1>
    <p class="text-purple-200 text-lg font-medium mb-10">أنت تبني سمعتك كل يوم — التوثيق يجعلها رسمية وموثوقة</p>

    <!-- Type switcher -->
    <div class="flex justify-center mb-2">
      <div class="flex bg-white/10 border border-white/20 rounded-2xl p-1 gap-1">
        <span class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-white/20">
          <i class="fa-solid fa-circle-check text-blue-300"></i> التوثيق فقط
        </span>
        <a href="membership.php?type=executive"
          class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-purple-200 hover:text-white hover:bg-white/10 transition">
          <i class="fa-solid fa-crown text-yellow-400"></i> رئيس تنفيذي
        </a>
      </div>
    </div>
  </div>
</section>

<!-- BENEFITS GRID -->
<section class="py-14 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
      <?php
      $benefits = [
        ['fa-arrow-up-right-dots','text-purple-600','bg-purple-50','border-purple-100','يجدك من يبحث عنك أولاً','كل مستثمر أو إعلامي يكتب اسمك يصل إليك قبل أي نتيجة أخرى'],
        ['fa-circle-check','text-blue-600','bg-blue-50','border-blue-100','يثق بك الناس قبل أن يكلموك','الشارة الزرقاء تقول عنك في ثانية ما تحتاج ساعة لإثباته'],
        ['fa-building','text-indigo-600','bg-indigo-50','border-indigo-100','دورك يظهر بوضوح داخل مؤسستك','لا أحد يتساءل عن موقعك — صفحتك تجيب عن كل شيء'],
        ['fa-headset','text-green-600','bg-green-50','border-green-100','لست وحدك في إدارة ملفك','مدير حساب مخصص يتولى الإعداد والتحديث حتى تركّز أنت على عملك'],
        ['fa-chart-bar','text-orange-600','bg-orange-50','border-orange-100','تعرف من اهتم بك هذا الشهر','أرقام حقيقية عن من شاهد ملفك — تساعدك تقيّم حضورك وتحسّنه'],
        ['fa-shield-halved','text-rose-600','bg-rose-50','border-rose-100','لا أحد ينتحل هويتك','ملفك الموثق يحميك من أي محاولة لانتحال صفتك على الإنترنت'],
      ];
      foreach ($benefits as [$icon,$tc,$bg,$bc,$title,$desc]):
      ?>
      <div class="rounded-2xl p-6 border-2 <?= $bg ?> <?= $bc ?>">
        <div class="w-11 h-11 rounded-xl <?= $bg ?> border <?= $bc ?> flex items-center justify-center mb-4">
          <i class="fa-solid <?= $icon ?> <?= $tc ?> text-lg"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2 text-sm"><?= $title ?></h3>
        <p class="text-gray-500 text-xs leading-relaxed"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- STATS -->
<section class="py-12" style="background:linear-gradient(135deg,#1e0a3c 0%,#5B1494 60%,#8829C8 100%);">
  <div class="max-w-4xl mx-auto px-4">
    <h2 class="text-white font-black text-2xl text-center mb-10">من هم في أرقام</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
      <div class="bg-white/10 border border-white/15 rounded-2xl p-5">
        <p class="text-4xl font-black text-white"><?= number_format($total_p) ?></p>
        <p class="text-purple-300 text-sm font-semibold mt-1">شخصية موثقة</p>
      </div>
      <div class="bg-white/10 border border-white/15 rounded-2xl p-5">
        <p class="text-4xl font-black text-white"><?= number_format($total_i) ?></p>
        <p class="text-purple-300 text-sm font-semibold mt-1">مؤسسة</p>
      </div>
      <div class="bg-white/10 border border-white/15 rounded-2xl p-5">
        <p class="text-4xl font-black text-white"><?= $total_countries ?>+</p>
        <p class="text-purple-300 text-sm font-semibold mt-1">دولة عربية</p>
      </div>
      <div class="bg-white/10 border border-white/15 rounded-2xl p-5">
        <p class="text-4xl font-black text-white"><?= $total_views > 0 ? number_format($total_views) : '100K' ?>+</p>
        <p class="text-purple-300 text-sm font-semibold mt-1">مشاهدة</p>
      </div>
    </div>
    <div class="mt-10 text-center">
      <a href="#pricing-section" onclick="event.preventDefault();document.getElementById('pricing-section').scrollIntoView({behavior:'smooth'})"
        class="inline-flex items-center gap-2 px-10 py-4 font-black text-base rounded-2xl hover:opacity-90 transition"
        style="background:linear-gradient(135deg,#8829C8,#5B1494);border:2px solid rgba(255,255,255,.3);color:#fff;">
        <i class="fa-solid fa-circle-check"></i> وثّق ملفك الآن
      </a>
    </div>
  </div>
</section>

<!-- SOCIAL PROOF: People -->
<?php if (!empty($verified_people)): ?>
<section class="py-14 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-2xl font-black text-gray-800 text-center mb-2">أفراد وثّقوا ملفاتهم</h2>
    <p class="text-gray-400 text-center mb-10 font-medium">انضم إلى نخبة الأفراد الموثقين على المنصة</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <?php foreach ($verified_people as $p): ?>
      <a href="profile.php?id=<?= $p['p_id'] ?>" class="text-center group">
        <?php if (!empty($p['p_photo'])): ?>
          <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
            class="w-16 h-16 rounded-full mx-auto mb-2 object-cover border-2 border-blue-200 group-hover:border-blue-400 transition">
        <?php else: ?>
          <div class="w-16 h-16 rounded-full pi-gradient flex items-center justify-center mx-auto mb-2">
            <span class="text-white font-black text-xl"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
          </div>
        <?php endif; ?>
        <p class="text-xs font-bold text-gray-700 leading-tight"><?= htmlspecialchars(mb_substr($p['p_name_ar'],0,12)) ?></p>
        <?php if (!empty($p['p_title'])): ?>
          <p class="text-xs text-gray-400 truncate px-1"><?= htmlspecialchars(mb_substr($p['p_title'],0,20)) ?></p>
        <?php endif; ?>
        <span class="inline-flex items-center gap-0.5 text-xs text-blue-500 font-bold mt-0.5"><i class="fa-solid fa-circle-check text-xs"></i></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- SOCIAL PROOF: Institutions -->
<?php if (!empty($verified_insts)): ?>
<section class="py-12 bg-gray-50 border-y border-gray-100">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-xl font-black text-gray-800 text-center mb-8">شركات وثّقت ملفاتها</h2>
    <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-4 gap-5">
      <?php foreach ($verified_insts as $inst): ?>
      <a href="institution.php?id=<?= $inst['inst_id'] ?>"
        class="bg-white rounded-2xl p-4 flex flex-col items-center gap-3 shadow-sm hover:shadow-md transition border border-gray-100">
        <?php if (!empty($inst['inst_logo'])): ?>
          <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
            class="h-10 max-w-full object-contain">
        <?php else: ?>
          <div class="w-12 h-12 rounded-xl pi-gradient flex items-center justify-center">
            <span class="text-white font-black text-lg"><?= mb_substr($inst['inst_name_ar'],0,1) ?></span>
          </div>
        <?php endif; ?>
        <p class="text-xs font-bold text-gray-600 text-center leading-tight"><?= htmlspecialchars(mb_substr($inst['inst_name_ar'],0,25)) ?></p>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- WHO USES -->
<section class="py-14 bg-white">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-2xl font-black text-gray-800 text-center mb-2">من يستفيد أكثر من التوثيق؟</h2>
    <p class="text-gray-400 text-center mb-10 font-medium">كل من يريد أن يُعرَف بالصورة التي يستحقها</p>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
      <?php
      $types = [
        ['fa-briefcase','text-purple-600','bg-purple-50','رجال وسيدات أعمال'],
        ['fa-graduation-cap','text-blue-600','bg-blue-50','أكاديميون وباحثون'],
        ['fa-landmark','text-indigo-600','bg-indigo-50','أفراد في المؤسسات الحكومية والعالمية'],
        ['fa-microphone','text-rose-600','bg-rose-50','أستاريون'],
        ['fa-building','text-amber-600','bg-amber-50','محامون'],
      ];
      foreach ($types as [$icon,$tc,$bg,$label]):
      ?>
      <div class="<?= $bg ?> rounded-2xl p-5 text-center border border-transparent hover:shadow-sm transition">
        <div class="w-12 h-12 rounded-xl <?= $bg ?> flex items-center justify-center mx-auto mb-3 border border-gray-200">
          <i class="fa-solid <?= $icon ?> <?= $tc ?> text-xl"></i>
        </div>
        <p class="text-xs font-bold text-gray-700 leading-tight"><?= $label ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-8">
      <a href="#pricing-section" onclick="event.preventDefault();document.getElementById('pricing-section').scrollIntoView({behavior:'smooth'})"
        class="inline-flex items-center gap-2 px-8 py-3 pi-primary-bg text-white font-black rounded-xl hover:opacity-90 transition">
        وثّق ملفك الآن <i class="fa-solid fa-arrow-left mr-1"></i>
      </a>
    </div>
  </div>
</section>

<!-- PRICING CARDS -->
<div id="pricing-section" class="max-w-4xl mx-auto px-4 py-16">
  <h2 class="text-3xl font-black text-gray-800 text-center mb-3">اختر باقتك</h2>
  <p class="text-gray-400 text-center mb-10 font-medium">استثمر في حضورك الرقمي الموثق</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
    <!-- Monthly -->
    <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 p-8 flex flex-col">
      <div class="mb-6">
        <p class="text-gray-500 font-semibold text-sm mb-1">الباقة الأساسية</p>
        <div class="flex items-end gap-1">
          <span class="text-5xl font-black text-gray-800">$90</span>
          <span class="text-gray-400 font-semibold mb-1">/ شهر</span>
        </div>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-circle-check text-blue-500 w-4 mt-0.5"></i> يثق بك الناس فوراً بمجرد رؤية شارتك</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> صفحتك تعكس احترافيتك بتصميم لا يشبه غيره</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> تسبق المنافسين في نتائج البحث</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> أنت من يتحكم في ما يعرفه الناس عنك</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> اترك أثراً في كل اجتماع ببطاقتك الرسمية</li>
      </ul>
      <button onclick="choosePlan('monthly','verified')"
        class="w-full py-3.5 border-2 border-purple-500 text-purple-600 font-black rounded-xl hover:bg-purple-50 transition">
        اشترك الآن
      </button>
    </div>

    <!-- Lifetime -->
    <div class="bg-white rounded-2xl shadow-lg border-2 p-8 flex flex-col relative overflow-hidden" style="border-color:#8829C8">
      <div class="absolute top-4 left-4">
        <span class="px-3 py-1 text-xs font-black text-white rounded-full" style="background:linear-gradient(135deg,#8829C8,#5B1494)">الأكثر طلباً ⭐</span>
      </div>
      <div class="mb-6 mt-4">
        <p class="font-semibold text-sm mb-1" style="color:#8829C8">باقة مدى الحياة</p>
        <div class="flex items-end gap-2">
          <span class="text-5xl font-black text-gray-800">$99</span>
          <div class="mb-1">
            <span class="text-gray-400 line-through text-sm font-semibold block">$700</span>
            <span class="text-green-600 text-xs font-bold">وفّر 86%</span>
          </div>
        </div>
        <p class="text-gray-500 text-sm mt-1">دفعة واحدة — مدى الحياة</p>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-circle-check text-blue-500 w-4 mt-0.5"></i> يثق بك الناس فوراً بمجرد رؤية شارتك</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> صفحتك تعكس احترافيتك بتصميم لا يشبه غيره</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> تسبق المنافسين في نتائج البحث</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> أنت من يتحكم في ما يعرفه الناس عنك</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4 mt-0.5"></i> اترك أثراً في كل اجتماع ببطاقتك الرسمية</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4 mt-0.5" style="color:#8829C8"></i> فريق الدعم بجانبك للأبد — لا تدفع مجدداً</li>
        <li class="flex items-start gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4 mt-0.5" style="color:#8829C8"></i> طلبك يُعالج بأولوية — لا تنتظر في الدور</li>
      </ul>
      <div class="rounded-2xl mb-4 p-3" style="background:rgba(136,41,200,.07);border:1px solid rgba(136,41,200,.2);">
        <p class="text-xs font-bold text-center mb-2" style="color:#8829C8;">⏳ سعر الإطلاق ينتهي خلال</p>
        <div class="flex justify-center gap-2">
          <div class="text-center"><div class="text-2xl font-black text-gray-800" id="mv-cd-d">00</div><div class="text-xs text-gray-400 font-semibold">يوم</div></div>
          <div class="text-2xl font-black text-gray-400 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black text-gray-800" id="mv-cd-h">00</div><div class="text-xs text-gray-400 font-semibold">ساعة</div></div>
          <div class="text-2xl font-black text-gray-400 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black text-gray-800" id="mv-cd-m">00</div><div class="text-xs text-gray-400 font-semibold">دقيقة</div></div>
          <div class="text-2xl font-black text-gray-400 mt-0.5">:</div>
          <div class="text-center"><div class="text-2xl font-black text-gray-800" id="mv-cd-s">00</div><div class="text-xs text-gray-400 font-semibold">ثانية</div></div>
        </div>
      </div>
      <button onclick="choosePlan('lifetime','verified')"
        class="w-full py-3.5 text-white font-black rounded-xl hover:opacity-90 transition" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
        اشترك الآن
      </button>
    </div>
  </div>
</div>

<!-- Upgrade Banner -->
<div class="max-w-3xl mx-auto px-4 pb-16">
  <div class="rounded-2xl p-6 flex items-center justify-between gap-4 flex-wrap" style="background:linear-gradient(135deg,#78350f,#b45309)">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-yellow-300 flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-crown text-amber-900 text-xl"></i>
      </div>
      <div>
        <p class="text-white font-black text-lg">هل أنت رئيس تنفيذي؟</p>
        <p class="text-amber-200 text-sm">حضورك يستحق أكثر من التوثيق — اكتشف ما يميزك فعلاً</p>
      </div>
    </div>
    <a href="membership.php?type=executive"
      class="px-6 py-3 bg-yellow-300 text-amber-900 font-black rounded-xl hover:bg-yellow-200 transition whitespace-nowrap">
      تعرف على الباقة <i class="fa-solid fa-arrow-left mr-1"></i>
    </a>
  </div>
</div>

<?php endif; ?>

<!-- CONTACT FORM -->
<div id="contact-section" class="max-w-2xl mx-auto px-4 pb-16 hidden">
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
    <div class="flex items-center gap-3 mb-6">
      <button onclick="goBack()" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fa-solid fa-arrow-right text-lg"></i>
      </button>
      <div>
        <h2 class="font-black text-gray-800 text-xl">أكمل بياناتك</h2>
        <p class="text-sm text-gray-400">الباقة المختارة: <span id="plan_display" class="font-bold text-purple-600"></span></p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
      <?php foreach($errors as $e): ?><p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
      <input type="hidden" name="mem_plan" id="mem_plan_input" value="">
      <input type="hidden" name="mem_type" id="mem_type_input" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">الاسم الكامل <span class="text-red-500">*</span></label>
          <input type="text" name="mem_name" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['mem_name']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">رقم الجوال <span class="text-red-500">*</span></label>
          <input type="tel" name="mem_phone" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['mem_phone']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-red-500">*</span></label>
          <input type="email" name="mem_email" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['mem_email']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">رابط ملفك على الموقع</label>
          <input type="url" name="mem_profile_url" dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" placeholder="https://..." value="<?= htmlspecialchars($_POST['mem_profile_url']??'') ?>">
        </div>
      </div>
      <button type="submit" class="w-full py-4 text-white font-black text-base rounded-xl hover:opacity-90 transition flex items-center justify-center gap-2" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
        <i class="fa-solid fa-paper-plane"></i> إرسال طلب العضوية
      </button>
    </form>
  </div>
</div>

<script>
var _planLabels = {
  'monthly_verified':  'التوثيق الشهرية ($90/شهر)',
  'lifetime_verified': 'التوثيق مدى الحياة ($99)',
  'monthly_executive': 'رئيس تنفيذي الشهرية ($210/شهر)',
  'lifetime_executive':'رئيس تنفيذي مدى الحياة ($250)',
};
function choosePlan(plan, type) {
  document.getElementById('pricing-section').classList.add('hidden');
  var cs = document.getElementById('contact-section');
  cs.classList.remove('hidden');
  document.getElementById('mem_plan_input').value = plan;
  document.getElementById('mem_type_input').value = type;
  document.getElementById('plan_display').textContent = _planLabels[plan+'_'+type] || plan;
  window.scrollTo({top: cs.offsetTop - 80, behavior:'smooth'});
}
function goBack() {
  document.getElementById('contact-section').classList.add('hidden');
  document.getElementById('pricing-section').classList.remove('hidden');
}
<?php if (!empty($errors) && !empty($_POST['mem_plan'])): ?>
document.addEventListener('DOMContentLoaded',function(){
  choosePlan('<?= htmlspecialchars($_POST['mem_plan']) ?>','<?= htmlspecialchars($_POST['mem_type']??'verified') ?>');
});
<?php endif; ?>
</script>

<script>
(function() {
  var CYCLE = 4 * 24 * 60 * 60 * 1000;
  var REF   = 1735689600000;
  function pad(n){ return String(n).padStart(2,'0'); }
  function tick() {
    var remaining = CYCLE - ((Date.now() - REF) % CYCLE);
    var d = Math.floor(remaining / 86400000);
    var h = Math.floor((remaining % 86400000) / 3600000);
    var m = Math.floor((remaining % 3600000) / 60000);
    var s = Math.floor((remaining % 60000) / 1000);
    ['mv-cd-d','mv-cd-h','mv-cd-m','mv-cd-s',
     'me-cd-d','me-cd-h','me-cd-m','me-cd-s'].forEach(function(id,i){
      var el = document.getElementById(id);
      if (el) el.textContent = pad([d,h,m,s][i % 4]);
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php include 'includes/footer.php'; ?>
