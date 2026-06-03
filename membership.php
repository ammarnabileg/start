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
$mysqli->query("ALTER TABLE pi_memberships ADD COLUMN IF NOT EXISTS mem_type ENUM('verified','executive') DEFAULT 'verified' AFTER mem_id");

// Determine which membership type is being shown
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

include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="max-w-xl mx-auto px-4 py-16 text-center">
  <div class="bg-green-50 border border-green-200 rounded-2xl p-12">
    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5"><i class="fa-solid fa-circle-check text-green-500 text-4xl"></i></div>
    <h2 class="text-2xl font-black text-green-800 mb-3">تم استلام طلبك!</h2>
    <p class="text-green-600 mb-6">سيراجع فريقنا طلبك ويتواصل معك قريباً لإتمام التوثيق</p>
    <a href="index.php" class="inline-block px-8 py-3 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition">العودة للرئيسية</a>
  </div>
</div>
<?php include 'includes/footer.php'; ?><?php exit; ?>
<?php endif; ?>

<?php if ($mem_type === 'executive'): ?>
<!-- ====== EXECUTIVE HERO ====== -->
<section class="py-20 text-white" style="background:linear-gradient(135deg,#78350f 0%,#92400e 40%,#b45309 100%);position:relative;overflow:hidden;">
  <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 20% 50%,#fbbf24 0%,transparent 60%),radial-gradient(circle at 80% 20%,#f59e0b 0%,transparent 50%);"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-yellow-200">
      <i class="fa-solid fa-crown text-yellow-300 text-xs"></i> الباقة الذهبية للقيادة
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight">عضوية الرؤساء التنفيذيين</h1>
    <p class="text-amber-200 text-lg font-medium">حضور رقمي استثنائي يليق بمكانتك القيادية</p>
  </div>
</section>

