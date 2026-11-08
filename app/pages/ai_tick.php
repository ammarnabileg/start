<?php
/**
 * تشغيل الرد الذكي من غير cron على السيرفر: أي صفحة مفتوحة في اللوحة بتنادي هنا كل دقيقة،
 * وبيشتغل مرة واحدة كل دقيقة للشركة (قفل في الإعدادات) مهما كان عدد المستخدمين.
 */
header('Content-Type: application/json; charset=utf-8');
$wid = (int)ws('id');
$out = ['ok' => true, 'ran' => false];
try {
    if ($wid && function_exists('ai_auto_enabled') && ai_auto_enabled($wid, 'reply') && ai_feature_on('auto_reply', $wid)) {
        $last = (int)setting('ai_tick_' . $wid, '0');
        if ($last < time() - 55) {
            save_setting('ai_tick_' . $wid, (string)time());
            session_write_close(); // ما نعطلش باقي طلبات المستخدم
            ignore_user_abort(true); @set_time_limit(120);
            $out['n'] = (int)ai_as_workspace($wid, function () use ($wid) { return ai_auto_reply($wid); });
            $out['ran'] = true;
        }
    }
} catch (Throwable $e) { $out = ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 200)]; }
echo json_encode($out, JSON_UNESCAPED_UNICODE);
exit;
