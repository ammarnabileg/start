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
    return ['access_removed' => $n];
}

/** الشركة اتحذفت (مقفولة)؟ الروابط العامة والويبهوك بيستخدموها عشان مايشتغلوش لشركة اتقفلت */
function ws_closed(int $wid): bool
{
    static $c = [];
    if (!isset($c[$wid])) { $st = db()->prepare('SELECT status FROM workspaces WHERE id = ?'); $st->execute([$wid]); $s = $st->fetchColumn(); $c[$wid] = $s === false || $s === 'deleted'; }
    return $c[$wid];
}
