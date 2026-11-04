<?php
/**
 * حملات الرسائل: رسالة لمجموعة محادثات، بتتبعت من طابور على دفعات صغيرة (سرعة محددة في الدقيقة)
 * عشان الخدمة ماتقفش والحساب مايتحظرش. بتشتغل من الصفحة وهي مفتوحة، ومن cron.
 */

function camp_get(int $wid, int $id): ?array
{
    $st = db()->prepare("SELECT * FROM msg_campaigns WHERE id = ? AND workspace_id = ?");
    $st->execute([$id, $wid]);
    return $st->fetch() ?: null;
}

function camp_list(int $wid, int $limit = 10): array
{
    $st = db()->prepare("SELECT * FROM msg_campaigns WHERE workspace_id = ? ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([$wid]);
    return $st->fetchAll();
}

/**
 * بينشئ حملة. $items = [[conv_id, account_id, name, platform, last_text], ...]
 */
function camp_create(int $wid, string $name, string $message, array $items, bool $personalize, int $perMinute): int
{
    $pdo = db();
    $pdo->prepare("INSERT INTO msg_campaigns (workspace_id, user_id, name, message, personalize, per_minute, total) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$wid, (int)(current_user()['id'] ?? 0) ?: null, mb_substr($name, 0, 160), $message, $personalize ? 1 : 0, max(1, min(60, $perMinute)), count($items)]);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare("INSERT INTO msg_campaign_items (campaign_id, conv_id, account_id, name, platform, last_text) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($items as $it) $st->execute([$id, mb_substr($it[0], 0, 120), mb_substr($it[1], 0, 120), mb_substr($it[2] ?? '', 0, 190), mb_substr($it[3] ?? '', 0, 30), mb_substr($it[4] ?? '', 0, 400)]);
    log_activity('campaign.create', $name . ' (' . count($items) . ')');
    return $id;
}

/** الرسالة لكل عميل: {name} بالاسم، ولو التخصيص شغال الذكاء الاصطناعي بيكتبها من رسالته الأخيرة */
function camp_render(array $camp, array $item): string
{
    $first = trim(explode(' ', trim((string)$item['name']))[0] ?? '');
    $msg = str_replace(['{name}', '{الاسم}'], $first !== '' ? $first : '', (string)$camp['message']);
    $msg = trim(preg_replace('/\s+([،,!.])/u', '$1', preg_replace('/ {2,}/', ' ', $msg)));
    if (!$camp['personalize'] || !ai_connection()) return $msg;
    $r = ai_chat([
        ['role' => 'system', 'content' => 'Rewrite this campaign message for ONE customer so it feels personal: use their first name if given, and lightly connect to their last message if relevant. Keep the offer, facts, links and length the same. Same language/dialect as the message. Return ONLY the message.'],
        ['role' => 'user', 'content' => "Message:\n" . $msg . "\n\nCustomer name: " . $item['name'] . "\nTheir last message: " . $item['last_text']],
    ], 400, 'compose');
    return trim($r['text'], " \n\"'«»") ?: $msg;
}

/**
 * بيبعت دفعة من الطابور حسب السرعة المسموحة. بيرجع عدد اللي اتبعت.
 * السرعة: per_minute رسالة في الدقيقة لكل حملة، ومش أكتر من $max في الطلب الواحد.
 */
function camp_tick(int $wid, int $max = 5): int
{
    $pdo = db();
    $done = 0;
    foreach ($pdo->query("SELECT * FROM msg_campaigns WHERE workspace_id = " . (int)$wid . " AND status = 'running' ORDER BY id")->fetchAll() as $camp) {
        // السرعة: كام رسالة اتبعتت آخر دقيقة
        $st = $pdo->prepare("SELECT COUNT(*) FROM msg_campaign_items WHERE campaign_id = ? AND sent_at >= ?");
        $st->execute([$camp['id'], gmdate('Y-m-d H:i:s', time() - 60)]);
        $room = min($max - $done, (int)$camp['per_minute'] - (int)$st->fetchColumn());
        if ($room <= 0) continue;
        // بنحجز العناصر عشان لو اتشغّل مرتين في نفس الوقت مايتبعتش لنفس العميل مرتين
        $ids = $pdo->query("SELECT id FROM msg_campaign_items WHERE campaign_id = " . (int)$camp['id'] . " AND status = 'queued' ORDER BY id LIMIT " . (int)$room)->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            $pdo->prepare("UPDATE msg_campaigns SET status = 'done', finished_at = NOW() WHERE id = ?")->execute([$camp['id']]);
            continue;
        }
        $in = implode(',', array_map('intval', $ids));
        $pdo->exec("UPDATE msg_campaign_items SET status = 'sending' WHERE id IN ($in) AND status = 'queued'");
        $z = zernio_or_null();
        foreach ($pdo->query("SELECT * FROM msg_campaign_items WHERE id IN ($in) AND status = 'sending'")->fetchAll() as $it) {
            try {
                $text = camp_render($camp, $it);
                if (getenv('CAMPAIGN_DRY_RUN')) { usleep(50000); }
                elseif (!$z) throw new Exception('Zernio');
                else $z->sendMessage($it['conv_id'], $it['account_id'], $text);
                $pdo->prepare("UPDATE msg_campaign_items SET status = 'sent', sent_at = NOW(), error = '' WHERE id = ?")->execute([$it['id']]);
                $pdo->prepare("UPDATE msg_campaigns SET sent = sent + 1, last_tick = NOW() WHERE id = ?")->execute([$camp['id']]);
                $done++;
            } catch (Throwable $e) {
                $pdo->prepare("UPDATE msg_campaign_items SET status = 'failed', sent_at = NOW(), error = ? WHERE id = ?")->execute([mb_substr($e->getMessage(), 0, 250), $it['id']]);
                $pdo->prepare("UPDATE msg_campaigns SET failed = failed + 1, last_tick = NOW() WHERE id = ?")->execute([$camp['id']]);
                // فشل كتير ورا بعض: غالبًا مشكلة في الحساب أو حد، نوقف الحملة بدل ما نكمل نفشل
                $st = $pdo->prepare("SELECT status FROM msg_campaign_items WHERE campaign_id = ? AND status IN ('sent','failed') ORDER BY sent_at DESC, id DESC LIMIT 5");
                $st->execute([$camp['id']]);
                $last = $st->fetchAll(PDO::FETCH_COLUMN);
                if (count($last) >= 5 && !array_diff($last, ['failed'])) {
                    $pdo->prepare("UPDATE msg_campaigns SET status = 'paused' WHERE id = ?")->execute([$camp['id']]);
                    break;
                }
            }
        }
        if ($done >= $max) break;
    }
    return $done;
}

/** شكل الحملة للواجهة */
function camp_public(array $c): array
{
    $left = max(0, (int)$c['total'] - (int)$c['sent'] - (int)$c['failed'] - (int)$c['skipped']);
    return ['id' => (int)$c['id'], 'name' => $c['name'], 'status' => $c['status'], 'total' => (int)$c['total'], 'sent' => (int)$c['sent'], 'failed' => (int)$c['failed'],
        'left' => $left, 'per_minute' => (int)$c['per_minute'], 'eta_min' => $c['status'] === 'running' && $c['per_minute'] ? (int)ceil($left / (int)$c['per_minute']) : null,
        'created' => fmt_date($c['created_at'], 'd/m h:i A'), 'personalize' => (bool)$c['personalize']];
}
