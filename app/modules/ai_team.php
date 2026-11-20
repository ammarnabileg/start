<?php
/**
 * فريق الوكلاء على الصفحة الرئيسية: 8 وكلاء، كل وكيل = فانكشن.
 * المشهد عبارة عن ملف SVG ثلاثي الأبعاد (assets/workspace_agents.svg) بمجموعات agent-<key>،
 * والوكيل بيشتغل (كلاس active) لما تكون موجود على صفحته أو بتستخدمها فعليًا.
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
        'launch_ad_campaign' => 'ads', 'list_ad_campaigns' => 'ads', 'create_ad_campaign' => 'ads',
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
function ai_team_svg(string $wsName, array $states): string
{
    $file = ROOT_DIR . '/assets/workspace_agents.svg';
    $svg = is_file($file) ? (string)file_get_contents($file) : '';
    if ($svg === '') return '';

    // تعريب العناوين وقت واجهة العربي
    if (function_exists('lang') && lang() === 'ar') {
        foreach (ai_team_labels_ar() as $en => $ar) {
            $svg = str_replace('>' . $en . '</text>', '>' . $ar . '</text>', $svg);
        }
    }

    // ضبط حالة كل وكيل والخط الموصّل له + شارة يعمل/جاهز حسب الحالة الحقيقية
    $ar = function_exists('lang') && lang() === 'ar';
    $live = $ar ? 'يعمل' : 'LIVE'; $ready = $ar ? 'جاهز' : 'READY';
    foreach (ai_team_keys() as $k) {
        $on = !empty($states[$k]['active']);
        $q = preg_quote($k, '/');
        $svg = ai_team_apply_active($svg, '/<g\b[^>]*\bdata-role="' . $q . '"[^>]*>/', $on);
        $svg = ai_team_apply_active($svg, '/<g\b[^>]*\bid="link-' . $q . '"[^>]*>/', $on);
        // نص الشارة (كان مثبّتًا في التصميم) يتبع الحالة الحقيقية
        $word = $on ? $live : $ready;
        $svg = preg_replace_callback('/(id="agent-' . $q . '".*?class="ui-sub state-label"[^>]*>)[^<]*(<\/text>)/s', function ($m) use ($word) {
            return $m[1] . $word . $m[2];
        }, $svg, 1);
    }

    // جعل الـ SVG متجاوب داخل الصفحة + خط عربي للعناوين
    $svg = preg_replace('/<svg /', '<svg style="width:100%;height:auto;display:block" ', $svg, 1);

    $css = '<style>#workspace-agents svg text{direction:ltr;unicode-bidi:isolate;font-family:Alexandria,Inter,system-ui,sans-serif}</style>';
    return '<div id="workspace-agents">' . $css . $svg . '</div>';
}
