<?php
/**
 * فريق الوكلاء على الصفحة الرئيسية: 8 وكلاء، كل وكيل = فانكشن في المنصة.
 * الوكيل «بيشتغل» (تحريك) لما يحصل نشاط حقيقي في مجاله خلال آخر 3 ساعات،
 * والإشارة بتتقرأ من activity_log (رخيص وبدون نداءات خارجية).
 */

/** تعريف الوكلاء الثمانية: المفتاح، الاسم، اللون، الأيقونة، الوصف، نوع شاشة الميني-UI. */
function ai_team_agents(): array
{
    return [
        'content'     => ['ar' => t('المحتوى'),   'sub' => t('الاستوديو الإبداعي'), 'c' => '#a78bfa', 'icon' => 'pen',   'screen' => 'chart'],
        'scheduling'  => ['ar' => t('الجدولة'),    'sub' => t('قائمة النشر'),        'c' => '#22d3ee', 'icon' => 'cal',   'screen' => 'cal'],
        'campaigns'   => ['ar' => t('الإعلانات'),  'sub' => t('منشئ الحملات'),       'c' => '#f59e0b', 'icon' => 'mega',  'screen' => 'donut'],
        'analytics'   => ['ar' => t('التحليلات'),  'sub' => t('رؤى وتقارير'),        'c' => '#38bdf8', 'icon' => 'chart', 'screen' => 'bars'],
        'messages'    => ['ar' => t('الرسائل'),    'sub' => t('الردود والمحادثات'),  'c' => '#34d399', 'icon' => 'chat',  'screen' => 'chat'],
        'automation'  => ['ar' => t('الأتمتة'),    'sub' => t('محرّك المهام'),       'c' => '#a3e635', 'icon' => 'bolt',  'screen' => 'nodes'],
        'competitors' => ['ar' => t('المنافسين'),  'sub' => t('رصد السوق'),          'c' => '#f472b6', 'icon' => 'eye',   'screen' => 'radar'],
        'crm'         => ['ar' => t('العملاء'),    'sub' => t('إدارة العملاء'),      'c' => '#818cf8', 'icon' => 'users', 'screen' => 'list'],
    ];
}

/** بادئات أحداث activity_log لكل وكيل. */
function ai_team_action_map(): array
{
    return [
        'content'     => ['post.create', 'post.update', 'post.duplicate', 'post.create_pro', 'ai.image', 'designer', 'studio', 'content', 'cpl'],
        'scheduling'  => ['queue', 'post.retry', 'post.schedule'],
        'campaigns'   => ['campaign.create', 'ads', 'ad_builder', 'ad.'],
        'analytics'   => ['analytics', 'reports', 'goals', 'alerts'],
        'messages'    => ['inbox', 'comment', 'review', 'ai.auto_reply', 'broadcast', 'sequence', 'wa_'],
        'automation'  => ['automation', 'workflow'],
        'competitors' => ['competitor', 'comp.'],
        'crm'         => ['contact', 'lead', 'crm', 'deal', 'prospect', 'tender'],
    ];
}

/** حالة كل وكيل: active + منذ كام ثانية آخر نشاط. */
function ai_team_states(int $wid): array
{
    $map = ai_team_action_map();
    $states = [];
    foreach (ai_team_agents() as $k => $_) $states[$k] = ['active' => false, 'ago' => null];
    try {
        $st = db()->prepare("SELECT action, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS ago FROM activity_log WHERE workspace_id = ? AND created_at > NOW() - INTERVAL 24 HOUR GROUP BY action");
        $st->execute([$wid]);
        $rows = $st->fetchAll();
    } catch (Throwable $t) {
        $rows = [];
    }
    foreach ($rows as $r) {
        $act = (string)$r['action']; $ago = (int)$r['ago'];
        foreach ($map as $k => $prefixes) {
            foreach ($prefixes as $p) {
                if (strpos($act, $p) === 0) {
                    if ($states[$k]['ago'] === null || $ago < $states[$k]['ago']) $states[$k]['ago'] = $ago;
                    if ($ago <= 10800) $states[$k]['active'] = true; // آخر 3 ساعات = «شغّال دلوقتي»
                    break;
                }
            }
        }
    }
    // ملاحظة: كل وكيل بيشتغل فقط لما يكون فيه نشاط حقيقي حديث في مجاله — مفيش تثبيت لأي وكيل.
    return $states;
}

