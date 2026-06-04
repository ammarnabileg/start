<?php
require_once 'includes/config.php';

$list_id = (int)($_GET['id'] ?? 0);
if (!$list_id) { header('Location: lists.php'); exit; }

$r = $mysqli->query("SELECT * FROM pi_lists WHERE list_id=$list_id AND list_active=1");
if (!$r || !$r->num_rows) { header('Location: lists.php'); exit; }
$list = $r->fetch_assoc();

$lang = isset($_GET['lang']) && $_GET['lang'] === 'en' ? 'en' : 'ar';
$is_ar = $lang === 'ar';

if (empty($_SESSION['viewed_list_'.$list_id])) {
    $mysqli->query("UPDATE pi_lists SET list_views=list_views+1 WHERE list_id=$list_id");
    $mysqli->query("INSERT INTO pi_visit_daily (vd_page, vd_date, vd_count) VALUES ('list/$list_id', CURDATE(), 1) ON DUPLICATE KEY UPDATE vd_count=vd_count+1");
    $_SESSION['viewed_list_'.$list_id] = 1;
}

$list_columns = json_decode($list['list_columns'] ?? '[]', true) ?: [];

$blocks = [];
$rb = $mysqli->query("SELECT * FROM pi_list_blocks WHERE lb_list_id=$list_id ORDER BY lb_order,lb_id");
if ($rb) while ($row=$rb->fetch_assoc()) $blocks[] = $row;

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

$spotlight_ids = json_decode($list['list_spotlight'] ?? '[]', true) ?: [];
if (empty($spotlight_ids)) {
    foreach ($items as $item) {
        if ($item['li_entity_type'] === 'personality' && ($item['entity']['p_verified'] ?? 0))
            $spotlight_ids[] = 'personality-'.$item['li_entity_id'];
    }
}
$spotlight_items = [];
foreach ($items as $item) {
    $key = $item['li_entity_type'].'-'.$item['li_entity_id'];
    if (in_array($key, $spotlight_ids)) $spotlight_items[] = $item;
}

$sponsor = null;
if (!empty($list['list_sponsor_id'])) {
    $sr = $mysqli->query("SELECT * FROM pi_sponsors WHERE sp_id=".(int)$list['list_sponsor_id']." AND sp_active=1");
    if ($sr && $sr->num_rows) $sponsor = $sr->fetch_assoc();
}
if (!$sponsor && !empty($list['list_sponsor_img']))
    $sponsor = ['sp_name'=>$list['list_sponsor_name']??'', 'sp_logo'=>$list['list_sponsor_img'], 'sp_url'=>$list['list_sponsor_url']??''];

// Custom sponsors (multiple)
$custom_sponsors = json_decode($list['list_sponsors_json'] ?? '[]', true) ?: [];

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
    if ($value === '' || $value === null) return '<span style="color:#d1d5db">—</span>';
    switch ($type) {
        case 'currency':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            return '<span style="font-weight:800;color:#111827;font-variant-numeric:tabular-nums" dir="ltr">$'.number_format($n,0).'</span>';
        case 'number':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            return '<span style="font-weight:800;color:#374151;font-variant-numeric:tabular-nums" dir="ltr">'.number_format($n,0).'</span>';
        case 'percent':
            $n = (float)preg_replace('/[^0-9.-]/', '', $value);
            $pos = $n >= 0;
            return '<span style="font-weight:800;color:'.($pos?'#059669':'#dc2626').'" dir="ltr">'.($pos?'▲':'▼').' '.abs($n).'%</span>';
        case 'badge':
            $colors=['#dbeafe:#1d4ed8','#dcfce7:#15803d','#fef3c7:#b45309','#f3e8ff:#7e22ce','#ffe4e6:#be123c','#ccfbf1:#0f766e'];
            $c = $colors[abs(crc32($value))%count($colors)];
            list($bg,$fg) = explode(':', $c);
            return '<span style="padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;background:'.$bg.';color:'.$fg.'">'.htmlspecialchars($value).'</span>';
        default:
            return '<span style="color:#374151;font-size:13px">'.htmlspecialchars($value).'</span>';
    }
}
function profile_col($item, $key) {
    $e = $item['entity'];
    if (!$e) return '';
    $is_p = $item['li_entity_type'] === 'personality';
    if ($key === 'country')   return $is_p ? ($e['p_nationality']??'') : '';
    if ($key === 'position')  return $is_p ? ($e['p_title']??'') : '';
    if ($key === 'residence') return $is_p ? ($e['p_residence']??'') : '';
    return '';
}

