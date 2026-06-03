<?php
$pageTitle = 'أعلن معنا - PioneerIcons';
require_once 'includes/config.php';

$mysqli->query("CREATE TABLE IF NOT EXISTS pi_advertise (
  adv_id INT AUTO_INCREMENT PRIMARY KEY,
  adv_company VARCHAR(200) NOT NULL,
  adv_contact VARCHAR(200) NOT NULL,
  adv_phone VARCHAR(50) NOT NULL,
  adv_email VARCHAR(200) NOT NULL,
  adv_plan VARCHAR(20) DEFAULT 'monthly',
  adv_note TEXT,
  adv_status ENUM('new','contacted','done') DEFAULT 'new',
  adv_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$mysqli->query("ALTER TABLE pi_advertise ADD COLUMN IF NOT EXISTS adv_plan VARCHAR(20) DEFAULT 'monthly' AFTER adv_email");

$success = false;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = trim($_POST['adv_company'] ?? '');
    $contact = trim($_POST['adv_contact'] ?? '');
    $phone   = trim($_POST['adv_phone']   ?? '');
    $email   = trim($_POST['adv_email']   ?? '');
    $plan    = in_array($_POST['adv_plan']??'', ['monthly','yearly']) ? $_POST['adv_plan'] : 'monthly';
    $note    = trim($_POST['adv_note']    ?? '');
    if (!$company) $errors[] = 'اسم الشركة مطلوب';
    if (!$contact) $errors[] = 'اسم المسؤول مطلوب';
    if (!$phone)   $errors[] = 'رقم الهاتف مطلوب';
    if (!$email)   $errors[] = 'البريد الإلكتروني مطلوب';
    if (empty($errors)) {
        $c=pi_escape($company);$cn=pi_escape($contact);$p=pi_escape($phone);
        $e=pi_escape($email);$pl=pi_escape($plan);$n=pi_escape($note);
        $mysqli->query("INSERT INTO pi_advertise(adv_company,adv_contact,adv_phone,adv_email,adv_plan,adv_note)VALUES('$c','$cn','$p','$e','$pl','$n')");
        $success = true;
    }
}
include 'includes/header.php';
?>

<!-- ═══════ HERO ═══════ -->
<section class="hero-bg py-20 text-white" style="position:relative;overflow:hidden;">
  <div class="hero-glow"></div>
  <div class="absolute inset-0 opacity-20" style="background-image:radial-gradient(circle at 10% 80%,#a855f7 0%,transparent 40%),radial-gradient(circle at 90% 20%,#7c3aed 0%,transparent 40%);pointer-events:none;"></div>
  <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
    <div class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-5 py-2 text-sm font-semibold mb-6 text-purple-200">
      <i class="fa-solid fa-crown text-yellow-300 text-xs"></i> شراكة إعلانية حصرية
    </div>
    <h1 class="text-4xl md:text-5xl font-black mb-5 leading-tight">علامتك التجارية<br>في قلب قرارات العرب</h1>
    <p class="text-purple-200 text-lg font-medium max-w-2xl mx-auto">شعارك يصل يومياً إلى آلاف رجال الأعمال والشخصيات المؤثرة عبر منشوراتنا على جميع منصات التواصل الاجتماعي</p>
    <div class="flex items-center justify-center gap-8 mt-10 flex-wrap">
      <div class="text-center">
        <p class="text-3xl font-black">+<?= number_format(pi_count_personalities()) ?></p>
        <p class="text-purple-300 text-sm font-semibold mt-1">شخصية موثقة</p>
      </div>
      <div class="w-px h-10 bg-white/20"></div>
      <div class="text-center">
        <p class="text-3xl font-black">+<?= number_format(pi_count_institutions()) ?></p>
        <p class="text-purple-300 text-sm font-semibold mt-1">مؤسسة وشركة</p>
      </div>
      <div class="w-px h-10 bg-white/20"></div>
      <div class="text-center">
        <p class="text-3xl font-black">17+</p>
        <p class="text-purple-300 text-sm font-semibold mt-1">دولة عربية</p>
      </div>
    </div>
  </div>
</section>

<?php if ($success): ?>
<div class="max-w-xl mx-auto px-4 py-16 text-center">
  <div class="bg-green-50 border border-green-200 rounded-2xl p-12">
    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5">
      <i class="fa-solid fa-circle-check text-green-500 text-4xl"></i>
    </div>
    <h2 class="text-2xl font-black text-green-800 mb-3">تم استلام طلبك!</h2>
    <p class="text-green-600 mb-6">سيتواصل معك فريقنا خلال 24 ساعة لإتمام الشراكة</p>
    <a href="index.php" class="inline-block px-8 py-3 pi-primary-bg text-white font-bold rounded-xl hover:opacity-90 transition">العودة للرئيسية</a>
  </div>
