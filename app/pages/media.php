<?php
/** مكتبة الوسائط: صور وفيديو وملفات الشركة — بتتستخدم من أي صفحة عن طريق window.mediaPick() */
$wid = (int)ws('id');

// ---- عرض ملف للمستخدم المسجل
if (isset($_GET['file'])) {
    $r = media_get($wid, (int)$_GET['file']);
    $p = $r ? (isset($_GET['thumb']) ? media_thumb_path($wid, $r) : media_file_path($wid, $r)) : '';
    if (!$r || !is_file($p)) { http_response_code(404); exit; }
    header('Content-Type: ' . (isset($_GET['thumb']) && substr($p, -4) === '.jpg' && strpos($p, '/thumbs/') !== false ? 'image/jpeg' : $r['mime']));
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    $inline = in_array(media_kind($r['mime']), ['image', 'video', 'audio'], true) || $r['mime'] === 'application/pdf';
    header('Content-Disposition: ' . ($inline && !isset($_GET['dl']) ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($r['name']));
    if ($r['mime'] !== 'application/pdf' && !$inline) header("Content-Security-Policy: sandbox");
    readfile($p); exit;
}

// ---- تحميل أكتر من ملف كـ ZIP
if (isset($_GET['zip'])) {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$_GET['zip']))), 0, 300);
    if (!$ids || !class_exists('ZipArchive')) { http_response_code(400); exit; }
    $tmp = tempnam(sys_get_temp_dir(), 'mz'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE); $used = [];
    foreach ($ids as $id) { $r = media_get($wid, $id); if (!$r) continue; $p = media_file_path($wid, $r); if (!is_file($p)) continue;
        $n = preg_replace('/[\x00-\x1f\/\\\\]+/u', '_', (string)$r['name']); $base = $n; $k = 2; while (isset($used[mb_strtolower($n)])) { $n = preg_replace('/(\.[^.]+)?$/', ' (' . $k++ . ')$1', $base, 1); } $used[mb_strtolower($n)] = 1;
        $z->addFile($p, ($r['folder'] !== '' ? $r['folder'] . '/' : '') . $n); }
    $z->close();
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp)); header('Content-Disposition: attachment; filename="media-' . date('Y-m-d') . '.zip"');
    readfile($tmp); @unlink($tmp); exit;
}

