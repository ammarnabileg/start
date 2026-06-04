<?php
require_once 'includes/config.php';

$inst_id = (int)($_GET['id'] ?? 0);
if (!$inst_id) { header('Location: index.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_id=$inst_id AND inst_active=1");
if (!$r || !$r->num_rows) { header('Location: index.php'); exit; }
$inst = $r->fetch_assoc();

// Increment views
$mysqli->query("UPDATE pi_institutions SET inst_views=inst_views+1 WHERE inst_id=$inst_id");
$_vi = 'vi_i_'.$inst_id;
if (empty($_SESSION[$_vi]) || (time()-$_SESSION[$_vi])>1800) {
    $_SESSION[$_vi]=time();
    $mysqli->query("INSERT INTO pi_visit_daily (vd_page,vd_date,vd_count) VALUES ('institution/$inst_id',CURDATE(),1) ON DUPLICATE KEY UPDATE vd_count=vd_count+1");
}

$pageTitle = htmlspecialchars($inst['inst_name_ar']) . ' - ' . pi_setting('site_name');
$total_count = pi_count_personalities() + pi_count_institutions();

// Social links
$socials = [];
$r = $mysqli->query("SELECT * FROM pi_social_links WHERE sl_entity_type='institution' AND sl_entity_id=$inst_id");
if ($r) while ($row = $r->fetch_assoc()) $socials[$row['sl_platform']] = $row['sl_url'];

// Categories
$inst_cats = [];
$cat_ids = [];
$r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c JOIN pi_institution_categories ic ON c.cat_id=ic.cat_id LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE ic.inst_id=$inst_id");
if ($r) while ($row = $r->fetch_assoc()) { $inst_cats[] = $row; $cat_ids[] = (int)$row['cat_id']; }

// Related personalities (same categories)
$related_personalities = [];
if (!empty($cat_ids)) {
    $cat_ids_str = implode(',', $cat_ids);
    $r = $mysqli->query("SELECT DISTINCT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id IN ($cat_ids_str) AND p.p_active=1 ORDER BY p.p_views DESC LIMIT 6");
    if ($r) while ($row = $r->fetch_assoc()) $related_personalities[] = $row;
}

$social_icons = [
    'facebook'  => ['fa-brands fa-facebook-f', 'bg-blue-600'],
    'instagram' => ['fa-brands fa-instagram', 'bg-pink-500'],
    'youtube'   => ['fa-brands fa-youtube', 'bg-red-600'],
    'twitter'   => ['fa-brands fa-x-twitter', 'bg-gray-900'],
    'linkedin'  => ['fa-brands fa-linkedin-in', 'bg-blue-700'],
    'website'   => ['fa-solid fa-globe', 'bg-teal-500'],
];

// Similar institutions (same name parts)
$similar_insts = [];
$name_parts = preg_split('/\s+/', trim($inst['inst_name_ar']));
$like_clauses = [];
foreach ($name_parts as $part) {
    if (mb_strlen($part) >= 3) $like_clauses[] = "inst_name_ar LIKE '%".pi_escape($part)."%'";
}
if ($like_clauses) {
    $like_sql = implode(' OR ', $like_clauses);
    $r = $mysqli->query("SELECT * FROM pi_institutions WHERE inst_active=1 AND inst_id!=$inst_id AND ($like_sql) ORDER BY inst_views DESC LIMIT 4");
    if ($r) while ($row=$r->fetch_assoc()) $similar_insts[] = $row;
}

// Same category institutions
$same_cat_insts = [];
if (!empty($cat_ids)) {
    $cids_str = implode(',', $cat_ids);
    $r = $mysqli->query("SELECT DISTINCT i.* FROM pi_institutions i JOIN pi_institution_categories ic ON i.inst_id=ic.inst_id WHERE ic.cat_id IN ($cids_str) AND i.inst_active=1 AND i.inst_id!=$inst_id ORDER BY i.inst_views DESC LIMIT 6");
    if ($r) while ($row=$r->fetch_assoc()) $same_cat_insts[] = $row;
}

// Executive personalities (sidebar)
$executives = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1 ORDER BY p_views DESC LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $executives[] = $row;

// Related articles (sidebar)
$related_articles = [];
$r = $mysqli->query("SELECT * FROM pi_articles WHERE art_active=1 ORDER BY art_id DESC LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $related_articles[] = $row;

// Daily personality
$daily = null;
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_verified=1 ORDER BY RAND() LIMIT 1");
if ($r && $r->num_rows) $daily = $r->fetch_assoc();

// Sponsors
$sponsors = [];
$r = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_active=1 ORDER BY sp_order LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $sponsors[] = $row;

include 'includes/header.php';
?>

<!-- HERO SEARCH -->
<section class="hero-bg py-10">
  <div class="max-w-3xl mx-auto px-4">
    <form action="search.php" method="GET" class="flex rounded-2xl overflow-hidden shadow-2xl">
      <button type="submit" class="pi-primary-bg px-8 py-4 text-white font-bold hover:opacity-90 transition whitespace-nowrap">ابحث</button>
      <input name="q" type="text" placeholder="ابحث عن <?= number_format($total_count) ?> شخصية ومؤسسة..."
        class="flex-1 px-4 py-4 text-gray-800 outline-none font-semibold placeholder-gray-400 bg-white">
    </form>
  </div>
</section>

<!-- SPONSORS SPOTLIGHT -->
<?php if (!empty($sponsors)): ?>
<div style="background:linear-gradient(135deg,#f0dfa0 0%,#e8d080 50%,#d4b84a 100%);box-shadow:0 4px 24px rgba(180,140,0,.18);border-bottom:1px solid #c9a93a;">
  <div class="max-w-7xl mx-auto px-4" style="display:flex;align-items:center;gap:0;min-height:88px;">
    <!-- Label + CTA (right in RTL) -->
    <div style="flex-shrink:0;padding:16px 0 16px 28px;border-left:1.5px solid rgba(0,0,0,.10);min-width:160px;text-align:right;">
      <p style="font-size:11px;letter-spacing:.05em;color:#78520a;font-weight:900;margin-bottom:10px;text-transform:uppercase;">تحت الضوء ✦</p>
      <a href="advertise.php"
        style="display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:999px;background:#2563eb;color:#fff;font-size:12px;font-weight:800;text-decoration:none;white-space:nowrap;box-shadow:0 2px 8px rgba(37,99,235,.35);">
        <i class="fa-solid fa-bullhorn" style="font-size:11px;"></i> أعلن عن شركتك
      </a>
    </div>
    <!-- Logos -->
    <div style="display:flex;align-items:center;gap:10px;flex:1;padding:16px 28px 16px 0;overflow-x:auto;scrollbar-width:none;">
      <?php foreach ($sponsors as $sp): ?>
      <a href="<?= htmlspecialchars($sp['sp_url']??'#') ?>" target="_blank" title="<?= htmlspecialchars($sp['sp_name']) ?>"
        style="display:flex;align-items:center;justify-content:center;flex-shrink:0;width:130px;height:72px;background:#fff;border-radius:14px;padding:10px;box-shadow:0 2px 10px rgba(0,0,0,.10);transition:transform .2s,box-shadow .2s;"
        onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.18)'"
        onmouseout="this.style.transform='';this.style.boxShadow='0 2px 10px rgba(0,0,0,.10)'">
        <?php if ($sp['sp_logo']): ?>
          <img src="<?= htmlspecialchars($sp['sp_logo']) ?>" alt="<?= htmlspecialchars($sp['sp_name']) ?>"
            style="max-height:52px;max-width:108px;object-fit:contain;">
        <?php else: ?>
          <span style="font-size:12px;font-weight:800;color:#374151;text-align:center;line-height:1.3;"><?= htmlspecialchars($sp['sp_name']) ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- BREADCRUMB -->
<div class="max-w-7xl mx-auto px-4 py-4">
  <nav class="flex items-center gap-2 text-sm text-gray-500">
    <a href="index.php" class="hover:text-purple-600 transition font-semibold">الرئيسية</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <a href="all_institutions.php" class="hover:text-purple-600 transition font-semibold">المؤسسات</a>
    <i class="fa-solid fa-slash text-xs text-gray-300"></i>
    <span class="text-gray-800 font-semibold"><?= htmlspecialchars($inst['inst_name_ar']) ?></span>
  </nav>
</div>

<!-- MAIN CONTENT -->
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- LEFT: Institution Info -->
    <div class="lg:col-span-2 space-y-8">

      <!-- Institution card -->
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex flex-col sm:flex-row gap-6">
          <!-- Logo -->
          <div class="flex-shrink-0">
            <?php if (!empty($inst['inst_logo'])): ?>
              <img src="<?= htmlspecialchars($inst['inst_logo']) ?>" alt="<?= htmlspecialchars($inst['inst_name_ar']) ?>"
                class="w-32 h-32 rounded-2xl object-cover border-4 border-purple-100">
            <?php else: ?>
              <div class="w-32 h-32 rounded-2xl pi-gradient flex items-center justify-center">
                <span class="text-white font-black text-4xl"><?= mb_substr($inst['inst_name_ar'], 0, 1) ?></span>
              </div>
            <?php endif; ?>
          </div>

          <!-- Info -->
          <div class="flex-1">
            <h1 class="text-2xl font-black text-gray-900 mb-1">
              <?= htmlspecialchars($inst['inst_name_ar']) ?>
              <?php if (!empty($inst['inst_verified'])): ?>
                <i class="fa-solid fa-circle-check verified-badge text-lg mr-1"></i>
              <?php endif; ?>
            </h1>
            <?php if (!empty($inst['inst_name_en'])): ?>
              <p class="text-gray-600 font-semibold mb-2"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
            <?php endif; ?>
            <?php if (!empty($inst['inst_name_en'])): ?>
              <p class="text-gray-400 text-sm mb-2" dir="ltr"><?= htmlspecialchars($inst['inst_name_en']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 text-sm text-gray-600 mb-4">
              <?php if (!empty($inst['inst_country'])): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-flag text-purple-500 text-xs"></i>
                <?= htmlspecialchars($inst['inst_country']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($inst['inst_founded'])): ?>
              <span class="flex items-center gap-1.5">
                <i class="fa-solid fa-calendar text-purple-500 text-xs"></i>
                تأسست <?= htmlspecialchars($inst['inst_founded']) ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($inst['inst_website'])): ?>
              <a href="<?= htmlspecialchars($inst['inst_website']) ?>" target="_blank" rel="noopener nofollow"
                class="flex items-center gap-1.5 text-purple-600 hover:underline">
                <i class="fa-solid fa-globe text-xs"></i>
                الموقع الرسمي
              </a>
              <?php endif; ?>
            </div>

            <!-- Social links -->
            <?php if (!empty($socials)): ?>
            <div class="flex flex-wrap gap-2 mb-4">
              <?php foreach ($socials as $platform => $url): ?>
              <?php $ico = $social_icons[$platform] ?? ['fa-solid fa-link', 'bg-gray-500']; ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="nofollow noopener"
                class="w-8 h-8 <?= $ico[1] ?> rounded-full flex items-center justify-center text-white text-sm hover:opacity-80 transition">
                <i class="<?= $ico[0] ?>"></i>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="flex flex-wrap gap-3">
              <button onclick="document.getElementById('inst-card-modal').style.display='flex'"
                style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;border-radius:999px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;">
                <i class="fa-solid fa-id-card"></i> تحميل البطاقة التعريفية
              </button>
              <button onclick="navigator.share ? navigator.share({title:'<?= addslashes(htmlspecialchars($inst['inst_name_ar'])) ?>',url:location.href}) : alert('تم نسخ الرابط')"
                class="w-9 h-9 border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:text-purple-600 hover:border-purple-300 transition">
                <i class="fa-solid fa-share-nodes"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Description -->
      <?php if (!empty($inst['inst_description'])): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h2 class="text-lg font-black text-gray-800 mb-4">عن <?= htmlspecialchars($inst['inst_name_ar']) ?></h2>
        <div class="text-gray-700 leading-8 text-sm space-y-2 prose prose-sm max-w-none">
          <?= $inst['inst_description'] ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Categories -->
      <?php if (!empty($inst_cats)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 class="font-black text-gray-800 mb-4" style="font-size:14px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">التصنيفات</h3>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($inst_cats as $cat): ?>
          <?php $lc = $cat['label_color'] ?? '#8829C8'; ?>
          <a href="categories.php?cat=<?= $cat['cat_id'] ?>"
            style="display:inline-flex;align-items:center;gap:7px;padding:7px 14px;background:<?= htmlspecialchars($lc) ?>18;color:<?= htmlspecialchars($lc) ?>;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;border:1.5px solid <?= htmlspecialchars($lc) ?>35;transition:opacity .15s;"
            onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
            <i class="fa-solid <?= htmlspecialchars($cat['cat_icon']) ?>" style="font-size:11px;"></i>
            <?= htmlspecialchars($cat['cat_name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- هل تقصد -->
      <?php if (!empty($similar_insts)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-circle-question" style="color:#8829C8;font-size:15px;"></i>
          هل تقصد؟
        </h3>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($similar_insts as $si): ?>
          <a href="institution.php?id=<?= $si['inst_id'] ?>" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='transparent'">
            <?php if ($si['inst_logo']): ?>
              <img src="<?= htmlspecialchars($si['inst_logo']) ?>" style="width:46px;height:46px;border-radius:12px;object-fit:contain;border:1px solid #f3f4f6;flex-shrink:0;background:#fff;padding:4px;">
            <?php else: ?>
              <div style="width:46px;height:46px;border-radius:12px;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:18px;font-weight:900;"><?= mb_substr($si['inst_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <p style="font-size:13px;font-weight:800;color:#111827;margin:0 0 3px;display:flex;align-items:center;gap:5px;">
                <?= htmlspecialchars($si['inst_name_ar']) ?>
                <?php if ($si['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:11px;"></i><?php endif; ?>
              </p>
              <?php if ($si['inst_name_en']): ?>
              <p style="font-size:11px;color:#9ca3af;margin:0;" dir="ltr"><?= htmlspecialchars($si['inst_name_en']) ?></p>
              <?php endif; ?>
            </div>
            <i class="fa-solid fa-arrow-left" style="color:#d1d5db;font-size:11px;flex-shrink:0;"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- شركات من نفس المجال -->
      <?php if (!empty($same_cat_insts)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-building" style="color:#8829C8;font-size:15px;"></i>
          مؤسسات من نفس المجال
        </h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php foreach ($same_cat_insts as $sci): ?>
          <a href="institution.php?id=<?= $sci['inst_id'] ?>" style="text-align:center;padding:14px 8px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='transparent'">
            <?php if ($sci['inst_logo']): ?>
              <img src="<?= htmlspecialchars($sci['inst_logo']) ?>" style="width:50px;height:50px;border-radius:12px;object-fit:contain;margin:0 auto 8px;border:1px solid #f3f4f6;background:#fff;padding:4px;">
            <?php else: ?>
              <div style="width:50px;height:50px;border-radius:12px;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <span style="color:#fff;font-size:20px;font-weight:900;"><?= mb_substr($sci['inst_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <p style="font-size:12px;font-weight:800;color:#111827;margin:0 0 3px;line-height:1.3;">
              <?= htmlspecialchars(mb_substr($sci['inst_name_ar'],0,16)) ?><?= mb_strlen($sci['inst_name_ar'])>16?'…':'' ?>
              <?php if ($sci['inst_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:9px;"></i><?php endif; ?>
            </p>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Personalities from same categories -->
      <?php if (!empty($related_personalities)): ?>
      <div class="bg-white rounded-2xl shadow-sm p-6">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-users" style="color:#8829C8;font-size:15px;"></i>
          شخصيات من نفس المجال
        </h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php foreach ($related_personalities as $rp): ?>
          <a href="profile.php?id=<?= $rp['p_id'] ?>" style="text-align:center;padding:14px 8px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='transparent'">
            <?php if ($rp['p_photo']): ?>
              <img src="<?= htmlspecialchars($rp['p_photo']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;margin:0 auto 8px;border:2px solid #f3e8ff;">
            <?php else: ?>
              <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <span style="color:#fff;font-size:20px;font-weight:900;"><?= mb_substr($rp['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <p style="font-size:12px;font-weight:800;color:#111827;margin:0 0 3px;line-height:1.3;">
              <?= htmlspecialchars(mb_substr($rp['p_name_ar'],0,16)) ?><?= mb_strlen($rp['p_name_ar'])>16?'…':'' ?>
              <?php if ($rp['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:9px;"></i><?php endif; ?>
            </p>
            <p style="font-size:10px;color:#9ca3af;margin:0;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars($rp['p_title']??'') ?></p>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT SIDEBAR -->
    <div style="position:sticky;top:80px;" class="space-y-4">

      <!-- Stats card -->
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:18px;">
        <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0 0 12px;">إحصائيات</h3>
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f9fafb;">
            <span style="font-size:12px;color:#6b7280;">المشاهدات</span>
            <span style="font-size:13px;font-weight:800;color:#111827;"><?= number_format($inst['inst_views'] ?? 0) ?></span>
          </div>
          <?php if (!empty($inst['inst_founded'])): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f9fafb;">
            <span style="font-size:12px;color:#6b7280;">سنة التأسيس</span>
            <span style="font-size:13px;font-weight:800;color:#111827;"><?= htmlspecialchars($inst['inst_founded']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($inst_cats)): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;">
            <span style="font-size:12px;color:#6b7280;">التصنيفات</span>
            <span style="font-size:13px;font-weight:800;color:#8829C8;"><?= count($inst_cats) ?></span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Verified membership promo -->
      <div style="background:linear-gradient(135deg,#fef9c3,#fef3c7);border:2px solid #fde68a;border-radius:18px;padding:18px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <i class="fa-solid fa-circle-check" style="color:#d97706;font-size:20px;"></i>
          <span style="font-size:14px;font-weight:900;color:#92400e;">عضوية موثقة</span>
        </div>
        <ul style="list-style:none;padding:0;margin:0 0 14px;font-size:12px;color:#78350f;">
          <?php foreach (['ظهور في نتائج البحث أولاً','شارة التوثيق الرسمية','حماية الهوية الرقمية','إحصائيات الزيارات'] as $b): ?>
          <li style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <i class="fa-solid fa-check" style="color:#d97706;font-size:10px;flex-shrink:0;"></i><?= $b ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="membership.php" style="display:block;text-align:center;padding:9px;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:10px;font-size:13px;font-weight:800;text-decoration:none;">اشترك الآن</a>
      </div>

      <!-- Daily personality -->
      <?php if ($daily): ?>
      <div style="background:linear-gradient(135deg,#8829C8,#5B1494);border-radius:18px;padding:20px;text-align:center;color:#fff;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <span style="font-size:13px;font-weight:800;">شخصية اليوم</span>
          <i class="fa-solid fa-circle-check" style="font-size:18px;color:#c4b5fd;"></i>
        </div>
        <a href="profile.php?id=<?= $daily['p_id'] ?>" style="text-decoration:none;color:inherit;">
          <?php if ($daily['p_photo']): ?>
            <img src="<?= htmlspecialchars($daily['p_photo']) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin:0 auto 10px;border:3px solid rgba(255,255,255,.3);">
          <?php else: ?>
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
              <span style="font-size:28px;font-weight:900;"><?= mb_substr($daily['p_name_ar'],0,1) ?></span>
            </div>
          <?php endif; ?>
          <p style="font-size:14px;font-weight:900;margin:0 0 3px;"><?= htmlspecialchars($daily['p_name_ar']) ?> <i class="fa-solid fa-circle-check" style="font-size:12px;"></i></p>
          <p style="font-size:11px;color:rgba(255,255,255,.7);margin:0 0 14px;"><?= htmlspecialchars($daily['p_title']??'') ?></p>
        </a>
        <a href="membership.php" style="display:inline-block;padding:8px 20px;background:#fff;color:#8829C8;border-radius:999px;font-size:12px;font-weight:800;text-decoration:none;">وثق ملفك لتظهر هنا</a>
      </div>
      <?php endif; ?>

      <!-- رؤساء تنفيذيون -->
      <?php if (!empty($executives)): ?>
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;" x-data="{showMore:false}">
        <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0 0 14px;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-crown" style="color:#d97706;font-size:13px;"></i> رؤساء تنفيذيون
        </h3>
        <?php foreach ($executives as $idx => $ex): ?>
        <?php
          $ex_job = '';
          $rj = $mysqli->query("SELECT tl_title, tl_institution FROM pi_timeline WHERE tl_p_id={$ex['p_id']} AND (tl_year_end='' OR tl_year_end IS NULL) AND tl_type='work' ORDER BY tl_year_start DESC LIMIT 1");
          if ($rj && $rj->num_rows) { $rjr = $rj->fetch_assoc(); $ex_job = trim(($rjr['tl_title']??'') . ($rjr['tl_institution'] ? ' في "'.$rjr['tl_institution'].'".' : '')); }
        ?>
        <div x-show="showMore || <?= $idx ?> < 2">
          <a href="profile.php?id=<?= $ex['p_id'] ?>" style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px;text-decoration:none;margin-bottom:6px;background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            <?php if ($ex['p_photo']): ?>
              <img src="<?= htmlspecialchars($ex['p_photo']) ?>" style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #fde68a;">
            <?php else: ?>
              <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#d97706,#b45309);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:17px;font-weight:900;"><?= mb_substr($ex['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <p style="font-size:12px;font-weight:900;color:#92400e;margin:0 0 3px;display:flex;align-items:center;gap:4px;">
                <?= htmlspecialchars($ex['p_name_ar']) ?> <i class="fa-solid fa-crown" style="color:#d97706;font-size:9px;"></i>
              </p>
              <p style="font-size:10px;color:#78350f;margin:0;line-height:1.4;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                <?= htmlspecialchars($ex_job ?: ($ex['p_title']??'')) ?>
              </p>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
        <?php if (count($executives) > 2): ?>
        <button @click="showMore=!showMore" style="width:100%;padding:7px;background:none;border:1px solid #fde68a;border-radius:8px;font-size:12px;font-weight:700;color:#92400e;cursor:pointer;font-family:inherit;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:4px;">
          <span x-text="showMore ? 'عرض أقل' : 'عرض المزيد'"></span>
          <i :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid" style="font-size:9px;"></i>
        </button>
        <?php endif; ?>
        <a href="membership.php?type=executive" style="display:block;text-align:center;padding:8px;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:10px;font-size:11px;font-weight:800;text-decoration:none;margin-top:10px;">
          للاستعلام عن باقة الرؤساء التنفيذيين
        </a>
      </div>
      <?php endif; ?>

      <!-- مقالات تهمك -->
      <?php if (!empty($related_articles)): ?>
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;">
        <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0 0 12px;display:flex;align-items:center;gap:6px;">
          <i class="fa-solid fa-newspaper" style="color:#8829C8;font-size:13px;"></i> مقالات تهمك
        </h3>
        <?php foreach ($related_articles as $idx => $ra): ?>
        <a href="<?= htmlspecialchars($ra['art_url'] ?: 'article.php?id='.$ra['art_id']) ?>" target="_blank"
          style="display:flex;align-items:center;gap:10px;padding:8px 0;<?= $idx>0?'border-top:1px solid #f9fafb;':'' ?>text-decoration:none;">
          <?php if ($ra['art_image']): ?>
            <img src="<?= htmlspecialchars($ra['art_image']) ?>" style="width:52px;height:40px;border-radius:8px;object-fit:cover;flex-shrink:0;">
          <?php else: ?>
            <div style="width:52px;height:40px;border-radius:8px;background:#f5f0ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-newspaper" style="color:#8829C8;font-size:14px;"></i>
            </div>
          <?php endif; ?>
          <p style="font-size:12px;font-weight:700;color:#374151;margin:0;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;transition:color .15s;" onmouseover="this.style.color='#8829C8'" onmouseout="this.style.color='#374151'">
            <?= htmlspecialchars($ra['art_title']) ?>
          </p>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
$_iS = pi_get_settings();
$_iLogo   = htmlspecialchars($_iS['site_logo'] ?? '');
$_iName   = htmlspecialchars($_iS['site_name'] ?? 'PioneerIcons');
$_iTag    = htmlspecialchars($_iS['site_tagline'] ?? 'منصة الحضور العربي الموثق');
$_instLogo = htmlspecialchars($inst['inst_logo'] ?? '');
$_instName = htmlspecialchars($inst['inst_name_ar'] ?? '');
$_instEn   = htmlspecialchars($inst['inst_name_en'] ?? '');
?>

<!-- INSTITUTION CARD MODAL -->
<div id="inst-card-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:16px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:640px;overflow:hidden;font-family:'Cairo',sans-serif;">
    <!-- Modal Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f3f4f6;flex-wrap:wrap;gap:8px;">
      <span style="font-size:15px;font-weight:900;color:#111827;">تحميل البطاقة التعريفية</span>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span style="font-size:12px;color:#9ca3af;font-weight:700;">اختر لون الخلفية</span>
        <?php foreach ([['#E05A1B','برتقالي'],['#8829C8','بنفسجي'],['#1d4ed8','أزرق'],['#0369a1','سماوي'],['#16a34a','أخضر'],['#1e293b','داكن']] as [$clr,$lbl]): ?>
        <button onclick="setInstCardColor('<?= $clr ?>')" title="<?= $lbl ?>"
          style="width:26px;height:26px;border-radius:50%;background:<?= $clr ?>;border:2px solid transparent;cursor:pointer;transition:transform .15s;"
          onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"></button>
        <?php endforeach; ?>
        <button onclick="downloadInstCard()" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
          <i class="fa-solid fa-download"></i> تحميل
        </button>
        <button onclick="document.getElementById('inst-card-modal').style.display='none'"
          style="width:30px;height:30px;border:none;background:#f3f4f6;border-radius:50%;cursor:pointer;color:#6b7280;font-size:16px;">✕</button>
      </div>
    </div>

    <!-- Card Preview -->
    <div style="padding:24px;background:#f9fafb;display:flex;justify-content:center;">
      <div id="pi-inst-card" style="width:480px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.12);">
        <!-- Top logos -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;">
          <div style="font-size:12px;font-weight:700;color:#9ca3af;"></div>
          <div>
            <?php if ($_iLogo): ?>
            <img src="<?= $_iLogo ?>" alt="<?= $_iName ?>" style="height:28px;object-fit:contain;">
            <?php else: ?>
            <span style="font-size:13px;font-weight:900;color:#8829C8;"><?= $_iName ?></span>
            <?php endif; ?>
          </div>
        </div>

        <p style="text-align:center;font-size:13px;font-weight:700;color:#374151;margin:0 0 6px;">تعرفوا على</p>

        <!-- Colored section -->
        <div id="inst-card-bg" style="background:#E05A1B;margin:0 16px;border-radius:14px;padding:24px 20px;display:flex;flex-direction:column;align-items:center;">
          <!-- Logo -->
          <?php if ($_instLogo): ?>
          <div style="width:100px;height:100px;border-radius:16px;overflow:hidden;border:4px solid #fff;background:#fff;box-shadow:0 6px 20px rgba(0,0,0,.15);margin-bottom:16px;">
            <img src="<?= $_instLogo ?>" alt="" style="width:100%;height:100%;object-fit:contain;">
          </div>
          <?php else: ?>
          <div style="width:100px;height:100px;border-radius:16px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <i class="fa-solid fa-building" style="color:#fff;font-size:40px;"></i>
          </div>
          <?php endif; ?>
          <h2 style="color:#fff;font-size:24px;font-weight:900;text-align:center;margin:0 0 6px;line-height:1.3;"><?= $_instName ?></h2>
          <?php if ($_instEn): ?>
          <p style="color:rgba(255,255,255,.8);font-size:13px;text-align:center;font-weight:600;"><?= $_instEn ?></p>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 20px;margin-top:8px;">
          <span style="font-size:12px;font-weight:700;color:#6b7280;"><?= $_iName ?>.com</span>
          <span style="color:#d1d5db;">|</span>
          <span style="font-size:12px;font-weight:700;color:#6b7280;"><?= $_iTag ?></span>
          <i class="fa-solid fa-circle-check" style="color:#8829C8;font-size:12px;"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function setInstCardColor(c) { document.getElementById('inst-card-bg').style.background = c; }
function downloadInstCard() {
  html2canvas(document.getElementById('pi-inst-card'), { scale:2, useCORS:true, allowTaint:true, backgroundColor:'#ffffff' }).then(function(canvas) {
    var a = document.createElement('a');
    a.download = 'institution-card-<?= $inst_id ?>.png';
    a.href = canvas.toDataURL('image/png');
    a.click();
  });
}
</script>

<?php include 'includes/footer.php'; ?>
