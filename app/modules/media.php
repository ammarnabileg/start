<?php
/**
 * مكتبة الوسائط: صور وفيديو وملفات لكل شركة.
 * الملفات متخزنة في uploads/media/{wid}/ (مقفول للعامة) وبتتعرض للمستخدم المسجل من ?p=media&file=ID.
 * لما ملف يتستخدم في منشور أو إعلان، بيترفع لزيرنيو مرة واحدة ويتحفظ رابطه العام (public_url).
 */

function media_dir(int $wid): string { return ROOT_DIR . '/uploads/media/' . $wid; }

/** الأنواع المسموحة (من محتوى الملف، مش الامتداد) */
function media_types(): array
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
        'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/csv' => 'csv',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx', 'application/zip' => 'zip', 'audio/mpeg' => 'mp3'];
}

function media_kind(string $mime): string
{
    return strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : (strpos($mime, 'audio/') === 0 ? 'audio' : 'doc'));
}

/** أقصى حجم رفع فعلي على السيرفر */
function media_max_bytes(): int
{
    $b = function ($v) { $v = trim((string)$v); $n = (float)$v; switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; } return (int)$n; };
    $a = $b(ini_get('upload_max_filesize') ?: '2M'); $p = $b(ini_get('post_max_size') ?: '8M');
    return min($a ?: PHP_INT_MAX, $p ?: PHP_INT_MAX, 200 * 1024 * 1024);
}

function media_row(array $r): array
{
    return ['id' => (int)$r['id'], 'folder' => (string)$r['folder'], 'name' => (string)$r['name'], 'mime' => (string)$r['mime'], 'kind' => media_kind((string)$r['mime']),
        'size' => (int)$r['size'], 'w' => $r['width'] !== null ? (int)$r['width'] : null, 'h' => $r['height'] !== null ? (int)$r['height'] : null,
        'url' => url('media', ['file' => $r['id']]), 'thumb' => url('media', ['file' => $r['id'], 'thumb' => 1]), 'public' => $r['public_url'] ?: null, 'tags' => (string)($r['tags'] ?? ''), 'at' => (string)$r['created_at'],
        'star' => !empty($r['starred']), 'del' => !empty($r['deleted_at']) ? (string)$r['deleted_at'] : null];
}

function media_list(int $wid, array $f = []): array
{
    $w = ['workspace_id = ?', !empty($f['trash']) ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL']; $a = [$wid];
    if (isset($f['folder']) && $f['folder'] !== '*') { $w[] = 'folder = ?'; $a[] = (string)$f['folder']; }
    if (!empty($f['kind'])) { $m = ['image' => 'image/%', 'video' => 'video/%', 'audio' => 'audio/%'][$f['kind']] ?? null; if ($m) { $w[] = 'mime LIKE ?'; $a[] = $m; } elseif ($f['kind'] === 'doc') { $w[] = "mime NOT LIKE 'image/%' AND mime NOT LIKE 'video/%' AND mime NOT LIKE 'audio/%'"; } }
    if (!empty($f['q'])) { $w[] = '(name LIKE ? OR tags LIKE ?)'; $a[] = '%' . $f['q'] . '%'; $a[] = '%' . $f['q'] . '%'; }
    $st = db()->prepare('SELECT * FROM media_files WHERE ' . implode(' AND ', $w) . ' ORDER BY created_at DESC, id DESC LIMIT 500');
    $st->execute($a);
    return array_map('media_row', $st->fetchAll());
}

function media_folders(int $wid): array
{
    $st = db()->prepare("SELECT folder, COUNT(*) n FROM media_files WHERE workspace_id = ? AND deleted_at IS NULL GROUP BY folder ORDER BY folder");
    $st->execute([$wid]);
    $out = []; foreach ($st->fetchAll() as $r) $out[(string)$r['folder']] = (int)$r['n'];
    foreach ((array)json_decode((string)setting('media_folders_' . $wid, '[]'), true) as $f) if (!isset($out[$f])) $out[$f] = 0; // فولدرات فاضية
    ksort($out); return $out;
}

function media_get(int $wid, int $id): ?array
{
    $st = db()->prepare('SELECT * FROM media_files WHERE workspace_id = ? AND id = ?');
    $st->execute([$wid, $id]);
    return $st->fetch() ?: null;
}

function media_usage(int $wid): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(size),0) FROM media_files WHERE workspace_id = ?'); $st->execute([$wid]);
    return (int)$st->fetchColumn();
}

