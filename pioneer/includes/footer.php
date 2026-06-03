<?php
$_S_f = pi_get_settings();
?>
<!-- FOOTER -->
<footer class="bg-gray-900 text-gray-300 mt-20">
  <div class="max-w-7xl mx-auto px-4 py-14">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">

      <!-- Col 1: Logo + About -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <?php if ($_S_f['site_logo']): ?>
            <img src="<?= htmlspecialchars($_S_f['site_logo']) ?>" class="h-9 object-contain filter brightness-200">
          <?php else: ?>
            <div class="w-9 h-9 rounded-lg pi-gradient flex items-center justify-center">
              <i class="fa-solid fa-star text-orange-400 text-sm"></i>
            </div>
            <span class="font-bold text-xl text-white"><?= htmlspecialchars($_S_f['site_name'] ?? 'PioneerIcons') ?></span>
          <?php endif; ?>
        </div>
        <p class="text-gray-400 text-sm leading-7 mb-5"><?= htmlspecialchars($_S_f['footer_about'] ?? '') ?></p>
        <p class="text-gray-500 text-sm font-semibold mb-3">تابعنا الآن</p>
        <div class="flex gap-3">
          <?php if ($_S_f['social_whatsapp']): ?>
          <a href="<?= htmlspecialchars($_S_f['social_whatsapp']) ?>" class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center hover:bg-green-600 transition">
            <i class="fab fa-whatsapp"></i>
          </a>
          <?php else: ?>
          <span class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center opacity-40 cursor-not-allowed">
            <i class="fab fa-whatsapp"></i>
          </span>
          <?php endif; ?>
          <?php if ($_S_f['social_linkedin']): ?>
          <a href="<?= htmlspecialchars($_S_f['social_linkedin']) ?>" class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center hover:bg-blue-700 transition">
            <i class="fab fa-linkedin-in"></i>
          </a>
          <?php else: ?>
          <span class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center opacity-40 cursor-not-allowed">
            <i class="fab fa-linkedin-in"></i>
          </span>
          <?php endif; ?>
          <?php if ($_S_f['social_twitter']): ?>
          <a href="<?= htmlspecialchars($_S_f['social_twitter']) ?>" class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center hover:bg-gray-600 transition">
            <i class="fa-brands fa-x-twitter"></i>
          </a>
          <?php else: ?>
          <span class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center opacity-40 cursor-not-allowed">
            <i class="fa-brands fa-x-twitter"></i>
          </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Col 2: Links -->
      <div>
        <h4 class="text-white font-bold text-lg mb-5">حول <?= htmlspecialchars($_S_f['site_name_ar'] ?? 'من هم') ?></h4>
        <ul class="space-y-2.5 text-sm">
          <li><a href="categories.php" class="hover:text-orange-400 transition">التصنيفات</a></li>
          <li><a href="admin.php" class="hover:text-orange-400 transition">إدارة الحسابات</a></li>
          <li><a href="about.php" class="hover:text-orange-400 transition">عن <?= htmlspecialchars($_S_f['site_name_ar'] ?? 'من هم') ?></a></li>
          <li><a href="complaints.php" class="hover:text-orange-400 transition">شكاوي وملاحظات</a></li>
          <li><a href="about_majara.php" class="hover:text-orange-400 transition">عن مجرة</a></li>
          <li><a href="privacy.php" class="hover:text-orange-400 transition">سياسة الخصوصية</a></li>
          <li><a href="terms.php" class="hover:text-orange-400 transition">شروط الاستخدام</a></li>
        </ul>
      </div>

      <!-- Col 3: Other sites -->
      <div>
        <h4 class="text-white font-bold text-lg mb-5">مواقع أخرى من مجرة</h4>
        <ul class="space-y-2.5 text-sm">
          <li><a href="#" class="hover:text-orange-400 transition">هارفارد بزنس ريفيو</a></li>
          <li><a href="#" class="hover:text-orange-400 transition">MIT Technology Review</a></li>
          <li><a href="#" class="hover:text-orange-400 transition">نفسيتي</a></li>
          <li><a href="#" class="hover:text-orange-400 transition">العلوم للعموم</a></li>
          <li><a href="#" class="hover:text-orange-400 transition">ستانفورد للابتكار الاجتماعي</a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="border-t border-gray-800 py-5">
    <p class="text-center text-gray-500 text-sm">
      <?= htmlspecialchars($_S_f['copyright_text'] ?? 'جميع الحقوق محفوظة') ?> &copy; <?= date('Y') ?>
    </p>
  </div>
</footer>
</body>
</html>
