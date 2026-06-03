<?php
pi_require_perm('manage_settings');
$msg = '';
$msg_type = 'green';

// Handle logo upload
function pi_upload_logo($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) return null;
    $name = 'logo_' . time() . '.' . $ext;
    $dest = __DIR__ . '/../uploads/' . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/' . $name;
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_name','site_name_ar','site_tagline','site_description','site_keywords',
        'footer_about','social_whatsapp','social_linkedin','social_twitter',
        'primary_color','admin_email','copyright_text','google_analytics','default_country',
        'hero_title','hero_subtitle',
    ];

    // Handle logo upload or URL
    if (!empty($_FILES['site_logo_file']['name'])) {
        $logo_path = pi_upload_logo($_FILES['site_logo_file']);
        if ($logo_path) {
            $logo_esc = pi_escape($logo_path);
            $mysqli->query("INSERT INTO pi_settings (s_key,s_value) VALUES ('site_logo','$logo_esc') ON DUPLICATE KEY UPDATE s_value='$logo_esc'");
        }
    } elseif (isset($_POST['site_logo'])) {
        $val = pi_escape($_POST['site_logo']);
        $mysqli->query("INSERT INTO pi_settings (s_key,s_value) VALUES ('site_logo','$val') ON DUPLICATE KEY UPDATE s_value='$val'");
    }

    foreach ($fields as $field) {
        $val = pi_escape($_POST[$field] ?? '');
        $mysqli->query("INSERT INTO pi_settings (s_key,s_value) VALUES ('$field','$val') ON DUPLICATE KEY UPDATE s_value='$val'");
    }
    $msg = 'تم حفظ الإعدادات بنجاح';
}

$S = pi_get_settings();
$countries_list = [];
$r = $mysqli->query("SELECT * FROM pi_countries WHERE c_active=1 ORDER BY c_order,c_id");
if ($r) while ($row=$r->fetch_assoc()) $countries_list[] = $row;
?>