/* ------- عناصر الرسم ------- */

function ai_team_icon(string $k, string $col): string
{
    $p = [
        'pen'   => 'M4 20l1-4L16 5l3 3L8 19l-4 1z',
        'cal'   => 'M8 3v4m8-4v4M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
        'mega'  => 'M3 10v4l4 1 2 5 2-1-1-4 9 3V5L9 9H5a2 2 0 00-2 1z',
        'chart' => 'M4 20V10M10 20V4M16 20v-7M20 20H2',
        'chat'  => 'M4 5h16v10H9l-5 4z',
        'bolt'  => 'M13 2L4 14h6l-1 8 9-12h-6z',
        'eye'   => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
        'users' => 'M16 11a4 4 0 10-8 0 4 4 0 008 0zM3 21a9 9 0 0118 0z',
    ][$k] ?? '';
    $stroke = in_array($k, ['cal', 'chart', 'eye'], true);
    return '<path d="' . $p . '" fill="' . ($stroke ? 'none' : $col) . '" stroke="' . $col . '" stroke-width="' . ($stroke ? 2 : 0) . '" stroke-linecap="round" stroke-linejoin="round"/>';
}

/** ميني-UI داخل الشاشة حسب نوع الوكيل (x,y أعلى-يسار المنطقة، w,h المساحة). */
function ai_team_screen(string $type, float $x, float $y, float $w, float $h, string $col): string
{
    $o = '';
    $dim = 'opacity=".9"';
    switch ($type) {
        case 'bars':
            $bw = $w / 7; for ($i = 0; $i < 5; $i++) { $bh = $h * (0.3 + 0.14 * (($i * 3) % 5)); $o .= '<rect x="' . ($x + 6 + $i * $bw) . '" y="' . ($y + $h - $bh) . '" width="' . ($bw - 5) . '" height="' . $bh . '" rx="2" fill="' . $col . '" ' . $dim . '/>'; }
            break;
        case 'chart':
            $pts = []; for ($i = 0; $i < 6; $i++) $pts[] = ($x + 6 + $i * ($w - 12) / 5) . ',' . ($y + $h - ($h * (0.3 + 0.5 * abs(sin($i * 1.1)))));
            $o .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $col . '" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>';
            $o .= '<circle cx="' . ($x + 6 + 3 * ($w - 12) / 5) . '" cy="' . ($y + $h - ($h * 0.72)) . '" r="3" fill="' . $col . '"/>';
            break;
        case 'donut':
            $cx = $x + $h / 2 + 4; $cy = $y + $h / 2; $r = $h * 0.32;
            $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $col . '" stroke-width="7" opacity=".25"/>';
            $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . $col . '" stroke-width="7" stroke-dasharray="' . (2 * M_PI * $r * 0.62) . ' ' . (2 * M_PI * $r) . '" stroke-linecap="round" transform="rotate(-90 ' . $cx . ' ' . $cy . ')"/>';
            for ($i = 0; $i < 3; $i++) $o .= '<rect x="' . ($cx + $r + 10) . '" y="' . ($cy - 10 + $i * 9) . '" width="' . ($w - $r - 40) . '" height="4" rx="2" fill="' . $col . '" opacity="' . (0.8 - $i * 0.2) . '"/>';
            break;
        case 'cal':
            $cw = $w / 5; $ch = $h / 3; for ($r = 0; $r < 3; $r++) for ($cI = 0; $cI < 5; $cI++) { $on = (($r * 5 + $cI) % 4 === 0); $o .= '<rect x="' . ($x + 4 + $cI * $cw) . '" y="' . ($y + 4 + $r * $ch) . '" width="' . ($cw - 5) . '" height="' . ($ch - 5) . '" rx="2.5" fill="' . $col . '" opacity="' . ($on ? '.95' : '.22') . '"/>'; }
            break;
        case 'chat':
            $o .= '<rect x="' . ($x + 6) . '" y="' . ($y + 6) . '" width="' . ($w * 0.6) . '" height="' . ($h * 0.26) . '" rx="6" fill="' . $col . '" opacity=".85"/>';
            $o .= '<rect x="' . ($x + $w * 0.3) . '" y="' . ($y + $h * 0.42) . '" width="' . ($w * 0.62) . '" height="' . ($h * 0.26) . '" rx="6" fill="' . $col . '" opacity=".45"/>';
            $o .= '<rect x="' . ($x + 6) . '" y="' . ($y + $h * 0.74) . '" width="' . ($w * 0.5) . '" height="' . ($h * 0.2) . '" rx="6" fill="' . $col . '" opacity=".7"/>';
            break;
        case 'nodes':
            $cx = $x + $w / 2; $cy = $y + $h / 2; $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="7" fill="' . $col . '"/>';
            foreach ([[-1, -1], [1, -1], [-1, 1], [1, 1]] as $d) { $nx = $cx + $d[0] * $w * 0.28; $ny = $cy + $d[1] * $h * 0.3; $o .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $nx . '" y2="' . $ny . '" stroke="' . $col . '" stroke-width="2" opacity=".6"/><circle cx="' . $nx . '" cy="' . $ny . '" r="5" fill="' . $col . '" opacity=".85"/>'; }
            break;
        case 'radar':
            $cx = $x + $w / 2; $cy = $y + $h / 2; for ($i = 1; $i <= 3; $i++) $o .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($i * $h * 0.16) . '" fill="none" stroke="' . $col . '" stroke-width="1.5" opacity=".5"/>';
            $o .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . ($cx + $h * 0.44) . '" y2="' . ($cy - $h * 0.2) . '" stroke="' . $col . '" stroke-width="2"/><circle cx="' . ($cx + $h * 0.3) . '" cy="' . ($cy - $h * 0.14) . '" r="3.5" fill="' . $col . '"/>';
            break;
        default: // list
            for ($i = 0; $i < 4; $i++) { $o .= '<circle cx="' . ($x + 10) . '" cy="' . ($y + 12 + $i * ($h - 10) / 4) . '" r="3.5" fill="' . $col . '" opacity=".8"/><rect x="' . ($x + 20) . '" y="' . ($y + 9 + $i * ($h - 10) / 4) . '" width="' . ($w - 30) . '" height="4.5" rx="2" fill="' . $col . '" opacity="' . (0.7 - $i * 0.12) . '"/>'; }
    }
    return $o;
}

