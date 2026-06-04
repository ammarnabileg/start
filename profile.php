<?php
require_once 'includes/config.php';

$p_id = (int)($_GET['id'] ?? 0);
if (!$p_id) { header('Location: index.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_id=$p_id AND p_active=1");
if (!$r || !$r->num_rows) { header('Location: index.php'); exit; }
$p = $r->fetch_assoc();

$mysqli->query("UPDATE pi_personalities SET p_views=p_views+1 WHERE p_id=$p_id");

$pageTitle = htmlspecialchars($p['p_name_ar']) . ' - ' . pi_setting('site_name');
$total_count = pi_count_personalities() + pi_count_institutions();

// Social links
$socials = [];
$r = $mysqli->query("SELECT * FROM pi_social_links WHERE sl_entity_type='personality' AND sl_entity_id=$p_id");
if ($r) while ($row=$r->fetch_assoc()) $socials[$row['sl_platform']] = $row['sl_url'];

// Categories
$p_cats = [];
$r = $mysqli->query("SELECT c.*, l.label_name, l.label_color FROM pi_categories c JOIN pi_personality_categories pc ON c.cat_id=pc.cat_id LEFT JOIN pi_labels l ON c.cat_label_id=l.label_id WHERE pc.p_id=$p_id");
if ($r) while ($row=$r->fetch_assoc()) $p_cats[] = $row;

// Timeline — work (current = no year_end, past = has year_end)
$tl_current = [];
$tl_past    = [];
$r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_p_id=$p_id AND tl_type='work' ORDER BY tl_year_start DESC");
if ($r) while ($row=$r->fetch_assoc()) {
    if (empty($row['tl_year_end']) || $row['tl_year_end'] === 'الآن') $tl_current[] = $row;
    else $tl_past[] = $row;
}
$tl_edu = [];
$r = $mysqli->query("SELECT * FROM pi_timeline WHERE tl_p_id=$p_id AND tl_type='education' ORDER BY tl_year_start DESC");
if ($r) while ($row=$r->fetch_assoc()) $tl_edu[] = $row;

// Related personalities
$related = [];
$r = $mysqli->query("SELECT p.* FROM pi_personalities p JOIN pi_related_personalities rp ON p.p_id=rp.related_p_id WHERE rp.p_id=$p_id AND p.p_active=1 LIMIT 3");
if ($r) while ($row=$r->fetch_assoc()) $related[] = $row;

// Similar names ("هل تقصد")
$similar = [];
$name_parts = preg_split('/\s+/', trim($p['p_name_ar']));
if (count($name_parts) >= 2) {
    $like_clauses = [];
    foreach ($name_parts as $part) {
        if (mb_strlen($part) >= 3) $like_clauses[] = "p_name_ar LIKE '%".pi_escape($part)."%'";
    }
    if ($like_clauses) {
        $like_sql = implode(' OR ', $like_clauses);
        $r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_id!=$p_id AND ($like_sql) ORDER BY p_views DESC LIMIT 4");
        if ($r) while ($row=$r->fetch_assoc()) $similar[] = $row;
    }
}

// Build cat_ids from p_cats for same-category queries
$cat_ids = array_column($p_cats, 'cat_id');

// Same categories personalities
$same_cat = [];
if (!empty($cat_ids)) {
    $cids_str = implode(',', array_map('intval', $cat_ids));
    $r = $mysqli->query("SELECT DISTINCT p.* FROM pi_personalities p JOIN pi_personality_categories pc ON p.p_id=pc.p_id WHERE pc.cat_id IN ($cids_str) AND p.p_active=1 AND p.p_id!=$p_id ORDER BY p.p_views DESC LIMIT 6");
    if ($r) while ($row=$r->fetch_assoc()) $same_cat[] = $row;
}

// Executive personalities (sidebar)
$executives = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_membership_type='executive' AND p_active=1 AND p_id!=$p_id ORDER BY p_views DESC LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $executives[] = $row;

// Related articles (same categories or general)
$related_articles = [];
$r = $mysqli->query("SELECT * FROM pi_articles WHERE art_active=1 AND art_p_id!=$p_id ORDER BY art_id DESC LIMIT 4");
if ($r) while ($row=$r->fetch_assoc()) $related_articles[] = $row;

$articles = [];
$r = $mysqli->query("SELECT * FROM pi_articles WHERE art_p_id=$p_id AND art_active=1 ORDER BY art_id DESC LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $articles[] = $row;

// Sponsors
$sponsors = [];
$r = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_active=1 ORDER BY sp_order LIMIT 6");
if ($r) while ($row=$r->fetch_assoc()) $sponsors[] = $row;

// Daily personality (random verified)
$daily = null;
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_verified=1 AND p_id!=$p_id ORDER BY RAND() LIMIT 1");
if ($r && $r->num_rows) $daily = $r->fetch_assoc();

// Verified members (sidebar)
$verified_members = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_membership_type IN ('verified','executive') AND p_active=1 AND p_id!=$p_id ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $verified_members[] = $row;

// Most visited (sidebar)
$most_visited = [];
$r = $mysqli->query("SELECT * FROM pi_personalities WHERE p_active=1 AND p_id!=$p_id ORDER BY p_views DESC LIMIT 5");
if ($r) while ($row=$r->fetch_assoc()) $most_visited[] = $row;

// Country
$cid = pi_current_country();
$country_name = '';
if ($cid) {
    $countries = pi_get_countries();
    foreach ($countries as $c) { if ($c['c_id'] == $cid) { $country_name = $c['c_name']; break; } }
}

$social_icons = [
  'facebook'  => ['fa-brands fa-facebook-f',  '#1877f2'],
  'instagram' => ['fa-brands fa-instagram',    '#e1306c'],
  'youtube'   => ['fa-brands fa-youtube',      '#ff0000'],
  'twitter'   => ['fa-brands fa-x-twitter',    '#000'],
  'linkedin'  => ['fa-brands fa-linkedin-in',  '#0a66c2'],
  'website'   => ['fa-solid fa-globe',         '#0ea5e9'],
  'snapchat'  => ['fa-brands fa-snapchat',     '#f7ca00'],
  'tiktok'    => ['fa-brands fa-tiktok',       '#010101'],
];

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

<div class="max-w-7xl mx-auto px-4 py-8">
  <div style="display:grid;grid-template-columns:1fr 300px;gap:28px;align-items:start;">

    <!-- ============ MAIN CONTENT ============ -->
    <div style="min-width:0;">

      <!-- PROFILE CARD -->
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:24px;margin-bottom:20px;">
        <div style="display:flex;gap:20px;align-items:flex-start;">

          <!-- Info (right in RTL) -->
          <div style="flex:1;min-width:0;">
            <h1 style="font-size:24px;font-weight:900;color:#111827;margin:0 0 4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <?= htmlspecialchars($p['p_name_ar']) ?>
              <?php if ($p['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:18px;"></i><?php endif; ?>
              <?php if ($p['p_membership_type']==='executive'): ?><i class="fa-solid fa-crown gold-badge" style="font-size:16px;"></i><?php endif; ?>
            </h1>
            <p style="font-size:14px;color:#6b7280;font-weight:600;margin:0 0 6px;">
              <?= htmlspecialchars($p['p_title'] ?? '') ?>
            </p>
            <?php if ($p['p_name_en']): ?>
            <p style="font-size:13px;color:#9ca3af;margin:0 0 10px;" dir="ltr"><?= htmlspecialchars($p['p_name_en']) ?></p>
            <?php endif; ?>

            <!-- Meta info -->
            <div style="display:flex;flex-wrap:wrap;gap:14px;margin-bottom:14px;">
              <?php if ($p['p_nationality']): ?>
              <span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:5px;">
                <span style="font-weight:700;color:#6b7280;">الاسم الكامل:</span> <?= htmlspecialchars($p['p_name_ar']) ?>
              </span>
              <?php endif; ?>
              <?php if ($p['p_nationality']): ?>
              <span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:5px;">
                <span style="font-weight:700;color:#6b7280;">الجنسية:</span> <?= htmlspecialchars($p['p_nationality']) ?>
              </span>
              <?php endif; ?>
              <?php if ($p['p_residence']): ?>
              <span style="font-size:13px;color:#374151;display:flex;align-items:center;gap:5px;">
                <span style="font-weight:700;color:#6b7280;">بلد الإقامة:</span> <?= htmlspecialchars($p['p_residence']) ?>
              </span>
              <?php endif; ?>
            </div>

            <!-- Social links -->
            <?php if (!empty($socials)): ?>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
              <?php foreach ($socials as $platform => $url): ?>
              <?php $ico = $social_icons[$platform] ?? ['fa-solid fa-link','#6b7280']; ?>
              <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="nofollow noopener"
                style="width:32px;height:32px;border-radius:50%;background:<?= $ico[1] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;text-decoration:none;transition:opacity .2s;"
                onmouseover="this.style.opacity=.8" onmouseout="this.style.opacity=1">
                <i class="<?= $ico[0] ?>"></i>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div style="display:flex;flex-wrap:wrap;gap:10px;">
              <button onclick="document.getElementById('card-modal').style.display='flex'"
                style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;border-radius:999px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;">
                <i class="fa-solid fa-id-card"></i> تحميل البطاقة التعريفية
              </button>
              <a href="admin.php?p=personalities&action=manage&id=<?= $p_id ?>"
                style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border:1.5px solid #e5e7eb;color:#374151;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;background:#fff;transition:border-color .2s;"
                onmouseover="this.style.borderColor='#8829C8';this.style.color='#8829C8'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#374151'">
                <i class="fa-solid fa-gear"></i> إدارة الصفحة
              </a>
              <button onclick="if(navigator.share)navigator.share({title:'<?= addslashes(htmlspecialchars($p['p_name_ar'])) ?>',url:location.href});else{navigator.clipboard.writeText(location.href);alert('تم نسخ الرابط');}"
                style="width:38px;height:38px;border:1.5px solid #e5e7eb;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;color:#6b7280;cursor:pointer;transition:all .2s;"
                onmouseover="this.style.borderColor='#8829C8';this.style.color='#8829C8'" onmouseout="this.style.borderColor='#e5e7eb';this.style.color='#6b7280'">
                <i class="fa-solid fa-share-nodes"></i>
              </button>
            </div>
          </div>

          <!-- Photo (left in RTL = appears visually on left) -->
          <div style="flex-shrink:0;">
            <?php if ($p['p_photo']): ?>
              <img src="<?= htmlspecialchars($p['p_photo']) ?>" alt="<?= htmlspecialchars($p['p_name_ar']) ?>"
                style="width:130px;height:160px;border-radius:16px;object-fit:cover;border:3px solid #f3e8ff;">
            <?php else: ?>
              <div style="width:130px;height:160px;border-radius:16px;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;">
                <span style="color:#fff;font-size:52px;font-weight:900;"><?= mb_substr($p['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BIO TABS -->
      <!-- Bio -->
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:16px;padding:24px;">
        <h2 style="font-size:17px;font-weight:900;color:#111827;margin:0 0 16px;">السيرة الذاتية</h2>
        <?php if (!empty($p['p_bio_platform']) && trim(strip_tags($p['p_bio_platform'])) !== ''): ?>
        <div style="background:#faf5ff;border-right:4px solid #8829C8;border-radius:12px;padding:16px;margin-bottom:16px;font-size:13px;color:#374151;line-height:1.9;" class="pi-rich-content">
          <p style="font-size:11px;font-weight:700;color:#9ca3af;margin-bottom:6px;">معلومات مضافة من منصة "<?= pi_setting('site_name_ar') ?>"</p>
          <?= $p['p_bio_platform'] ?>
        </div>
        <?php endif; ?>
        <?php if ($p['p_bio']): ?>
        <div style="font-size:14px;color:#374151;line-height:2;font-weight:500;" class="pi-rich-content">
          <?= $p['p_bio'] ?>
        </div>
        <?php else: ?>
        <p style="color:#9ca3af;text-align:center;padding:24px 0;font-size:14px;">لم تتم إضافة سيرة ذاتية بعد</p>
        <?php endif; ?>
      </div>

      <!-- Categories -->
      <?php if (!empty($p_cats)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:16px;padding:20px 24px;">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 14px;text-transform:uppercase;letter-spacing:.05em;">التصنيفات</h3>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <?php foreach ($p_cats as $cat): ?>
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

      <!-- COUNTRY TAG -->
      <?php if ($country_name): ?>
      <div style="margin-bottom:20px;">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:8px 18px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:999px;font-size:13px;font-weight:700;color:#166534;">
          <i class="fa-solid fa-location-dot" style="color:#16a34a;"></i>
          تابع <?= pi_setting('site_name_ar') ?> <?= htmlspecialchars($country_name) ?>
        </span>
      </div>
      <?php endif; ?>

      <!-- RELATED PERSONALITIES -->
      <?php if (!empty($related)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:20px;margin-bottom:20px;">
        <h3 style="font-size:15px;font-weight:900;color:#111827;margin:0 0 14px;">شخصيات متعلقة</h3>
        <div style="display:grid;grid-template-columns:repeat(<?= min(count($related),3) ?>,1fr);gap:12px;">
          <?php foreach ($related as $rel): ?>
          <a href="profile.php?id=<?= $rel['p_id'] ?>" style="text-align:center;padding:16px 12px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:border-color .2s,background .2s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='#fff'">
            <?php if ($rel['p_photo']): ?>
              <img src="<?= htmlspecialchars($rel['p_photo']) ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin:0 auto 10px;">
            <?php else: ?>
              <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;">
                <span style="color:#fff;font-size:22px;font-weight:900;"><?= mb_substr($rel['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <p style="font-size:13px;font-weight:800;color:#111827;margin:0 0 4px;">
              <?= htmlspecialchars($rel['p_name_ar']) ?>
              <?php if ($rel['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:11px;"></i><?php endif; ?>
            </p>
            <p style="font-size:11px;color:#9ca3af;margin:0;"><?= htmlspecialchars($rel['p_title']??'') ?></p>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- TIMELINE -->
      <?php if (!empty($tl_current) || !empty($tl_past) || !empty($tl_edu)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:24px;margin-bottom:20px;">
        <h3 style="font-size:15px;font-weight:900;color:#111827;margin:0 0 20px;">
          محطات في حياة <?= htmlspecialchars($p['p_name_ar']) ?> في العمل والتعليم
        </h3>

        <?php if (!empty($tl_current)): ?>
        <div style="margin-bottom:20px;">
          <h4 style="font-size:13px;font-weight:800;color:#8829C8;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#8829C8;flex-shrink:0;"></span>
            مناصب حالية
          </h4>
          <?php foreach ($tl_current as $tl): ?>
          <div style="display:flex;gap:14px;margin-bottom:14px;">
            <?php if (false): // tl_institution_logo not in schema ?>
              <img src="" style="width:44px;height:44px;border-radius:10px;object-fit:contain;border:1px solid #f3f4f6;flex-shrink:0;">
            <?php else: ?>
              <div style="width:44px;height:44px;border-radius:10px;background:#f5f0ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fa-solid fa-briefcase" style="color:#8829C8;font-size:16px;"></i>
              </div>
            <?php endif; ?>
            <div>
              <p style="font-size:14px;font-weight:800;color:#111827;margin:0 0 3px;"><?= htmlspecialchars($tl['tl_title']) ?></p>
              <?php if ($tl['tl_institution']): ?>
              <p style="font-size:12px;color:#6b7280;margin:0 0 4px;"><?= htmlspecialchars($tl['tl_institution']) ?></p>
              <?php endif; ?>
              <span style="font-size:11px;color:#9ca3af;background:#f9fafb;border:1px solid #f3f4f6;border-radius:999px;padding:2px 10px;">
                منذ <?= htmlspecialchars($tl['tl_year_start']) ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tl_past)): ?>
        <div style="margin-bottom:20px;">
          <h4 style="font-size:13px;font-weight:800;color:#6b7280;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#9ca3af;flex-shrink:0;"></span>
            مناصب سابقة
          </h4>
          <?php foreach ($tl_past as $tl): ?>
          <div style="display:flex;gap:14px;margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:10px;background:#f9fafb;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-briefcase" style="color:#9ca3af;font-size:16px;"></i>
            </div>
            <div>
              <p style="font-size:14px;font-weight:800;color:#374151;margin:0 0 3px;"><?= htmlspecialchars($tl['tl_title']) ?></p>
              <?php if ($tl['tl_institution']): ?>
              <p style="font-size:12px;color:#6b7280;margin:0 0 4px;"><?= htmlspecialchars($tl['tl_institution']) ?></p>
              <?php endif; ?>
              <span style="font-size:11px;color:#9ca3af;background:#f9fafb;border:1px solid #f3f4f6;border-radius:999px;padding:2px 10px;">
                <?= htmlspecialchars($tl['tl_year_start']) ?><?= $tl['tl_year_end'] ? ' — '.htmlspecialchars($tl['tl_year_end']) : '' ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($tl_edu)): ?>
        <div>
          <h4 style="font-size:13px;font-weight:800;color:#2563eb;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
            <span style="width:8px;height:8px;border-radius:50%;background:#2563eb;flex-shrink:0;"></span>
            التعليم
          </h4>
          <?php foreach ($tl_edu as $tl): ?>
          <div style="display:flex;gap:14px;margin-bottom:14px;">
            <div style="width:44px;height:44px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-graduation-cap" style="color:#2563eb;font-size:16px;"></i>
            </div>
            <div>
              <p style="font-size:14px;font-weight:800;color:#111827;margin:0 0 3px;"><?= htmlspecialchars($tl['tl_title']) ?></p>
              <?php if ($tl['tl_institution']): ?>
              <p style="font-size:12px;color:#6b7280;margin:0 0 4px;"><?= htmlspecialchars($tl['tl_institution']) ?></p>
              <?php endif; ?>
              <?php if ($tl['tl_year_start']): ?>
              <span style="font-size:11px;color:#9ca3af;background:#f9fafb;border:1px solid #f3f4f6;border-radius:999px;padding:2px 10px;">
                <?= htmlspecialchars($tl['tl_year_start']) ?><?= $tl['tl_year_end'] ? ' — '.htmlspecialchars($tl['tl_year_end']) : '' ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- هل تقصد -->
      <?php if (!empty($similar)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:22px;margin-bottom:16px;">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-circle-question" style="color:#8829C8;font-size:15px;"></i>
          هل تقصد؟
        </h3>
        <div style="display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($similar as $sim): ?>
          <a href="profile.php?id=<?= $sim['p_id'] ?>" style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='transparent'">
            <?php if ($sim['p_photo']): ?>
              <img src="<?= htmlspecialchars($sim['p_photo']) ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
              <div style="width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:18px;font-weight:900;"><?= mb_substr($sim['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <p style="font-size:13px;font-weight:800;color:#111827;margin:0 0 3px;display:flex;align-items:center;gap:5px;">
                <?= htmlspecialchars($sim['p_name_ar']) ?>
                <?php if ($sim['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:11px;"></i><?php endif; ?>
              </p>
              <p style="font-size:11px;color:#9ca3af;margin:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= htmlspecialchars($sim['p_title']??'') ?></p>
            </div>
            <i class="fa-solid fa-arrow-left" style="color:#d1d5db;font-size:11px;flex-shrink:0;"></i>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- من نفس التصنيفات -->
      <?php if (!empty($same_cat)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:22px;margin-bottom:16px;">
        <h3 style="font-size:14px;font-weight:900;color:#6b7280;margin:0 0 16px;display:flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-users" style="color:#8829C8;font-size:15px;"></i>
          شخصيات من نفس المجال
        </h3>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
          <?php foreach ($same_cat as $sc): ?>
          <a href="profile.php?id=<?= $sc['p_id'] ?>" style="text-align:center;padding:14px 8px;border-radius:14px;border:1px solid #f3f4f6;text-decoration:none;transition:all .15s;" onmouseover="this.style.borderColor='#c4b5fd';this.style.background='#faf5ff'" onmouseout="this.style.borderColor='#f3f4f6';this.style.background='transparent'">
            <?php if ($sc['p_photo']): ?>
              <img src="<?= htmlspecialchars($sc['p_photo']) ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;margin:0 auto 8px;border:2px solid #f3e8ff;">
            <?php else: ?>
              <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;margin:0 auto 8px;">
                <span style="color:#fff;font-size:20px;font-weight:900;"><?= mb_substr($sc['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <p style="font-size:12px;font-weight:800;color:#111827;margin:0 0 3px;line-height:1.3;">
              <?= htmlspecialchars(mb_substr($sc['p_name_ar'],0,16)) ?><?= mb_strlen($sc['p_name_ar'])>16?'…':'' ?>
              <?php if ($sc['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:9px;"></i><?php endif; ?>
            </p>
            <p style="font-size:10px;color:#9ca3af;margin:0;line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= htmlspecialchars($sc['p_title']??'') ?></p>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- ARTICLES -->
      <?php if (!empty($articles)): ?>
      <div style="background:#fff;border-radius:20px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:24px;" x-data="{showAll:false}">
        <h3 style="font-size:15px;font-weight:900;color:#111827;margin:0 0 16px;">مقالات تمثلك</h3>
        <?php foreach ($articles as $idx => $art): ?>
        <div x-show="showAll || <?= $idx ?> < 3" style="display:flex;gap:14px;padding:12px 0;<?= $idx > 0 ? 'border-top:1px solid #f9fafb;' : '' ?>">
          <?php if ($art['art_image']): ?>
            <img src="<?= htmlspecialchars($art['art_image']) ?>" style="width:80px;height:60px;border-radius:10px;object-fit:cover;flex-shrink:0;">
          <?php elseif ($art['art_body']??''): ?>
            <div style="width:80px;height:60px;border-radius:10px;background:#f5f0ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa-solid fa-newspaper" style="color:#8829C8;font-size:18px;"></i>
            </div>
          <?php endif; ?>
          <div style="flex:1;min-width:0;">
            <?php if ($art['art_source']): ?>
            <p style="font-size:11px;color:#9ca3af;font-weight:700;margin:0 0 4px;"><?= htmlspecialchars($art['art_source']) ?></p>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($art['art_url']??'article.php?id='.$art['art_id']) ?>" target="_blank"
              style="font-size:13px;font-weight:800;color:#111827;text-decoration:none;display:block;line-height:1.5;transition:color .15s;"
              onmouseover="this.style.color='#8829C8'" onmouseout="this.style.color='#111827'">
              <?= htmlspecialchars($art['art_title']) ?>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (count($articles) > 3): ?>
        <div style="text-align:center;margin-top:12px;">
          <button @click="showAll=!showAll"
            style="padding:8px 24px;background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;"
            x-text="showAll ? 'عرض أقل' : 'عرض المزيد'"></button>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- /main -->

    <!-- ============ SIDEBAR ============ -->
    <div style="position:sticky;top:80px;">

      <!-- Personality of the day -->
      <?php if ($daily): ?>
      <div style="background:linear-gradient(135deg,#8829C8,#5B1494);border-radius:18px;padding:20px;text-align:center;color:#fff;margin-bottom:16px;">
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
          <p style="font-size:14px;font-weight:900;margin:0 0 3px;">
            <?= htmlspecialchars($daily['p_name_ar']) ?>
            <i class="fa-solid fa-circle-check" style="font-size:12px;"></i>
          </p>
          <p style="font-size:11px;color:rgba(255,255,255,.7);margin:0 0 14px;"><?= htmlspecialchars($daily['p_title']??'') ?></p>
        </a>
        <a href="membership.php" style="display:inline-block;padding:8px 20px;background:#fff;color:#8829C8;border-radius:999px;font-size:12px;font-weight:800;text-decoration:none;">وثق ملفك لتظهر هنا</a>
      </div>
      <?php endif; ?>

      <!-- Verified membership promo -->
      <div style="background:linear-gradient(135deg,#fef9c3,#fef3c7);border:2px solid #fde68a;border-radius:18px;padding:18px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
          <i class="fa-solid fa-circle-check" style="color:#d97706;font-size:20px;"></i>
          <span style="font-size:14px;font-weight:900;color:#92400e;">عضوية موثقة</span>
        </div>
        <ul style="list-style:none;padding:0;margin:0 0 14px;font-size:12px;color:#78350f;">
          <?php foreach (['ظهور في نتائج البحث أولاً','شارة التوثيق الرسمية','حماية الهوية الرقمية','إحصائيات الزيارات والمتابعين'] as $benefit): ?>
          <li style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <i class="fa-solid fa-check" style="color:#d97706;font-size:10px;flex-shrink:0;"></i>
            <?= $benefit ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="membership.php" style="display:block;text-align:center;padding:9px;background:linear-gradient(135deg,#d97706,#b45309);color:#fff;border-radius:10px;font-size:13px;font-weight:800;text-decoration:none;">اشترك الآن</a>
      </div>

      <!-- Verified members -->
      <?php if (!empty($verified_members)): ?>
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;margin-bottom:16px;" x-data="{showMore:false}">
        <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0 0 12px;">عضويات مميزة</h3>
        <?php foreach ($verified_members as $idx => $vm): ?>
        <div x-show="showMore || <?= $idx ?> < 3">
          <a href="profile.php?id=<?= $vm['p_id'] ?>" style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:10px;text-decoration:none;margin-bottom:4px;transition:background .15s;" onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background='transparent'">
            <?php if ($vm['p_photo']): ?>
              <img src="<?= htmlspecialchars($vm['p_photo']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
              <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:14px;font-weight:900;"><?= mb_substr($vm['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <p style="font-size:12px;font-weight:800;color:#111827;margin:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                <?= htmlspecialchars($vm['p_name_ar']) ?>
                <?php if ($vm['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:10px;"></i><?php endif; ?>
                <?php if ($vm['p_membership_type']==='executive'): ?><i class="fa-solid fa-crown gold-badge" style="font-size:10px;"></i><?php endif; ?>
              </p>
              <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= htmlspecialchars($vm['p_title']??'') ?></p>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
        <?php if (count($verified_members) > 3): ?>
        <button @click="showMore=!showMore" style="width:100%;text-align:center;padding:7px;background:none;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;font-family:inherit;margin-top:6px;">
          <span x-text="showMore ? 'عرض أقل' : 'عرض المزيد'"></span>
          <i :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid" style="font-size:9px;margin-right:4px;"></i>
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Most visited -->
      <?php if (!empty($most_visited)): ?>
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;" x-data="{showMore:false}">
        <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0 0 12px;">الأعلى زيارة</h3>
        <?php foreach ($most_visited as $idx => $mv): ?>
        <div x-show="showMore || <?= $idx ?> < 3">
          <a href="profile.php?id=<?= $mv['p_id'] ?>" style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:10px;text-decoration:none;margin-bottom:4px;transition:background .15s;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
            <?php if ($mv['p_photo']): ?>
              <img src="<?= htmlspecialchars($mv['p_photo']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
              <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="color:#fff;font-size:14px;font-weight:900;"><?= mb_substr($mv['p_name_ar'],0,1) ?></span>
              </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <p style="font-size:12px;font-weight:800;color:#111827;margin:0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">
                <?= htmlspecialchars($mv['p_name_ar']) ?>
                <?php if ($mv['p_verified']): ?><i class="fa-solid fa-circle-check verified-badge" style="font-size:10px;"></i><?php endif; ?>
              </p>
              <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;"><?= htmlspecialchars($mv['p_title']??'') ?></p>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
        <?php if (count($most_visited) > 3): ?>
        <button @click="showMore=!showMore" style="width:100%;text-align:center;padding:7px;background:none;border:1px solid #e5e7eb;border-radius:8px;font-size:12px;font-weight:700;color:#6b7280;cursor:pointer;font-family:inherit;margin-top:6px;">
          <span x-text="showMore ? 'عرض أقل' : 'عرض المزيد'"></span>
          <i :class="showMore ? 'fa-chevron-up' : 'fa-chevron-down'" class="fa-solid" style="font-size:9px;margin-right:4px;"></i>
        </button>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- رؤساء تنفيذيون -->
      <?php if (!empty($executives)): ?>
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;margin-bottom:16px;" x-data="{showMore:false}">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <h3 style="font-size:13px;font-weight:900;color:#111827;margin:0;display:flex;align-items:center;gap:6px;">
            <i class="fa-solid fa-crown" style="color:#d97706;font-size:13px;"></i> رؤساء تنفيذيون
          </h3>
        </div>
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
                <?= htmlspecialchars($ex['p_name_ar']) ?>
                <i class="fa-solid fa-crown" style="color:#d97706;font-size:9px;"></i>
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
      <div style="background:#fff;border-radius:18px;box-shadow:0 1px 4px rgba(0,0,0,.07);padding:16px;margin-bottom:16px;">
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

    </div><!-- /sidebar -->

  </div>
</div>

<style>
.pi-rich-content h1,.pi-rich-content h2 { font-size:18px;font-weight:900;color:#111827;margin:16px 0 8px; }
.pi-rich-content h3 { font-size:15px;font-weight:800;color:#374151;margin:14px 0 6px; }
.pi-rich-content p { margin-bottom:12px; }
.pi-rich-content ul,.pi-rich-content ol { padding-right:20px;margin-bottom:12px; }
.pi-rich-content li { margin-bottom:4px; }
.pi-rich-content strong { font-weight:800;color:#111827; }
.pi-rich-content blockquote { border-right:4px solid #8829C8;padding:12px 16px;background:#faf5ff;border-radius:8px;margin:12px 0;color:#6b21a8; }
.pi-rich-content a { color:#8829C8;text-decoration:underline; }
@media(max-width:768px){
  .max-w-7xl > div[style*=grid] { grid-template-columns:1fr !important; }
  .max-w-7xl > div[style*=grid] > div:last-child { position:static !important; }
}
</style>

<?php
// Current job title for card
$card_title = '';
if (!empty($tl_current)) {
    $t = $tl_current[0];
    $card_title = trim(($t['tl_title']??'') . ($t['tl_institution'] ? ' في "'.$t['tl_institution'].'"' : ''));
} elseif (!empty($tl_past)) {
    $t = $tl_past[0];
    $card_title = trim(($t['tl_title']??'') . ($t['tl_institution'] ? ' في "'.$t['tl_institution'].'"' : ''));
}
$site_logo  = htmlspecialchars($_S['site_logo'] ?? '');
$site_name  = htmlspecialchars(pi_setting('site_name'));
$site_tagline = htmlspecialchars($_S['site_tagline'] ?? 'منصة الحضور العربي الموثق');
$card_photo = htmlspecialchars($p['p_photo'] ?? '');
$card_name  = htmlspecialchars($p['p_name_ar'] ?? '');
$card_title_esc = htmlspecialchars($card_title);
?>

<!-- PROFILE CARD MODAL -->
<div id="card-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:16px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:20px;width:100%;max-width:640px;overflow:hidden;font-family:'Cairo',sans-serif;">
    <!-- Modal Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid #f3f4f6;">
      <span style="font-size:15px;font-weight:900;color:#111827;">تحميل البطاقة التعريفية</span>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <!-- Color Swatches -->
        <span style="font-size:12px;color:#9ca3af;font-weight:700;">اختر لون الخلفية</span>
        <?php foreach ([['#E05A1B','برتقالي'],['#8829C8','بنفسجي'],['#1d4ed8','أزرق'],['#0369a1','سماوي'],['#16a34a','أخضر'],['#1e293b','داكن']] as [$clr,$lbl]): ?>
        <button onclick="setCardColor('<?= $clr ?>')" title="<?= $lbl ?>"
          style="width:26px;height:26px;border-radius:50%;background:<?= $clr ?>;border:2px solid transparent;cursor:pointer;transition:transform .15s;"
          onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'"></button>
        <?php endforeach; ?>
        <!-- Download -->
        <button onclick="downloadCard()" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;">
          <i class="fa-solid fa-download"></i> تحميل الصورة
        </button>
        <button onclick="document.getElementById('card-modal').style.display='none'"
          style="width:30px;height:30px;border:none;background:#f3f4f6;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:16px;">✕</button>
      </div>
    </div>

    <!-- Card Preview -->
    <div style="padding:24px;background:#f9fafb;display:flex;justify-content:center;">
      <div id="pi-profile-card" style="width:480px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.12);position:relative;">
        <!-- Top row: logos -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;">
          <div id="card-left-logo" style="font-size:13px;font-weight:900;color:#8829C8;"></div>
          <div>
            <?php if ($site_logo): ?>
            <img src="<?= $site_logo ?>" alt="<?= $site_name ?>" style="height:32px;object-fit:contain;">
            <?php else: ?>
            <span style="font-size:14px;font-weight:900;color:#8829C8;"><?= $site_name ?></span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Center label -->
        <p style="text-align:center;font-size:13px;font-weight:700;color:#374151;margin:0 0 6px;">تعرفوا على</p>

        <!-- Colored section with photo -->
        <div id="card-bg-section" style="background:#E05A1B;margin:0 16px;border-radius:14px;padding:0 20px 24px;position:relative;display:flex;flex-direction:column;align-items:center;">
          <!-- Photo -->
          <div style="width:140px;height:160px;border-radius:16px;overflow:hidden;border:5px solid #fff;background:#f3f4f6;margin-top:-50px;box-shadow:0 8px 24px rgba(0,0,0,.18);flex-shrink:0;">
            <?php if ($card_photo): ?>
            <img id="card-photo-img" src="<?= $card_photo ?>" alt="" style="width:100%;height:100%;object-fit:cover;filter:grayscale(40%);">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#e5e7eb;">
              <i class="fa-solid fa-user" style="font-size:48px;color:#9ca3af;"></i>
            </div>
            <?php endif; ?>
          </div>
          <!-- Name & title -->
          <h2 style="color:#fff;font-size:28px;font-weight:900;text-align:center;margin:14px 0 6px;line-height:1.3;"><?= $card_name ?></h2>
          <?php if ($card_title_esc): ?>
          <p style="color:rgba(255,255,255,.9);font-size:14px;text-align:center;font-weight:600;line-height:1.5;"><?= $card_title_esc ?></p>
          <?php endif; ?>
        </div>

        <!-- Footer -->
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:14px 20px;border-top:1px solid #f3f4f6;margin-top:16px;">
          <span style="font-size:12px;font-weight:700;color:#6b7280;"><?= $site_name ?>.com</span>
          <span style="color:#d1d5db;">|</span>
          <span style="font-size:12px;font-weight:700;color:#6b7280;"><?= $site_tagline ?></span>
          <i class="fa-solid fa-circle-check" style="color:#8829C8;font-size:12px;"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function setCardColor(color) {
  document.getElementById('card-bg-section').style.background = color;
}
function downloadCard() {
  var card = document.getElementById('pi-profile-card');
  html2canvas(card, { scale: 2, useCORS: true, allowTaint: true, backgroundColor: '#ffffff' }).then(function(canvas) {
    var link = document.createElement('a');
    link.download = 'profile-card-<?= $p_id ?>.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  });
}
</script>

<?php include 'includes/footer.php'; ?>