<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-5 py-3 mb-5 font-bold text-sm flex items-center gap-2">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="max-w-3xl">
  <div class="mb-6">
    <h2 class="text-xl font-black text-gray-800">إعدادات الموقع</h2>
    <p class="text-gray-400 text-sm mt-0.5">تحكم في كل تفاصيل الموقع من مكان واحد</p>
  </div>

  <form method="POST" enctype="multipart/form-data" class="space-y-6">

    <!-- هوية الموقع -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
          <i class="fa-solid fa-id-card text-white text-xs"></i>
        </div>
        هوية الموقع
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="form-label">اسم الموقع (إنجليزي)</label>
          <input type="text" name="site_name" class="form-input" value="<?= htmlspecialchars($S['site_name'] ?? '') ?>" placeholder="PioneerIcons">
        </div>
        <div>
          <label class="form-label">اسم الموقع (عربي)</label>
          <input type="text" name="site_name_ar" class="form-input" value="<?= htmlspecialchars($S['site_name_ar'] ?? '') ?>" placeholder="من هم">
        </div>

        <!-- Logo upload -->
        <div class="md:col-span-2">
          <label class="form-label">شعار الموقع (لوجو)</label>
          <div class="flex items-start gap-4">
            <?php if ($S['site_logo']): ?>
            <div class="flex-shrink-0 w-16 h-16 rounded-xl border border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
              <img src="../<?= htmlspecialchars($S['site_logo']) ?>" class="max-w-full max-h-full object-contain" onerror="this.style.display='none'">
            </div>
            <?php endif; ?>
            <div class="flex-1 space-y-2">
              <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 hover:border-purple-400 transition cursor-pointer" onclick="document.getElementById('logo_file').click()">
                <input type="file" id="logo_file" name="site_logo_file" accept="image/*" class="hidden" onchange="previewLogo(this)">
                <div class="text-center">
                  <i class="fa-solid fa-cloud-arrow-up text-gray-400 text-2xl mb-1"></i>
                  <p class="text-sm text-gray-500 font-semibold">اضغط لرفع صورة الشعار</p>
                  <p class="text-xs text-gray-400">PNG, JPG, SVG, WebP</p>
                </div>
                <img id="logo_preview" class="hidden mx-auto mt-3 max-h-16 object-contain rounded-lg">
              </div>
              <p class="text-xs text-gray-400 text-center">— أو —</p>
              <input type="url" name="site_logo" class="form-input" dir="ltr"
                value="<?= htmlspecialchars($S['site_logo'] ?? '') ?>" placeholder="https://... رابط صورة خارجي">
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">اللون الرئيسي</label>
          <div class="flex gap-2">
            <input type="color" name="primary_color" id="color_picker" class="w-12 h-11 border border-gray-200 rounded-xl p-1 cursor-pointer" value="<?= htmlspecialchars($S['primary_color'] ?? '#8829C8') ?>">
            <input type="text" id="primary_hex" class="form-input flex-1" dir="ltr" value="<?= htmlspecialchars($S['primary_color'] ?? '#8829C8') ?>" readonly>
          </div>
        </div>
        <div>
          <label class="form-label">البريد الإلكتروني للإدارة</label>
          <input type="email" name="admin_email" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['admin_email'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">الدولة الافتراضية</label>
          <select name="default_country" class="form-input">
            <option value="0">كل الدول</option>
            <?php foreach ($countries_list as $c): ?>
            <option value="<?= $c['c_id'] ?>" <?= ($S['default_country']??'0')==$c['c_id']?'selected':'' ?>>
              <?= htmlspecialchars($c['c_flag'].' '.$c['c_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- الهيرو -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
          <i class="fa-solid fa-wand-magic-sparkles text-white text-xs"></i>
        </div>
        نصوص الصفحة الرئيسية (الهيرو)
      </h3>
      <div class="space-y-4">
        <div>
          <label class="form-label">العنوان الرئيسي (H1)</label>
          <input type="text" name="hero_title" class="form-input" value="<?= htmlspecialchars($S['hero_title'] ?? $S['site_tagline'] ?? 'منصة الحضور العربي الموثق') ?>" placeholder="منصة الحضور العربي الموثق">
        </div>
        <div>
          <label class="form-label">النص التعريفي تحت العنوان</label>
          <input type="text" name="hero_subtitle" class="form-input" value="<?= htmlspecialchars($S['hero_subtitle'] ?? $S['site_description'] ?? 'تحكم بما يعرفه الناس عنك') ?>" placeholder="تحكم بما يعرفه الناس عنك">
        </div>
      </div>
    </div>

    <!-- SEO -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
          <i class="fa-solid fa-magnifying-glass text-white text-xs"></i>
        </div>
        تحسين محركات البحث (SEO)
      </h3>
      <div class="space-y-4">
        <div>
          <label class="form-label">الشعار التعريفي (Tagline)</label>
          <input type="text" name="site_tagline" class="form-input" value="<?= htmlspecialchars($S['site_tagline'] ?? '') ?>" placeholder="منصة الحضور العربي الموثق">
        </div>
        <div>
          <label class="form-label">وصف الموقع (Meta Description)</label>
          <textarea name="site_description" rows="2" class="form-input resize-y"><?= htmlspecialchars($S['site_description'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">150-160 حرف مثالي</p>
        </div>
        <div>
          <label class="form-label">الكلمات المفتاحية</label>
          <input type="text" name="site_keywords" class="form-input" value="<?= htmlspecialchars($S['site_keywords'] ?? '') ?>" placeholder="شخصيات عربية, مؤسسات, من هم">
        </div>
        <div>
          <label class="form-label">Google Analytics ID</label>
          <input type="text" name="google_analytics" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
        </div>
      </div>
      <!-- Google preview -->
      <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mt-4">
        <p class="text-xs text-gray-500 font-bold mb-2">معاينة في Google</p>
        <p class="text-blue-600 text-base font-semibold" id="preview_title"><?= htmlspecialchars(($S['site_name']??'PioneerIcons').' | '.($S['site_tagline']??'')) ?></p>
        <p class="text-green-700 text-xs mt-0.5">https://yoursite.com</p>
        <p class="text-gray-600 text-sm mt-1" id="preview_desc"><?= htmlspecialchars($S['site_description']??'') ?></p>
      </div>
    </div>

    <!-- Footer -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 bg-gray-700 rounded-lg flex items-center justify-center">
          <i class="fa-solid fa-rectangle-ad text-white text-xs"></i>
        </div>
        الـ Footer
      </h3>
      <div class="space-y-4">
        <div>
          <label class="form-label">وصف الموقع في الفوتر</label>
          <textarea name="footer_about" rows="3" class="form-input resize-y"><?= htmlspecialchars($S['footer_about'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="form-label">نص حقوق الملكية</label>
          <input type="text" name="copyright_text" class="form-input" value="<?= htmlspecialchars($S['copyright_text'] ?? '') ?>" placeholder="جميع الحقوق محفوظة لـ PioneerIcons">
        </div>
      </div>
    </div>

    <!-- Social -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 bg-pink-500 rounded-lg flex items-center justify-center">
          <i class="fa-solid fa-share-nodes text-white text-xs"></i>
        </div>
        روابط السوشيال ميديا
      </h3>
      <div class="space-y-4">
        <div>
          <label class="form-label"><i class="fab fa-whatsapp text-green-500 mr-2"></i>واتساب</label>
          <input type="url" name="social_whatsapp" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['social_whatsapp'] ?? '') ?>" placeholder="https://wa.me/...">
        </div>
        <div>
          <label class="form-label"><i class="fab fa-linkedin-in text-blue-600 mr-2"></i>لينكدان</label>
          <input type="url" name="social_linkedin" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['social_linkedin'] ?? '') ?>" placeholder="https://linkedin.com/...">
        </div>
        <div>
          <label class="form-label"><i class="fa-brands fa-x-twitter text-gray-900 mr-2"></i>إكس (تويتر)</label>
          <input type="url" name="social_twitter" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['social_twitter'] ?? '') ?>" placeholder="https://x.com/...">
        </div>
      </div>
    </div>

    <button type="submit" class="w-full py-4 text-white font-black text-lg rounded-2xl hover:opacity-90 transition flex items-center justify-center gap-3" style="background:linear-gradient(135deg,#8829C8,#5B1494)">
      <i class="fa-solid fa-floppy-disk"></i> حفظ جميع الإعدادات
    </button>
  </form>
</div>

<script>
document.getElementById('color_picker')?.addEventListener('input', function() {
  document.getElementById('primary_hex').value = this.value;
});
document.querySelector('[name=site_name]')?.addEventListener('input', updatePreview);
document.querySelector('[name=site_tagline]')?.addEventListener('input', updatePreview);
document.querySelector('[name=site_description]')?.addEventListener('input', function() {
  document.getElementById('preview_desc').textContent = this.value;
});
function updatePreview() {
  const n = document.querySelector('[name=site_name]')?.value || '';
  const t = document.querySelector('[name=site_tagline]')?.value || '';
  document.getElementById('preview_title').textContent = n + (t ? ' | ' + t : '');
}
function previewLogo(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById('logo_preview');
      img.src = e.target.result;
      img.classList.remove('hidden');
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