</div>
<?php include 'includes/footer.php'; ?><?php exit; ?>
<?php endif; ?>

<!-- ═══════ WHAT YOU GET ═══════ -->
<section class="py-14 bg-white border-b border-gray-100">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-2xl font-black text-gray-800 text-center mb-2">ماذا يعني أن تكون شريكنا؟</h2>
    <p class="text-gray-400 text-center mb-10 font-medium">وصولك المباشر إلى أصحاب القرار والنخب العربية</p>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="text-center p-6 rounded-2xl bg-purple-50 border border-purple-100">
        <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-brands fa-instagram text-purple-600 text-2xl"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">حضور على السوشيال ميديا</h3>
        <p class="text-gray-500 text-sm leading-relaxed">شعارك يظهر في كل منشورات وبوستات المنصة عبر إنستجرام، تويتر، لينكدإن — يومياً أمام الآلاف</p>
      </div>
      <div class="text-center p-6 rounded-2xl bg-amber-50 border border-amber-100">
        <div class="w-14 h-14 rounded-2xl bg-amber-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-user-tie text-amber-600 text-2xl"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">جمهور صانع القرار</h3>
        <p class="text-gray-500 text-sm leading-relaxed">علامتك أمام رجال الأعمال والمديرين والشخصيات المؤثرة — الجمهور الأصعب وصولاً والأعلى قيمة</p>
      </div>
      <div class="text-center p-6 rounded-2xl bg-green-50 border border-green-100">
        <div class="w-14 h-14 rounded-2xl bg-green-100 flex items-center justify-center mx-auto mb-4">
          <i class="fa-solid fa-chart-line text-green-600 text-2xl"></i>
        </div>
        <h3 class="font-black text-gray-800 mb-2">تقارير أداء مفصّلة</h3>
        <p class="text-gray-500 text-sm leading-relaxed">تقرير شهري يوضح مرات الظهور والوصول وعدد التفاعلات التي حققتها شراكتك</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══════ PRICING ═══════ -->