// Top 3 and rest
$top3  = array_filter($items, function($i){ return (int)($i['li_rank'] ?: 999) <= 3; });
$rest  = array_filter($items, function($i){ return (int)($i['li_rank'] ?: 999) > 3; });
usort($top3, function($a,$b){ return (int)($a['li_rank']?:999)-(int)($b['li_rank']?:999); });
usort($rest, function($a,$b){ return (int)($a['li_rank']?:999)-(int)($b['li_rank']?:999); });
?>

<style>
:root{--pi-purple:#7c3aed;--pi-dark:#0f0a1e;}
.lp-hero{background:var(--pi-dark);position:relative;overflow:hidden;}
.lp-hero-bg{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.18;}
.lp-hero-grad{position:absolute;inset:0;background:linear-gradient(170deg,rgba(15,10,30,.55) 0%,rgba(15,10,30,.92) 60%,rgba(15,10,30,1) 100%);}
.lp-logo-wrap{width:100px;height:100px;border-radius:22px;background:rgba(255,255,255,.08);backdrop-filter:blur(8px);border:2px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
.lp-stat{display:flex;align-items:center;gap:6px;color:rgba(255,255,255,.6);font-size:13px;font-weight:700;}
.lp-stat i{color:rgba(255,255,255,.35);}
.medal-1{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;}
.medal-2{background:linear-gradient(135deg,#94a3b8,#64748b);color:#fff;}
.medal-3{background:linear-gradient(135deg,#c2763a,#a85d2a);color:#fff;}
.medal-n{background:#f3f4f6;color:#6b7280;}
.top3-card{background:#fff;border-radius:20px;overflow:hidden;transition:transform .2s,box-shadow .2s;border:1px solid #f0f0f0;}
.top3-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(124,58,237,.15);}
.top3-rank-badge{width:38px;height:38px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:900;font-size:16px;flex-shrink:0;}
.lp-table tr:hover td{background:#faf5ff!important;}
.lp-table td,th{padding:13px 18px;}
.sponsor-strip{display:flex;align-items:center;justify-content:center;gap:20px;flex-wrap:wrap;}
.pi-rich-content h1,.pi-rich-content h2,.pi-rich-content h3{font-weight:900;color:#111827;margin:.8em 0 .4em;}
.pi-rich-content p{margin:.5em 0;line-height:1.9;}
.pi-rich-content ul,.pi-rich-content ol{padding-right:1.5em;margin:.5em 0;}
.pi-rich-content blockquote{border-right:4px solid #7c3aed;padding:.5em 1em;background:#faf5ff;border-radius:0 10px 10px 0;color:#6b21a8;font-weight:700;margin:1em 0;}
.filter-pill{padding:7px 16px;border-radius:999px;font-size:12px;font-weight:700;border:1.5px solid #e5e7eb;background:#fff;color:#374151;cursor:pointer;transition:all .15s;appearance:none;font-family:inherit;}
.filter-pill:focus,.filter-pill:hover{border-color:#7c3aed;color:#7c3aed;outline:none;}
</style>

<!-- ═══ HERO ═══ -->
<section class="lp-hero">
  <?php if ($list['list_cover']): ?>
  <div class="lp-hero-bg" style="background-image:url('<?= htmlspecialchars($list['list_cover']) ?>')"></div>
  <?php endif; ?>
  <div class="lp-hero-grad"></div>

  <div class="relative z-10 max-w-5xl mx-auto px-5 pt-16 pb-14">

    <!-- Lang toggle -->
    <?php if (!empty($list['list_title_en'])): ?>
    <div class="flex gap-2 mb-6">
      <a href="?id=<?= $list_id ?>" style="padding:4px 14px;border-radius:999px;font-size:11px;font-weight:800;border:1.5px solid;transition:all .15s;<?= $is_ar?'background:#fff;color:#7c3aed;border-color:#fff':'background:transparent;color:rgba(255,255,255,.5);border-color:rgba(255,255,255,.25)' ?>">العربية</a>
      <a href="?id=<?= $list_id ?>&lang=en" style="padding:4px 14px;border-radius:999px;font-size:11px;font-weight:800;border:1.5px solid;transition:all .15s;<?= !$is_ar?'background:#fff;color:#7c3aed;border-color:#fff':'background:transparent;color:rgba(255,255,255,.5);border-color:rgba(255,255,255,.25)' ?>">English</a>
    </div>
    <?php endif; ?>

    <div class="flex items-start gap-6 flex-wrap">
      <!-- Logo -->
      <?php if ($list['list_logo']): ?>
      <div class="lp-logo-wrap">
        <img src="<?= htmlspecialchars($list['list_logo']) ?>" alt="" style="max-width:80px;max-height:80px;object-fit:contain;">
      </div>
      <?php endif; ?>

      <div class="flex-1 min-w-0">
        <?php if ($list['list_year']): ?>
        <span style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#a855f7);color:#fff;font-size:11px;font-weight:900;padding:3px 14px;border-radius:999px;letter-spacing:.5px;margin-bottom:12px;">
          <?= htmlspecialchars($list['list_year']) ?>
        </span>
        <?php endif; ?>

        <h1 style="font-size:clamp(1.8rem,4vw,2.8rem);font-weight:900;color:#fff;line-height:1.15;margin:0 0 6px 0;">
          <?= htmlspecialchars($title_display) ?>
        </h1>

        <?php if ($is_ar && !empty($list['list_title_en'])): ?>
        <p style="color:rgba(255,255,255,.4);font-size:13px;font-weight:700;margin-bottom:10px;" dir="ltr"><?= htmlspecialchars($list['list_title_en']) ?></p>
        <?php endif; ?>

        <?php if ($list['list_description']): ?>
        <p style="color:rgba(255,255,255,.65);font-size:15px;line-height:1.8;max-width:600px;margin:0 0 16px 0;">
          <?= nl2br(htmlspecialchars($list['list_description'])) ?>
        </p>
        <?php endif; ?>

        <div style="display:flex;gap:20px;flex-wrap:wrap;">
          <span class="lp-stat"><i class="fa-solid fa-list-ol"></i><?= count($items) ?> <?= $is_ar?'عنصر':'entries' ?></span>
          <span class="lp-stat"><i class="fa-solid fa-eye"></i><?= number_format((int)$list['list_views']) ?> <?= $is_ar?'مشاهدة':'views' ?></span>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="max-w-5xl mx-auto px-4 py-10 space-y-10">

<!-- ═══ SPONSOR BAR ═══ -->
<?php
$all_sponsors = [];
if ($sponsor) $all_sponsors[] = $sponsor;
foreach ($custom_sponsors as $cs) { if (!empty($cs['name']) || !empty($cs['img'])) $all_sponsors[] = ['sp_name'=>$cs['name'],'sp_logo'=>$cs['img'],'sp_url'=>$cs['url']]; }
if (!empty($all_sponsors)):
?>
<div style="background:#fff;border-radius:18px;border:1px solid #f0f0f0;padding:20px 28px;">
  <p style="text-align:center;font-size:10px;font-weight:800;color:#9ca3af;letter-spacing:2px;text-transform:uppercase;margin-bottom:16px;"><?= $is_ar?'برعاية':'Sponsored by' ?></p>
  <div class="sponsor-strip">
    <?php foreach ($all_sponsors as $sp):
      $slink = $sp['sp_url'] ?? '';
      $logo  = $sp['sp_logo'] ?? '';
      $sname = $sp['sp_name'] ?? '';
    ?>
    <?php if ($slink): ?><a href="<?= htmlspecialchars($slink) ?>" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;text-decoration:none;"><?php else: ?><div style="display:flex;align-items:center;gap:10px;"><?php endif; ?>
      <?php if ($logo): ?>
      <img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($sname) ?>" style="height:44px;max-width:140px;object-fit:contain;filter:grayscale(.2);">
      <?php else: ?>
      <span style="font-weight:900;font-size:15px;color:#374151;"><?= htmlspecialchars($sname) ?></span>
      <?php endif; ?>
    <?php if ($slink): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══ CONTENT BLOCKS ═══ -->
<?php if (!empty($blocks)): ?>
<div class="space-y-5">
  <?php foreach ($blocks as $block): ?>
  <?php if ($block['lb_type']==='text' && trim(strip_tags($block['lb_content']??''))): ?>
  <div style="background:#fff;border-radius:18px;padding:28px 32px;border:1px solid #f0f0f0;" class="pi-rich-content" dir="rtl">
    <?= $block['lb_content'] ?>
  </div>
  <?php elseif ($block['lb_type']==='image' && $block['lb_content']): ?>
  <div style="border-radius:18px;overflow:hidden;border:1px solid #f0f0f0;">
    <img src="<?= htmlspecialchars($block['lb_content']) ?>" alt="" style="width:100%;max-height:420px;object-fit:cover;display:block;">
  </div>
  <?php elseif ($block['lb_type']==='video' && $block['lb_content']): ?>
  <div style="border-radius:18px;overflow:hidden;background:#000;aspect-ratio:16/9;">
    <?php
    $vc = $block['lb_content'];
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/i', $vc, $m))
        echo '<iframe src="https://www.youtube.com/embed/'.$m[1].'" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
    elseif (strpos($vc,'<iframe')===false)
        echo '<iframe src="'.htmlspecialchars($vc).'" frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>';
    else echo $vc;
    ?>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ SPOTLIGHT ═══ -->
<?php if (!empty($spotlight_items)): ?>
<div style="background:linear-gradient(135deg,#1e1040,#2d1b69);border-radius:20px;padding:28px 28px 24px;overflow:hidden;position:relative;">
  <div style="position:absolute;top:-30px;right:-30px;width:200px;height:200px;border-radius:50%;background:rgba(124,58,237,.15);pointer-events:none;"></div>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
    <i class="fa-solid fa-bolt" style="color:#fbbf24;font-size:18px;"></i>
    <h3 style="font-weight:900;color:#fff;font-size:15px;margin:0;"><?= $is_ar?'تحت الضوء':'In the Spotlight' ?></h3>
    <span style="background:rgba(251,191,36,.15);color:#fbbf24;font-size:10px;font-weight:800;padding:2px 10px;border-radius:999px;letter-spacing:.5px;"><?= $is_ar?'موثّقون':'VERIFIED' ?></span>
  </div>
  <div style="display:flex;gap:14px;flex-wrap:wrap;">
    <?php foreach (array_slice($spotlight_items, 0, 8) as $si):
      $e = $si['entity'];
      if (!$e) continue;
      $is_p2 = $si['li_entity_type']==='personality';
      $sname  = $is_p2 ? ($e['p_name_ar']??'') : ($e['inst_name_ar']??'');
      $sphoto = $is_p2 ? ($e['p_photo']??'') : ($e['inst_logo']??'');
      $slink  = $is_p2 ? "profile.php?id={$e['p_id']}" : "institution.php?id={$e['inst_id']}";
    ?>
    <a href="<?= $slink ?>" style="display:flex;flex-direction:column;align-items:center;gap:8px;text-decoration:none;width:68px;">
      <?php if ($sphoto): ?>
      <img src="<?= htmlspecialchars($sphoto) ?>" alt="" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(124,58,237,.6);box-shadow:0 0 0 3px rgba(124,58,237,.2);">
      <?php else: ?>
      <div style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:18px;border:2.5px solid rgba(124,58,237,.6);"><?= mb_substr($sname,0,1) ?></div>
      <?php endif; ?>
      <span style="font-size:10px;font-weight:700;color:rgba(255,255,255,.8);text-align:center;line-height:1.35;word-break:break-word;"><?= htmlspecialchars(mb_substr($sname,0,14)) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <a href="membership.php?type=verified" style="display:inline-block;margin-top:20px;padding:8px 20px;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.2);color:#fff;font-size:12px;font-weight:800;border-radius:999px;text-decoration:none;transition:background .15s;"
    onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.1)'">
    <?= $is_ar?'وثّق ملفك الآن ←':'Get Verified →' ?>
  </a>
</div>
<?php endif; ?>

<!-- ═══ ITEMS ═══ -->
<?php if (!empty($items)): ?>
<div>

  <!-- Section header + filters -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
    <h2 style="font-size:19px;font-weight:900;color:#111827;margin:0;display:flex;align-items:center;gap:10px;">
      <i class="fa-solid fa-ranking-star" style="color:#7c3aed;font-size:20px;"></i>
      <?= $is_ar?'قائمة التصنيف':'Rankings' ?>
      <span style="background:#f3e8ff;color:#7c3aed;font-size:12px;font-weight:800;padding:3px 12px;border-radius:999px;"><?= count($items) ?></span>
    </h2>
    <?php if (!empty($filter_values)): ?>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
      <?php foreach ($filterable_cols as $col):
        if (empty($filter_values[$col['key']])) continue;
        $label = $is_ar ? ($col['label']??$col['key']) : ($col['label_en']??$col['label']??$col['key']);
      ?>
      <select onchange="applyFilters()" data-filter-key="<?= htmlspecialchars($col['key']) ?>" class="filter-pill">
        <option value=""><?= htmlspecialchars($label) ?> — <?= $is_ar?'الكل':'All' ?></option>
        <?php foreach ($filter_values[$col['key']] as $fv): ?>
        <option value="<?= htmlspecialchars($fv) ?>"><?= htmlspecialchars($fv) ?></option>
        <?php endforeach; ?>
      </select>
      <?php endforeach; ?>
      <button onclick="clearFilters()" class="filter-pill" style="color:#ef4444;border-color:#fecaca;">
        <i class="fa-solid fa-xmark" style="margin-<?= $is_ar?'left':'right' ?>:4px;"></i><?= $is_ar?'مسح':'Clear' ?>
      </button>
    </div>
    <?php endif; ?>
  </div>

  <?php
  // Helper to extract entity info
  function item_info($item, $is_ar) {
      $ent = $item['entity'];
      if (!$ent) return null;
      $is_p = $item['li_entity_type'] === 'personality';
      $name_ar = $is_p ? ($ent['p_name_ar']??'') : ($ent['inst_name_ar']??'');
      $name_en = $is_p ? ($ent['p_name_en']??'') : ($ent['inst_name_en']??'');
      return [
          'name'     => ($is_ar || empty($name_en)) ? $name_ar : $name_en,
          'photo'    => $is_p ? ($ent['p_photo']??'') : ($ent['inst_logo']??''),
          'link'     => $is_p ? "profile.php?id={$ent['p_id']}" : "institution.php?id={$ent['inst_id']}",
          'title'    => $is_p ? ($ent['p_title']??'') : '',
          'verified' => $is_p && ($ent['p_verified']??0),
          'is_p'     => $is_p,
      ];
  }
  ?>

  <!-- ══ TOP 3 PODIUM CARDS ══ -->
  <?php if (!empty($top3)):
    $top3_arr = array_values($top3);
    // Podium order: 2, 1, 3 (visually) — only if all 3 exist
    if (count($top3_arr) === 3) {
        $podium = [$top3_arr[1], $top3_arr[0], $top3_arr[2]]; // 2nd | 1st | 3rd
    } else {
        $podium = $top3_arr;
    }
    $medal_cfg = [
        1 => ['grad'=>'linear-gradient(135deg,#f59e0b,#d97706)', 'border'=>'#f59e0b', 'glow'=>'rgba(245,158,11,.2)', 'icon'=>'🥇', 'h'=>'220px'],
        2 => ['grad'=>'linear-gradient(135deg,#9ca3af,#6b7280)', 'border'=>'#9ca3af', 'glow'=>'rgba(156,163,175,.15)', 'icon'=>'🥈', 'h'=>'200px'],
        3 => ['grad'=>'linear-gradient(135deg,#cd7f32,#a0522d)', 'border'=>'#cd7f32', 'glow'=>'rgba(205,127,50,.15)', 'icon'=>'🥉', 'h'=>'190px'],
    ];
  ?>
  <div style="display:flex;gap:16px;align-items:flex-end;margin-bottom:24px;justify-content:center;flex-wrap:wrap;">
    <?php foreach ($podium as $item):
      $info = item_info($item, $is_ar);
      if (!$info) continue;
      $rank = (int)($item['li_rank'] ?: 999);
      $cfg  = $medal_cfg[$rank] ?? ['grad'=>'linear-gradient(135deg,#7c3aed,#4c1d95)','border'=>'#7c3aed','glow'=>'rgba(124,58,237,.15)','icon'=>'#'.$rank,'h'=>'180px'];
      $is_first = ($rank === 1);

      // First column value
      $col1_val = ''; $col1_lbl = ''; $col1_type = 'text';
      if (!empty($list_columns)) {
          $col1 = $list_columns[0];
          $col1_lbl  = $is_ar ? ($col1['label']??'') : ($col1['label_en']??$col1['label']??'');
          $col1_type = $col1['type']??'text';
          $col1_val  = ($col1['source']??'manual')==='profile' ? profile_col($item,$col1['key']) : ($item['_data'][$col1['key']]??'');
      }
    ?>
    <a href="<?= $info['link'] ?>" style="flex:1;min-width:160px;max-width:<?= $is_first?'240px':'210px' ?>;
      background:#fff;border-radius:20px;border:2px solid <?= $cfg['border'] ?>;
      box-shadow:0 8px 32px <?= $cfg['glow'] ?>,0 2px 8px rgba(0,0,0,.06);
      text-decoration:none;display:flex;flex-direction:column;overflow:hidden;
      position:relative;transition:transform .2s,box-shadow .2s;
      <?= $is_first ? 'transform:translateY(-14px);' : '' ?>"
      onmouseover="this.style.transform='translateY(<?= $is_first?'-18px':'-4px' ?>)'"
      onmouseout="this.style.transform='translateY(<?= $is_first?'-14px':'0' ?>)'">

      <!-- Medal badge (top-left corner) -->
      <div style="position:absolute;top:10px;right:10px;z-index:2;
        background:<?= $cfg['grad'] ?>;color:#fff;width:32px;height:32px;
        border-radius:50%;display:flex;align-items:center;justify-content:center;
        font-size:15px;font-weight:900;border:2px solid rgba(255,255,255,.8);
        box-shadow:0 2px 8px <?= $cfg['glow'] ?>;">
        <?= $rank ?>
      </div>

      <!-- Portrait Photo -->
      <div style="width:100%;aspect-ratio:<?= $info['is_p']?'3/4':'1/1' ?>;overflow:hidden;background:#f3f4f6;flex-shrink:0;">
        <?php if ($info['photo']): ?>
        <img src="<?= htmlspecialchars($info['photo']) ?>" alt=""
          style="width:100%;height:100%;object-fit:cover;object-position:top;filter:grayscale(15%);transition:filter .3s,transform .3s;"
          onmouseover="this.style.filter='grayscale(0)';this.style.transform='scale(1.04)'"
          onmouseout="this.style.filter='grayscale(15%)';this.style.transform='scale(1)'">
        <?php else: ?>
        <div style="width:100%;height:100%;background:<?= $cfg['grad'] ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:<?= $is_first?'3rem':'2.5rem' ?>;">
          <?= mb_substr($info['name'],0,1) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Name & info -->
      <div style="padding:12px 14px 14px;">
        <p style="font-weight:900;font-size:<?= $is_first?'15px':'13px' ?>;color:#111827;margin:0 0 3px;line-height:1.3;">
          <?= htmlspecialchars($info['name']) ?>
          <?php if ($info['verified']): ?><i class="fa-solid fa-circle-check" style="color:#3b82f6;font-size:11px;margin-right:3px;"></i><?php endif; ?>
        </p>
        <?php if ($info['title']): ?>
        <p style="font-size:11px;color:#9ca3af;font-weight:600;margin:0 0 8px;line-height:1.3;"><?= htmlspecialchars($info['title']) ?></p>
        <?php endif; ?>
        <?php if ($col1_val !== ''): ?>
        <div style="background:#f9fafb;border-radius:8px;padding:6px 10px;display:inline-block;">
          <?php if ($col1_lbl): ?><span style="font-size:9px;color:#9ca3af;font-weight:800;display:block;letter-spacing:.5px;margin-bottom:2px;"><?= htmlspecialchars($col1_lbl) ?></span><?php endif; ?>
          <?= fmt_col($col1_val, $col1_type) ?>
        </div>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ══ REST TABLE ══ -->
  <?php if (!empty($rest)): ?>
  <div style="background:#fff;border-radius:18px;border:1px solid #f0f0f0;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.04);">
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#fafafa;border-bottom:1px solid #f0f0f0;">
            <th style="padding:12px 20px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:800;color:#9ca3af;letter-spacing:.3px;width:60px;"><?= $is_ar?'#':'#' ?></th>
            <th style="padding:12px 20px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:800;color:#9ca3af;letter-spacing:.3px;"><?= $is_ar?'الاسم':'Name' ?></th>
            <?php foreach ($list_columns as $col): ?>
            <th style="padding:12px 20px;text-align:<?= $is_ar?'right':'left' ?>;font-size:11px;font-weight:800;color:#9ca3af;letter-spacing:.3px;white-space:nowrap;">
              <?= htmlspecialchars($is_ar ? ($col['label']??$col['key']) : ($col['label_en']??$col['label']??$col['key'])) ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody id="items-tbody">
          <?php foreach ($rest as $item):
            $ent = $item['entity'];
            if (!$ent) continue;
            $info = item_info($item, $is_ar);
            if (!$info) continue;
            $rank = (int)($item['li_rank'] ?: 999);

            $data_attrs = '';
            foreach ($list_columns as $col) {
                $key = $col['key']??'';
                $val = ($col['source']??'manual')==='profile' ? profile_col($item,$key) : ($item['_data'][$key]??'');
                $data_attrs .= ' data-col-'.htmlspecialchars($key).'="'.htmlspecialchars($val).'"';
            }
          ?>
          <tr class="item-row" <?= $data_attrs ?>
            style="border-top:1px solid #f5f5f7;transition:background .12s;"
            onmouseover="this.style.background='#faf5ff'" onmouseout="this.style.background=''">
            <td style="padding:14px 20px;">
              <span style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;
                border-radius:50%;font-weight:900;font-size:13px;background:#f3f4f6;color:#6b7280;">
                <?= $rank ?>
              </span>
            </td>
            <td style="padding:14px 20px;">
              <a href="<?= $info['link'] ?>" style="display:flex;align-items:center;gap:12px;text-decoration:none;">
                <?php if ($info['photo']): ?>
                <img src="<?= htmlspecialchars($info['photo']) ?>" alt=""
                  style="width:<?= $info['is_p']?'34px':'42px' ?>;height:<?= $info['is_p']?'44px':'42px' ?>;
                  border-radius:<?= $info['is_p']?'8px':'10px' ?>;object-fit:cover;object-position:top;
                  border:1.5px solid #ede9fe;flex-shrink:0;filter:grayscale(15%);">
                <?php else: ?>
                <div style="width:34px;height:44px;border-radius:8px;
                  background:linear-gradient(135deg,#7c3aed,#4c1d95);display:flex;align-items:center;
                  justify-content:center;color:#fff;font-weight:900;font-size:14px;flex-shrink:0;">
                  <?= mb_substr($info['name'],0,1) ?>
                </div>
                <?php endif; ?>
                <div>
                  <span style="font-weight:800;font-size:14px;color:#111827;display:flex;align-items:center;gap:5px;">
                    <?= htmlspecialchars($info['name']) ?>
                    <?php if ($info['verified']): ?><i class="fa-solid fa-circle-check" style="color:#3b82f6;font-size:11px;"></i><?php endif; ?>
                  </span>
                  <?php if ($info['title']): ?>
                  <span style="font-size:11px;color:#9ca3af;font-weight:600;"><?= htmlspecialchars($info['title']) ?></span>
                  <?php endif; ?>
                </div>
              </a>
            </td>
            <?php foreach ($list_columns as $col):
              $key  = $col['key']??'';
              $src  = $col['source']??'manual';
              $type = $col['type']??'text';
              $val  = $src==='profile' ? profile_col($item,$key) : ($item['_data'][$key]??'');
            ?>
            <td style="padding:14px 20px;white-space:nowrap;"><?= fmt_col($val,$type) ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div id="no-results" style="display:none;text-align:center;padding:56px;color:#9ca3af;">
      <i class="fa-solid fa-magnifying-glass" style="font-size:36px;margin-bottom:14px;opacity:.2;display:block;"></i>
      <p style="font-weight:700;"><?= $is_ar?'لا توجد نتائج تطابق التصفية':'No results match your filters' ?></p>
    </div>
  </div>
  <?php endif; ?>

</div>
<?php else: ?>
<div style="text-align:center;padding:72px;background:#fff;border-radius:20px;border:1px solid #f0f0f0;color:#9ca3af;">
  <i class="fa-solid fa-users" style="font-size:52px;opacity:.12;display:block;margin-bottom:18px;"></i>
  <p style="font-weight:700;font-size:15px;"><?= $is_ar?'لم يتم إضافة أعضاء لهذه القائمة بعد':'No entries added yet' ?></p>
</div>
<?php endif; ?>

</div><!-- /max-w-5xl -->

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
  document.querySelectorAll('[data-filter-key]').forEach(function(s){ s.value=''; });
  applyFilters();
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
