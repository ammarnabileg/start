<?php
require_once __DIR__ . '/head.php';

/** يلف محتوى الصفحة بالتصميم العام */
function render(string $title, string $active, callable $body, array $opts = []): void
{
    $hideTitle = !empty($opts['hideTitle']);
    $fullWidth = !empty($opts['full']);
    ob_start();
    $body();
    $content = ob_get_clean();
    require __DIR__ . (is_embed() ? '/embed.php' : '/layout.php');
    exit;
}

/** شكل موحد لصندوق خطأ */
function error_box(string $msg): void
{
    echo '<div class="bg-red-50 border border-red-200 text-red-800 rounded-xl p-4 text-sm">' . e($msg) . '</div>';
}

function no_api_box(): void
{
    echo '<div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-5">';
    echo t('<p class="font-bold mb-1">البرنامج لسه مش مربوط بخدمة النشر</p>');
    if (can('integrations.manage')) {
        echo '<p class="text-sm"><a class="underline font-semibold" href="' . e(url('connectors')) . '">' . e(t('اربط الشركة من صفحة التكاملات.')) . '</a></p>';
    } else {
        echo t('<p class="text-sm">اطلب من صاحب الشركة يضيف مفتاح الـ API.</p>');
    }
    echo '</div>';
}

function avatar(?string $src, string $name, string $size = 'w-10 h-10'): string
{
    if ($src) {
        return '<img src="' . e($src) . '" alt="" class="' . $size . ' rounded-full object-cover bg-slate-200" loading="lazy" referrerpolicy="no-referrer">';
    }
    $letter = mb_substr(trim($name) ?: '?', 0, 1);
    return '<div class="' . $size . ' rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">' . e($letter) . '</div>';
}

function card_open(string $extra = ''): string
{
    return '<div class="bg-white rounded-xl border border-slate-200 ' . $extra . '">';
}

