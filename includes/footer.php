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
              <i class="fa-solid fa-star text-purple-400 text-sm"></i>
            </div>
            <span class="font-bold text-xl text-white"><?= htmlspecialchars($_S_f['site_name'] ?? 'PioneerIcons') ?></span>
          <?php endif; ?>
        </div>
        <p class="text-gray-400 text-sm leading-7 mb-5"><?= htmlspecialchars($_S_f['footer_about'] ?? '') ?></p>
        <p class="text-gray-500 text-sm font-semibold mb-3">تابعنا الآن</p>
        <div class="flex flex-wrap gap-2">
          <?php
          $footer_socials = [
            ['social_whatsapp',  'fab fa-whatsapp',       'hover:bg-green-600'],
            ['social_instagram',  'fab fa-instagram',      'hover:bg-gradient-to-br hover:from-pink-500 hover:to-yellow-400'],
            ['social_facebook',   'fab fa-facebook-f',     'hover:bg-blue-600'],
            ['social_twitter',    'fa-brands fa-x-twitter','hover:bg-gray-600'],
            ['social_linkedin',   'fab fa-linkedin-in',    'hover:bg-blue-700'],
            ['social_youtube',    'fab fa-youtube',        'hover:bg-red-600'],
            ['social_tiktok',     'fab fa-tiktok',         'hover:bg-gray-700'],
            ['social_snapchat',   'fab fa-snapchat',       'hover:bg-yellow-400 hover:text-gray-900'],
            ['social_telegram',   'fab fa-telegram',       'hover:bg-sky-500'],
            ['social_threads',    'fab fa-threads',        'hover:bg-gray-700'],
            ['social_pinterest',  'fab fa-pinterest',      'hover:bg-red-600'],
          ];
          foreach ($footer_socials as list($key, $icon, $hover)):
            if (empty($_S_f[$key])) continue;
          ?>
          <a href="<?= htmlspecialchars($_S_f[$key]) ?>" target="_blank" rel="noopener"
            class="w-9 h-9 rounded-full bg-gray-800 flex items-center justify-center <?= $hover ?> transition text-sm">
            <i class="<?= $icon ?>"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Col 2: Links -->
      <div>
        <h4 class="text-white font-bold text-lg mb-5">حول <?= htmlspecialchars($_S_f['site_name_ar'] ?? 'من هم') ?></h4>
        <ul class="space-y-2.5 text-sm">
          <li><a href="categories.php" class="hover:text-purple-400 transition">التصنيفات</a></li>
          <li><a href="admin.php" class="hover:text-purple-400 transition">إدارة الحسابات</a></li>
          <li><a href="about.php" class="hover:text-purple-400 transition">عن <?= htmlspecialchars($_S_f['site_name_ar'] ?? 'من هم') ?></a></li>
          <li><a href="complaints.php" class="hover:text-purple-400 transition">شكاوي وملاحظات</a></li>
          <li><a href="about_majara.php" class="hover:text-purple-400 transition">عن مجرة</a></li>
          <li><a href="privacy.php" class="hover:text-purple-400 transition">سياسة الخصوصية</a></li>
          <li><a href="terms.php" class="hover:text-purple-400 transition">شروط الاستخدام</a></li>
        </ul>
      </div>

      <!-- Col 3: Other sites -->
      <div>
        <h4 class="text-white font-bold text-lg mb-5">مواقع أخرى من مجرة</h4>
        <ul class="space-y-2.5 text-sm">
          <li><a href="#" class="hover:text-purple-400 transition">هارفارد بزنس ريفيو</a></li>
          <li><a href="#" class="hover:text-purple-400 transition">MIT Technology Review</a></li>
          <li><a href="#" class="hover:text-purple-400 transition">نفسيتي</a></li>
          <li><a href="#" class="hover:text-purple-400 transition">العلوم للعموم</a></li>
          <li><a href="#" class="hover:text-purple-400 transition">ستانفورد للابتكار الاجتماعي</a></li>
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
<!-- Global image upload preview handler -->
<script>
(function() {
  function initUploadZone(input) {
    input.addEventListener('change', function() {
      if (!this.files || !this.files[0]) return;
      var prevId = this.getAttribute('data-preview');
      var phId   = this.getAttribute('data-placeholder');
      var reader = new FileReader();
      reader.onload = function(e) {
        if (prevId) {
          var img = document.getElementById(prevId);
          if (img) { img.src = e.target.result; img.classList.remove('hidden'); img.style.display = ''; }
        }
        if (phId) {
          var ph = document.getElementById(phId);
          if (ph) { ph.style.display = 'none'; ph.classList.add('hidden'); }
        }
      };
      reader.readAsDataURL(this.files[0]);
    });
    // Drag & drop support on parent zone
    var zone = input.closest('.pi-upload-zone');
    if (zone) {
      zone.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('drag-over'); });
      zone.addEventListener('dragleave', function() { this.classList.remove('drag-over'); });
      zone.addEventListener('drop', function(e) {
        e.preventDefault(); this.classList.remove('drag-over');
        var files = e.dataTransfer.files;
        if (files.length) {
          var inp = this.querySelector('input[type="file"]');
          if (inp) {
            var dt = new DataTransfer(); dt.items.add(files[0]);
            inp.files = dt.files;
            inp.dispatchEvent(new Event('change'));
          }
        }
      });
    }
  }
  // Init all existing upload inputs with data-preview
  document.querySelectorAll('input[type="file"][data-preview]').forEach(initUploadZone);
  // Watch for any dynamically added inputs
  var obs = new MutationObserver(function(mutations) {
    mutations.forEach(function(m) {
      m.addedNodes.forEach(function(n) {
        if (n.nodeType === 1) {
          n.querySelectorAll && n.querySelectorAll('input[type="file"][data-preview]').forEach(initUploadZone);
        }
      });
    });
  });
  obs.observe(document.body, { childList: true, subtree: true });
})();
</script>

<script>
// ══ PioneerIcons Text Reveal Engine ══
(function(){
  var classes = ['pi-reveal','pi-reveal-strong','pi-reveal-left','pi-reveal-right','pi-reveal-fade','pi-section-head'];
  var selector = classes.map(function(c){ return '.'+c; }).join(',');

  function activate(el) {
    el.classList.add('active');
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          // Respect delay classes
          var delay = 0;
          if (e.target.classList.contains('pi-delay-1')) delay = 100;
          else if (e.target.classList.contains('pi-delay-2')) delay = 200;
          else if (e.target.classList.contains('pi-delay-3')) delay = 350;
          else if (e.target.classList.contains('pi-delay-4')) delay = 500;
          else if (e.target.classList.contains('pi-delay-5')) delay = 650;
          else if (e.target.classList.contains('pi-delay-6')) delay = 800;
          if (delay) {
            setTimeout(function(){ activate(e.target); }, delay);
          } else {
            activate(e.target);
          }
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll(selector).forEach(function(el){
      io.observe(el);
    });
  } else {
    // Fallback: activate all immediately
    document.querySelectorAll(selector).forEach(activate);
  }
})();
</script>
</body>
</html>
