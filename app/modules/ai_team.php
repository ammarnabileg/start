<?php
/**
 * فريق الوكلاء على الصفحة الرئيسية: 8 وكلاء، كل وكيل = فانكشن.
 * المشهد = رسمة الوكلاء (assets/workspace_signal.png) فوقها طبقة SVG لشبكة الإشارات المتحرّكة،
 * والقسم بيشتغل (إشاراته بتتحرّك + كارت الوكيل بينوّر) لما تكون موجود على صفحته أو بتستخدمها فعليًا.
 */

/** مفاتيح الوكلاء الثمانية (مطابقة لمجموعات agent-<key> في الـ SVG). */
function ai_team_keys(): array
{
    return ['content', 'publishing', 'ads', 'analytics', 'tenders', 'crm', 'automation', 'communications'];
}

/** بادئات أحداث activity_log لكل وكيل. */
function ai_team_action_map(): array
{
    return [
        'content'        => ['post.create', 'post.update', 'post.duplicate', 'post.create_pro', 'ai.image', 'designer', 'studio', 'content', 'cpl'],
        'publishing'     => ['queue', 'post.retry', 'post.schedule', 'calendar'],
        'ads'            => ['ads', 'ad_builder', 'ad.', 'pixel'],
        'analytics'      => ['analytics', 'reports', 'goals', 'alerts'],
        'tenders'        => ['tender'],
        'crm'            => ['contact', 'lead', 'crm', 'deal', 'prospect'],
        'automation'     => ['automation', 'workflow'],
        'communications' => ['inbox', 'comment', 'review', 'ai.auto_reply', 'broadcast', 'sequence', 'wa_', 'campaign', 'call', 'sms', 'phone'],
    ];
}

/** خريطة صفحات المنصة → الوكيل المسؤول. */
function ai_team_page_map(): array
{
    return [
        'content_plan' => 'content', 'content_bank' => 'content', 'designer' => 'content',
        'posts' => 'publishing', 'calendar' => 'publishing', 'queue' => 'publishing', 'compose' => 'publishing', 'post_log' => 'publishing', 'approvals' => 'publishing',
        'ad_builder' => 'ads', 'ads' => 'ads', 'ad_campaign' => 'ads', 'audiences' => 'ads',
        'analytics' => 'analytics', 'inbox_analytics' => 'analytics', 'reports' => 'analytics', 'report_print' => 'analytics', 'goals' => 'analytics',
        'tenders' => 'tenders', 'tender_awards' => 'tenders',
        'crm' => 'crm', 'crm_leads' => 'crm', 'prospects' => 'crm', 'crm_find' => 'crm', 'contacts' => 'crm', 'crm_reports' => 'crm',
        'workflows' => 'automation', 'automation' => 'automation', 'auto_replies' => 'automation',
        'inbox' => 'communications', 'messages' => 'communications', 'comments' => 'communications', 'broadcasts' => 'communications', 'sequences' => 'communications', 'wa_templates' => 'communications', 'calls' => 'communications', 'sms' => 'communications',
    ];
}

/** مسار ملف «آخر زيارة» لكل وكيل في شركة. */
function ai_team_seen_file(int $wid): string
{
    $dir = ROOT_DIR . '/uploads/cache'; @mkdir($dir, 0750, true);
    return $dir . '/aiteam_seen_' . $wid . '.json';
}

/** تحديث وقت «آخر شغل» لوكيل بمفتاحه مباشرة. */
function ai_team_mark(int $wid, string $key): void
{
    if (!in_array($key, ai_team_keys(), true)) return;
    $f = ai_team_seen_file($wid);
    $seen = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $now = time();
    if (isset($seen[$key]) && $now - (int)$seen[$key] < 10) return;
    $seen[$key] = $now;
    @file_put_contents($f, json_encode($seen), LOCK_EX);
}

/** تسجيل دخول/وجود على صفحة: بيشعّل وكيل الصفحة (أي عضو في الفريق). */
function ai_team_touch(int $wid, string $page): void
{
    $k = ai_team_page_map()[$page] ?? null;
    if ($k !== null) ai_team_mark($wid, $k);
}

