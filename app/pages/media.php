<?php
/** مكتبة الوسائط: صور وفيديو وملفات الشركة — بتتستخدم من أي صفحة عن طريق window.mediaPick() */
$wid = (int)ws('id');

// ---- عرض ملف للمستخدم المسجل
if (isset($_GET['file'])) {
    $r = media_get($wid, (int)$_GET['file']);
    $p = $r ? media_file_path($wid, $r) : '';
    if (!$r || !is_file($p)) { http_response_code(404); exit; }
    header('Content-Type: ' . $r['mime']);
    header('Content-Length: ' . filesize($p));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    $inline = in_array(media_kind($r['mime']), ['image', 'video', 'audio'], true) || $r['mime'] === 'application/pdf';
    header('Content-Disposition: ' . ($inline && !isset($_GET['dl']) ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($r['name']));
    if ($r['mime'] !== 'application/pdf' && !$inline) header("Content-Security-Policy: sandbox");
    readfile($p); exit;
}

// ---- JSON
$json = function ($a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; };
if (($_GET['ajax'] ?? '') === 'list') {
    $json(['ok' => true, 'items' => media_list($wid, ['folder' => (string)($_GET['folder'] ?? '*'), 'kind' => (string)($_GET['kind'] ?? ''), 'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100)]),
        'folders' => media_folders($wid), 'usage' => media_usage($wid), 'max' => media_max_bytes()]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['action'] ?? '');
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
    if ($act === 'rename') { $n = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 255); if ($n === '') $json(['ok' => false]); db()->prepare('UPDATE media_files SET name = ? WHERE workspace_id = ? AND id = ?')->execute([$n, $wid, $id]); $json(['ok' => true]); }
    if ($act === 'tags') { db()->prepare('UPDATE media_files SET tags = ? WHERE workspace_id = ? AND id = ?')->execute([mb_substr(trim((string)($_POST['tags'] ?? '')), 0, 500), $wid, $id]); $json(['ok' => true]); }
    if ($act === 'move') { $f = mb_substr(trim((string)($_POST['folder'] ?? '')), 0, 120); foreach ($ids as $x) db()->prepare('UPDATE media_files SET folder = ? WHERE workspace_id = ? AND id = ?')->execute([$f, $wid, $x]); $json(['ok' => true]); }
    if ($act === 'delete') { foreach ($ids as $x) media_delete($wid, $x); log_activity('media.delete', (string)count($ids)); $json(['ok' => true]); }
    if ($act === 'folder') {
        $f = mb_substr(trim(str_replace(['/', '\\'], '', (string)($_POST['name'] ?? ''))), 0, 120); if ($f === '') $json(['ok' => false]);
        $all = (array)json_decode((string)setting('media_folders_' . $wid, '[]'), true); if (!in_array($f, $all, true)) $all[] = $f;
        save_setting('media_folders_' . $wid, json_encode(array_values($all), JSON_UNESCAPED_UNICODE)); $json(['ok' => true, 'folder' => $f]);
    }
    if ($act === 'public') {
        $err = null; $z = zernio_or_null($err); if (!$z) $json(['ok' => false, 'error' => $err ?: t('زيرنيو مش مربوط')]);
        $out = [];
        try { foreach ($ids as $x) $out[$x] = media_public_url($z, $wid, $x); } catch (Throwable $e) { $json(['ok' => false, 'error' => $e->getMessage(), 'urls' => $out]); }
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
