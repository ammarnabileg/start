<?php
/** صفحة عامة لفولدر متشارك برابط (من غير تسجيل دخول): عرض + تحميل. التوكن 40 حرف عشوائي وليه تاريخ انتهاء */
$tok = (string)($_GET['t'] ?? '');
$sh = media_share_get($tok);
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
if (!$sh) { http_response_code(404); echo '<!doctype html><meta charset="utf-8"><title>404</title><body style="font-family:system-ui;display:grid;place-items:center;height:100vh;color:#475569">' . e(t('الرابط ده مش موجود أو انتهت صلاحيته.')) . '</body>'; exit; }
$wid = (int)$sh['workspace_id']; $base = (string)$sh['folder'];
$inShare = function (array $r) use ($base) { return empty($r['deleted_at']) && ($base === '' || $r['folder'] === $base || strpos((string)$r['folder'], $base . '/') === 0); };

// ملف واحد
if (isset($_GET['file'])) {
    $r = media_get($wid, (int)$_GET['file']);
    if (!$r || !$inShare($r)) { http_response_code(404); exit; }
    $p = isset($_GET['thumb']) ? media_thumb_path($wid, $r) : media_file_path($wid, $r);
    if (!is_file($p)) { http_response_code(404); exit; }
    $dl = isset($_GET['dl']) && $sh['allow_download'];
    $inline = in_array(media_kind($r['mime']), ['image', 'video', 'audio'], true) || $r['mime'] === 'application/pdf';
    if (!$inline && !$sh['allow_download']) { http_response_code(403); exit; }
    header('Content-Type: ' . (isset($_GET['thumb']) && strpos($p, '/thumbs/') !== false ? 'image/jpeg' : $r['mime']));
    header('Content-Length: ' . filesize($p)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: ' . ($dl || !$inline ? 'attachment' : 'inline') . "; filename*=UTF-8''" . rawurlencode($r['name']));
    readfile($p); exit;
}

$st = db()->prepare("SELECT * FROM media_files WHERE workspace_id = ? AND deleted_at IS NULL AND (? = '' OR folder = ? OR folder LIKE ?) ORDER BY folder, created_at DESC LIMIT 1000");
$st->execute([$wid, $base, $base, $base . '/%']);
$files = $st->fetchAll();

// الكل كـ ZIP
if (isset($_GET['zip']) && $sh['allow_download'] && class_exists('ZipArchive')) {
    $tmp = tempnam(sys_get_temp_dir(), 'sz'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE); $used = [];
    foreach ($files as $r) { $p = media_file_path($wid, $r); if (!is_file($p)) continue; $rel = $base !== '' ? ltrim(substr((string)$r['folder'], strlen($base)), '/') : (string)$r['folder'];
        $n = ($rel !== '' ? $rel . '/' : '') . preg_replace('/[\x00-\x1f\/\\\\]+/u', '_', (string)$r['name']); $k = 2; $o = $n; while (isset($used[$n])) $n = preg_replace('/(\.[^.\/]+)?$/', ' (' . $k++ . ')$1', $o, 1); $used[$n] = 1; $z->addFile($p, $n); }
    $z->close(); header('Content-Type: application/zip'); header('Content-Length: ' . filesize($tmp)); header('Content-Disposition: attachment; filename="' . ($base !== '' ? preg_replace('/[^\w\-]+/u', '_', basename($base)) : 'files') . '.zip"');
    readfile($tmp); @unlink($tmp); exit;
}
if (empty($_SESSION['share_seen'][$sh['id']])) { $_SESSION['share_seen'][$sh['id']] = 1; db()->prepare('UPDATE media_shares SET views = views + 1 WHERE id = ?')->execute([$sh['id']]); }

$wsName = (string)(db()->query('SELECT name FROM workspaces WHERE id = ' . $wid)->fetchColumn() ?: '');
$title = $base !== '' ? basename($base) : t('ملفات مشتركة');
$u = function (array $q) use ($tok) { return url('share', ['t' => $tok] + $q); };
$groups = []; foreach ($files as $r) { $rel = $base !== '' ? ltrim(substr((string)$r['folder'], strlen($base)), '/') : (string)$r['folder']; $groups[$rel][] = $r; }
?><!doctype html>
<?= html_open() ?>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title . ' · ' . $wsName) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= e(APP_VERSION) ?>">
<style>:root{--ink-50:245 247 251;--ink-100:237 241 248;--ink-200:221 227 238;--ink-300:195 205 223;--ink-400:138 151 176;--ink-500:95 109 136;--ink-600:69 82 109;--ink-700:49 61 87;--ink-800:29 40 66;--ink-900:13 22 48;--brand-50:238 244 255;--brand-100:219 231 255;--brand-500:59 111 240;--brand-600:37 87 224;--brand-700:29 69 189;--cyan-600:8 145 178;--surface:255 255 255}</style>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;600&display=swap" rel="stylesheet">
</head>
<body class="bg-ink-50 min-h-screen">
  <header class="bg-white border-b border-ink-200">
    <div class="max-w-6xl mx-auto px-4 py-4 flex flex-wrap items-center gap-3">
      <span class="w-10 h-10 rounded-xl bg-gradient-to-l from-brand-600 to-cyan-600 grid place-items-center text-white text-lg"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a1 1 0 011-1h5l2 2h9a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1z"/></svg></span>
      <div class="min-w-0 flex-1"><h1 class="font-semibold text-ink-900 truncate"><?= e($title) ?></h1><p class="text-xs text-ink-500"><?= e(t('من %s · %s ملف', $wsName, count($files))) ?><?php if ($sh['expires_at']): ?> · <?= e(t('الرابط صالح لحد %s', fmt_date($sh['expires_at'], 'd/m/Y'))) ?><?php endif; ?></p></div>
      <?php if ($sh['allow_download'] && $files): ?><a href="<?= e($u(['zip' => 1])) ?>" class="btn-primary text-sm">⤓ <?= e(t('تحميل الكل (ZIP)')) ?></a><?php endif; ?>
    </div>
  </header>
  <main class="max-w-6xl mx-auto px-4 py-6 space-y-8">
    <?php if (!$files): ?><p class="text-center text-ink-500 py-20"><?= e(t('الفولدر ده فاضي.')) ?></p><?php endif; ?>
    <?php foreach ($groups as $rel => $list): ?>
      <section>
        <?php if ($rel !== ''): ?><h2 class="text-sm font-semibold text-ink-700 mb-3"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6a1 1 0 011-1h5l2 2h9a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1z"/></svg> <?= e($rel) ?></h2><?php endif; ?>
        <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
          <?php foreach ($list as $r): $k = media_kind($r['mime']); ?>
            <figure class="card overflow-hidden group">
              <a href="<?= e($u(['file' => $r['id']])) ?>" target="_blank" rel="noopener" class="block aspect-square bg-ink-100 grid place-items-center overflow-hidden">
                <?php if ($k === 'image'): ?><img src="<?= e($u(['file' => $r['id'], 'thumb' => 1])) ?>" alt="" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition">
                <?php elseif ($k === 'video'): ?><video src="<?= e($u(['file' => $r['id']])) ?>#t=0.5" preload="metadata" muted class="w-full h-full object-cover"></video>
                <?php else: ?><span class="text-4xl"><svg class="w-4 h-4 inline-block align-[-3px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6"/></svg></span><?php endif; ?>
              </a>
              <figcaption class="p-2.5 flex items-center gap-2 text-xs"><span class="flex-1 truncate text-ink-800" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></span><span class="text-ink-400" dir="ltr"><?= e(media_fmt_size((int)$r['size'])) ?></span>
                <?php if ($sh['allow_download']): ?><a href="<?= e($u(['file' => $r['id'], 'dl' => 1])) ?>" class="text-brand-700 hover:underline" aria-label="<?= e(t('تحميل')) ?>">⤓</a><?php endif; ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </main>
</body></html>
<?php exit;