// ---- JSON
$json = function ($a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
if (($_GET['ajax'] ?? '') === 'uses') $json(['ok' => true, 'uses' => media_uses($wid, (int)($_GET['id'] ?? 0))]);
if (($_GET['ajax'] ?? '') === 'shares') $json(['ok' => true, 'shares' => media_shares($wid, (string)($_GET['folder'] ?? ''))]);
if (($_GET['ajax'] ?? '') === 'list') {
    try { media_sync_ai($wid); } catch (Throwable $e) {}
    try { media_purge_old($wid); } catch (Throwable $e) {}
    $json(['ok' => true, 'trash' => media_list($wid, ['trash' => true]), 'items' => media_list($wid, ['folder' => (string)($_GET['folder'] ?? '*'), 'kind' => (string)($_GET['kind'] ?? ''), 'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100)]),
        'folders' => media_folders($wid), 'usage' => media_usage($wid), 'max' => media_max_bytes()]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
    $fclean = function ($v) { $parts = array_filter(array_map(function ($x) { return trim(str_replace('\\', '', $x)); }, explode('/', (string)$v)), 'strlen'); return mb_substr(implode('/', $parts), 0, 120); };
    $fkey = 'media_folders_' . $wid; $fall = function () use ($fkey) { return (array)json_decode((string)setting($fkey, '[]'), true); };
    if (!can('posts.create')) $json(['ok' => false, 'error' => t('مش مسموحلك تعدّل المكتبة.')]);
    if ($act === 'upload') {
        $items = []; $errs = [];
        $files = $_FILES['files'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $n) {
                try { $items[] = media_store_upload($wid, ['name' => $n, 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]], (string)($_POST['folder'] ?? '')); }
                catch (Throwable $e) { $errs[] = $n . ': ' . $e->getMessage(); }
            }
        }
        if ($items) log_activity('media.upload', (string)count($items));
        $json(['ok' => (bool)$items || !$errs, 'items' => $items, 'errors' => $errs]);
    }
    $id = (int)($_POST['id'] ?? 0); $ids = array_slice(array_map('intval', (array)($_POST['ids'] ?? ($id ? [$id] : []))), 0, 200);
    if ($act === 'derive') { // نسخة معدلة (قص/تصغير) من المتصفح: صورة جديدة جنب الأصل
        $src = media_get($wid, (int)($_POST['src'] ?? 0)); $f = $_FILES['file'] ?? null;
        if (!$src || !$f) $json(['ok' => false, 'error' => t('الملف مش موجود')]);
        try { $f['name'] = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 200) ?: $src['name']; $row = media_store_upload($wid, $f, (string)$src['folder']); $json(['ok' => true, 'item' => $row]); }
        catch (Throwable $e) { $json(['ok' => false, 'error' => $e->getMessage()]); }
    }
    if ($act === 'rename') { $n = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255); if ($n === '') $json(['ok' => false]); db()->prepare('UPDATE media_files SET name = ? WHERE workspace_id = ? AND id = ?')->execute([$n, $wid, $id]); $json(['ok' => true]); }
    if ($act === 'tags') { db()->prepare('UPDATE media_files SET tags = ? WHERE workspace_id = ? AND id = ?')->execute([mb_substr(trim((string)($_POST['tags'] ?? '')), 0, 500), $wid, $id]); $json(['ok' => true]); }
    if ($act === 'move') { $f = $fclean($_POST['folder'] ?? ''); foreach ($ids as $x) db()->prepare('UPDATE media_files SET folder = ? WHERE workspace_id = ? AND id = ?')->execute([$f, $wid, $x]); $json(['ok' => true]); }
    if ($act === 'delete') { media_trash($wid, $ids); log_activity('media.trash', (string)count($ids)); $json(['ok' => true]); }
    if ($act === 'restore') { media_restore($wid, $ids); $json(['ok' => true]); }
    if ($act === 'purge') { foreach ($ids as $x) { $r = media_get($wid, $x); if ($r && $r['deleted_at']) media_delete($wid, $x); } log_activity('media.delete', (string)count($ids)); $json(['ok' => true]); }
    if ($act === 'empty_trash') { foreach (media_list($wid, ['trash' => true]) as $r) media_delete($wid, $r['id']); $json(['ok' => true]); }
    if ($act === 'star') { $on = !empty($_POST['on']) ? 1 : 0; foreach ($ids as $x) db()->prepare('UPDATE media_files SET starred = ? WHERE workspace_id = ? AND id = ?')->execute([$on, $wid, $x]); $json(['ok' => true]); }
    if ($act === 'folder_rename') { // بيغيّر الفولدر وكل اللي جواه
        $old = $fclean($_POST['from'] ?? ''); $new = $fclean($_POST['to'] ?? ''); if ($old === '' || $new === '') $json(['ok' => false]);
        db()->prepare("UPDATE media_files SET folder = CONCAT(?, SUBSTRING(folder, ?)) WHERE workspace_id = ? AND (folder = ? OR folder LIKE ?)")->execute([$new, mb_strlen($old) + 1, $wid, $old, $old . '/%']);
        $all = array_map(function ($f) use ($old, $new) { return $f === $old || strpos($f, $old . '/') === 0 ? $new . substr($f, strlen($old)) : $f; }, $fall());
        save_setting($fkey, json_encode(array_values(array_unique($all)), JSON_UNESCAPED_UNICODE)); $json(['ok' => true, 'folder' => $new]);
    }
    if ($act === 'folder_delete') { // الملفات اللي جواه بتطلع للفولدر الأب، مش بتتمسح
        $old = $fclean($_POST['folder'] ?? ''); if ($old === '') $json(['ok' => false]); $parent = strpos($old, '/') !== false ? substr($old, 0, strrpos($old, '/')) : '';
        db()->prepare("UPDATE media_files SET folder = ? WHERE workspace_id = ? AND (folder = ? OR folder LIKE ?)")->execute([$parent, $wid, $old, $old . '/%']);
        save_setting($fkey, json_encode(array_values(array_filter($fall(), function ($f) use ($old) { return $f !== $old && strpos($f, $old . '/') !== 0; })), JSON_UNESCAPED_UNICODE)); $json(['ok' => true, 'folder' => $parent]);
    }
    if ($act === 'folder') {
        $f = $fclean($_POST['name'] ?? ''); if ($f === '') $json(['ok' => false]);
        $all = (array)json_decode((string)setting('media_folders_' . $wid, '[]'), true); if (!in_array($f, $all, true)) $all[] = $f;
        save_setting('media_folders_' . $wid, json_encode(array_values($all), JSON_UNESCAPED_UNICODE)); $json(['ok' => true, 'folder' => $f]);
    }
    if ($act === 'ai_tag') {
        if (!can('ai.use') || !function_exists('ai_connection') || !ai_connection()) $json(['ok' => false, 'error' => t('اربط مزود ذكاء اصطناعي الأول من صفحة التكاملات.')]);
        $out = []; $errs = [];
        foreach (array_slice($ids, 0, 5) as $x) { try { $out[] = media_ai_tag($wid, $x, !empty($_POST['rename'])); } catch (Throwable $e) { $errs[] = $e->getMessage(); } }
        if ($out) log_activity('media.ai_tag', (string)count($out));
        $json(['ok' => (bool)$out || !$errs, 'items' => $out, 'errors' => $errs]);
    }
    if ($act === 'share_create') {
        $f = $fclean($_POST['folder'] ?? ''); $days = max(0, min(365, (int)($_POST['days'] ?? 7)));
        $sh = media_share_create($wid, $f, $days, !empty($_POST['dl'])); log_activity('media.share', $f); $json(['ok' => true] + $sh);
    }
    if ($act === 'share_revoke') { db()->prepare('DELETE FROM media_shares WHERE workspace_id = ? AND id = ?')->execute([$wid, (int)($_POST['sid'] ?? 0)]); $json(['ok' => true]); }
    if ($act === 'public') {
        $err = null; $z = zernio_or_null($err); if (!$z) $json(['ok' => false, 'error' => $err ?: t('زيرنيو مش مربوط')]);
        $out = [];
        $ctx = preg_replace('/[^a-z_]/', '', (string)($_POST['ctx'] ?? 'link')) ?: 'link';
        try { foreach ($ids as $x) $out[$x] = media_public_url($z, $wid, $x, $ctx, mb_substr((string)($_POST['ref'] ?? ''), 0, 200)); } catch (Throwable $e) { $json(['ok' => false, 'error' => $e->getMessage(), 'urls' => $out]); }
        $json(['ok' => true, 'urls' => $out]);
    }
    $json(['ok' => false, 'error' => 'bad action']);
}

render(t('مكتبة الوسائط'), 'media', function () {
    ?>
    <div id="media-page" data-media-page="1" class="min-h-[60vh]"><div class="card p-8 text-center text-sm text-ink-500"><?= e(t('بيحمّل...')) ?></div></div>
    <script>document.addEventListener('DOMContentLoaded', function () { window.mediaLoad(function () { window.Media.page(document.getElementById('media-page')); }); });</script>
    <?php
}, ['full' => true]);