/** ربط أدوات الـMCP بوكلائها (شغل المساعد الخارجي بيشعّل القسم المناسب). */
function ai_team_mcp_tool_map(): array
{
    return [
        'list_content_bank' => 'content', 'list_content_plans' => 'content', 'create_content_plan' => 'content', 'delete_content_plan' => 'content', 'get_company_brief' => 'content', 'list_media' => 'content',
        'list_posts' => 'publishing', 'schedule_post' => 'publishing', 'publish_content_bank_item' => 'publishing', 'delete_post' => 'publishing', 'list_social_accounts' => 'publishing', 'schedule_content_bank' => 'publishing',
        'launch_ad_campaign' => 'ads', 'list_ad_campaigns' => 'ads', 'create_ad_campaign' => 'ads', 'auto_launch_ad_campaign' => 'ads',
        'analytics_summary' => 'analytics', 'ad_performance' => 'analytics',
    ];
}

/** حالة كل وكيل: active فقط وقت الوجود على صفحته أو استخدامه فعليًا للتوّ. */
function ai_team_states(int $wid): array
{
    $states = [];
    foreach (ai_team_keys() as $k) $states[$k] = ['active' => false];
    $now = time();
    // 1) حضور فعلي على صفحة الفانكشن (آخر ~45 ثانية، بتتجدّد بنبضة كل 15ث)
    $f = ai_team_seen_file($wid);
    if (is_file($f)) {
        $seen = json_decode((string)@file_get_contents($f), true) ?: [];
        foreach ($seen as $sk => $ts) {
            if (isset($states[$sk]) && $now - (int)$ts <= 45) $states[$sk]['active'] = true;
        }
    }
    // 2) استخدام فعلي للتوّ (حدث في activity_log آخر ~دقيقة ونص)
    $map = ai_team_action_map();
    try {
        $st = db()->prepare("SELECT action, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) AS ago FROM activity_log WHERE workspace_id = ? AND created_at > NOW() - INTERVAL 5 MINUTE GROUP BY action");
        $st->execute([$wid]);
        foreach ($st->fetchAll() as $r) {
            $act = (string)$r['action']; $ago = (int)$r['ago'];
            if ($ago > 90) continue;
            foreach ($map as $k => $prefixes) {
                foreach ($prefixes as $p) { if (strpos($act, $p) === 0) { $states[$k]['active'] = true; break 2; } }
            }
        }
    } catch (Throwable $t) {
    }
    return $states;
}

/** عناوين كل وكيل بالعربي (للتعريب داخل الـ SVG). */
function ai_team_labels_ar(): array
{
    return [
        'AI OPERATIONS TEAM' => 'فريق عمليات الذكاء', '8 MODULES / 1 WORKSPACE' => '٨ أقسام / مساحة واحدة',
        'AI WORKSPACE' => 'منصّة الذكاء', 'Eight agents. One engine.' => 'ثمانية وكلاء · محرّك واحد',
        'CONNECTED SYSTEM' => 'نظام متصل', 'AGENTS' => 'وكلاء',
        'LIVE' => 'يعمل', 'READY' => 'جاهز',
        'CREATIVE' => 'إبداع', 'DELIVERY' => 'تسليم', 'GROWTH' => 'نمو', 'INSIGHTS' => 'رؤى',
        'OPPORTUNITY' => 'فرص', 'REVENUE' => 'إيرادات', 'SYSTEMS' => 'أنظمة', 'ENGAGEMENT' => 'تفاعل',
        'CONTENT STUDIO' => 'المحتوى', 'WRITE · DESIGN' => 'كتابة · تصميم',
        'PUBLISHING' => 'النشر', 'QUEUE · SCHEDULE' => 'طابور · جدولة',
        'AD CAMPAIGNS' => 'الإعلانات', 'PAID · CREATIVE' => 'مموّل · إبداع',
        'ANALYTICS' => 'التحليلات', 'PERFORMANCE · DATA' => 'أداء · بيانات',
        'TENDER INTELLIGENCE' => 'المناقصات', 'PROJECTS · AWARDS' => 'مشاريع · ترسيات',
        'CRM &amp; LEADS' => 'العملاء', 'PIPELINE · CLIENTS' => 'مسار · عملاء',
        'AUTOMATION' => 'الأتمتة', 'WORKFLOWS · AGENTS' => 'مهام · وكلاء',
        'COMMUNICATIONS' => 'التواصل', 'INBOX · MESSAGING' => 'رسائل · محادثات',
        'INTERACTIVE WORKSPACE VISUALIZATION' => 'عرض تفاعلي للمنصّة',
    ];
}

