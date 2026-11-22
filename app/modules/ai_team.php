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

    // ألوان كل وكيل (من تصميم الروبوتات الجديد — تُستخدم في شارة كل قسم)
    $colors = [
        'content' => '#B89AFF', 'publishing' => '#6CE8FF', 'ads' => '#AB86FF', 'analytics' => '#63E4F3',
        'tenders' => '#BDA0FF', 'crm' => '#73ECD5', 'automation' => '#63D8FF', 'communications' => '#C9A3FF',
    ];

    // مشهد الوكلاء المتحرّك (الرسمة + طبقة الإشارات SMIL + محرّكها) — نفس تصميم الديمو «multi»،
    // بنحمّله ونربطه بالحالة الحقيقية، والصورة بتُحمَّل كأصل خارجي.
    $sceneFile = ROOT_DIR . '/assets/ai_team_scene.html';
    if (!is_file($sceneFile)) return '';
    $svg = str_replace('__VER__', e($ver), (string)file_get_contents($sceneFile));

    // الحالة المبدئية (أي وكيل شغّال دلوقتي حسب النشاط الحقيقي)
    $init = [];
    foreach (array_keys($colors) as $k) $init[$k] = !empty($states[$k]['active']);
    $initJson = json_encode($init);

    // أسماء الأقسام (ثنائية اللغة) لشارة كل روبوت
    $labels = [
        'content' => ['المحتوى', 'Content'], 'publishing' => ['الجدولة', 'Publishing'],
        'ads' => ['الإعلانات', 'Ads'], 'analytics' => ['التحليلات', 'Analytics'],
        'tenders' => ['المناقصات', 'Tenders'], 'crm' => ['العملاء', 'CRM'],
        'automation' => ['الأتمتة', 'Automation'], 'communications' => ['التواصل', 'Comms'],
    ];
    // مراكز كل محطة فوق الرسمة (مطابقة لمواضع الروبوتات ÷ عرض الرسمة 2048)
    $centers = [152, 400, 648, 903, 1152, 1401, 1653, 1900];
    $order = array_keys($labels);
    $live = $ar ? 'يعمل' : 'LIVE'; $ready = $ar ? 'جاهز' : 'READY';
    $tags = '';
    foreach ($order as $i => $k) {
        $lab = $labels[$k];
        $on = !empty($states[$k]['active']);
        $left = round($centers[$i] / 2048 * 100, 3);
        $nm = $ar ? $lab[0] : $lab[1];
        $tags .= '<button class="aw-tag' . ($on ? ' on' : '') . '" data-role="' . $k . '" type="button" aria-pressed="' . ($on ? 'true' : 'false') . '" style="--accent:' . $colors[$k] . ';left:' . $left . '%">'
            . '<span class="aw-tdot"></span><b>' . e($nm) . '</b><span class="aw-tst">' . e($on ? $live : $ready) . '</span></button>';
    }
    $capTxt = $ar ? '٨ وكلاء ذكاء اصطناعي متصلين · الإشارات بتتحرّك لحظيًا مع كل شغل حقيقي على القسم' : '8 connected AI agents · signals move live with real activity';

    $css = <<<CSS