<section class="py-16 bg-gray-50">
  <div class="max-w-5xl mx-auto px-4">
    <h2 class="text-3xl font-black text-gray-800 text-center mb-2">اختر شراكتك</h2>
    <p class="text-gray-400 text-center mb-12 font-medium">باقتان فقط — لضمان حصرية كاملة لكل شريك</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">

      <!-- ── MONTHLY ── -->
      <div class="bg-white rounded-3xl shadow-md border-2 border-gray-100 p-8 flex flex-col">
        <div class="mb-2">
          <span class="text-xs font-black text-gray-400 tracking-widest uppercase">الباقة الشهرية</span>
        </div>
        <div class="flex items-end gap-2 mb-6">
          <span class="text-6xl font-black text-gray-800">$3,000</span>
          <span class="text-gray-400 font-bold mb-2">/ شهر</span>
        </div>
        <p class="text-gray-500 text-sm mb-6 leading-relaxed">حضور شهري متجدد — شعارك في كل البوستات طوال الشهر، مع إمكانية التجديد أو الإيقاف في أي وقت</p>
        <ul class="space-y-3 flex-1 mb-8">
          <li class="flex items-start gap-3 text-sm text-gray-700">
            <i class="fa-solid fa-circle-check text-purple-500 mt-0.5 flex-shrink-0"></i>
            <span>شعارك في <strong>كل منشورات السوشيال ميديا</strong> لدينا طوال الشهر</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-gray-700">
            <i class="fa-solid fa-circle-check text-purple-500 mt-0.5 flex-shrink-0"></i>
            <span>ظهور في شريط "تحت الضوء" على جميع صفحات المنصة</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-gray-700">
            <i class="fa-solid fa-circle-check text-purple-500 mt-0.5 flex-shrink-0"></i>
            <span>وصول مباشر لآلاف رجال الأعمال والشخصيات يومياً</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-gray-700">
            <i class="fa-solid fa-circle-check text-purple-500 mt-0.5 flex-shrink-0"></i>
            <span>رابط مباشر لموقعك أو صفحتك من شريط الرعاة</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-gray-700">
            <i class="fa-solid fa-circle-check text-purple-500 mt-0.5 flex-shrink-0"></i>
            <span>تقرير أداء شهري بمرات الظهور والوصول</span>
          </li>
        </ul>
        <button onclick="chooseAdPlan('monthly')"
          class="w-full py-4 border-2 border-purple-500 text-purple-600 font-black rounded-2xl hover:bg-purple-50 transition text-base">
          ابدأ الشهري
        </button>
      </div>

      <!-- ── YEARLY (PREMIUM) ── -->
      <div class="rounded-3xl shadow-2xl p-8 flex flex-col relative overflow-hidden text-white"
        style="background:linear-gradient(145deg,#1e0a3c 0%,#3b0d6e 40%,#5B1494 75%,#8829C8 100%);">

        <!-- Glow orbs -->
        <div style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(168,85,247,.25);filter:blur(40px);pointer-events:none;"></div>
        <div style="position:absolute;bottom:-40px;left:-40px;width:160px;height:160px;border-radius:50%;background:rgba(139,92,246,.2);filter:blur(30px);pointer-events:none;"></div>

        <!-- Badge -->
        <div class="absolute top-5 left-5">
          <span class="px-3 py-1 text-xs font-black rounded-full bg-yellow-300 text-purple-900">الأوفر ✦ وفّر $33,501</span>
        </div>

        <div class="mt-8 mb-2 relative z-10">
          <span class="text-xs font-black text-purple-300 tracking-widest uppercase">الباقة السنوية</span>
        </div>
        <div class="flex items-end gap-2 mb-1 relative z-10">
          <span class="text-6xl font-black">$2,499</span>
          <span class="text-purple-300 font-bold mb-2">/ سنة</span>
        </div>
        <p class="text-purple-300 text-sm mb-1 relative z-10 line-through">$36,000 / سنة</p>
        <p class="text-green-300 text-xs font-black mb-5 relative z-10">⬇ وفّر 93% — بدلاً من $10,000 كل 4 أشهر</p>

        <!-- Countdown -->
        <div class="rounded-2xl mb-6 p-4 relative z-10" style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);">
          <p class="text-xs text-purple-300 font-bold text-center mb-2">⏳ العرض ينتهي خلال</p>
          <div class="flex justify-center gap-3" id="adv-countdown">
            <div class="text-center">
              <div class="text-3xl font-black text-white" id="adv-cd-d">00</div>
              <div class="text-xs text-purple-300 font-semibold mt-0.5">يوم</div>
            </div>
            <div class="text-3xl font-black text-purple-300 mt-0.5">:</div>
            <div class="text-center">
              <div class="text-3xl font-black text-white" id="adv-cd-h">00</div>
              <div class="text-xs text-purple-300 font-semibold mt-0.5">ساعة</div>
            </div>
            <div class="text-3xl font-black text-purple-300 mt-0.5">:</div>
            <div class="text-center">
              <div class="text-3xl font-black text-white" id="adv-cd-m">00</div>
              <div class="text-xs text-purple-300 font-semibold mt-0.5">دقيقة</div>
            </div>
            <div class="text-3xl font-black text-purple-300 mt-0.5">:</div>
            <div class="text-center">
              <div class="text-3xl font-black text-white" id="adv-cd-s">00</div>
              <div class="text-xs text-purple-300 font-semibold mt-0.5">ثانية</div>
            </div>
          </div>
        </div>

        <ul class="space-y-3 flex-1 mb-8 relative z-10">
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-crown text-yellow-300 mt-0.5 flex-shrink-0"></i>
            <span><strong>شعارك الحصري</strong> في كل منشورات السوشيال ميديا طوال العام</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-circle-check text-green-300 mt-0.5 flex-shrink-0"></i>
            <span>ظهور دائم في شريط "تحت الضوء" على كل صفحات المنصة</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-circle-check text-green-300 mt-0.5 flex-shrink-0"></i>
            <span>وصول لأكثر من <strong>مليون ظهور</strong> سنوي أمام النخب العربية</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-circle-check text-green-300 mt-0.5 flex-shrink-0"></i>
            <span>إشارة مميزة لشركتك في المحتوى الإعلامي الشهري</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-circle-check text-green-300 mt-0.5 flex-shrink-0"></i>
            <span>مدير حساب مخصص لمتابعة حضورك الإعلاني</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-circle-check text-green-300 mt-0.5 flex-shrink-0"></i>
            <span>تقرير أداء <strong>أسبوعي</strong> بالأرقام التفصيلية</span>
          </li>
          <li class="flex items-start gap-3 text-sm text-white">
            <i class="fa-solid fa-star text-yellow-300 mt-0.5 flex-shrink-0"></i>
            <span>بوست ترحيبي خاص لإطلاق الشراكة على حساباتنا</span>
          </li>
        </ul>
        <button onclick="chooseAdPlan('yearly')"
          class="w-full py-4 font-black rounded-2xl transition text-purple-900 text-base relative z-10"
          style="background:linear-gradient(135deg,#fde68a,#f59e0b);box-shadow:0 4px 24px rgba(251,191,36,.4);">
          <i class="fa-solid fa-crown ml-2"></i> ابدأ الشراكة السنوية
        </button>
      </div>

    </div>
  </div>
</section>