function ai_team_apply_active(string $svg, string $tagRegex, bool $on): string
{
    return preg_replace_callback($tagRegex, function ($m) use ($on) {
        $tag = $m[0];
        if (!preg_match('/class="([^"]*)"/', $tag, $cm)) return $tag;
        $cls = trim(preg_replace('/\s*\bactive\b/', '', $cm[1]));
        if ($on) $cls = trim($cls . ' active');
        return str_replace('class="' . $cm[1] . '"', 'class="' . $cls . '"', $tag);
    }, $svg, 1);
}

/**
 * يبني مشهد فريق الوكلاء: يحمّل الـ SVG، يعرّب العناوين (وقت العربي)،
 * ويضبط كلاس active لكل وكيل حسب الحالة الحقيقية.
 */
/** أسماء الوكلاء القصيرة للطبقة العلوية. */
function ai_team_overlay_names(): array
{
    $ar = function_exists('lang') && lang() === 'ar';
    return $ar ? [
        'content' => 'المحتوى', 'publishing' => 'النشر', 'ads' => 'الإعلانات', 'analytics' => 'التحليلات',
        'tenders' => 'المناقصات', 'crm' => 'العملاء', 'automation' => 'الأتمتة', 'communications' => 'التواصل',
    ] : [
        'content' => 'Content', 'publishing' => 'Publishing', 'ads' => 'Ads', 'analytics' => 'Analytics',
        'tenders' => 'Tenders', 'crm' => 'CRM', 'automation' => 'Automation', 'communications' => 'Comms',
    ];
}

