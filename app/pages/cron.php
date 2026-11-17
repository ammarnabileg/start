<?php
/**
 * تشغيل دوري (من غير تسجيل دخول): index.php?p=cron&token=...
 * بيبعت التقارير المستحقة لكل الشركات ويراجع قواعد التنبيه.
 */
header('Content-Type: text/plain; charset=utf-8');
$token = (string)setting('cron_token', '');
if (strlen($token) < 32 || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("forbidden\n");
}
@set_time_limit(600); $GLOBALS['cron_t0'] = microtime(true); // ميزانية وقت: الخطوات الطويلة (قراءة المنافسين عبر Apify) تقف قبل ما PHP يقتل التشغيلة في النص
ignore_user_abort(true);

// منع تشغيلين في نفس الوقت: قفل من قاعدة البيانات (ذرّي، وبيتفك لوحده لو العملية وقعت)
if ((int)db()->query("SELECT GET_LOCK(CONCAT(DATABASE(), '_cron'), 0)")->fetchColumn() !== 1) {
    exit("already running\n");
}
save_setting('cron_running', (string)time()); // للعرض بس (آخر تشغيل)

$out = [];
try {
    // كل الشركات النشطة: شغل الـ CRM (المتابعات، سرعة الرد، الاجتماعات، الإيميلات) مالوش علاقة بـ Zernio
    // والخطوات اللي محتاجة Zernio (تنبيهات الأداء، الرد الآلي، الحملات، تقارير التحليلات) بتتعدى للي مش رابطه
    $rows = db()->query("SELECT w.id, w.name, EXISTS (SELECT 1 FROM connectors c WHERE c.workspace_id = w.id AND c.provider = 'zernio') has_z
        FROM workspaces w WHERE COALESCE(w.status, 'active') = 'active' ORDER BY w.id")->fetchAll();
    foreach ($rows as $w) {
        $wid = (int)$w['id']; $hasZ = (bool)$w['has_z'];
        $line = 'ws ' . $wid . ($hasZ ? '' : ' (no zernio)') . ':';
        if ($hasZ) try {
            $line .= ' alerts+' . rp_evaluate_alerts($wid, true);
        } catch (Throwable $t) {
            $line .= ' alerts=error';
        }
        // الذكاء الاصطناعي في الخلفية (لو صاحب الشركة مفعّله)
        // تحليل الرسايل بقى يدوي بس (المستخدم بيختار)، عشان مايستهلكش رصيد لوحده
        foreach ($hasZ ? ['alerts' => 'ai_smart_alerts', 'reply' => 'ai_auto_reply'] : [] as $what => $fn) {
            if (!ai_auto_enabled($wid, $what) || !ai_feature_on(['classify' => 'classify_btn', 'alerts' => 'alerts_bg', 'reply' => 'auto_reply'][$what], $wid)) continue;
            try {
                $line .= ' ai_' . $what . '+' . (int)ai_as_workspace($wid, function () use ($fn, $wid) { return $fn($wid); });
            } catch (Throwable $t) {
                $line .= ' ai_' . $what . '=error';
            }
        }
        // ردود تلقائية بالذكاء الاصطناعي (قواعد صفحة «الردود التلقائية»): شغالة لوحدها حتى لو «الرد الذكي» العام مقفول
        if ($hasZ && function_exists('acr_tick') && function_exists('ai_connection')) try {
            $k = (int)ai_as_workspace($wid, function () use ($wid) { $z = zernio_or_null(); return ($z && ai_connection() && acr_all($wid)) ? acr_tick($wid, $z) : 0; });
            if ($k) $line .= ' ai_rules+' . $k;
        } catch (Throwable $t) { $line .= ' ai_rules=error'; }
        // leads الإعلانات ← الـ Leads الموحدة (قراءة بس، بيقف عند اللي اتسحب قبل كده)
        try {
            $n = (int)ai_as_workspace($wid, function () {
                if (!module_enabled('crm') || !module_enabled('ads')) return 0;
                $z = zernio_or_null();
                return $z ? crm_sync_ads($z, 3)[0] : 0;
            });
            if ($n) $line .= ' ad_leads+' . $n;
        } catch (Throwable $t) {
            $line .= ' ad_leads=error';
        }
        // العملاء المستهدفين: كل اللي اتعاملوا معانا (رسائل، تعليقات، واتساب، إعلانات)، كل ساعتين
        try {
            if (strtotime(explode('|', (string)setting('tg_sync_' . $wid, ''))[0] ?: '2000-01-01') < time() - 2 * 3600) {
                $n = (int)ai_as_workspace($wid, function () { if (!module_enabled('crm') || !function_exists('tg_capture_all')) return 0; $z = zernio_or_null(); return $z ? tg_capture_all($z) : 0; });
                if ($n) $line .= ' prospects+' . $n;
            }
        } catch (Throwable $t) {
            $line .= ' prospects=error';
        }
        // تنبيه بمواعيد المتابعة اللي جت (الجرس + إيميل للمسؤول)
        try {
            $n = (int)ai_as_workspace($wid, function () { if (!module_enabled('crm') || !function_exists('crm_followup_tick')) return 0; return array_sum(array_map('count', crm_followup_tick(true))); });
            if ($n) $line .= ' followups+' . $n;
        } catch (Throwable $t) {
            $line .= ' followups=error';
        }
        // الشركات المستهدفة: تكميل بيانات الشركات الناقصة (عدد قليل كل مرة)
        try {
            $n = (int)ai_as_workspace($wid, function () { if (!module_enabled('crm') || !function_exists('tg_enrich_due')) return 0; tg_sync_inbound(); return tg_enrich_due(8); });
            if ($n) $line .= ' targets_enriched+' . $n;
        } catch (Throwable $t) {
            $line .= ' targets_enrich=error';
        }
        // الالتقاط التلقائي للعملاء من الرسائل والتعليقات (مرة كل 6 ساعات، لو مفعّل)
        try {
            if (crm_auto_capture_on($wid) && strtotime(explode('|', (string)setting('crm_auto_last_' . $wid, ''))[0] ?: '2000-01-01') < time() - 6 * 3600) {
                $n = (int)ai_as_workspace($wid, function () {
                    if (!module_enabled('crm')) return 0;
                    $z = zernio_or_null();
                    return $z ? crm_auto_capture($z) : 0;
                });
                $line .= ' auto_leads+' . $n;
            }
        } catch (Throwable $t) {
            $line .= ' auto_leads=error';
        }
        // #19 Evergreen: المنشورات الناجحة بتتعاد بصياغة جديدة
        try {
            $n = (int)ai_as_workspace($wid, function () { $z = zernio_or_null(); return $z && function_exists('ps_evergreen_tick') ? ps_evergreen_tick($z) : 0; });
            if ($n) $line .= ' evergreen+' . $n;
        } catch (Throwable $t) {
            $line .= ' evergreen=error';
        }
        // #100 تقرير شهري للمدير (أول يوم في الشهر)
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') && function_exists('crm_monthly_report') ? crm_monthly_report() : 0; });
            if ($n) $line .= ' monthly+' . $n;
        } catch (Throwable $t) {
            $line .= ' monthly=error';
        }
        // #84 تذكير الصبح بالإيميل لكل موظف
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') && function_exists('crm_daily_digest') ? crm_daily_digest() : 0; });
            if ($n) $line .= ' digest+' . $n;
        } catch (Throwable $t) {
            $line .= ' digest=error';
        }
        // #60 Google Sheets: الصفوف الجديدة بتبقى Leads
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') && function_exists('cap_sheet_tick') ? cap_sheet_tick() : 0; });
            if ($n) $line .= ' sheet+' . $n;
        } catch (Throwable $t) {
            $line .= ' sheet=error';
        }
        // #39 إخفاء تلقائي للشتايم والسبام
        try {
            $n = (int)ai_as_workspace($wid, function () { $z = zernio_or_null(); return $z && function_exists('cm_autohide_tick') ? cm_autohide_tick($z) : 0; });
            if ($n) $line .= ' autohide+' . $n;
        } catch (Throwable $t) {
            $line .= ' autohide=error';
        }
        // #74 تذكير بالاجتماعات المحجوزة
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') ? crm_meeting_reminders() : 0; });
            if ($n) $line .= ' meet_remind+' . $n;
        } catch (Throwable $t) {
            $line .= ' meet=error';
        }
        // #69 #70: سرعة الرد على العملاء الجداد
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') ? crm_sla_tick() : 0; });
            if ($n) $line .= ' sla+' . $n;
        } catch (Throwable $t) {
            $line .= ' sla=error';
        }
        // البحوث المحفوظة في Apollo: الأشخاص الجداد كل يوم (مابيصرفش كريديت)
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') ? crm_searches_daily() : 0; });
            if ($n) $line .= ' apollo_new+' . $n;
        } catch (Throwable $t) {
            $line .= ' apollo_saved=error';
        }
        // حملات الرسائل: دفعة لكل شركة
        if ($hasZ) try {
            $n = (int)ai_as_workspace($wid, function () use ($wid) { return camp_tick($wid, 60); });
            if ($n) $line .= ' campaigns+' . $n;
        } catch (Throwable $t) {
            $line .= ' campaigns=error';
        }
        if ($hasZ) try {
            $s = rp_schedule($wid);
            $kind = rp_due($s, rp_tz($wid));
            if ($kind && (int)setting('rp_fail_' . $wid, '0') > time() - 6 * 3600) $kind = null; // فشل كله من قريب: نستنى 6 ساعات قبل إعادة المحاولة
            if ($kind) {
                [$emails] = rp_parse_emails((string)$s['recipients']);
                [$ok, $fail] = rp_send_report($wid, $emails, $kind, (string)$s['lang']);
                $res = gmdate('Y-m-d H:i') . ' ' . $kind . ' ok=' . $ok . ' fail=' . $fail;
                rp_mark_sent($wid, $res, $ok > 0); save_setting('rp_fail_' . $wid, $ok > 0 ? '0' : (string)time());
                $line .= ' report ' . $res;
            } else {
                $line .= ' report=not-due';
            }
        } catch (Throwable $t) {
            $line .= ' report=error';
        }
        $out[] = $line;
    }
    // المنافسات الحكومية: سحب اعتماد من Apify (مرة في اليوم على مستوى المنصة)
    try { $ap = function_exists('tnd_apify_tick') ? tnd_apify_tick() : ''; if ($ap !== '') $out[] = 'tenders_apify: ' . $ap; } catch (Throwable $t) {}
    // المنافسات والمناقصات: تنبيه بالجديد المطابق لكل شركة عندها معرفة (الكلمات بتيجي من معرفتها)
    try {
        foreach (db()->query("SELECT DISTINCT k.workspace_id FROM kb_sections k JOIN workspaces w ON w.id = k.workspace_id WHERE COALESCE(w.status, 'active') = 'active' AND k.section IN ('company', 'services', 'frame_ref')")->fetchAll(PDO::FETCH_COLUMN) as $tw) {
            try { $n = (int)ai_as_workspace((int)$tw, function () { return function_exists('tnd_tick') ? tnd_tick() : 0; }); if ($n) $out[] = 'ws ' . (int)$tw . ': tenders+' . $n; }
            catch (Throwable $t) { $out[] = 'ws ' . (int)$tw . ': tenders=error'; }
        }
    } catch (Throwable $t) {}
    // متابعة المنافسين: لكل الشركات (حتى اللي مش مربوطة بالنشر)، كل منافس مرة في اليوم
    try {
        foreach (db()->query("SELECT DISTINCT c.workspace_id FROM competitors c JOIN workspaces w ON w.id = c.workspace_id WHERE COALESCE(w.status, 'active') = 'active'")->fetchAll(PDO::FETCH_COLUMN) as $cw) {
            try { $n = (int)ai_as_workspace((int)$cw, function () { $k = function_exists('comp_tick') ? comp_tick(10) : 0; if (function_exists('cs_tick')) { try { $k += cs_tick(4); } catch (Throwable $e) {} } if (function_exists('ca_tick')) { try { $k += ca_tick(3); } catch (Throwable $e) {} } if (function_exists('cw_rank_tick')) { try { cw_rank_tick(); } catch (Throwable $e) {} } if (function_exists('cg_tick')) { try { $k += cg_tick(3); } catch (Throwable $e) {} } if (function_exists('cmp_alert_tick')) { try { if (cmp_disc_pending()) cmp_disc_collect(); cmp_alert_tick(); cmp_digest_tick(); } catch (Throwable $e) {} } return $k; }); if ($n) $out[] = 'ws ' . (int)$cw . ': competitors+' . $n; }
            catch (Throwable $t) { $out[] = 'ws ' . (int)$cw . ': competitors=error'; }
        }
    } catch (Throwable $t) {}
    save_setting('cron_last_run', gmdate('Y-m-d H:i:s'));
} finally {
    save_setting('cron_running', '0');
    try { db()->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), '_cron'))"); } catch (Throwable $e) {}
}
echo "ok\n" . implode("\n", $out) . "\n";
exit;