<!-- ═══════ SOCIAL PROOF ═══════ -->
<section class="py-12 bg-white border-y border-gray-100">
  <div class="max-w-4xl mx-auto px-4 text-center">
    <p class="text-gray-400 text-sm font-semibold mb-6">منصات التواصل التي يظهر فيها شعارك</p>
    <div class="flex items-center justify-center gap-8 flex-wrap">
      <div class="flex items-center gap-2 text-gray-600 font-bold"><i class="fa-brands fa-instagram text-2xl text-pink-500"></i> إنستجرام</div>
      <div class="flex items-center gap-2 text-gray-600 font-bold"><i class="fa-brands fa-x-twitter text-2xl text-gray-900"></i> تويتر / X</div>
      <div class="flex items-center gap-2 text-gray-600 font-bold"><i class="fa-brands fa-linkedin text-2xl text-blue-600"></i> لينكدإن</div>
      <div class="flex items-center gap-2 text-gray-600 font-bold"><i class="fa-brands fa-tiktok text-2xl text-gray-900"></i> تيكتوك</div>
      <div class="flex items-center gap-2 text-gray-600 font-bold"><i class="fa-brands fa-snapchat text-2xl text-yellow-400"></i> سناب شات</div>
    </div>
  </div>
</section>

<!-- ═══════ CONTACT FORM ═══════ -->
<div id="adv-form-section" class="max-w-2xl mx-auto px-4 py-14 hidden">
  <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-8">
    <div class="flex items-center gap-3 mb-6">
      <button onclick="document.getElementById('adv-form-section').classList.add('hidden');window.scrollTo({top:0,behavior:'smooth'})" class="text-gray-400 hover:text-gray-600 transition">
        <i class="fa-solid fa-arrow-right text-lg"></i>
      </button>
      <div>
        <h2 class="font-black text-gray-800 text-xl">أكمل طلبك</h2>
        <p class="text-sm text-gray-400">الباقة المختارة: <span id="adv-plan-display" class="font-bold text-purple-600"></span></p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
      <?php foreach($errors as $e): ?><p class="text-red-700 text-sm font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
      <input type="hidden" name="adv_plan" id="adv_plan_input" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">اسم الشركة <span class="text-red-500">*</span></label>
          <input type="text" name="adv_company" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_company']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">اسم المسؤول <span class="text-red-500">*</span></label>
          <input type="text" name="adv_contact" required class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_contact']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">رقم الهاتف <span class="text-red-500">*</span></label>
          <input type="tel" name="adv_phone" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_phone']??'') ?>">
        </div>
        <div>
          <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني <span class="text-red-500">*</span></label>
          <input type="email" name="adv_email" required dir="ltr" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition" value="<?= htmlspecialchars($_POST['adv_email']??'') ?>">
        </div>
      </div>
      <div>
        <label class="block text-sm font-bold text-gray-700 mb-1.5">ملاحظات إضافية</label>
        <textarea name="adv_note" rows="3" class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm outline-none focus:border-purple-400 transition resize-none" placeholder="أخبرنا عن شركتك وأهدافك..."><?= htmlspecialchars($_POST['adv_note']??'') ?></textarea>
      </div>
      <button type="submit" class="w-full py-4 pi-primary-bg text-white font-black text-base rounded-2xl hover:opacity-90 transition flex items-center justify-center gap-2">
        <i class="fa-solid fa-paper-plane"></i> إرسال طلب الشراكة
      </button>
    </form>
  </div>
</div>

<script>
// ── Cycling 4-day countdown (same for everyone, same moment) ──
(function() {
  var CYCLE = 4 * 24 * 60 * 60 * 1000;
  var REF   = 1735689600000; // fixed epoch anchor — Mon Jan 1 2024 00:00 UTC

  function tick() {
    var remaining = CYCLE - ((Date.now() - REF) % CYCLE);
    var d = Math.floor(remaining / 86400000);
    var h = Math.floor((remaining % 86400000) / 3600000);
    var m = Math.floor((remaining % 3600000)  / 60000);
    var s = Math.floor((remaining % 60000)    / 1000);
    var pad = function(n){ return String(n).padStart(2,'0'); };
    var dEl = document.getElementById('adv-cd-d');
    if (dEl) {
      dEl.textContent = pad(d);
      document.getElementById('adv-cd-h').textContent = pad(h);
      document.getElementById('adv-cd-m').textContent = pad(m);
      document.getElementById('adv-cd-s').textContent = pad(s);
    }
  }
  tick();
  setInterval(tick, 1000);
})();

function chooseAdPlan(plan) {
  document.getElementById('adv_plan_input').value = plan;
  document.getElementById('adv-plan-display').textContent = plan === 'yearly' ? 'السنوية — $2,499' : 'الشهرية — $3,000 / شهر';
  document.getElementById('adv-form-section').classList.remove('hidden');
  setTimeout(function(){ document.getElementById('adv-form-section').scrollIntoView({behavior:'smooth',block:'start'}); }, 100);
}
</script>

<?php include 'includes/footer.php'; ?>
