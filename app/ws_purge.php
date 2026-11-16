<?php
/**
 * حذف شركة = سحب الوصول بس. كل البيانات بتفضل كاملة زي ما هي (للإحصائيات فيما بعد).
 * الشركة بتتقفل (status = deleted): بتختفي من عند الفريق ومن إدارة المنصة، والمهام المجدولة بتعدّيها،
 * والروابط العامة (الفورم، الحجز، صفحة الهبوط، ويبهوك Zernio للرد الذكي...) بتقف ليها (ws_closed).
 */
function ws_purge(int $wid): array
{
    if ($wid <= 0) throw new InvalidArgumentException('bad workspace');
    $pdo = db();
    $st = $pdo->prepare('DELETE FROM workspace_users WHERE workspace_id = ?'); $st->execute([$wid]); $n = $st->rowCount();
    $pdo->prepare('DELETE FROM workspace_invites WHERE workspace_id = ?')->execute([$wid]);
    $pdo->prepare("UPDATE workspaces SET status = 'deleted' WHERE id = ?")->execute([$wid]);
    // التكاملات بتتوقف (المفاتيح بتفضل محفوظة): علامة disabled وسببها، وconnections() كمان بترفض أي شركة مقفولة
    $pdo->prepare("UPDATE connectors SET settings = JSON_SET(COALESCE(NULLIF(NULLIF(TRIM(settings), ''), '[]'), '{}'), '$.disabled', 1, '$.disabled_reason', 'workspace_deleted') WHERE workspace_id = ?")->execute([$wid]);
    ws_closed($wid, true);
    return ['access_removed' => $n];
}

/** الشركة اتحذفت (مقفولة)؟ الروابط العامة والويبهوك بيستخدموها عشان مايشتغلوش لشركة اتقفلت */
function ws_closed(int $wid, bool $forget = false): bool
{
    static $c = [];
    if ($forget) { unset($c[$wid]); return false; } // بعد الحذف في نفس الطلب: نقرأ الحالة من جديد
    if (!isset($c[$wid])) { $st = db()->prepare('SELECT status FROM workspaces WHERE id = ?'); $st->execute([$wid]); $s = $st->fetchColumn(); $c[$wid] = $s === false || $s === 'deleted'; }
    return $c[$wid];
}
