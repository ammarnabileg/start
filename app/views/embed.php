<?php
/** شكل الصفحة جوه النافذة المنبثقة: من غير القائمة والشريط العلوي */
?><!doctype html>
<?= html_open() ?>
<head>
<?php page_head($title); ?>
</head>
<body class="min-h-screen">
<main class="px-5 py-5">
  <?php foreach (take_flashes() as $f):
    $cls = $f['type'] === 'error' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>
    <div data-flash="<?= e($f['type']) ?>" class="mb-4 border rounded-xl px-4 py-3 text-sm <?= $cls ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
<script>
// جوه إطار: أي رابط لصفحة تانية يفتح في الصفحة الأساسية مش جوه الإطار
(function () { if (window.top === window) return; var cur = (/[?&]p=([a-z_]+)/.exec(location.search) || [])[1];
  document.addEventListener('click', function (e) { var a = e.target.closest('a[href]'); if (!a || a.target) return; var m = /[?&]p=([a-z_]+)/.exec(a.getAttribute('href') || ''); if (m && m[1] !== cur) a.target = '_top'; }, true); })();
</script>
<script src="assets/live.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
