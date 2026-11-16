<?php
/**
 * حذف شركة بالكامل: كل بياناتها من كل الجداول + إعداداتها + ملفاتها.
 * الجداول بتتلاقى تلقائيًا (أي جدول فيه workspace_id)، فأي جدول جديد بيتحذف من غير ما حد يفتكره.
 * الجداول الفرعية (بيانات المنافسين، رسائل الحملات...) مربوطة بأهلها بـ ON DELETE CASCADE.
 * الحسابات والمنشورات عند خدمة النشر (Zernio) مابتتلمسش.
 */
function ws_purge(int $wid): array
{
    if ($wid <= 0) throw new InvalidArgumentException('bad workspace');
    $pdo = db(); $rows = 0; $tables = 0;
    $list = $pdo->query("SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'workspace_id' AND table_name <> 'workspaces'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($list as $t) {
        if (!preg_match('/^[a-z0-9_]+$/', (string)$t)) continue;
        $st = $pdo->prepare("DELETE FROM `$t` WHERE workspace_id = ?"); $st->execute([$wid]); $rows += $st->rowCount(); $tables++;
    }
    // الإعدادات بتتخزن باسم بينتهي بـ _رقم الشركة (مثلًا crm_form_token_12)
    $st = $pdo->prepare('DELETE FROM settings WHERE name REGEXP ?'); $st->execute(['_' . $wid . '$']); $rows += $st->rowCount();
    $pdo->prepare('DELETE FROM workspaces WHERE id = ?')->execute([$wid]);
    // الملفات: مكتبة الوسائط، صور الذكاء الاصطناعي، خطوط الهوية
    $files = 0;
    foreach (['media', 'ai', 'brandfonts'] as $d) {
        $dir = ROOT_DIR . '/uploads/' . $d . '/' . $wid; $real = realpath($dir); $base = realpath(ROOT_DIR . '/uploads/' . $d);
        if (!$real || !$base || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) continue; // مايخرجش برّه فولدر الرفع
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname()); else { @unlink($f->getPathname()); $files++; } }
        @rmdir($real);
    }
    return ['tables' => $tables, 'rows' => $rows, 'files' => $files];
}
