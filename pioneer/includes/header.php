<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'PioneerIcons - من هم') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    * { font-family: 'Cairo', sans-serif; }
    .verified-badge { color: #1d9bf0; }
    .gold-badge { color: #D4AF37; }
    .pi-gradient { background: linear-gradient(135deg, #1a3a6b 0%, #0f2548 100%); }
    .pi-orange { color: #f97316; }
    .pi-orange-bg { background-color: #f97316; }
    .card-hover { transition: transform .2s, box-shadow .2s; }
    .card-hover:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,.12); }
    .hero-bg { background: linear-gradient(135deg, #0f2548 0%, #1a3a6b 50%, #1e4080 100%); }
    .daily-bg { background: linear-gradient(135deg, #1d9bf0 0%, #0f7dc5 100%); }
    [x-cloak] { display: none !important; }
  </style>
</head>
<body class="bg-gray-50">

<!-- NAVBAR -->
<nav class="bg-white shadow-md sticky top-0 z-50" x-data="{ addOpen: false, memberOpen: false, countryOpen: false }">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center justify-between h-16">

      <!-- Logo -->
      <a href="index.php" class="flex items-center gap-2 flex-shrink-0">
        <div class="w-9 h-9 rounded-lg pi-gradient flex items-center justify-center">
          <i class="fa-solid fa-star text-orange-400 text-sm"></i>
        </div>
        <span class="font-bold text-xl text-gray-800">PioneerIcons</span>
      </a>

      <!-- Nav links -->
      <div class="hidden md:flex items-center gap-1 flex-1 justify-center">

        <!-- أضف -->
        <div class="relative" x-data="{ open: false }">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
            أضف <i class="fa-solid fa-chevron-down text-xs mt-0.5"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full right-0 mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <a href="add_personality.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <i class="fa-solid fa-user-plus w-5 text-orange-400"></i> أضف شخصية
            </a>
            <a href="add_institution.php" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition">
              <i class="fa-solid fa-building w-5 text-orange-400"></i> أضف شركة
            </a>
          </div>
        </div>

        <!-- عضويات -->
        <div class="relative" x-data="{ open: false }">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1 px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
            عضويات <i class="fa-solid fa-chevron-down text-xs mt-0.5"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full right-0 mt-1 w-56 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <a href="membership.php?type=verified" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition">
              <i class="fa-solid fa-circle-check w-5 text-blue-500"></i>
              العضوية الموثقة
            </a>
            <a href="membership.php?type=executive" class="flex items-center gap-2 px-4 py-2.5 text-gray-700 hover:bg-yellow-50 hover:text-yellow-700 transition">
              <i class="fa-solid fa-crown w-5 text-yellow-500"></i>
              عضوية الرؤساء التنفيذيين
            </a>
          </div>
        </div>

        <a href="appointments.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
          نشرة تعيينات السعودية
        </a>
        <a href="categories.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
          التصنيفات
        </a>
        <a href="lists.php" class="px-4 py-2 text-gray-700 hover:text-orange-500 font-semibold rounded-lg hover:bg-orange-50 transition">
          القوائم
        </a>
      </div>

      <!-- Right side: country + login -->
      <div class="flex items-center gap-3">
        <!-- Country selector -->
        <div class="relative" x-data="{ open: false, country: { flag: '🇸🇦', name: 'السعودية' } }">
          <button @click="open=!open" @click.outside="open=false"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 hover:border-orange-300 transition text-sm font-semibold">
            <span x-text="country.flag"></span>
            <span x-text="country.name" class="hidden sm:inline"></span>
            <i class="fa-solid fa-chevron-down text-xs"></i>
          </button>
          <div x-show="open" x-cloak x-transition
            class="absolute top-full left-0 mt-1 w-48 bg-white rounded-xl shadow-xl border border-gray-100 py-2 z-50">
            <?php
            $countries = [
              ['flag'=>'🇸🇦','name'=>'السعودية'],['flag'=>'🇪🇬','name'=>'مصر'],
              ['flag'=>'🇦🇪','name'=>'الإمارات'],['flag'=>'🇰🇼','name'=>'الكويت'],
              ['flag'=>'🇧🇭','name'=>'البحرين'],['flag'=>'🇴🇲','name'=>'عمان'],
              ['flag'=>'🇶🇦','name'=>'قطر'],['flag'=>'🇸🇾','name'=>'سوريا'],['flag'=>'🇮🇶','name'=>'العراق'],
            ];
            foreach($countries as $c): ?>
            <button @click="country={flag:'<?=$c['flag']?>',name:'<?=$c['name']?>'};open=false"
              class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-orange-50 hover:text-orange-600 transition text-sm">
              <?=$c['flag']?> <?=$c['name']?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <a href="admin.php?p=login"
          class="px-5 py-2 pi-orange-bg text-white rounded-full font-bold hover:opacity-90 transition text-sm whitespace-nowrap">
          تسجيل الدخول
        </a>
      </div>
    </div>
  </div>
</nav>
