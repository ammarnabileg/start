<?php
$pageTitle = 'عضوية موثقة - PioneerIcons';
require_once 'includes/config.php';

$mysqli->query("CREATE TABLE IF NOT EXISTS pi_memberships (
  mem_id INT AUTO_INCREMENT PRIMARY KEY,
  mem_plan ENUM('monthly','lifetime') NOT NULL,
  mem_name VARCHAR(200) NOT NULL,
  mem_phone VARCHAR(50) NOT NULL,
  mem_email VARCHAR(200) NOT NULL,
  mem_profile_url VARCHAR(500),
  mem_status ENUM('pending','active','cancelled') DEFAULT 'pending',
  mem_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan    = ($_POST['mem_plan']??'') === 'lifetime' ? 'lifetime' : 'monthly';
    $name    = trim($_POST['mem_name'] ?? '');
    $phone   = trim($_POST['mem_phone'] ?? '');
    $email   = trim($_POST['mem_email'] ?? '');
    $profile = trim($_POST['mem_profile_url'] ?? '');
    if (!$name)  $errors[] = 'الاسم مطلوب';
    if (!$phone) $errors[] = 'رقم الجوال مطلوب';
    if (!$email) $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($errors)) {
        $pl=pi_escape($plan);$n=pi_escape($name);$ph=pi_escape($phone);$e=pi_escape($email);$pr=pi_escape($profile);
        $mysqli->query("INSERT INTO pi_memberships(mem_plan,mem_name,mem_phone,mem_email,mem_profile_url)VALUES('$pl','$n','$ph','$e','$pr')");
        $success = true;
    }
}

include 'includes/header.php';
?>

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

<!-- Pricing Cards -->
<div id="pricing-section" class="max-w-4xl mx-auto px-4 py-16">
  <h2 class="text-3xl font-black text-gray-800 text-center mb-3">اختر باقتك</h2>
  <p class="text-gray-400 text-center mb-10 font-medium">استثمر في حضورك الرقمي الموثق</p>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
    <!-- Monthly -->
    <div class="bg-white rounded-2xl shadow-sm border-2 border-gray-100 p-8 flex flex-col">
      <div class="mb-6">
        <p class="text-gray-500 font-semibold text-sm mb-1">الباقة الشهرية</p>
        <div class="flex items-end gap-1">
          <span class="text-5xl font-black text-gray-800">$90</span>
          <span class="text-gray-400 font-semibold mb-1">/ شهر</span>
        </div>
      </div>
      <ul class="space-y-3 flex-1 mb-8">
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> شارة التوثيق الزرقاء</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> صفحة شخصية مميزة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> ظهور أولوية في البحث</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> إدارة محتوى صفحتك</li>
      </ul>
      <button onclick="choosePlan('monthly')"
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
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> شارة التوثيق الزرقاء</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> صفحة شخصية مميزة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> ظهور أولوية في البحث</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check text-green-500 w-4"></i> إدارة محتوى صفحتك</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4" style="color:#8829C8"></i> دعم مدى الحياة</li>
        <li class="flex items-center gap-2 text-sm text-gray-700"><i class="fa-solid fa-check w-4" style="color:#8829C8"></i> أولوية في المراجعة</li>
      </ul>
      <button onclick="choosePlan('lifetime')"
        class="w-full py-3.5 text-white font-black rounded-xl hover:opacity-90 transition" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
        اشترك الآن
      </button>
    </div>
  </div>
</div>

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
function choosePlan(plan) {
  document.getElementById('pricing-section').classList.add('hidden');
  document.getElementById('contact-section').classList.remove('hidden');
  document.getElementById('mem_plan_input').value = plan;
  document.getElementById('plan_display').textContent = plan === 'lifetime' ? 'مدى الحياة ($99)' : 'الشهرية ($90/شهر)';
  window.scrollTo({top: document.getElementById('contact-section').offsetTop - 80, behavior:'smooth'});
}
function goBack() {
  document.getElementById('contact-section').classList.add('hidden');
  document.getElementById('pricing-section').classList.remove('hidden');
}
<?php if (!empty($errors) && !empty($_POST['mem_plan'])): ?>
// Re-show contact form if validation errors
document.addEventListener('DOMContentLoaded',function(){
  choosePlan('<?= htmlspecialchars($_POST['mem_plan']) ?>');
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