/** حفظ ملف مرفوع. بيرجع الصف أو يرمي استثناء برسالة واضحة */
function media_store_upload(int $wid, array $file, string $folder = ''): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? t('الملف أكبر من المسموح على السيرفر (%s).', media_fmt_size(media_max_bytes())) : t('الرفع فشل، جرّب تاني.'));
    }
    if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException(t('الرفع فشل، جرّب تاني.'));
    $mime = (string)(mime_content_type($file['tmp_name']) ?: '');
    if ($mime === 'application/octet-stream' && preg_match('/\.(docx|xlsx|pptx)$/i', (string)$file['name'])) $mime = array_search(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), media_types(), true) ?: $mime;
    $types = media_types();
    if (!isset($types[$mime])) throw new RuntimeException(t('نوع الملف ده مش مسموح: %s', $mime ?: '?'));
    return media_store_path($wid, $file['tmp_name'], (string)$file['name'], $mime, $folder, true);
}

/** حفظ ملف من مسار على السيرفر (رفع أو ملف مولد) */
function media_store_path(int $wid, string $src, string $name, string $mime, string $folder = '', bool $uploaded = false): array
{
    $dir = media_dir($wid); if (!is_dir($dir)) @mkdir($dir, 0750, true);
    // نفس الملف اترفع قبل كده؟ نرجّع القديم بدل نسخة مكررة
    $hash = @sha1_file($src);
    if ($hash) { $st = db()->prepare('SELECT * FROM media_files WHERE workspace_id = ? AND sha1 = ? LIMIT 1'); $st->execute([$wid, $hash]); if ($old = $st->fetch()) { if ($uploaded) @unlink($src); if (!empty($old['deleted_at'])) { db()->prepare('UPDATE media_files SET deleted_at = NULL, folder = ? WHERE id = ?')->execute([mb_substr(trim($folder), 0, 120), $old['id']]); $old = media_get($wid, (int)$old['id']); } return media_row($old) + ['dup' => true]; } }
    $ext = media_types()[$mime] ?? 'bin';
    $fn = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $fn;
    $ok = $uploaded ? move_uploaded_file($src, $dest) : copy($src, $dest);
    if (!$ok) throw new RuntimeException(t('ما قدرناش نحفظ الملف على السيرفر.'));
    @chmod($dest, 0640);
    $w = $h = null;
    if (media_kind($mime) === 'image' && ($sz = @getimagesize($dest))) { $w = (int)$sz[0]; $h = (int)$sz[1]; }
    $clean = trim(preg_replace('/[\x00-\x1f\/\\\\]+/u', '', $name)) ?: $fn;
    $st = db()->prepare('INSERT INTO media_files (workspace_id, folder, name, mime, size, width, height, path, uploaded_by, sha1) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$wid, mb_substr(trim($folder), 0, 120), mb_substr($clean, 0, 255), $mime, (int)filesize($dest), $w, $h, $fn, function_exists('current_user') ? ((int)(current_user()['id'] ?? 0) ?: null) : null, $hash ?: null]);
    return media_row(media_get($wid, (int)db()->lastInsertId()));
}

/** صورة مصغرة 480px (JPEG) متخزنة جنب الملف. لو GD مش موجود بيرجع الأصل */
function media_thumb_path(int $wid, array $r): string
{
    $src = media_file_path($wid, $r);
    if (media_kind((string)$r['mime']) !== 'image' || !function_exists('imagecreatetruecolor') || (int)$r['size'] < 120000) return $src;
    $dir = media_dir($wid) . '/thumbs'; $t = $dir . '/' . (int)$r['id'] . '.jpg';
    if (is_file($t)) return $t;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $fn = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp', 'image/gif' => 'imagecreatefromgif'][$r['mime']] ?? null;
    if (!$fn || !function_exists($fn) || !($im = @$fn($src))) return $src;
    $w = imagesx($im); $h = imagesy($im); $k = min(1, 480 / max($w, $h)); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $out = imagecreatetruecolor($nw, $nh); imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagejpeg($out, $t, 78); imagedestroy($im); imagedestroy($out);
    return is_file($t) ? $t : $src;
}

/** ضم صور الذكاء الاصطناعي المولدة (uploads/ai/{wid}) للمكتبة في فولدر لوحده */
function media_ai_folder(): string { return t('مولدة بالذكاء الاصطناعي'); }
function media_sync_ai(int $wid): int
{
    if (!function_exists('hf_lib_dir')) return 0;
    $files = glob(hf_lib_dir($wid) . '/*.{png,jpg,webp,mp4}', GLOB_BRACE) ?: []; if (!$files) return 0;
    $done = (array)json_decode((string)setting('media_ai_sync_' . $wid, '[]'), true); $n = 0;
    foreach ($files as $f) {
        $b = basename($f); if (in_array($b, $done, true)) continue;
        $mime = (string)(mime_content_type($f) ?: ''); if (!isset(media_types()[$mime])) { $done[] = $b; continue; }
        try { media_store_path($wid, $f, 'ai-' . $b, $mime, media_ai_folder()); $n++; } catch (Throwable $e) {}
        $done[] = $b; if ($n >= 40) break;
    }
    save_setting('media_ai_sync_' . $wid, json_encode(array_slice($done, -2000)));
    return $n;
}

function media_file_path(int $wid, array $r): string { return media_dir($wid) . '/' . basename((string)$r['path']); }

/** رابط عام للملف (للمنصات): بيترفع لزيرنيو أول مرة ويتحفظ */
function media_public_url(Zernio $z, int $wid, int $id): string
{
    $r = media_get($wid, $id); if (!$r) throw new RuntimeException(t('الملف مش موجود'));
    if (!empty($r['public_url'])) return (string)$r['public_url'];
    $u = $z->uploadFile(media_file_path($wid, $r), (string)$r['name'], (string)$r['mime']);
    db()->prepare('UPDATE media_files SET public_url = ? WHERE id = ?')->execute([$u, $id]);
    return $u;
}

/** حذف مؤقت (للسلة) — بيترجع بـ media_restore، وبيتمسح نهائي بعد 30 يوم */
function media_trash(int $wid, array $ids): void { foreach ($ids as $id) db()->prepare('UPDATE media_files SET deleted_at = NOW() WHERE workspace_id = ? AND id = ?')->execute([$wid, (int)$id]); }
function media_restore(int $wid, array $ids): void { foreach ($ids as $id) db()->prepare('UPDATE media_files SET deleted_at = NULL WHERE workspace_id = ? AND id = ?')->execute([$wid, (int)$id]); }
function media_purge_old(int $wid): void
{
    $st = db()->prepare('SELECT id FROM media_files WHERE workspace_id = ? AND deleted_at IS NOT NULL AND deleted_at < (NOW() - INTERVAL 30 DAY) LIMIT 100'); $st->execute([$wid]);
    foreach ($st->fetchAll() as $r) media_delete($wid, (int)$r['id']);
}

function media_delete(int $wid, int $id): void
{
    $r = media_get($wid, $id); if (!$r) return;
    $p = media_file_path($wid, $r); if (is_file($p)) @unlink($p);
    $t = media_dir($wid) . '/thumbs/' . (int)$r['id'] . '.jpg'; if (is_file($t)) @unlink($t);
    db()->prepare('DELETE FROM media_files WHERE workspace_id = ? AND id = ?')->execute([$wid, $id]);
}

function media_fmt_size(int $b): string
{
    return $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : ($b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB');
}

/**
 * خانة رفع ملف بتقبل كمان ملف من المكتبة: الـ JS بيبعت lib_{field}=ID.
 * بيرجع شكل $_FILES العادي (tmp_name = مسار الملف في المكتبة، lib = true) أو null.
 */
function media_lib_file(string $field): ?array
{
    $id = (int)($_POST['lib_' . $field] ?? 0); if (!$id) return null;
    $r = media_get((int)ws('id'), $id); if (!$r) return null;
    $p = media_file_path((int)ws('id'), $r); if (!is_file($p)) return null;
    return ['name' => (string)$r['name'], 'type' => (string)$r['mime'], 'tmp_name' => $p, 'error' => UPLOAD_ERR_OK, 'size' => (int)filesize($p), 'lib' => true, 'lib_id' => $id];
}

/** ملف مرفوع بأمان؟ (مرفوع فعلًا أو جاي من المكتبة) */
function media_is_file(?array $f): bool
{
    return $f && ($f['error'] ?? 1) === UPLOAD_ERR_OK && (!empty($f['lib']) ? is_file($f['tmp_name']) : is_uploaded_file((string)$f['tmp_name']));
}

/** نسخة من أي صورة/فيديو اترفع من أي صفحة في المكتبة (فولدر باسم الصفحة) — من غير ما يوقف لو فشل */
function media_capture(string $path, string $name, string $folder): void
{
    try {
        $mime = (string)(mime_content_type($path) ?: '');
        if (!isset(media_types()[$mime]) || !in_array(media_kind($mime), ['image', 'video'], true)) return;
        media_store_path((int)ws('id'), $path, $name, $mime, $folder);
    } catch (Throwable $e) { /* المكتبة اختيارية هنا */ }
}
