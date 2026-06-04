<?php
require_once 'includes/config.php';

$list_id = (int)($_GET['id'] ?? 0);
if (!$list_id) { header('Location: lists.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_lists WHERE list_id=$list_id AND list_active=1");
if (!$r || !$r->num_rows) { header('Location: lists.php'); exit; }
$list = $r->fetch_assoc();

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'ar';
$is_ar = $lang === 'ar';

// Track view (once per session)
if (empty($_SESSION['viewed_list_'.$list_id])) {
    $mysqli->query("UPDATE pi_lists SET list_views=list_views+1 WHERE list_id=$list_id");
    $_SESSION['viewed_list_'.$list_id] = 1;
}

// Load columns
$list_columns = json_decode($list['list_columns'] ?? '[]', true) ?: [];

// Load blocks
$blocks = [];
$rb = $mysqli->query("SELECT * FROM pi_list_blocks WHERE lb_list_id=$list_id ORDER BY lb_order,lb_id");
if ($rb) while ($row=$rb->fetch_assoc()) $blocks[] = $row;

// Load items with entity data
$items = [];
$ri = $mysqli->query("SELECT li.* FROM pi_list_items li WHERE li.li_list_id=$list_id ORDER BY li.li_rank ASC, li.li_id ASC");
if ($ri) while ($row=$ri->fetch_assoc()) {
    $row['_data'] = json_decode($row['li_data'] ?? '{}', true) ?: [];
    if ($row['li_entity_type'] === 'personality') {
        $eid = (int)$row['li_entity_id'];
        $er = $mysqli->query("SELECT p_id,p_name_ar,p_name_en,p_photo,p_title,p_nationality,p_residence,p_verified FROM pi_personalities WHERE p_id=$eid AND p_active=1");
        $row['entity'] = ($er && $er->num_rows) ? $er->fetch_assoc() : null;
    } else {
        $eid = (int)$row['li_entity_id'];
        $er = $mysqli->query("SELECT inst_id,inst_name_ar,inst_name_en,inst_logo,inst_country_id FROM pi_institutions WHERE inst_id=$eid AND inst_active=1");
        $row['entity'] = ($er && $er->num_rows) ? $er->fetch_assoc() : null;
    }
    if ($row['entity']) $items[] = $row;
}

// Spotlight — manually selected IDs
$spotlight_ids = json_decode($list['list_spotlight'] ?? '[]', true) ?: [];
// If none manually set, auto-pick verified personalities in list
if (empty($spotlight_ids)) {
    foreach ($items as $item) {
        if ($item['li_entity_type'] === 'personality' && ($item['entity']['p_verified'] ?? 0)) {
            $spotlight_ids[] = 'personality-'.$item['li_entity_id'];
        }
    }
}
$spotlight_items = [];
foreach ($items as $item) {
    $key = $item['li_entity_type'].'-'.$item['li_entity_id'];
    if (in_array($key, $spotlight_ids)) $spotlight_items[] = $item;
}

// Sponsor
$sponsor = null;
if (!empty($list['list_sponsor_id'])) {
    $sr = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_id=".(int)$list['list_sponsor_id']." AND sp_active=1");
    if ($sr && $sr->num_rows) $sponsor = $sr->fetch_assoc();
}
if (!$sponsor && !empty($list['list_sponsor_img'])) {
    $sponsor = ['sp_name'=>$list['list_sponsor_name']??'', 'sp_logo'=>$list['list_sponsor_img'], 'sp_url'=>$list['list_sponsor_url']??''];
}

// Filterable columns
$filterable_cols = array_values(array_filter($list_columns, function($c){ return ($c['filterable']??0) && ($c['source']??'manual')==='manual'; }));
$filter_values = [];
foreach ($filterable_cols as $col) {
    $vals = [];
    foreach ($items as $item) {
        $v = $item['_data'][$col['key']] ?? '';
        if ($v !== '' && !in_array($v, $vals)) $vals[] = $v;
    }
    if (!empty($vals)) $filter_values[$col['key']] = $vals;
}

$title_display = $lang==='en' && !empty($list['list_title_en']) ? $list['list_title_en'] : $list['list_title'];
$pageTitle = htmlspecialchars($title_display) . ' | ' . pi_setting('site_name_ar');
include 'includes/header.php';

function fmt_col($value, $type) {
    if ($value === '' || $value === null) return '<span class="text-gray-300">—</span>';
    switch ($type) {
        case 'currency':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            return '<span class="font-bold text-gray-800 tabular-nums" dir="ltr">$'.number_format($n,0).'</span>';
        case 'number':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            return '<span class="font-bold text-gray-700 tabular-nums" dir="ltr">'.number_format($n,0).'</span>';
        case 'percent':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            $pos = $n >= 0;
            return '<span class="font-bold '.($pos?'text-green-600':'text-red-500').'" dir="ltr">'.($pos?'▲':'▼').' '.abs($n).'%</span>';
        case 'badge':
            $colors=['bg-blue-100 text-blue-700','bg-green-100 text-green-700','bg-orange-100 text-orange-700','bg-purple-100 text-purple-700','bg-rose-100 text-rose-700','bg-teal-100 text-teal-700'];
            return '<span class="px-2 py-0.5 rounded-full text-xs font-bold '.$colors[abs(crc32($value))%count($colors)].'">'.htmlspecialchars($value).'</span>';
        default:
            return '<span class="text-gray-700 text-sm">'.htmlspecialchars($value).'</span>';
    }
}

function profile_col($item, $key) {
    $e = $item['entity'];
    if (!$e) return '';
    $is_p = $item['li_entity_type'] === 'personality';
    if ($key === 'country')   return $is_p ? ($e['p_nationality']??'') : ($e['inst_country_id'] ? (string)$e['inst_country_id'] : '');
    if ($key === 'position')  return $is_p ? ($e['p_title']??'') : '';
    if ($key === 'residence') return $is_p ? ($e['p_residence']??'') : '';
    return '';
}
?>
<style>
.list-hero { background: linear-gradient(160deg, #0B0B1F 0%, #130B2B 50%, #1A0D35 100%); }
.rank-top3 { background: linear-gradient(135deg, #8829C8, #5B1494); color: #fff; }
.spotlight-card { transition: transform .2s, box-shadow .2s; }
.spotlight-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(136,41,200,.18); }
.pi-rich-content h1,.pi-rich-content h2,.pi-rich-content h3 { font-weight:900; color:#111827; margin:.8em 0 .4em; }
.pi-rich-content p { margin:.5em 0; line-height:1.9; }
.pi-rich-content ul,.pi-rich-content ol { padding-right:1.5em; margin:.5em 0; }
.pi-rich-content li { margin:.3em 0; }
.pi-rich-content blockquote { border-right:4px solid #8829C8; padding:.5em 1em; background:#faf5ff; border-radius:0 8px 8px 0; color:#6b21a8; font-weight:600; margin:1em 0; }
</style>

<!-- Hero -->
<section class="list-hero relative overflow-hidden min-h-72 flex items-end pb-10 pt-20">
  <?php if ($list['list_cover']): ?>
  <div class="absolute inset-0">
    <img src="<?= htmlspecialchars($list['list_cover']) ?>" alt="" class="w-full h-full object-cover opacity-25">
    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
  </div>
  <?php endif; ?>

  <div class="relative z-10 max-w-5xl mx-auto px-4 w-full">
    <!-- Language toggle -->
    <?php if (!empty($list['list_title_en'])): ?>
    <div class="flex gap-2 mb-4">
      <a href="?id=<?= $list_id ?>" class="px-3 py-1 rounded-full text-xs font-bold border transition <?= $is_ar?'bg-white text-purple-700 border-white':'bg-transparent text-white/60 border-white/30 hover:border-white' ?>">العربية</a>
      <a href="?id=<?= $list_id ?>&lang=en" class="px-3 py-1 rounded-full text-xs font-bold border transition <?= !$is_ar?'bg-white text-purple-700 border-white':'bg-transparent text-white/60 border-white/30 hover:border-white' ?>">English</a>
    </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-end gap-6">
      <?php if ($list['list_logo']): ?>
      <img src="<?= htmlspecialchars($list['list_logo']) ?>" alt=""
        class="h-16 max-w-32 object-contain bg-white/10 rounded-2xl p-2 border border-white/20 flex-shrink-0">
      <?php endif; ?>
      <div class="flex-1 min-w-0">
        <?php if ($list['list_year']): ?>
        <span class="inline-block bg-purple-600 text-white text-xs font-black px-3 py-1 rounded-full mb-3">
          <?= htmlspecialchars($list['list_year']) ?>
        </span>
        <?php endif; ?>
        <h1 class="text-3xl md:text-4xl font-black text-white leading-tight mb-2">
          <?= htmlspecialchars($title_display) ?>
        </h1>
        <?php if ($is_ar && !empty($list['list_title_en'])): ?>
        <p class="text-gray-400 text-sm mb-2" dir="ltr"><?= htmlspecialchars($list['list_title_en']) ?></p>
        <?php endif; ?>
        <?php if ($list['list_description']): ?>
        <p class="text-gray-300 text-base leading-relaxed max-w-2xl">
          <?= nl2br(htmlspecialchars($list['list_description'])) ?>
        </p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-4 mt-4 text-gray-400 text-sm">
          <span><i class="fa-solid fa-users ml-1"></i><?= count($items) ?> <?= $is_ar?'عنصر':'entries' ?></span>
          <span><i class="fa-solid fa-eye ml-1"></i><?= number_format((int)$list['list_views']) ?> <?= $is_ar?'مشاهدة':'views' ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="max-w-5xl mx-auto px-4 py-10">

  <!-- Sponsor section -->
  <?php if ($sponsor): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8 text-center">
    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4"><?= $is_ar?'برعاية':'Sponsored by' ?></p>
    <?php $slink = $sponsor['sp_url'] ?? ''; $logo_src = $sponsor['sp_logo'] ?? ($sponsor['inst_logo']??''); ?>
    <?php if ($slink): ?><a href="<?= htmlspecialchars($slink) ?>" target="_blank" rel="noopener"><?php endif; ?>
    <?php if ($logo_src): ?>
    <img src="<?= htmlspecialchars($logo_src) ?>" alt="<?= htmlspecialchars($sponsor['sp_name']??'') ?>"
      class="h-16 max-w-xs mx-auto object-contain">
    <?php else: ?>
    <p class="font-black text-gray-700 text-xl"><?= htmlspecialchars($sponsor['sp_name']??'') ?></p>
    <?php endif; ?>
    <?php if ($slink): ?></a><?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Criteria / intro content blocks -->
  <?php if (!empty($blocks)): ?>
  <section class="mb-10 space-y-6">
    <?php foreach ($blocks as $block): ?>
    <?php if ($block['lb_type']==='text' && trim(strip_tags($block['lb_content']??''))): ?>
    <div class="bg-white rounded-2xl p-7 shadow-sm border border-gray-100 pi-rich-content" dir="rtl">
      <?= $block['lb_content'] ?>
    </div>
    <?php elseif ($block['lb_type']==='image' && $block['lb_content']): ?>
    <div class="rounded-2xl overflow-hidden shadow-sm border border-gray-100">
      <img src="<?= htmlspecialchars($block['lb_content']) ?>" alt="" class="w-full max-h-96 object-cover">
    </div>
    <?php elseif ($block['lb_type']==='video' && $block['lb_content']): ?>
    <div class="rounded-2xl overflow-hidden shadow-sm bg-black aspect-video">
      <?php
      $vc = $block['lb_content'];
      if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/i', $vc, $m))
          $vc = '<iframe src="https://www.youtube.com/embed/'.$m[1].'" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
      elseif (strpos($vc,'<iframe')===false)
          $vc = '<iframe src="'.htmlspecialchars($vc).'" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
      echo $vc;
      ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- تحت الضوء / Spotlight -->
  <?php if (!empty($spotlight_items)): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-8">
    <div class="flex flex-wrap items-start gap-5">
      <div class="flex-shrink-0">
        <h3 class="font-black text-amber-900 text-lg"><?= $is_ar?'تحت الضوء':'In the Spotlight' ?></h3>
        <p class="text-amber-700 text-xs mt-1 max-w-44"><?= $is_ar?'شخصيات من القائمة تملك عضوية موثقة':'Verified members of this list' ?></p>
        <a href="membership.php?type=verified"
          class="mt-3 inline-block px-4 py-2 bg-amber-600 text-white text-xs font-black rounded-xl hover:bg-amber-700 transition">
          <?= $is_ar?'وثّق ملفك الآن':'Get Verified' ?>
        </a>
      </div>
      <div class="flex flex-wrap gap-4 flex-1">
        <?php foreach (array_slice($spotlight_items, 0, 6) as $si):
          $e = $si['entity'];
          if (!$e) continue;
          $is_p = $si['li_entity_type']==='personality';
          $sname = $is_p ? ($e['p_name_ar']??'') : ($e['inst_name_ar']??'');
          $sphoto = $is_p ? ($e['p_photo']??'') : ($e['inst_logo']??'');
          $slink = $is_p ? "profile.php?id={$e['p_id']}" : "institution.php?id={$e['inst_id']}";
        ?>
        <a href="<?= $slink ?>" class="spotlight-card flex flex-col items-center gap-2 text-center max-w-24">
          <?php if ($sphoto): ?>
          <img src="<?= htmlspecialchars($sphoto) ?>" alt=""
            class="w-16 h-16 rounded-full object-cover border-2 border-amber-300 shadow">
          <?php else: ?>
          <div class="w-16 h-16 rounded-full bg-amber-200 flex items-center justify-center text-amber-700 font-black text-xl">
            <?= mb_substr($sname, 0, 1) ?>
          </div>
          <?php endif; ?>
          <span class="text-xs font-bold text-amber-900 leading-tight">
            <?= htmlspecialchars($sname) ?>
            <i class="fa-solid fa-circle-check text-blue-500 text-xs block text-center mt-0.5"></i>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Filter bar -->
  <?php if (!empty($filter_values)): ?>
  <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 mb-5" id="filter-bar"
    style="background:linear-gradient(135deg,#f0f4ff,#faf5ff);">
    <div class="flex flex-wrap items-center gap-3">
      <span class="text-sm font-black text-gray-600 flex items-center gap-2">
        <i class="fa-solid fa-sliders text-purple-500"></i>
        <?= $is_ar?'تصفية الشخصيات':'Filter' ?>
      </span>
      <?php foreach ($filterable_cols as $col):
        if (empty($filter_values[$col['key']])) continue;
        $label = $is_ar ? ($col['label']??$col['key']) : ($col['label_en']??$col['label']??$col['key']);
      ?>
      <select onchange="applyFilters()" data-filter-key="<?= htmlspecialchars($col['key']) ?>"
        class="border border-gray-200 rounded-xl px-3 py-2 text-sm font-bold text-gray-700 bg-white focus:outline-none focus:border-purple-400"
        style="font-family:'Cairo',sans-serif">
        <option value=""><?= $is_ar?'الكل':'All' ?> <?= htmlspecialchars($label) ?></option>
        <?php foreach ($filter_values[$col['key']] as $fv): ?>
        <option value="<?= htmlspecialchars($fv) ?>"><?= htmlspecialchars($fv) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endforeach; ?>
      <button onclick="clearFilters()" class="text-xs text-gray-400 hover:text-gray-600 font-bold px-2 py-1 rounded-lg hover:bg-white/70">
        <i class="fa-solid fa-xmark ml-1"></i><?= $is_ar?'إزالة التصفية':'Clear' ?>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Ranked table -->
  <?php if (!empty($items)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" id="items-table">
    <div class="overflow-x-auto">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:linear-gradient(135deg,#6B21A8,#4C1D95);">
            <th style="padding:12px 16px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:900;color:#e9d5ff;width:48px;">#</th>
            <th style="padding:12px 16px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:900;color:#e9d5ff;">
              <?= $is_ar?'الاسم':'Name' ?>
            </th>
            <?php foreach ($list_columns as $col): ?>
            <th style="padding:12px 16px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:900;color:#e9d5ff;white-space:nowrap;">
              <?= htmlspecialchars($is_ar ? ($col['label']??$col['key']) : ($col['label_en']??$col['label']??$col['key'])) ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="items-tbody">
          <?php foreach ($items as $idx => $item):
            $ent = $item['entity'];
            if (!$ent) continue;
            $is_p = $item['li_entity_type'] === 'personality';
            $ename  = $is_ar || empty($ent[$is_p?'p_name_en':'inst_name_en'])
                    ? ($is_p?$ent['p_name_ar']:$ent['inst_name_ar'])
                    : ($is_p?$ent['p_name_en']:$ent['inst_name_en']);
            $ephoto = $is_p ? ($ent['p_photo']??'') : ($ent['inst_logo']??'');
            $elink  = $is_p ? "profile.php?id={$ent['p_id']}" : "institution.php?id={$ent['inst_id']}";
            $verified = $is_p && ($ent['p_verified']??0);
            $rank   = (int)($item['li_rank'] ?: $idx+1);
            $is_top3 = $rank <= 3;

            $data_attrs = '';
            foreach ($list_columns as $col) {
                $key = $col['key']??'';
                $val = ($col['source']??'manual')==='profile' ? profile_col($item,$key) : ($item['_data'][$key]??'');
                $data_attrs .= ' data-col-'.htmlspecialchars($key).'="'.htmlspecialchars($val).'"';
            }
          ?>
          <tr class="item-row" <?= $data_attrs ?>
            style="border-top:1px solid #f3f4f6;transition:background .15s;"
            onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background=''">
            <td style="padding:14px 16px;">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;font-weight:900;font-size:13px;
                <?= $is_top3 ? 'background:linear-gradient(135deg,#8829C8,#5B1494);color:#fff;' : 'background:#f3f4f6;color:#6b7280;' ?>">
                <?= $rank ?>
              </span>
            </td>
            <td style="padding:14px 16px;">
              <a href="<?= $elink ?>" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;">
                <?php if ($ephoto): ?>
                <img src="<?= htmlspecialchars($ephoto) ?>" alt=""
                  style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e9d5ff;flex-shrink:0;">
                <?php else: ?>
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#8829C8,#5B1494);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:14px;flex-shrink:0;">
                  <?= mb_substr($ename,0,1) ?>
                </div>
                <?php endif; ?>
                <div>
                  <span style="font-weight:800;font-size:14px;color:#111827;">
                    <?= htmlspecialchars($ename) ?>
                    <?php if ($verified): ?><i class="fa-solid fa-circle-check" style="color:#3b82f6;font-size:11px;margin-right:3px;"></i><?php endif; ?>
                  </span>
                  <span style="display:block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;margin-top:2px;display:inline-block;
                    <?= $is_p?'background:#eff6ff;color:#2563eb;':'background:#fff7ed;color:#c2410c;' ?>">
                    <?= $is_p?($is_ar?'شخصية':'Person'):($is_ar?'مؤسسة':'Institution') ?>
                  </span>
                </div>
              </a>
            </td>
            <?php foreach ($list_columns as $col):
              $key  = $col['key']??'';
              $src  = $col['source']??'manual';
              $type = $col['type']??'text';
              $val  = $src==='profile' ? profile_col($item,$key) : ($item['_data'][$key]??'');
            ?>
            <td style="padding:14px 16px;white-space:nowrap;"><?= fmt_col($val,$type) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="no-results" style="display:none;text-align:center;padding:40px;color:#9ca3af;">
      <i class="fa-solid fa-magnifying-glass" style="font-size:28px;margin-bottom:10px;opacity:.3;display:block;"></i>
      <p style="font-weight:700;"><?= $is_ar?'لا توجد نتائج تطابق التصفية':'No results match your filters' ?></p>
    </div>
  </div>
  <?php else: ?>
  <div class="text-center py-16 text-gray-400 bg-white rounded-2xl border border-gray-100">
    <i class="fa-solid fa-users text-5xl mb-4 opacity-20"></i>
    <p class="font-bold"><?= $is_ar?'لم يتم إضافة أعضاء لهذه القائمة بعد':'No entries added yet' ?></p>
  </div>
  <?php endif; ?>

</div>

<?php if (!empty($filter_values)): ?>
<script>
function applyFilters() {
  var rows = document.querySelectorAll('#items-tbody .item-row');
  var filters = {};
  document.querySelectorAll('[data-filter-key]').forEach(function(s) {
    if (s.value) filters[s.getAttribute('data-filter-key')] = s.value;
  });
  var visible = 0;
  rows.forEach(function(row) {
    var show = true;
    for (var k in filters) {
      if ((row.getAttribute('data-col-'+k)||'') !== filters[k]) { show=false; break; }
    }
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('no-results').style.display = visible ? 'none' : 'block';
}
function clearFilters() {
  document.querySelectorAll('[data-filter-key]').forEach(function(s){s.value='';});
  applyFilters();
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