<style>
#ai-workforce{--awp:#fff;--awb:#D5E2F0;--awl:#F8FBFF;--awm:#647795;--awink:#152844;direction:ltr;position:relative}
html.dark #ai-workforce{--awp:#111D34;--awb:#283957;--awl:#12253D;--awm:#91A2C0;--awink:#EEF4FF}
#ai-workforce .aw-frame{border:1px solid var(--awb);border-radius:23px;background:linear-gradient(180deg,var(--awl),transparent 90%);padding:12px 15px 7px;position:relative;overflow:hidden}
#ai-workforce .aw-visual{position:relative;overflow:hidden;width:100%;padding:9px 0 0;border-radius:18px;background:radial-gradient(ellipse 72% 62% at 50% 44%,rgba(36,70,125,.10),transparent 72%)}
html.dark #ai-workforce .aw-visual{background:radial-gradient(ellipse 72% 62% at 50% 44%,rgba(36,70,125,.34),transparent 74%)}
#ai-workforce .aw-visual svg{width:100%;height:auto;display:block;pointer-events:none}
#ai-workforce .aw-tags{position:absolute;inset:0;pointer-events:none;z-index:4}
#ai-workforce .aw-tag{position:absolute;top:2%;transform:translateX(-50%);pointer-events:auto;cursor:pointer;display:flex;align-items:center;gap:5px;font-family:inherit;font-size:11.5px;font-weight:800;color:var(--awm);background:var(--awp);border:1px solid var(--awb);border-radius:999px;padding:4px 10px;white-space:nowrap;box-shadow:0 3px 12px rgba(15,23,42,.14);transition:border-color .25s,box-shadow .25s,color .25s,transform .15s}
#ai-workforce .aw-tag:hover{transform:translateX(-50%) translateY(-1px)}
#ai-workforce .aw-tag .aw-tdot{width:8px;height:8px;border-radius:50%;background:#F59E0B;opacity:1;flex:none;box-shadow:0 0 0 3px rgba(245,158,11,.22);transition:background .25s,box-shadow .25s}
#ai-workforce .aw-tag b{color:var(--awink);font-weight:800}
#ai-workforce .aw-tag .aw-tst{font-size:9px;font-weight:850;letter-spacing:.4px;color:#D97706}
#ai-workforce .aw-tag.on{border-color:var(--accent);box-shadow:0 0 0 1px var(--accent),0 6px 18px -6px var(--accent)}
#ai-workforce .aw-tag.on .aw-tdot{background:var(--accent);box-shadow:0 0 0 4px color-mix(in srgb,var(--accent) 24%,transparent);animation:awBlink 1.4s infinite}
#ai-workforce .aw-tag.on .aw-tst{color:var(--accent)}
@keyframes awBlink{0%,100%{opacity:1}50%{opacity:.35}}
#ai-workforce .aw-cap{display:flex;align-items:center;justify-content:space-between;gap:12px;color:var(--awm);font-size:11px;padding:6px 11px 12px;flex-wrap:wrap}
#ai-workforce .aw-cap .mark{color:#52C5D0;font-weight:750}
@media(max-width:780px){#ai-workforce .aw-frame{padding:10px 6px}#ai-workforce .aw-visual{overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;scrollbar-width:none}#ai-workforce .aw-visual::-webkit-scrollbar{display:none}#ai-workforce .aw-visual svg{min-width:900px}#ai-workforce .aw-tags{right:auto;width:900px}#ai-workforce .aw-tag{font-size:10px;padding:3px 8px;gap:4px}#ai-workforce .aw-tag .aw-tst{display:none}}
</style>
CSS;

    $js = <<<JS
<script>
(function(){
  if(window.__awInit)return; window.__awInit=true;
  var root=document.getElementById('ai-workforce'); if(!root)return;
  var roleKeys=['content','publishing','ads','analytics','tenders','crm','automation','communications'];
  var LIVE_T='$live', READY_T='$ready';
  var tags=[].slice.call(root.querySelectorAll('.aw-tag'));
  var INIT=$initJson; var active=[];
  roleKeys.forEach(function(k){ if(INIT[k]) active.push(k); });
  function paintTags(){ tags.forEach(function(b){ var on=active.indexOf(b.getAttribute('data-role'))>=0; b.classList.toggle('on',on); b.setAttribute('aria-pressed',on?'true':'false'); var st=b.querySelector('.aw-tst'); if(st) st.textContent=on?LIVE_T:READY_T; }); }
  function drive(){ var api=window.aiTeamNetwork; if(!api){ setTimeout(drive,160); return; } if(active.length) api.cycleMany(active.slice()); else if(api.stop) api.stop(); }
  function apply(){ paintTags(); drive(); }
  window.AIWorkforceSignals={
    setStates:function(s){ active=[]; roleKeys.forEach(function(k){ var v=s&&s[k]; if(v===true||v===1||(v&&v.active)) active.push(k); }); apply(); },
    setActive:function(r,on){ var i=active.indexOf(r); if(on&&i<0)active.push(r); if(!on&&i>=0)active.splice(i,1); apply(); },
    getState:function(){ return active.slice(); }
  };
  tags.forEach(function(b){ b.addEventListener('click',function(){ var r=b.getAttribute('data-role'); window.AIWorkforceSignals.setActive(r, active.indexOf(r)<0); }); });
  paintTags();
  drive();
  // إعادة تشغيل نبضة الإشارات للأقسام النشطة باستمرار (المحرّك بيهدأ بعد كل دورة)
  setInterval(function(){ if(active.length && window.aiTeamNetwork && !document.hidden) window.aiTeamNetwork.cycleMany(active.slice()); }, 4800);
  // تحريك أفقي تلقائي رايح‑جاي على الموبايل؛ لو المستخدم سحب بإيده يتوقف 5 ثواني ويكمّل
  var vis=root.querySelector('.aw-visual');
  if(vis){
    var dir=1, pausedUntil=0, lastT=0, pos=vis.scrollLeft, SPEED=55; // ~55px/ث
    function bump(){ pausedUntil=Date.now()+5000; }
    ['touchstart','pointerdown','wheel','mousedown'].forEach(function(ev){ vis.addEventListener(ev,bump,{passive:true}); });
    function tick(ts){
      requestAnimationFrame(tick);
      var max=vis.scrollWidth-vis.clientWidth;
      if(max<=8||document.hidden){ lastT=ts; pos=vis.scrollLeft; return; } // شاشة كبيرة: مفيش سحب
      if(Date.now()<pausedUntil){ lastT=ts; pos=vis.scrollLeft; return; } // المستخدم بيسحب
      if(!lastT){ lastT=ts; return; }
      var dt=(ts-lastT)/1000; lastT=ts; if(dt>0.1)dt=0.1;
      pos+=dir*SPEED*dt;
      if(pos>=max){ pos=max; dir=-1; } else if(pos<=0){ pos=0; dir=1; }
      vis.scrollLeft=pos;
    }
    requestAnimationFrame(tick);
  }
})();
</script>
JS;

    return '<div id="ai-workforce">' . $css
        . '<div class="aw-frame"><div class="aw-visual">' . $svg . '<div class="aw-tags">' . $tags . '</div></div>'
        . '<div class="aw-cap"><span>◉ ' . e($capTxt) . '</span><span class="mark">LIVE SIGNAL LAYER</span></div></div>'
        . $js . '</div>';
}