/** أفاتار مبسّط على ثيم داكن (نص جسم). */
function ai_team_avatar(float $cx, float $cy, string $col): string
{
    $skin = '#f1c8a6';
    return '<g>'
        . '<path d="M' . ($cx - 26) . ' ' . ($cy + 34) . ' C' . ($cx - 28) . ' ' . ($cy + 2) . ' ' . ($cx - 16) . ' ' . ($cy - 8) . ' ' . $cx . ' ' . ($cy - 8) . ' C' . ($cx + 16) . ' ' . ($cy - 8) . ' ' . ($cx + 28) . ' ' . ($cy + 2) . ' ' . ($cx + 26) . ' ' . ($cy + 34) . ' Z" fill="' . $col . '" opacity=".85"/>'
        . '<circle cx="' . $cx . '" cy="' . ($cy - 24) . '" r="15" fill="' . $skin . '"/>'
        . '<path d="M' . ($cx - 16) . ' ' . ($cy - 26) . ' a16 16 0 0132 0 l-5 4 a11 9 0 00-22 0 z" fill="#2a2740"/>'
        . '<circle cx="' . ($cx - 5) . '" cy="' . ($cy - 23) . '" r="1.6" fill="#2b2f3a"/><circle cx="' . ($cx + 5) . '" cy="' . ($cy - 23) . '" r="1.6" fill="#2b2f3a"/>'
        . '<path d="M' . ($cx - 4) . ' ' . ($cy - 17) . ' q4 3 8 0" stroke="#b9876a" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
        . '</g>';
}

/**
 * يبني مشهد فريق الوكلاء (SVG داكن بـ 8 كروت).
 * $states: خريطة المفتاح => ['active'=>bool].
 */
