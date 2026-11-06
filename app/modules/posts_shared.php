<?php
/**
 * أدوات المنشورات المشتركة (صفحة المنشورات + الـ cron): المنصات، الوسائط، UTC، الصياغة بالقواعد، و#19 Evergreen.
 */
/** المنصات بإعداداتها من منشور موجود (عشان التعديل والنسخ مايضيعوش حاجة) */
function ps_platforms(array $p): array
{
    $pls = [];
    foreach ($p['platforms'] ?? [] as $pl) {
        $item = ['platform' => $pl['platform'], 'accountId' => pub_acc_id($pl['accountId'] ?? '')];
        foreach (['customContent', 'platformSpecificData'] as $k) if (!empty($pl[$k])) $item[$k] = $pl[$k];
        $pls[] = $item;
    }
    return $pls;
}

function ps_media(array $p): array
{
    return array_map(function ($m) { return array_intersect_key($m, array_flip(['type', 'url', 'altText', 'thumbnail'])); }, (array)($p['mediaItems'] ?? []));
}

function ps_utc(DateTime $dt): string { $u = clone $dt; $u->setTimezone(new DateTimeZone('UTC')); return $u->format('Y-m-d\TH:i:s.000\Z'); }

/**
 * صياغة مختلفة لنفس المنشور بالقواعد (من غير AI): بنغير إيموجي الافتتاحية، ونلف ترتيب السطور اللي في النص
 * (السطر الأول والهاشتاجات بيفضلوا مكانهم)، ونرتب الهاشتاجات، ونضيف ختام قصير مختلف. المعلومات نفسها مابتتغيرش.
 */
function ps_vary_text(string $base, int $i): string
{
    if ($i === 0) return $base;
    $ar = (bool)preg_match('/\p{Arabic}/u', $base);
    $lines = preg_split('/\R/u', trim($base));
    $tags = []; while ($lines && preg_match('/^(\s*#\S+\s*)+$/u', end($lines))) $tags = array_merge(preg_split('/\s+/', trim(array_pop($lines))), $tags);
    $first = array_shift($lines);
    $emo = ['🔔', '📌', '✨', '🚀', '📣', '💼', '⭐', '🔥'];
    $first = preg_replace('/^[\p{So}\p{Sk}\x{FE0F}\x{200D}\s]+/u', '', (string)$first);
    $first = $emo[($i - 1) % count($emo)] . ' ' . $first;
    // نلف السطور اللي في النص (لو فيه سطور فاضية بنحافظ على الفقرات)
    $mid = array_values(array_filter($lines, function ($x) { return trim($x) !== ''; }));
    if (count($mid) > 2) { $r = $i % count($mid); $mid = array_merge(array_slice($mid, $r), array_slice($mid, 0, $r)); }
    if ($tags) { $r = $i % count($tags); $tags = array_merge(array_slice($tags, $r), array_slice($tags, 0, $r)); }
    $ends = $ar ? ['قدّم الآن ولا تفوّت الفرصة 👇', 'شارك المنشور مع من يحتاجه 🙌', 'كل التفاصيل في الأعلى ✅', 'راسلنا لأي استفسار 💬'] : ['Apply now 👇', 'Share this with someone who needs it 🙌', 'Full details above ✅', 'Message us with any questions 💬'];
    $out = $first . "\n\n" . implode("\n", $mid) . "\n\n" . $ends[($i - 1) % count($ends)];
    if ($tags) $out .= "\n\n" . implode(' ', $tags);
    return mb_substr($out, 0, 5000);
}

/** #34 حل مقترح لأشهر أسباب فشل النشر */
function ps_fail_hint(string $err): string
{
    $e = mb_strtolower($err);
    $map = [
        ['/token|expired|reauth|reconnect|permission|unauthori[sz]ed|access/', t('الحساب محتاج يتربط تاني: افتح "الحسابات المربوطة" ودوس إعادة ربط.')],
        ['/aspect|ratio|dimension|resolution|too small|width|height/', t('مقاس الصورة/الفيديو مش مناسب للمنصة. جرّب مربع 1080×1080 أو طولي 1080×1350.')],
        ['/duplicate|same content|already posted|identical/', t('المنصة رفضت نص متكرر. غيّر الصياغة (جرّب "صياغات سريعة" في التقويم).')],
        ['/rate|limit|too many|quota|429/', t('المنصة وقّفت الطلبات مؤقتًا. أعد المحاولة بعد ساعة أو وزّع المنشورات على مواعيد أبعد.')],
        ['/too long|character|length|exceed/', t('النص أطول من المسموح. اختصره أو استخدم "نسخة لكل منصة".')],
        ['/video|duration|codec|format|file type|unsupported/', t('صيغة أو مدة الفيديو مش مدعومة. استخدم MP4 وأقل من 60 ثانية للريلز.')],
        ['/media|image|upload|download|fetch/', t('الصورة مااتحملتش. ارفعها تاني من مكتبة الوسائط أو بجودة أقل من 8 ميجا.')],
        ['/link|url/', t('اللينك مرفوض أو مش شغال. اتأكد إنه بيفتح، أو حطه في أول كومنت.')],
    ];
    foreach ($map as $x) if (preg_match($x[0], $e)) return $x[1];
    return $err !== '' ? t('جرّب "إعادة المحاولة". لو اتكرر، افتح المنشور وعدّل المحتوى أو أعد ربط الحساب.') : '';
}

/** #19: كل منشور Evergreen جه ميعاده بيتعمل منه نسخة جديدة بصياغة مختلفة (بالقواعد) في أحسن ساعة، وبعدها الميعاد الجاي */
function ps_evergreen_tick(Zernio $z, int $max = 10): int
{
    $wid = (int)ws('id'); $n = 0;
    $st = db()->prepare('SELECT * FROM post_evergreen WHERE workspace_id = ? AND next_at <= ? ORDER BY next_at LIMIT ' . (int)$max);
    $st->execute([$wid, date('Y-m-d H:i:s')]);
    $tz = new DateTimeZone(site_timezone());
    $hour = function_exists('best_slots_local') ? (int)((best_slots_local($z, 1)[0]['hour'] ?? 11)) : 11;
    foreach ($st->fetchAll() as $e) {
        try {
            $p = pub_get_post($z, (string)$e['post_id']);
            $pl = ps_platforms($p); if (!$pl) throw new ZernioException(t('المنشور ده مالوش حسابات.'));
            $at = new DateTime('tomorrow', $tz); $at->setTime($hour, 0);
            $copy = ['content' => ps_vary_text((string)($p['content'] ?? ''), (int)$e['runs'] + 1), 'platforms' => array_map(function ($x) { unset($x['customContent']); return $x; }, $pl), 'timezone' => site_timezone(), 'scheduledFor' => ps_utc($at)];
            if ($m = ps_media($p)) $copy['mediaItems'] = $m;
            $z->createPost($copy);
            db()->prepare('UPDATE post_evergreen SET runs = runs + 1, next_at = ?, last_error = NULL WHERE id = ?')->execute([date('Y-m-d H:i:s', time() + 86400 * max(1, (int)$e['every_days'])), (int)$e['id']]);
            log_activity('post.evergreen_run', (string)$e['post_id']); $n++;
        } catch (Throwable $ex) {
            db()->prepare('UPDATE post_evergreen SET last_error = ?, next_at = ? WHERE id = ?')->execute([mb_substr($ex->getMessage(), 0, 255), date('Y-m-d H:i:s', time() + 86400), (int)$e['id']]);
        }
    }
    return $n;
}