function ai_team_svg(string $wsName, array $states): string
{
    $file = ROOT_DIR . '/assets/workspace_signal.png';
    if (!is_file($file)) return '';
    $ver = defined('APP_VERSION') ? APP_VERSION : (string)@filemtime($file);
    $ar = function_exists('lang') && lang() === 'ar';

    // ألوان كل وكيل (نفس هوية التصميم)
    $colors = [
        'content' => '#AA85FF', 'publishing' => '#48E5F0', 'ads' => '#FE9B67', 'analytics' => '#68ADFF',
        'tenders' => '#FFC36A', 'crm' => '#54DFB8', 'automation' => '#9E91FF', 'communications' => '#FF88C5',
    ];
    // المسارات الفرعية لكل وكيل (branch)
    $branches = [
        'content' => 'M190 482 C190 530 195 568 230 568', 'publishing' => 'M403 482 C403 530 418 568 454 568',
        'ads' => 'M653 482 C653 531 672 568 701 568', 'analytics' => 'M916 482 C916 530 954 555 983 594',
        'tenders' => 'M1143 482 C1143 530 1173 551 1189 594', 'crm' => 'M1403 482 C1403 534 1390 568 1364 568',
        'automation' => 'M1667 482 C1667 527 1653 568 1625 568', 'communications' => 'M1893 482 C1893 525 1880 568 1850 568',
    ];
    $bridges = [
        ['content publishing', '#AA85FF', 'M264 420 H324'], ['publishing ads', '#48E5F0', 'M510 420 H569'],
        ['ads analytics', '#FE9B67', 'M755 420 H814'], ['analytics tenders', '#68ADFF', 'M996 420 H1056'],
        ['tenders crm', '#FFC36A', 'M1245 420 H1306'], ['crm automation', '#54DFB8', 'M1516 420 H1575'],
        ['automation communications', '#9E91FF', 'M1765 420 H1817'],
    ];
    $buses = [
        'M230 568 H932 C950 568 954 601 988 601 H1025', 'M1134 601 H1160 C1197 601 1203 568 1230 568 H1850',
    ];
    $wire = function (string $cls, string $id, string $roles, string $col, string $d, string $route, string $dur): string {
        return '<g class="wire ' . $cls . '" id="' . $id . '" data-roles="' . $roles . '" style="--wire:' . $col . '">'
            . '<path class="wire-guide" d="' . $d . '"/><path class="wire-glow" d="' . $d . '"/>'
            . '<path class="wire-run" d="' . $d . '"/><path class="wire-curve" d="' . $d . '" data-route="' . $route . '" data-duration="' . $dur . '"/></g>';
    };
    $net = '';
    foreach ($branches as $k => $d) $net .= $wire('branch', 'wire-' . $k, $k, $colors[$k], $d, 'branch', '1.55');
    foreach ($bridges as $i => $b) $net .= $wire('bridge', 'bridge-' . $i, $b[0], $b[1], $b[2], 'bridge', '1.4');
    foreach ($buses as $i => $d) $net .= $wire('backbone', 'bus-' . $i, 'all', '#8FEFFF', $d, 'bus', '6.9');

    $svg = '<svg id="teamArt" xmlns="http://www.w3.org/2000/svg" width="2172" height="535" viewBox="0 122 2172 535" preserveAspectRatio="xMidYMid meet" role="img" aria-label="AI workforce">'
        . '<defs><filter id="litGlow" x="-250%" y="-250%" width="600%" height="600%"><feGaussianBlur stdDeviation="3" result="soft"/><feMerge><feMergeNode in="soft"/><feMergeNode in="SourceGraphic"/></feMerge></filter>'
        . '<filter id="heavyGlow" x="-300%" y="-300%" width="700%" height="700%"><feGaussianBlur stdDeviation="7"/></filter></defs>'
        . '<image x="0" y="0" width="2172" height="724" href="assets/workspace_signal.png?v=' . e($ver) . '"/>'
        . '<g id="wireNetwork" pointer-events="none">' . $net . '</g>'
        . '<g id="movingSignals" pointer-events="none"></g><g id="agentClickRegions" aria-hidden="true"></g></svg>';

    // الحالة المبدئية (أي وكيل شغّال دلوقتي حسب النشاط الحقيقي)
    $init = [];
    foreach (array_keys($colors) as $k) $init[$k] = !empty($states[$k]['active']);
    $initJson = json_encode($init);

    // لوحة تحكّم/مفتاح الوكلاء (نفس هوية التصميم، ثنائية اللغة)
    $labels = [
        'content' => ['المحتوى', 'Content Studio'], 'publishing' => ['الجدولة', 'Publishing'],
        'ads' => ['الإعلانات', 'Ads Manager'], 'analytics' => ['التحليلات', 'Analytics'],
        'tenders' => ['المناقصات', 'Tender Intel'], 'crm' => ['العملاء', 'CRM & Leads'],
        'automation' => ['الأتمتة', 'Automation'], 'communications' => ['التواصل', 'Communications'],
    ];
    $roles = '';
    foreach ($labels as $k => $lab) {
        $on = !empty($states[$k]['active']);
        $roles .= '<button class="role' . ($on ? ' on' : '') . '" data-role="' . $k . '" type="button" aria-pressed="' . ($on ? 'true' : 'false') . '" style="--accent:' . $colors[$k] . '">'
            . '<span class="role-dot"></span><span><b>' . e($lab[0]) . '</b><small>' . e($lab[1]) . '</small></span><span class="role-light"></span></button>';
    }
    $liveLbl = $ar ? 'شبكة الوكلاء شغّالة' : 'SIGNAL FABRIC LIVE';
    $capTxt = $ar ? '٨ وظائف متصلة · الإشارات بتتحرّك لحظيًا مع كل شغل حقيقي على القسم' : '8 connected functions · signals move live with real activity';
    $rolesHead = $ar ? 'نشاط كل قسم لحظيًا' : 'Live activity per function';
    $rolesSub = $ar ? 'القسم بينوّر وإشاراته بتتحرّك بمجرد ما يشتغل عليه أي عضو أو الذكاء الاصطناعي' : 'A function lights up the moment anyone — or the AI — works on it';
    $n0 = count(array_filter($init));

    $css = <<<CSS
<style>
#ai-workforce{--awp:#fff;--awb:#D5E2F0;--awl:#F8FBFF;--awm:#647795;--awink:#152844;direction:ltr;position:relative}
html.dark #ai-workforce{--awp:#111D34;--awb:#283957;--awl:#12253D;--awm:#91A2C0;--awink:#EEF4FF}
#ai-workforce .aw-frame{border:1px solid var(--awb);border-radius:23px;background:linear-gradient(180deg,var(--awl),transparent 90%);padding:12px 15px 7px;position:relative;overflow:hidden}
#ai-workforce .aw-top{position:absolute;z-index:3;inset-inline-start:22px;top:17px;display:flex;gap:8px;align-items:center;background:var(--awp);border:1px solid var(--awb);border-radius:999px;padding:7px 11px;font-size:10px;font-weight:760;direction:ltr;color:var(--awm)}
#ai-workforce .aw-ind{width:7px;height:7px;border-radius:50%;background:#48E7C6;box-shadow:0 0 14px #48E7C688}
#ai-workforce .aw-ind.off{background:#F6B45C;box-shadow:0 0 14px #F6B45C66}
#ai-workforce .aw-visual{position:relative;overflow:hidden;width:100%;padding:9px 0 0;border-radius:18px;background:radial-gradient(ellipse 72% 62% at 50% 44%,rgba(36,70,125,.10),transparent 72%)}
html.dark #ai-workforce .aw-visual{background:radial-gradient(ellipse 72% 62% at 50% 44%,rgba(36,70,125,.34),transparent 74%)}
#ai-workforce .aw-visual svg{width:100%;height:auto;display:block;overflow:visible;pointer-events:none}
#ai-workforce .aw-visual svg .agentHotspot{pointer-events:all;cursor:pointer}
#ai-workforce .aw-visual svg .agentHotspot:focus{outline:none}
#ai-workforce .aw-visual svg .agentHotspot:focus-visible{stroke:#fff;stroke-width:3;stroke-opacity:.8}
#ai-workforce .wire{--wire:#83E8FB;opacity:.16;transition:opacity .3s;pointer-events:none}
#ai-workforce .wire.on{opacity:1}
#ai-workforce .wire-guide{fill:none;stroke:var(--wire);stroke-width:7;stroke-opacity:.08;stroke-linecap:round}
#ai-workforce .wire-glow{fill:none;stroke:var(--wire);stroke-opacity:.5;stroke-width:5;stroke-linecap:round;filter:url(#litGlow)}
#ai-workforce .wire-run{fill:none;stroke:#E7FFFF;stroke-width:2.5;stroke-linecap:round;stroke-dasharray:3 28;animation:awDash 1.75s linear infinite;opacity:.1}
html.dark #ai-workforce .wire-run{stroke:#E7FFFF}
#ai-workforce .wire.on .wire-run{opacity:.9}
#ai-workforce .wire-curve{fill:none;stroke:none}
@keyframes awDash{to{stroke-dashoffset:-124}}
#ai-workforce.paused .wire-run{animation-play-state:paused}
#ai-workforce .packet{pointer-events:none;opacity:0;transition:opacity .25s}
#ai-workforce .packet.on{opacity:1}
#ai-workforce .packet circle{fill:#F8FFFF}
#ai-workforce .packet .halo{fill:var(--col);filter:url(#heavyGlow);opacity:.7}
#ai-workforce .packet .tail{fill:var(--col);opacity:.6}
#ai-workforce .packet .spark{filter:url(#litGlow)}
#ai-workforce .aw-cap{display:flex;align-items:center;justify-content:space-between;gap:12px;color:var(--awm);font-size:11px;padding:6px 11px 12px;flex-wrap:wrap}
#ai-workforce .aw-cap .mark{color:#52C5D0;font-weight:750}
#ai-workforce .aw-rh{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;margin:16px 2px 11px;gap:10px}
#ai-workforce .aw-rh b{font-size:14px;color:var(--awink)}
#ai-workforce .aw-rh small{font-size:11px;color:var(--awm)}
#ai-workforce .roles{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:9px}
#ai-workforce .role{display:flex;align-items:center;gap:9px;text-align:start;background:var(--awp);border:1px solid var(--awb);border-radius:14px;padding:10px 11px;cursor:pointer;transition:border-color .25s,box-shadow .25s,transform .15s;font-family:inherit}
#ai-workforce .role:hover{transform:translateY(-1px)}
#ai-workforce .role .role-dot{width:11px;height:11px;border-radius:50%;background:var(--accent);opacity:.45;flex:none;transition:opacity .25s,box-shadow .25s}
#ai-workforce .role b{display:block;font-size:12.5px;color:var(--awink);font-weight:800;line-height:1.3}
#ai-workforce .role small{display:block;font-size:9.5px;color:var(--awm);letter-spacing:.3px}
#ai-workforce .role .role-light{width:7px;height:7px;border-radius:50%;background:var(--awb);margin-inline-start:auto;flex:none;transition:background .25s,box-shadow .25s}
#ai-workforce .role.on{border-color:var(--accent);box-shadow:0 6px 20px -8px var(--accent)}
#ai-workforce .role.on .role-dot{opacity:1;box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 22%,transparent)}
#ai-workforce .role.on .role-light{background:var(--accent);box-shadow:0 0 10px var(--accent);animation:awBlink 1.4s infinite}
@keyframes awBlink{0%,100%{opacity:1}50%{opacity:.35}}
@media(max-width:1190px){#ai-workforce .roles{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:780px){#ai-workforce .aw-frame{padding:10px 5px}#ai-workforce .aw-visual{overflow:auto}#ai-workforce .aw-visual svg{min-width:950px}#ai-workforce .aw-top{inset-inline-start:8px;top:8px}#ai-workforce .roles{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(prefers-reduced-motion:reduce){#ai-workforce *,#ai-workforce *::before,#ai-workforce *::after{animation:none!important}}
</style>
CSS;

    $js = <<<JS
<script>
(function(){
  if(window.__awInit)return; window.__awInit=true;
  var roleKeys=['content','publishing','ads','analytics','tenders','crm','automation','communications'];
  var ns='http://www.w3.org/2000/svg';
  var root=document.getElementById('ai-workforce'); if(!root)return;
  var svg=root.querySelector('#teamArt');
  var wireEls=[].slice.call(svg.querySelectorAll('.wire'));
  var layers=svg.querySelector('#movingSignals');
  var roleBtns=[].slice.call(root.querySelectorAll('.role'));
  var ind=root.querySelector('#aw-ind'), cnt=root.querySelector('#aw-cnt');
  var active=new Set(); var INIT=$initJson;
  roleKeys.forEach(function(k){ if(INIT[k])active.add(k); });
  var paused=false, speed=1.1, lastTime=0, elapsed=0, packets=[];
  function el(name,props){var e=document.createElementNS(ns,name);props=props||{};for(var k in props)e.setAttribute(k,String(props[k]));return e;}
  function setupPackets(){
    wireEls.forEach(function(wire){
      var path=wire.querySelector('.wire-curve'); var route=path.getAttribute('data-route');
      var count=route==='bus'?4:1;
      var g=el('g',{'class':'packet','style':'--col:'+getComputedStyle(wire).getPropertyValue('--wire')});
      var units=[];
      for(var i=0;i<count;i++){
        var unit=el('g');
        unit.appendChild(el('circle',{r:route==='bus'?12:10,'class':'halo'}));
        unit.appendChild(el('circle',{r:5.4,'class':'tail'}));
        unit.appendChild(el('circle',{r:3.15,'class':'spark'}));
        unit.appendChild(el('circle',{r:1.5,fill:'#FFF'}));
        g.appendChild(unit); units.push({unit:unit,offset:i/count});
      }
      layers.appendChild(g);
      packets.push({wire:wire,path:path,g:g,units:units,length:path.getTotalLength(),duration:parseFloat(path.getAttribute('data-duration'))});
    });
  }
  function wireIsActive(wire){var keys=wire.getAttribute('data-roles').split(' ');return keys[0]==='all'?active.size>0:keys.some(function(k){return active.has(k);});}
  function updateUI(){
    roleBtns.forEach(function(b){var on=active.has(b.getAttribute('data-role'));b.classList.toggle('on',on);b.setAttribute('aria-pressed',on?'true':'false');});
    wireEls.forEach(function(wire){wire.classList.toggle('on',wireIsActive(wire));});
    packets.forEach(function(p){p.g.classList.toggle('on',wireIsActive(p.wire));});
    if(cnt)cnt.textContent=active.size+' / 8';
    if(ind)ind.classList.toggle('off',!active.size);
  }
  function setActive(role,on){if(roleKeys.indexOf(role)<0)return;if(on)active.add(role);else active.delete(role);updateUI();}
  function setStates(states){active.clear();roleKeys.forEach(function(role){var v=states&&states[role];if(v===true||v===1||(v&&v.active))active.add(role);});updateUI();}
  function animate(now){
    if(!lastTime)lastTime=now;
    var delta=Math.min((now-lastTime)/1000,.045);lastTime=now;
    if(!paused&&!document.hidden){elapsed+=delta*speed;
      packets.forEach(function(p){if(!wireIsActive(p.wire))return;
        p.units.forEach(function(u){var fraction=((elapsed/p.duration)+u.offset)%1;var pt=p.path.getPointAtLength(p.length*fraction);u.unit.setAttribute('transform','translate('+pt.x.toFixed(2)+' '+pt.y.toFixed(2)+')');});
      });
    }
    requestAnimationFrame(animate);
  }
  function addClickRegions(){
    var xs=[155,413,663,916,1149,1412,1661,1911];
    var regions=svg.querySelector('#agentClickRegions');
    xs.forEach(function(x,i){
      var role=roleKeys[i];
      var rect=el('rect',{x:x-110,y:230,width:220,height:256,rx:22,fill:'transparent','class':'agentHotspot',tabindex:0,role:'button','aria-label':role});
      rect.addEventListener('click',function(){setActive(role,!active.has(role));});
      rect.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();setActive(role,!active.has(role));}});
      regions.appendChild(rect);
    });
  }
  roleBtns.forEach(function(btn){btn.addEventListener('click',function(){setActive(btn.getAttribute('data-role'),!active.has(btn.getAttribute('data-role')));});});
  window.AIWorkforceSignals={setActive:setActive,setStates:setStates,getState:function(){return [].slice.call(active);}};
  setupPackets();addClickRegions();updateUI();
  if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches){paused=true;root.classList.add('paused');}
  requestAnimationFrame(animate);
})();
</script>
JS;

    return '<div id="ai-workforce">' . $css
        . '<div class="aw-frame"><div class="aw-top"><span class="aw-ind" id="aw-ind"></span><span>' . e($liveLbl) . '</span><span>·</span><strong id="aw-cnt">' . $n0 . ' / 8</strong></div>'
        . '<div class="aw-visual">' . $svg . '</div>'
        . '<div class="aw-cap"><span>◉ ' . e($capTxt) . '</span><span class="mark">LIVE SIGNAL LAYER</span></div></div>'
        . '<div class="aw-rh"><b>' . e($rolesHead) . '</b><small>' . e($rolesSub) . '</small></div>'
        . '<div class="roles" id="roles">' . $roles . '</div>'
        . $js . '</div>';
}