function ai_team_svg(string $wsName, array $states): string
{
    $agents = ai_team_agents();
    $W = 1600; $H = 470;
    $pad = 34; $gap = 18; $cols = 4; $top = 70;
    $cw = ($W - 2 * $pad - ($cols - 1) * $gap) / $cols;
    $ch = (($H - $top - $pad) - $gap) / 2;
    $u = 'ait';

    // ثيم بهوية المنصة (لايت) مع دعم الدارك مود (html.dark)
    $css = '<style>'
        . '#'.$u.'{--bg:#ffffff;--card:#f8fafc;--scr:#eef2f8;--bd:#e2e8f0;--ink:#0f172a;--mut:#64748b}'
        . '#'.$u.' text{direction:ltr;unicode-bidi:isolate}'
        . 'html.dark #'.$u.'{--bg:#0f1729;--card:#14203a;--scr:#0b1324;--bd:#243354;--ink:#eaf0fb;--mut:#8fa0c0}'
        . '#'.$u.' .st .arm{transform-box:fill-box;transform-origin:50% 0}'
        . '#'.$u.' .st .scr-ill{opacity:0;transition:opacity .3s}'
        . '#'.$u.' .st .tint{opacity:0;transition:opacity .3s}'
        . '#'.$u.' .st .pod{transition:stroke-opacity .3s,stroke-width .2s}'
        . '#'.$u.' .st .led{opacity:.32;transition:opacity .3s;animation:'.$u.'idle 3.2s ease-in-out infinite}'
        . '#'.$u.' .st.on .led{animation:none}'
        . '@keyframes '.$u.'idle{0%,100%{opacity:.2}50%{opacity:.5}}'
        . '#'.$u.' .st .selbar{opacity:0;transition:opacity .3s}'
        . '#'.$u.' .st .wp{opacity:0}'
        . '#'.$u.' .st .halo{opacity:0;transition:opacity .3s}'
        . '#'.$u.' .st.on .pod{stroke-opacity:.95;stroke-width:2.4}'
        . '#'.$u.' .st.on .led{opacity:1}'
        . '#'.$u.' .st.on .tint{opacity:.10}'
        . '#'.$u.' .st.on .halo{opacity:.5}'
        . '#'.$u.' .st.on .selbar{opacity:1}'
        . '#'.$u.' .st.on .scr-ill{opacity:.16;animation:'.$u.'brz 1.8s ease-in-out infinite}'
        . '#'.$u.' .st.on .arm{animation:'.$u.'typ .5s ease-in-out infinite alternate}'
        . '#'.$u.' .st.on .wp{opacity:1;animation:'.$u.'pls 1.7s linear infinite}'
        . '#'.$u.' .livedot{animation:'.$u.'pls 1.6s linear infinite}'
        . '#'.$u.' .lnk{stroke-dasharray:3 8;animation:'.$u.'flow 1.1s linear infinite}'
        . '#'.$u.' .lnode{animation:'.$u.'pls 2s linear infinite}'
        . '@keyframes '.$u.'flow{to{stroke-dashoffset:-22}}'
        . '@keyframes '.$u.'typ{from{transform:translateY(0) rotate(-2deg)}to{transform:translateY(2px) rotate(3deg)}}'
        . '@keyframes '.$u.'brz{0%,100%{opacity:.06}50%{opacity:.18}}'
        . '@keyframes '.$u.'pls{0%{opacity:.2}50%{opacity:1}100%{opacity:.2}}'
        . '@media(prefers-reduced-motion:reduce){#'.$u.' .st.on .arm,#'.$u.' .st.on .scr-ill,#'.$u.' .st.on .wp{animation:none}}'
        . '</style>';

    // ===== خطوط نور تربط الكروت (زي الريفرانس) =====
    $pos = []; $ii = 0;
    foreach ($agents as $k => $a) { $pos[$ii] = ['x' => $pad + ($ii % $cols) * ($cw + $gap), 'y' => $top + intdiv($ii, $cols) * ($ch + $gap), 'c' => $a['c'], 'on' => !empty($states[$k]['active'])]; $ii++; }
    $lnkCol = '#94a3b8';
    $seg = function ($x1, $y1, $x2, $y2, $bright) use ($lnkCol) {
        $c = $bright ? '#3b6ff0' : $lnkCol; $op = $bright ? '.85' : '.4';
        return '<line class="lnk" x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="'.$c.'" stroke-width="'.($bright ? 2.4 : 1.8).'" stroke-opacity="'.$op.'" stroke-linecap="round"/>'
            . '<circle class="lnode" cx="'.$x1.'" cy="'.$y1.'" r="3" fill="'.$c.'" opacity="'.$op.'"/><circle class="lnode" cx="'.$x2.'" cy="'.$y2.'" r="3" fill="'.$c.'" opacity="'.$op.'"/>';
    };
    $links = '';
    // أفقي: بين الأعمدة في كل صف
    for ($r = 0; $r < 2; $r++) for ($c = 0; $c < $cols - 1; $c++) {
        $i1 = $r * $cols + $c; $i2 = $i1 + 1; if (!isset($pos[$i1], $pos[$i2])) continue;
        $y = $pos[$i1]['y'] + $ch / 2;
        $links .= $seg($pos[$i1]['x'] + $cw, $y, $pos[$i2]['x'], $y, $pos[$i1]['on'] && $pos[$i2]['on']);
    }
    // رأسي: بين الصفين تحت كل عمود
    for ($c = 0; $c < $cols; $c++) {
        $i1 = $c; $i2 = $cols + $c; if (!isset($pos[$i1], $pos[$i2])) continue;
        $x = $pos[$i1]['x'] + $cw / 2;
        $links .= $seg($x, $pos[$i1]['y'] + $ch, $x, $pos[$i2]['y'], $pos[$i1]['on'] && $pos[$i2]['on']);
    }
    // من الهيدر لأعلى الصف الأول (عمودين في النص)
    $hubX = $W / 2; $hubY = 60;
    foreach ([1, 2] as $c) { if (!isset($pos[$c])) continue; $tx = $pos[$c]['x'] + $cw / 2; $links .= $seg($hubX, $hubY, $tx, $pos[$c]['y'], false); }

    $cards = '';
    $i = 0;
    foreach ($agents as $k => $a) {
        $col = $a['c']; $on = !empty($states[$k]['active']);
        $cx = $pad + ($i % $cols) * ($cw + $gap);
        $cy = $top + intdiv($i, $cols) * ($ch + $gap);
        $i++;
        $card = '<g class="st'.($on ? ' on' : '').'" data-k="'.$k.'">';
        $card .= '<rect class="halo" x="'.($cx - 3).'" y="'.($cy - 3).'" width="'.($cw + 6).'" height="'.($ch + 6).'" rx="18" fill="none" stroke="'.$col.'" stroke-opacity=".45" stroke-width="3"/>';
        $card .= '<rect x="'.$cx.'" y="'.$cy.'" width="'.$cw.'" height="'.$ch.'" rx="15" fill="var(--card)"/>';
        $card .= '<rect class="tint" x="'.$cx.'" y="'.$cy.'" width="'.$cw.'" height="'.$ch.'" rx="15" fill="'.$col.'"/>';
        $card .= '<rect class="pod" x="'.$cx.'" y="'.$cy.'" width="'.$cw.'" height="'.$ch.'" rx="15" fill="none" stroke="'.$col.'" stroke-opacity="0.22" stroke-width="1.6"/>';
        // الهيدر: اسم (يمين) + نقطة
        $card .= '<circle class="led" cx="'.($cx + $cw - 22).'" cy="'.($cy + 23).'" r="5" fill="'.$col.'"/>';
        $card .= '<text x="'.($cx + $cw - 34).'" y="'.($cy + 28).'" text-anchor="end" font-size="15" font-weight="800" fill="var(--ink)">'.e($a['ar']).'</text>';
        $card .= '<circle class="wp" cx="'.($cx + 22).'" cy="'.($cy + 23).'" r="4" fill="'.$col.'"/>';
        // الأفاتار (شمال) + ذراع الكتابة
        $avX = $cx + 52; $avY = $cy + 66;
        $card .= ai_team_avatar($avX, $avY, $col);
        $card .= '<rect class="arm" x="'.($avX + 14).'" y="'.($avY + 6).'" width="22" height="9" rx="4" fill="'.$col.'" opacity=".85"/>';
        // الشاشة (يمين الأفاتار)
        $scX = $cx + 96; $scW = $cw - 116; $scH = $ch - 116; $scY = $cy + 52;
        $card .= '<rect x="'.$scX.'" y="'.$scY.'" width="'.$scW.'" height="'.$scH.'" rx="8" fill="var(--scr)" stroke="'.$col.'" stroke-opacity=".3" stroke-width="1.4"/>';
        $card .= '<rect class="scr-ill" x="'.$scX.'" y="'.$scY.'" width="'.$scW.'" height="'.$scH.'" rx="8" fill="'.$col.'"/>';
        $card .= '<g opacity=".5"><circle cx="'.($scX + 10).'" cy="'.($scY + 10).'" r="2.2" fill="'.$col.'"/><circle cx="'.($scX + 18).'" cy="'.($scY + 10).'" r="2.2" fill="#94a3b8"/><circle cx="'.($scX + 26).'" cy="'.($scY + 10).'" r="2.2" fill="#94a3b8"/></g>';
        $card .= ai_team_screen($a['screen'], $scX + 8, $scY + 22, $scW - 16, $scH - 32, $col);
        // المكتب
        $card .= '<rect x="'.($cx + 14).'" y="'.($cy + $ch - 52).'" width="'.($cw - 28).'" height="13" rx="4" fill="'.$col.'" opacity=".16"/>';
        // شريط التحديد
        $card .= '<rect class="selbar" x="'.($cx + 16).'" y="'.($cy + $ch - 4).'" width="'.($cw - 32).'" height="3" rx="2" fill="'.$col.'"/>';
        // الفوتر
        $fY = $cy + $ch - 30;
        $card .= '<g transform="translate('.($cx + $cw - 38).','.($fY - 2).') scale(0.78)">'.ai_team_icon($a['icon'], $col).'</g>';
        $card .= '<text x="'.($cx + $cw - 48).'" y="'.($fY + 4).'" text-anchor="end" font-size="12.5" font-weight="800" fill="var(--ink)">'.e($a['ar']).'</text>';
        $card .= '<text x="'.($cx + $cw - 48).'" y="'.($fY + 19).'" text-anchor="end" font-size="10.5" fill="var(--mut)">'.e($a['sub']).'</text>';
        $card .= '</g>';
        $cards .= $card;
    }

    $hw = 500; $hx = ($W - $hw) / 2;
    $header = '<g>'
        . '<rect x="'.$hx.'" y="16" width="'.$hw.'" height="44" rx="13" fill="var(--card)" stroke="var(--bd)"/>'
        . '<circle cx="'.($hx + $hw - 26).'" cy="38" r="13" fill="#3b6ff0"/><path d="M'.($hx + $hw - 26).' 31v14M'.($hx + $hw - 33).' 38h14" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>'
        . '<text x="'.($hx + $hw - 48).'" y="35" text-anchor="end" font-size="15" font-weight="800" fill="var(--ink)">'.e(t('فريق %s', $wsName)).'</text>'
        . '<text x="'.($hx + $hw - 48).'" y="52" text-anchor="end" font-size="11" fill="var(--mut)">'.e(t('مساحة واحدة · ٨ وكلاء متصلين')).'</text>'
        . '<circle class="livedot" cx="'.($hx + 20).'" cy="38" r="5" fill="#16a34a"/>'
        . '<text x="'.($hx + 32).'" y="42" font-size="11.5" font-weight="800" fill="#16a34a">'.e(t('النظام مباشر')).'</text>'
        . '</g>';

    return '<div id="'.$u.'">'.$css
        . '<svg viewBox="0 0 '.$W.' '.$H.'" width="100%" style="display:block;font-family:Alexandria,system-ui,sans-serif" role="img" aria-label="'.e(t('فريق الوكلاء الأذكياء')).'">'
        . '<rect width="'.$W.'" height="'.$H.'" rx="20" fill="var(--bg)"/>'
        . $links . $header . $cards
        . '</svg></div>';
}
