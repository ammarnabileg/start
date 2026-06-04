<?php
// POST is handled early in admin.php before output — $login_error is set there if credentials fail
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول - PioneerIcons</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Cairo', sans-serif; }
    .pi-gradient { background: linear-gradient(160deg, #0B0B1F 0%, #130B2B 50%, #1A0D35 100%); }
    .btn-purple { background: linear-gradient(135deg, #8829C8 0%, #5B1494 100%); }
    .star-bg {
      background-image: radial-gradient(circle, rgba(255,255,255,.6) 1px, transparent 1px);
      background-size: 40px 40px;
      opacity: .08;
      position: absolute; inset: 0;
    }
  </style>
</head>
<body class="min-h-screen pi-gradient flex items-center justify-center p-4 relative overflow-hidden">
  <div class="star-bg"></div>
  <div class="absolute w-96 h-96 rounded-full top-0 right-0 -translate-y-1/2 translate-x-1/3" style="background:radial-gradient(circle, rgba(136,41,200,.3) 0%, transparent 70%)"></div>
  <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 relative z-10">
    <div class="text-center mb-8">
      <div class="w-16 h-16 rounded-2xl btn-purple flex items-center justify-center mx-auto mb-4">
        <i class="fa-solid fa-star text-white text-2xl"></i>
      </div>
      <h1 class="text-2xl font-black text-gray-800">تسجيل الدخول</h1>
      <p class="text-gray-400 text-sm mt-1">لوحة تحكم PioneerIcons</p>
    </div>

    <?php if (!empty($login_error)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm font-semibold">
      <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($login_error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-1.5">البريد الإلكتروني</label>
        <input type="email" name="email" required
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-purple-400 transition"
          placeholder="admin@pioneericons.com"
          value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-6">
        <label class="block text-sm font-bold text-gray-700 mb-1.5">كلمة المرور</label>
        <input type="password" name="password" required
          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-purple-400 transition"
          placeholder="••••••••">
      </div>
      <button type="submit"
        class="w-full btn-purple text-white font-black py-3.5 rounded-xl hover:opacity-90 transition text-base">
        دخول <i class="fa-solid fa-arrow-left mr-2"></i>
      </button>
    </form>

    <div class="mt-6 text-center">
      <a href="index.php" class="text-sm text-gray-400 hover:text-purple-500 transition">
        <i class="fa-solid fa-arrow-right text-xs mr-1"></i> العودة للموقع
      </a>
    </div>
  </div>
</body>
</html>
<?php exit; ?>
