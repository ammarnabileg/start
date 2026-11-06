<?php
/** #13 موافقة المدير قبل النشر: الموظف بيبعت، والمدير يوافق (يتنشر في الميعاد المطلوب) أو يرفض بملاحظة */
$err = null;
$z = zernio_or_null($err);
$fxd = getenv('UI_FIXTURES');
if ($fxd && is_file($fxd . '/compose_fake_z.php')) $z = require $fxd . '/compose_fake_z.php'; // اختبارات بس
$wid = (int)ws('id'); $uid = (int)(current_user()['id'] ?? 0); $mgr = can('posts.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = (string)($_POST['action'] ?? '');
    if ($a === 'toggle' && $mgr) { save_setting('post_approval_' . $wid, !empty($_POST['on']) ? '1' : '0'); flash('success', t('اتحفظ ✓')); redirect(url('approvals')); }
    $st = db()->prepare("SELECT * FROM post_approvals WHERE id = ? AND workspace_id = ? AND status = 'pending'"); $st->execute([(int)($_POST['id'] ?? 0), $wid]); $r = $st->fetch();
    if (!$r || !$mgr || !$z) { flash('error', t('بيانات ناقصة')); redirect(url('approvals')); }
    if ($a === 'approve') {
        try {
            $p = json_decode((string)$r['payload'], true) ?: [];
            if (isset($_POST['content'])) $p['content'] = mb_substr((string)$_POST['content'], 0, 10000);
            if ($r['want'] !== 'now' && $r['want'] !== 'queue' && strtotime($r['want']) < time()) { unset($p['scheduledFor']); $p['publishNow'] = true; } // الميعاد فات وهو مستني
            $z->createPost($p);
            foreach ((array)json_decode((string)$r['extra'], true) as $f) if (strtotime($f) > time()) { try { $z->createPost(['scheduledFor' => $f] + array_diff_key($p, ['publishNow' => 1])); } catch (ZernioException $e) {} }
            if ($r['draft_id']) { try { $z->request('DELETE', '/v1/posts/' . rawurlencode($r['draft_id'])); } catch (ZernioException $e) {} }
            db()->prepare("UPDATE post_approvals SET status = 'approved', decided_by = ?, decided_at = NOW() WHERE id = ?")->execute([$uid, (int)$r['id']]);
            if ($r['user_id'] && function_exists('crm_notify')) crm_notify((int)$r['user_id'], null, 'approval', t('✅ المدير وافق على منشورك'));
            log_activity('post.approved', (string)$r['id']);
            flash('success', t('اتوافق عليه ✓'));
        } catch (ZernioException $ex) { flash('error', $ex->getMessage()); }
    }
    if ($a === 'reject') {
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300);
        db()->prepare("UPDATE post_approvals SET status = 'rejected', note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?")->execute([$note ?: null, $uid, (int)$r['id']]);
        if ($r['user_id'] && function_exists('crm_notify')) crm_notify((int)$r['user_id'], null, 'approval', t('✕ المدير رفض منشورك') . ($note !== '' ? ': ' . $note : ''));
        log_activity('post.rejected', (string)$r['id']);
        flash('success', t('اترفض، وصاحبه وصله ملاحظتك.'));
    }
    redirect(url('approvals'));
}
$st = db()->prepare('SELECT a.*, u.name uname FROM post_approvals a LEFT JOIN users u ON u.id = a.user_id WHERE a.workspace_id = ?' . ($mgr ? '' : ' AND a.user_id = ' . $uid) . ' ORDER BY a.status = \'pending\' DESC, a.id DESC LIMIT 100');
$st->execute([$wid]); $rows = $st->fetchAll();
render(t('الموافقات'), 'approvals', function () use ($rows, $mgr, $wid) {
    $on = setting('post_approval_' . $wid, '0') === '1';
    $badge = ['pending' => ['bg-amber-100 text-amber-800', t('مستني')], 'approved' => ['bg-lime-100 text-lime-800', t('اتوافق')], 'rejected' => ['bg-red-100 text-red-700', t('اترفض')]];
    ?>
    <?php if ($mgr): ?>
    <form method="post" class="card p-4 mb-4 flex flex-wrap items-center gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="toggle">
      <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="on" class="rounded" <?= $on ? 'checked' : '' ?> onchange="this.form.submit()"> <b><?= e(t('الموظفين لازم ياخدوا موافقة قبل النشر')) ?></b></label>
      <span class="text-xs text-ink-500"><?= e(t('المدير وصاحب الشركة بينشروا على طول. أي حد تاني منشوره بيتحفظ مسودة لحد ما توافق.')) ?></span>
    </form>
    <?php endif; ?>
    <?php if (!$rows): ?><?= an_empty(t('مفيش منشورات مستنية موافقة'), $on ? '' : t('الموافقة مقفولة دلوقتي.'), '📝') ?><?php endif; ?>
    <div class="space-y-3">
    <?php foreach ($rows as $r): $p = json_decode((string)$r['payload'], true) ?: []; $b = $badge[$r['status']] ?? $badge['pending']; $media = (array)($p['mediaItems'] ?? []); ?>
      <div class="card p-4">
        <div class="flex flex-wrap items-center gap-2 text-xs text-ink-500 mb-2"><span class="rounded-full px-2 py-0.5 <?= $b[0] ?>"><?= e($b[1]) ?></span>
          <span><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zM4 21a8 8 0 0116 0"/></svg> <?= e($r['uname'] ?: '—') ?></span><span>· <?= e(fmt_date($r['created_at'], 'd/m h:i A')) ?></span>
          <span>· <svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2"/></svg> <?= e($r['want'] === 'now' ? t('ينزل على طول') : ($r['want'] === 'queue' ? t('الطابور') : fmt_date($r['want'], 'D d/m h:i A'))) ?></span>
          <span>· <?= e(t('%s حساب', count((array)($p['platforms'] ?? [])))) ?></span>
          <?php if ($r['note']): ?><span class="text-red-600">· <?= e($r['note']) ?></span><?php endif; ?></div>
        <?php if ($r['status'] === 'pending' && $mgr): ?>
          <form method="post" class="space-y-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <textarea name="content" rows="4" class="input text-sm" dir="auto"><?= e((string)($p['content'] ?? '')) ?></textarea>
            <?php if ($media): ?><div class="flex gap-2"><?php foreach (array_slice($media, 0, 4) as $mi): if (($mi['type'] ?? '') === 'image'): ?><img src="<?= e($mi['url'] ?? '') ?>" class="w-16 h-16 rounded-lg object-cover border border-ink-200" alt=""><?php endif; endforeach; ?></div><?php endif; ?>
            <div class="flex flex-wrap items-center gap-2"><button name="action" value="approve" class="btn-primary !py-1.5 !text-sm">✓ <?= e(t('وافق وانشر')) ?></button>
              <input name="note" class="input !py-1.5 text-sm !w-64 !mt-0" maxlength="300" placeholder="<?= e(t('ملاحظة لو هترفض (اختياري)')) ?>"><button name="action" value="reject" class="btn-ghost !py-1.5 !text-sm text-red-600">✕ <?= e(t('ارفض')) ?></button></div>
          </form>
        <?php else: ?><p class="text-sm text-ink-800 whitespace-pre-line" dir="auto"><?= e(mb_substr((string)($p['content'] ?? ''), 0, 600)) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
    <?php
});
