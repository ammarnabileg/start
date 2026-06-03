<?php
pi_require_perm('manage_settings');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [
        'site_name','site_name_ar','site_tagline','site_description','site_keywords',
        'site_logo','footer_about','social_whatsapp','social_linkedin','social_twitter',
        'primary_color','admin_email','copyright_text','google_analytics','default_country',
    ];
    foreach ($fields as $field) {
        $val = pi_escape($_POST[$field] ?? '');
        $mysqli->query("INSERT INTO pi_settings (s_key,s_value) VALUES ('$field','$val') ON DUPLICATE KEY UPDATE s_value='$val'");
    }
    // Clear settings cache by destroying static var
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
    <p class="text-gray-400 text-sm mt-0.5">تحكم في اسم الموقع، SEO، الألوان، السوشيال ميديا، وكل التفاصيل</p>
  </div>

  <form method="POST" class="space-y-6">

    <!-- Site Identity -->
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
        <div>
          <label class="form-label">الشعار (رابط صورة)</label>
          <input type="url" name="site_logo" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['site_logo'] ?? '') ?>" placeholder="https://...">
        </div>
        <div>
          <label class="form-label">اللون الرئيسي</label>
          <div class="flex gap-2">
            <input type="color" name="primary_color" class="w-12 h-11 border border-gray-200 rounded-xl p-1 cursor-pointer" value="<?= htmlspecialchars($S['primary_color'] ?? '#f97316') ?>">
            <input type="text" id="primary_hex" class="form-input flex-1" dir="ltr" value="<?= htmlspecialchars($S['primary_color'] ?? '#f97316') ?>" readonly>
          </div>
          <script>document.querySelector('[name=primary_color]').addEventListener('input',function(){document.getElementById('primary_hex').value=this.value});</script>
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
          <p class="text-xs text-gray-400 mt-1">يُفضَّل 150-160 حرف</p>
        </div>
        <div>
          <label class="form-label">الكلمات المفتاحية (Keywords)</label>
          <input type="text" name="site_keywords" class="form-input" value="<?= htmlspecialchars($S['site_keywords'] ?? '') ?>" placeholder="شخصيات عربية, مؤسسات, من هم">
          <p class="text-xs text-gray-400 mt-1">افصل بينها بفاصلة</p>
        </div>
        <div>
          <label class="form-label">Google Analytics ID</label>
          <input type="text" name="google_analytics" class="form-input" dir="ltr" value="<?= htmlspecialchars($S['google_analytics'] ?? '') ?>" placeholder="G-XXXXXXXXXX">
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <h3 class="font-black text-gray-800 mb-5 flex items-center gap-2">
        <div class="w-8 h-8 bg-gray-700 rounded-lg flex items-center justify-center">
          <i class="fa-solid fa-rectangle-ad text-white text-xs"></i>
        </div>
        الـ Footer والحقوق
      </h3>
      <div class="space-y-4">
        <div>
          <label class="form-label">وصف الموقع في الفوتر</label>
          <textarea name="footer_about" rows="3" class="form-input resize-y"><?= htmlspecialchars($S['footer_about'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="form-label">نص حقوق الملكية</label>
          <input type="text" name="copyright_text" class="form-input" value="<?= htmlspecialchars($S['copyright_text'] ?? '') ?>" placeholder="جميع الحقوق محفوظة لـ PioneerIcons">
          <p class="text-xs text-gray-400 mt-1">سيُضاف تلقائياً © والسنة</p>
        </div>
      </div>
    </div>

    <!-- Social Media -->
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

    <!-- Preview -->
    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5">
      <h4 class="font-bold text-gray-700 mb-3 text-sm">معاينة — كيف سيظهر في Google</h4>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <p class="text-blue-600 text-lg font-semibold leading-tight" id="preview_title">
          <?= htmlspecialchars($S['site_name'] ?? 'PioneerIcons') ?> | <?= htmlspecialchars($S['site_tagline'] ?? '') ?>
        </p>
        <p class="text-green-700 text-sm mt-0.5">https://yoursite.com/pioneer</p>
        <p class="text-gray-600 text-sm mt-1 leading-6" id="preview_desc"><?= htmlspecialchars($S['site_description'] ?? '') ?></p>
      </div>
      <script>
        document.querySelector('[name=site_name]')?.addEventListener('input',function(){
          document.getElementById('preview_title').textContent=this.value+' | '+document.querySelector('[name=site_tagline]').value;
        });
        document.querySelector('[name=site_tagline]')?.addEventListener('input',function(){
          document.getElementById('preview_title').textContent=document.querySelector('[name=site_name]').value+' | '+this.value;
        });
        document.querySelector('[name=site_description]')?.addEventListener('input',function(){
          document.getElementById('preview_desc').textContent=this.value;
        });
      </script>
    </div>

    <button type="submit" class="w-full py-4 pi-primary-bg text-white font-black text-lg rounded-2xl hover:opacity-90 transition flex items-center justify-center gap-3">
      <i class="fa-solid fa-floppy-disk"></i> حفظ جميع الإعدادات
    </button>
  </form>
</div>
