<?php
/**
 * حذف شركة: الشركة بتتقفل (status = deleted) وبتختفي من كل حاجة، لكن بياناتها المفيدة للإحصائيات بتفضل.
 *
 * بيفضل كامل: المنافسين وكل بياناتهم (سوشيال، منشورات، إعلانات، مواقع، صفحات، مراجعات)، الشركات المستهدفة،
 *   ملف الشركة في قاعدة المعرفة، استهلاك الذكاء الاصطناعي، الحملات، المواعيد، سجل النشاط.
 * بيفضل من غير بيانات شخصية: العملاء (المرحلة، المصدر، الدولة، القيمة، التواريخ — من غير اسم/رقم/إيميل/ملاحظات)،
 *   الأنشطة والصفقات (النوع والقيمة والتاريخ — من غير النص)، تصنيفات الذكاء الاصطناعي (من غير الملخص).
 * بيتمسح: مفاتيح الربط، الفريق والدعوات، الملاحظات الداخلية، نصوص الرسائل، المستندات والملفات، الإعدادات.
 * الحسابات والمنشورات عند خدمة النشر (Zernio) مابتتلمسش.
 */
function ws_purge(int $wid): array
{
    if ($wid <= 0) throw new InvalidArgumentException('bad workspace');
    $pdo = db(); $n = 0;
    $run = function (string $sql) use ($pdo, $wid, &$n) { try { $st = $pdo->prepare($sql); $st->execute([$wid]); $n += $st->rowCount(); } catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e; /* جدول مش موجود بس؛ عمود ناقص = خطأ واضح (مانسيبش بيانات شخصية من غير ما نعرف) */ } };
    // 1) مسح: أمان وخصوصية (مفاتيح، فريق، ملاحظات، نصوص، مستندات)
    foreach (['connectors', 'workspace_users', 'workspace_invites', 'workspace_roles', 'conv_notes', 'saved_replies', 'crm_notifs', 'crm_form_log', 'kb_files', 'media_shares', 'media_usage', 'media_files', 'designs', 'crm_prefs'] as $t)
        $run("DELETE FROM `$t` WHERE workspace_id = ?");
    // 2) إخفاء البيانات الشخصية مع الاحتفاظ بالأرقام والحالات للإحصائيات
    $run("UPDATE crm_leads SET name = CONCAT('#', id), title = NULL, email = NULL, phone = NULL, phones = NULL, emails = NULL, linkedin = NULL, note = NULL, next_note = NULL, src_url = NULL, ext_id = NULL, custom = NULL WHERE workspace_id = ?");
    $run("UPDATE crm_acts SET body = NULL WHERE workspace_id = ?");
    $run("UPDATE crm_deals SET title = NULL WHERE workspace_id = ?");
    $run("UPDATE crm_tasks SET title = NULL WHERE workspace_id = ?");
    $run("UPDATE crm_mails SET subject = NULL, token = NULL WHERE workspace_id = ?");
    $run("UPDATE lead_meta SET note = NULL WHERE workspace_id = ?");
    $run("UPDATE ai_labels SET summary = NULL WHERE workspace_id = ?");
    $run("UPDATE sms_gate_messages SET phone = NULL, body = NULL WHERE workspace_id = ?");
    $run("UPDATE msg_campaign_items i JOIN msg_campaigns c ON c.id = i.campaign_id SET i.name = NULL, i.last_text = NULL WHERE c.workspace_id = ?");
    // 3) الإعدادات (توكنز الفورم والويبهوك وغيرها) — بتنتهي بـ _رقم الشركة
    $st = $pdo->prepare('DELETE FROM settings WHERE name REGEXP ?'); $st->execute(['_' . $wid . '$']); $n += $st->rowCount();
    // 4) الشركة نفسها بتتقفل (مش بتتمسح: مسح صفها كان هيمسح العملاء والإحصائيات معاه بالـ CASCADE)
    $pdo->prepare("UPDATE workspaces SET status = 'deleted' WHERE id = ?")->execute([$wid]);
    // 5) الملفات على السيرفر (مكتبة الوسائط، صور الذكاء الاصطناعي، الخطوط)
    $files = 0;
    foreach (['media', 'ai', 'brandfonts'] as $d) {
        $real = realpath(ROOT_DIR . '/uploads/' . $d . '/' . $wid); $base = realpath(ROOT_DIR . '/uploads/' . $d);
        if (!$real || !$base || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) continue; // مايخرجش برّه فولدر الرفع
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { if ($f->isDir() && !$f->isLink()) @rmdir($f->getPathname()); else { @unlink($f->getPathname()); $files++; } }
        @rmdir($real);
    }
    return ['changed' => $n, 'files' => $files];
}