<!-- Type switcher -->
<div class="max-w-xl mx-auto px-4 py-6 flex justify-center">
  <div class="flex bg-gray-100 rounded-2xl p-1 gap-1">
    <a href="membership.php?type=verified"
      class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-gray-500 hover:text-gray-700 transition">
      <i class="fa-solid fa-circle-check text-blue-400"></i> التوثيق فقط
    </a>
    <span class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white" style="background:linear-gradient(135deg,#92400e,#b45309)">
      <i class="fa-solid fa-crown text-yellow-300"></i> رئيس تنفيذي
    </span>
  </div>
</div>

<!-- Pricing Cards — Executive -->
<div id="pricing-section" class="max-w-4xl mx-auto px-4 pb-16">
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
        <li class="flex items-center gap-2 text-sm text-gray-700 font-semibold"><i class="fa-solid fa-circle-check text-blue-500 w-4"></i> <span>شارة التوثيق الزرقاء <span class="text-xs text-blue-500 font-bold">(مشمولة)</span></span></li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-crown text-yellow-500 w-4"></i> شارة الرئيس التنفيذي الذهبية</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> صفحة شخصية مميزة VIP</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> ظهور أولوية قصوى في البحث</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> إدارة كاملة لمحتوى صفحتك</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> بطاقة تعريفية قابلة للتحميل</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> دعم مخصص على مدار الساعة</li>
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
        <li class="flex items-center gap-2 text-sm text-white font-semibold"><i class="fa-solid fa-circle-check text-blue-300 w-4"></i> <span>شارة التوثيق الزرقاء <span class="text-xs text-blue-300 font-bold">(مشمولة)</span></span></li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-crown text-yellow-300 w-4"></i> شارة الرئيس التنفيذي الذهبية</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> صفحة شخصية مميزة VIP</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> ظهور أولوية قصوى في البحث</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> إدارة كاملة لمحتوى صفحتك</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> بطاقة تعريفية قابلة للتحميل</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> دعم مخصص على مدار الساعة</li>
        <li class="flex items-center gap-2 text-sm text-white"><i class="fa-solid fa-check text-green-300 w-4"></i> تجديد مجاني مدى الحياة</li>
      </ul>
      <button onclick="choosePlan('lifetime','executive')"
        class="w-full py-3.5 font-black rounded-xl hover:opacity-90 transition text-amber-900 bg-yellow-300 hover:bg-yellow-200">
        اشترك الآن
      </button>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ====== VERIFIED HERO ====== -->
<section class="hero-bg py-20 text-white">
  <div class="hero-glow"></div>
  <div class="hero-glow-2"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-purple-200">
      <i class="fa-solid fa-circle-check text-blue-300 text-xs"></i> شارة التوثيق الرسمية
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-4 leading-tight">عضويتك الموثقة</h1>
    <p class="text-purple-200 text-lg font-medium">تحكم بما يعرفه الناس عنك — بشكل رسمي وموثق</p>
  </div>
</section>

<!-- Type switcher -->
<div class="max-w-xl mx-auto px-4 py-6 flex justify-center">
  <div class="flex bg-gray-100 rounded-2xl p-1 gap-1">
    <span class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-white" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
      <i class="fa-solid fa-circle-check text-blue-300"></i> التوثيق فقط
    </span>
    <a href="membership.php?type=executive"
      class="flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-sm text-gray-500 hover:text-amber-700 transition">
      <i class="fa-solid fa-crown text-yellow-500"></i> رئيس تنفيذي
    </a>
  </div>
</div>

<!-- Pricing Cards — Verified -->
<div id="pricing-section" class="max-w-4xl mx-auto px-4 pb-16">
  <h2 class="text-3xl font-black text-gray-800 text-center mb-3">اختر باقتك</h2>
  <p class="text-gray-400 text-center mb-10 font-medium">استثمر في حضورك الرقمي الموثق</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
    <!-- Monthly Verified -->
    <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 p-8 flex flex-col">
      <div class="mb-6">
        <p class="text-gray-500 font-semibold text-sm mb-1">الباقة الشهرية</p>
        <div class="flex items-end gap-1">
          <span class="text-5xl font-black text-gray-800">$90</span>
          <span class="text-gray-400 font-semibold mb-1">/ شهر</span>
        </div>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-circle-check text-blue-500 w-4"></i> شارة التوثيق الزرقاء</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> صفحة شخصية مميزة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> ظهور أولوية في البحث</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> إدارة محتوى صفحتك</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> بطاقة تعريفية قابلة للتحميل</li>
      </ul>
      <button onclick="choosePlan('monthly','verified')"
        class="w-full py-3.5 border-2 border-purple-500 text-purple-600 font-black rounded-xl hover:bg-purple-50 transition">
        اشترك الآن
      </button>
    </div>

    <!-- Lifetime Verified -->
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
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-circle-check text-blue-500 w-4"></i> شارة التوثيق الزرقاء</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> صفحة شخصية مميزة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> ظهور أولوية في البحث</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> إدارة محتوى صفحتك</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> بطاقة تعريفية قابلة للتحميل</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4" style="color:#8829C8"></i> دعم مدى الحياة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4" style="color:#8829C8"></i> أولوية في المراجعة</li>
      </ul>
      <button onclick="choosePlan('lifetime','verified')"
        class="w-full py-3.5 text-white font-black rounded-xl hover:opacity-90 transition" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
        اشترك الآن
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Upgrade Banner (shown on verified page) -->
<?php if ($mem_type === 'verified'): ?>
<div class="max-w-3xl mx-auto px-4 pb-16">
  <div class="rounded-2xl p-6 flex items-center justify-between gap-4 flex-wrap" style="background:linear-gradient(135deg,#78350f,#b45309)">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-yellow-300 flex items-center justify-center flex-shrink-0">
        <i class="fa-solid fa-crown text-amber-900 text-xl"></i>
      </div>
      <div>
        <p class="text-white font-black text-lg">هل أنت رئيس تنفيذي؟</p>
        <p class="text-amber-200 text-sm">احصل على باقة القيادة الشاملة بالتوثيق وكل الخدمات</p>
      </div>
    </div>
    <a href="membership.php?type=executive"
      class="px-6 py-3 bg-yellow-300 text-amber-900 font-black rounded-xl hover:bg-yellow-200 transition whitespace-nowrap">
      تعرف على الباقة <i class="fa-solid fa-arrow-left mr-1"></i>
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Step 2: Contact Form -->
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
  document.getElementById('contact-section').classList.remove('hidden');
  document.getElementById('mem_plan_input').value = plan;
  document.getElementById('mem_type_input').value = type;
  document.getElementById('plan_display').textContent = _planLabels[plan+'_'+type] || plan;
  window.scrollTo({top: document.getElementById('contact-section').offsetTop - 80, behavior:'smooth'});
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

<?php include 'includes/footer.php'; ?>
