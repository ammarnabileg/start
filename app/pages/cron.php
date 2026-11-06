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
@set_time_limit(600);
ignore_user_abort(true);

// منع تشغيلين في نفس الوقت
$lock = (int)setting('cron_running', 0);
if ($lock > time() - 600) {
    exit("already running\n");
}
save_setting('cron_running', (string)time());

$out = [];
try {
    $rows = db()->query("SELECT w.id, w.name FROM workspaces w WHERE COALESCE(w.status, 'active') = 'active'
        AND EXISTS (SELECT 1 FROM connectors c WHERE c.workspace_id = w.id AND c.provider = 'zernio') ORDER BY w.id")->fetchAll();
    foreach ($rows as $w) {
        $wid = (int)$w['id'];
        $line = 'ws ' . $wid . ':';
        try {
            $line .= ' alerts+' . rp_evaluate_alerts($wid, true);
        } catch (Throwable $t) {
            $line .= ' alerts=error';
        }
        // الذكاء الاصطناعي في الخلفية (لو صاحب الشركة مفعّله)
        // تحليل الرسايل بقى يدوي بس (المستخدم بيختار)، عشان مايستهلكش رصيد لوحده
        foreach (['alerts' => 'ai_smart_alerts', 'reply' => 'ai_auto_reply'] as $what => $fn) {
            if (!ai_auto_enabled($wid, $what) || !ai_feature_on(['classify' => 'classify_btn', 'alerts' => 'alerts_bg', 'reply' => 'auto_reply'][$what], $wid)) continue;
            try {
                $line .= ' ai_' . $what . '+' . (int)ai_as_workspace($wid, function () use ($fn, $wid) { return $fn($wid); });
            } catch (Throwable $t) {
                $line .= ' ai_' . $what . '=error';
            }
        }
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
        // البحوث المحفوظة في Apollo: الأشخاص الجداد كل يوم (مابيصرفش كريديت)
        try {
            $n = (int)ai_as_workspace($wid, function () { return module_enabled('crm') ? crm_searches_daily() : 0; });
            if ($n) $line .= ' apollo_new+' . $n;
        } catch (Throwable $t) {
            $line .= ' apollo_saved=error';
        }
        // حملات الرسائل: دفعة لكل شركة
        try {
            $n = (int)ai_as_workspace($wid, function () use ($wid) { return camp_tick($wid, 60); });
            if ($n) $line .= ' campaigns+' . $n;
        } catch (Throwable $t) {
            $line .= ' campaigns=error';
        }
        try {
            $s = rp_schedule($wid);
            $kind = rp_due($s, rp_tz($wid));
            if ($kind) {
                [$emails] = rp_parse_emails((string)$s['recipients']);
                [$ok, $fail] = rp_send_report($wid, $emails, $kind, (string)$s['lang']);
                $res = gmdate('Y-m-d H:i') . ' ' . $kind . ' ok=' . $ok . ' fail=' . $fail;
                rp_mark_sent($wid, $res, $ok > 0);
                $line .= ' report ' . $res;
            } else {
                $line .= ' report=not-due';
            }
        } catch (Throwable $t) {
            $line .= ' report=error';
        }
        $out[] = $line;
    }
    save_setting('cron_last_run', gmdate('Y-m-d H:i:s'));
} finally {
    save_setting('cron_running', '0');
}
echo "ok\n" . implode("\n", $out) . "\n";
exit;