/** صفحة بسيطة في النص (الدخول، التسجيل، الدعوة) */
function auth_page(string $title, callable $body): void
{
    $siteName = setting('site_name', t('لوحة النشر'));
    $isReg = in_array($GLOBALS['page'] ?? '', ['register', 'invite'], true);
    ?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($title . ' · ' . $siteName); ?>
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-b from-brand-50 via-ink-50 to-cyan-50">
  <div class="fixed top-4 end-4 flex gap-2 z-10"><?= pref_switch() ?></div>
  <div class="w-full max-w-4xl grid md:grid-cols-2 card p-0 overflow-hidden">
    <div class="hidden md:flex flex-col justify-between p-10 bg-gradient-to-br from-brand-700 via-brand-600 to-cyan-500 text-[#fff]">
      <div>
        <div class="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center text-xl font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></div>
        <h2 class="text-2xl font-bold mt-6 leading-snug"><?= e(t('كل السوشيال ميديا والرسايل والإعلانات في مكان واحد')) ?></h2>
      </div>
      <ul class="space-y-3 text-sm text-white/90 my-8">
        <?php foreach ([t('انشر وجدول على كل المنصات مرة واحدة'), t('رد على رسايل وتعليقات كل الحسابات من صندوق واحد'), t('إعلانات وتقارير وذكاء اصطناعي بيساعدك تكتب وتحلل')] as $f): ?>
          <li class="flex gap-3"><span class="w-6 h-6 shrink-0 rounded-full bg-white/20 flex items-center justify-center text-xs">✓</span><span><?= e($f) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="text-xs text-white/60">© <?= date('Y') ?> <?= e($siteName) ?></p>
    </div>
    <div class="p-8 sm:p-10 space-y-5">
      <div class="text-center md:text-start">
        <div class="md:hidden w-12 h-12 mx-auto mb-3 rounded-2xl bg-gradient-to-br from-brand-600 via-cyan-500 to-lime-400 text-[#fff] flex items-center justify-center text-xl font-bold"><?= e(mb_substr($siteName, 0, 1)) ?></div>
        <h1 class="text-xl font-bold text-ink-900"><?= e($title) ?></h1>
        <p class="text-ink-500 text-sm mt-1"><?= e($siteName) ?></p>
      </div>
      <?php foreach (take_flashes() as $f): $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-green-50 text-green-800 border-green-200'; ?>
        <div class="border rounded-xl p-3 text-sm <?= $cls ?>" role="alert"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>
      <?php $body(); ?>
    </div>
  </div>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
(function () {
  var T = <?= json_encode(['show' => t('إظهار'), 'hide' => t('إخفاء'), 'caps' => t('الـ Caps Lock شغال'), 'weak' => t('ضعيفة'), 'ok' => t('مقبولة'), 'good' => t('كويسة'), 'great' => t('قوية جدًا')], JSON_UNESCAPED_UNICODE) ?>, REG = <?= $isReg ? 'true' : 'false' ?>;
  document.querySelectorAll('input[type=password]').forEach(function (i) {
    var wrap = document.createElement('span'); wrap.className = 'relative block'; i.parentNode.insertBefore(wrap, i); wrap.appendChild(i); i.classList.add('pe-14');
    var b = document.createElement('button'); b.type = 'button'; b.tabIndex = -1; b.className = 'absolute inset-y-0 end-0 px-3 text-xs text-ink-500 hover:text-ink-800'; b.textContent = T.show;
    b.onclick = function () { var sh = i.type === 'password'; i.type = sh ? 'text' : 'password'; b.textContent = sh ? T.hide : T.show; i.focus(); };
    wrap.appendChild(b);
    var cw = document.createElement('span'); cw.className = 'text-[11px] text-amber-700 hidden'; cw.textContent = '⇪ ' + T.caps; wrap.parentNode.appendChild(cw);
    var caps = function (e) { if (e.getModifierState) cw.classList.toggle('hidden', !e.getModifierState('CapsLock')); };
    i.addEventListener('keydown', caps); i.addEventListener('keyup', caps); i.addEventListener('blur', function () { cw.classList.add('hidden'); });
    if (REG && i.name !== 'current' && i.name !== 'password_confirm') {
      var m = document.createElement('div'); m.className = 'grid grid-cols-4 gap-1 mt-1.5'; m.innerHTML = '<span></span><span></span><span></span><span></span>';
      var tx = document.createElement('span'); tx.className = 'text-[11px] text-ink-500';
      wrap.parentNode.appendChild(m); wrap.parentNode.appendChild(tx);
      i.addEventListener('input', function () {
        var p = i.value, s = 0; if (p.length >= 8) s++; if (p.length >= 12) s++; if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++; if (/\d/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
        if (/^(.)\1+$/.test(p) || /^(1234|password|qwerty)/i.test(p)) s = 0; s = p ? Math.max(1, Math.min(4, s)) : 0;
        var col = ['#eef0f3', '#dc2626', '#d97706', '#65a30d', '#16a34a'][s];
        m.querySelectorAll('span').forEach(function (x, k) { x.className = 'h-1 rounded-full'; x.style.background = k < s ? col : '#eef0f3'; });
        tx.textContent = p ? [T.weak, T.weak, T.ok, T.good, T.great][s] : '';
      });
    }
  });
  var f = document.querySelector('form'); if (f) f.addEventListener('submit', function () { var b = f.querySelector('button.btn-primary, button[type=submit]'); if (b) setTimeout(function () { b.disabled = true; b.classList.add('opacity-60'); }, 0); });
  var first = document.querySelector('form input:not([type=hidden])'); if (first && !first.value) first.focus();
})();
</script>
</body>
</html>
<?php
    exit;
}

function auth_input(string $name, string $label, string $type = 'text', string $value = '', bool $ltr = false): string
{
    $ac = ['email' => 'email', 'name' => 'name', 'password' => 'new-password'][$name] ?? ($type === 'email' ? 'email' : 'on');
    if ($type === 'password' && ($GLOBALS['page'] ?? '') === 'login') $ac = 'current-password';
    return '<label class="block"><span class="label">' . e($label) . '</span>'
        . '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" required autocomplete="' . $ac . '"'
        . ($type === 'password' ? ' minlength="8"' : '')
        . ' class="input"' . ($ltr ? ' dir="ltr"' : '') . '></label>';
}

/** بعد الدخول: لو كان جاي من لينك دعوة، رجّعه ليه */
function after_login_redirect(): void
{
    if (!empty($_SESSION['invite_token'])) {
        redirect(url('invite', ['t' => $_SESSION['invite_token']]));
    }
    redirect(url('dashboard'));
}
