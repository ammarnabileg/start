<?php require __DIR__ . '/header.php'; ?>
<div class="max-w-md mx-auto mt-20 text-center p-8 bg-white rounded-2xl shadow">
    <div class="text-6xl mb-4">⛔</div>
    <h1 class="text-2xl font-bold mb-2">غير مصرح لك</h1>
    <p class="text-gray-600 mb-6">ليس لديك صلاحية للوصول إلى هذه الصفحة.</p>
    <a href="<?= url('dashboard.php') ?>" class="inline-block bg-emerald-600 text-white px-6 py-2 rounded-lg hover:bg-emerald-700">العودة للوحة التحكم</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
