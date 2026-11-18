<?php
/* قراءة موقع الشركة بالكامل (حتى 10,000 صفحة) بدون ذكاء اصطناعي:
 * مهمة في الخلفية: تكتشف الصفحات من sitemap.xml والروابط الداخلية، تحترم robots.txt، تقرأ 8 صفحات في نفس الوقت،
 * وتستخرج البيانات بنفس قواعد kb_import_site وتدمجها. بتكمّل من التشغيل الدوري لو المستخدم قفل الصفحة. */

const KBC_CAP = 10000;

function kbc_get(int $wid, int $id): ?array
{
    $st = db()->prepare('SELECT * FROM kb_crawls WHERE id = ? AND workspace_id = ?'); $st->execute([$id, $wid]);
    return $st->fetch() ?: null;
}
function kbc_active(int $wid): ?array
{
    $st = db()->prepare("SELECT * FROM kb_crawls WHERE workspace_id = ? AND status IN ('running', 'done', 'stopped') ORDER BY id DESC LIMIT 1"); $st->execute([$wid]);
    $c = $st->fetch() ?: null;
    return $c && ($c['status'] === 'running' || strtotime((string)$c['updated_at']) > time() - 86400) ? $c : null;
}
function kbc_norm(string $u): string
{
    $u = preg_replace('~#.*$~', '', trim($u));
    $p = parse_url($u); if (!$p || empty($p['host'])) return '';
    $path = $p['path'] ?? '/'; if ($path === '') $path = '/';
    $q = '';
    if (!empty($p['query'])) { parse_str($p['query'], $qa); foreach (array_keys($qa) as $k) if (preg_match('/^(utm_|fbclid|gclid|ref$|share|replytocom|sessionid|sid$)/i', (string)$k)) unset($qa[$k]); ksort($qa); $q = $qa ? '?' . http_build_query($qa) : ''; }
    return strtolower($p['scheme'] ?? 'https') . '://' . strtolower($p['host']) . (isset($p['port']) ? ':' . (int)$p['port'] : '') . $path . $q;
}
function kbc_host(string $u): string { return preg_replace('/^www\./', '', strtolower((string)parse_url($u, PHP_URL_HOST))); }
function kbc_skip(string $u): bool
{
    return (bool)preg_match('~\.(pdf|jpe?g|png|gif|webp|svg|ico|zip|rar|7z|mp4|mov|avi|mp3|wav|docx?|xlsx?|pptx?|css|js|json|xml|woff2?|ttf|eot)(\?|$)~i', $u)
        || (bool)preg_match('~/(wp-admin|wp-login|cart|checkout|my-account|login|logout|register|signin|signup|feed)(/|$|\?)~i', $u)
        || (bool)preg_match('~[?&](add-to-cart|orderby|filter_|min_price|max_price|replytocom)=~i', $u);
}
function kbc_allowed(string $u, array $dis): bool
{
    $path = (string)(parse_url($u, PHP_URL_PATH) ?? '/') . (($q = parse_url($u, PHP_URL_QUERY)) ? '?' . $q : '');
    foreach ($dis as $d) if ($d !== '' && strpos($path, $d) === 0) return false;
    return true;
}
function kbc_enqueue(array $c, array $urls): int
{
    $have = (int)db()->query('SELECT COUNT(*) FROM kb_crawl_urls WHERE crawl_id = ' . (int)$c['id'])->fetchColumn();
    $room = KBC_CAP - $have; if ($room <= 0) return 0;
    $meta = json_decode((string)$c['meta'], true) ?: []; $dis = (array)($meta['dis'] ?? []); $n = 0;
    $ins = db()->prepare('INSERT IGNORE INTO kb_crawl_urls (crawl_id, h, url) VALUES (?, ?, ?)');
    foreach ($urls as $u) {
        if ($n >= $room) break;
        $u = kbc_norm($u); if ($u === '' || kbc_host($u) !== $c['host'] || kbc_skip($u) || !kbc_allowed($u, $dis) || strlen($u) > 1900) continue;
        $ins->execute([(int)$c['id'], sha1($u), $u]); $n += $ins->rowCount();
    }
    if ($n) db()->prepare('UPDATE kb_crawls SET found = found + ? WHERE id = ?')->execute([$n, (int)$c['id']]);
    return $n;
}
/** sitemap.xml (وفهارس الـ sitemap) — حتى 10,000 رابط */
function kbc_sitemaps(string $root, array $sm): array
{
    $out = []; $queue = $sm ?: [rtrim($root, '/') . '/sitemap.xml', rtrim($root, '/') . '/sitemap_index.xml']; $seen = [];
    while ($queue && count($seen) < 60 && count($out) < KBC_CAP) {
        $s = array_shift($queue); if (isset($seen[$s])) continue; $seen[$s] = 1;
        $r = safe_fetch($s, ['max' => 8000000, 'timeout' => 15]); if (!empty($r['err'])) continue;
        $x = (string)($r['body'] ?? ''); if (preg_match('/\.gz$/i', $s) && function_exists('gzdecode')) $x = (string)@gzdecode($x);
        if (!preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?\s*([^<\]]+?)\s*(?:\]\]>)?\s*</loc>~i', $x, $m)) continue;
        $isIndex = stripos($x, '<sitemapindex') !== false;
        foreach ($m[1] as $loc) { $loc = html_entity_decode(trim($loc)); if ($isIndex) $queue[] = $loc; else $out[] = $loc; }
    }
    return $out;
}
function kbc_start(int $wid, string $url): array
{
    $url = trim($url); if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    $home = kbi_fetch($url); if (!empty($home['err'])) return ['err' => $home['err']];
    $root = kbc_norm((string)$home['url']); $host = kbc_host($root);
    db()->prepare("UPDATE kb_crawls SET status = 'cancelled' WHERE workspace_id = ? AND status = 'running'")->execute([$wid]);
    db()->prepare('DELETE u FROM kb_crawl_urls u JOIN kb_crawls c ON c.id = u.crawl_id WHERE c.workspace_id = ?')->execute([$wid]);
    // robots.txt: المسارات الممنوعة + روابط الـ sitemap
    $dis = []; $sm = [];
    $rb = safe_fetch(preg_replace('~^(https?://[^/]+).*$~', '$1', $root) . '/robots.txt', ['max' => 300000, 'timeout' => 8]);
    if (empty($rb['err'])) { $ua = false; foreach (preg_split('/\R/', (string)($rb['body'] ?? '')) as $ln) { $ln = trim(preg_replace('/#.*$/', '', $ln));
        if (preg_match('/^user-agent:\s*(.+)$/i', $ln, $mm)) $ua = trim($mm[1]) === '*';
        elseif ($ua && preg_match('/^disallow:\s*(\S+)/i', $ln, $mm)) $dis[] = $mm[1];
        elseif (preg_match('/^sitemap:\s*(\S+)/i', $ln, $mm)) $sm[] = $mm[1]; } }
    db()->prepare('INSERT INTO kb_crawls (workspace_id, user_id, root, host, status, meta, acc) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$wid, (int)(current_user()['id'] ?? 0) ?: null, $root, $host, 'running', json_encode(['dis' => array_slice(array_values(array_unique($dis)), 0, 200)]), '{}']);
    $c = kbc_get($wid, (int)db()->lastInsertId());
    kbc_enqueue($c, array_merge([$root], kbc_sitemaps($root, array_slice($sm, 0, 10))));
    log_activity('kb.crawl_start', $root);
    return ['id' => (int)$c['id']];
}
/** جلب متوازي لصفحات من نفس الموقع (العنوان IP اتحقق إنه عام) */
function kbc_fetch_many(array $urls, string $host): array
{
    $ips = getenv('UI_FIXTURES') && in_array($host, ['127.0.0.1', 'localhost'], true) ? ['127.0.0.1'] : (@gethostbynamel($host) ?: (@gethostbynamel('www.' . $host) ?: []));
    $out = [];
    if (!$ips) { foreach ($urls as $u) $out[$u] = ['err' => 'dns']; return $out; }
    if (!getenv('UI_FIXTURES')) foreach ($ips as $ip) if (!safe_fetch_public_ip($ip)) { foreach ($urls as $u) $out[$u] = ['err' => 'ip']; return $out; }
    $mh = curl_multi_init(); $hs = [];
    foreach ($urls as $u) {
        $p = parse_url($u); $port = (int)($p['port'] ?? (($p['scheme'] ?? 'https') === 'https' ? 443 : 80)); $h = strtolower((string)$p['host']);
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$h . ':' . $port . ':' . $ips[0]], CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AIPushProfile/1.0)', CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: ar,en;q=0.8'],
            CURLOPT_BUFFERSIZE => 65536, CURLOPT_MAXFILESIZE => 1500000]);
        curl_multi_add_handle($mh, $ch); $hs[$u] = $ch;
    }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st === CURLM_OK);
    foreach ($hs as $u => $ch) {
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $body = (string)curl_multi_getcontent($ch);
        if ($code >= 300 && $code < 400 && $loc !== '') $out[$u] = ['redirect' => $loc];
        elseif ($code >= 200 && $code < 300 && ($type === '' || stripos($type, 'html') !== false)) $out[$u] = ['html' => substr($body, 0, 1500000)];
        else $out[$u] = ['err' => (string)$code];
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}
/** دمج نتيجة دفعة صفحات في المجمّع: الحقل الأول اللي يتلاقي يكسب، والصفوف بتتجمع بدون تكرار */
function kbc_merge(array $acc, array $r): array
{
    foreach ((array)($r['sections'] ?? []) as $sec => $data) {
        if (isset($data['rows'])) { $rows = (array)($acc['sections'][$sec]['rows'] ?? []); $keys = [];
            foreach ($rows as $x) $keys[mb_strtolower(trim((string)(reset($x) ?: '')))] = 1;
            foreach ((array)$data['rows'] as $x) { $k = mb_strtolower(trim((string)(reset($x) ?: ''))); if ($k === '' || isset($keys[$k])) continue; $keys[$k] = 1; $rows[] = $x; }
            $acc['sections'][$sec]['rows'] = array_slice($rows, 0, $sec === 'faq' ? 200 : 500);
        } else foreach ((array)$data as $f => $v) if (trim((string)($acc['sections'][$sec][$f] ?? '')) === '' && trim((string)$v) !== '') { $acc['sections'][$sec][$f] = $v; if (isset($r['src'][$sec . '.' . $f])) $acc['src'][$sec . '.' . $f] = $r['src'][$sec . '.' . $f]; }
    }
    $o = array_values(array_unique(array_merge((array)($acc['offers'] ?? []), (array)($r['offers'] ?? []))));
    $acc['offers'] = array_slice($o, 0, 100);
    return $acc;
}
/** خطوة: تقرأ صفحات لحد ما الوقت يخلص. بترجع حالة التقدم */
function kbc_step(array $c, float $budget = 12.0): array
{
    if (!(int)db()->query("SELECT GET_LOCK('kbc_" . (int)$c['id'] . "', 0)")->fetchColumn()) return $c;
    $c = kbc_get((int)$c['workspace_id'], (int)$c['id']) ?: $c;
    $t0 = microtime(true); $acc = json_decode((string)$c['acc'], true) ?: [];
    while ($c['status'] === 'running' && microtime(true) - $t0 < $budget) {
        $live = db()->prepare('SELECT status FROM kb_crawls WHERE id = ?'); $live->execute([(int)$c['id']]); if ($live->fetchColumn() !== 'running') { $c['status'] = 'stopped'; $stoppedNow = true; break; }
        $st = db()->prepare('SELECT id, url FROM kb_crawl_urls WHERE crawl_id = ? AND st = 0 ORDER BY id LIMIT 8'); $st->execute([(int)$c['id']]); $batch = $st->fetchAll();
        if (!$batch) { $c['status'] = 'done'; break; }
        $res = kbc_fetch_many(array_column($batch, 'url'), $c['host']); $pages = []; $links = []; $ok = 0; $bad = 0; $chars = 0;
        foreach ($batch as $b) {
            $r = $res[$b['url']] ?? ['err' => 'x'];
            if (isset($r['redirect'])) { $links[] = kbi_abs($r['redirect'], $b['url']); $st2 = 2; $bad++; }
            elseif (isset($r['html'])) { $pages[$b['url']] = $r['html']; $st2 = 1; $ok++;
                // النص المقروء يتحفظ عشان المستخدم يرجعله في أي وقت (حتى لو النت فصل أو الجهاز قفل)
                $tt = preg_match('~<title[^>]*>(.*?)</title>~is', $r['html'], $tm) ? mb_substr(kbi_txt($tm[1]), 0, 250) : '';
                $plain = trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags(preg_replace(['~<(head|script|style|noscript|svg)\b.*?</\1>~is', '~<(br|/p|/li|/h[1-6]|/div|/tr)\b[^>]*>~i'], [' ', "\n"], $r['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                $plain = mb_substr($plain, 0, 200000); $ch = mb_strlen($plain); $chars += $ch;
                db()->prepare('UPDATE kb_crawl_urls SET chars = ?, title = ?, txt = ? WHERE id = ?')->execute([$ch, $tt !== '' ? $tt : null, gzcompress($plain, 6), (int)$b['id']]);
                if (preg_match_all('~<a\b[^>]*href=["\']([^"\'#]+)["\']~i', $r['html'], $m)) foreach ($m[1] as $h) $links[] = kbi_abs(html_entity_decode($h), $b['url']); }
            else { $st2 = 2; $bad++; }
            db()->prepare('UPDATE kb_crawl_urls SET st = ? WHERE id = ?')->execute([$st2, (int)$b['id']]);
        }
        if ($links) kbc_enqueue($c, array_unique($links));
        if ($pages) { $r = kb_import_site($c['root'], $pages); if (empty($r['err'])) $acc = kbc_merge($acc, $r); }
        db()->prepare('UPDATE kb_crawls SET done = done + ?, failed = failed + ?, chars = chars + ? WHERE id = ?')->execute([$ok, $bad, $chars, (int)$c['id']]);
    }
    db()->prepare('UPDATE kb_crawls SET acc = ?, status = ?, updated_at = NOW() WHERE id = ? AND status = \'running\'')->execute([json_encode($acc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $c['status'], (int)$c['id']]);
    if (!empty($stoppedNow)) { db()->prepare('UPDATE kb_crawls SET acc = ? WHERE id = ?')->execute([json_encode($acc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int)$c['id']]); kbc_to_kb($c); } // آخر دفعة بعد الإيقاف تدخل في الملف
    if ($c['status'] === 'done') { db()->prepare('DELETE FROM kb_crawl_urls WHERE crawl_id = ? AND st <> 1')->execute([(int)$c['id']]); log_activity('kb.crawl_done', $c['root']); kbc_to_kb($c); }
    db()->query("SELECT RELEASE_LOCK('kbc_" . (int)$c['id'] . "')");
    return kbc_get((int)$c['workspace_id'], (int)$c['id']);
}
/** التشغيل الدوري: يكمّل أي قراءة شغالة حتى لو المستخدم قفل الصفحة */
function kbc_tick(int $wid): int
{
    $st = db()->prepare("SELECT * FROM kb_crawls WHERE workspace_id = ? AND status = 'running' ORDER BY id DESC LIMIT 1"); $st->execute([$wid]); $c = $st->fetch();
    if (!$c) return 0;
    $before = (int)$c['done']; $c = kbc_step($c, 40.0);
    return (int)$c['done'] - $before;
}

/** الصفحات المقروءة (للعرض والبحث) */
function kbc_pages(array $c, string $q = '', int $off = 0): array
{
    $w = 'crawl_id = ? AND st = 1'; $p = [(int)$c['id']];
    if ($q !== '') { $w .= ' AND (url LIKE ? OR title LIKE ?)'; $p[] = '%' . $q . '%'; $p[] = '%' . $q . '%'; }
    $st = db()->prepare("SELECT id, url, title, chars FROM kb_crawl_urls WHERE $w ORDER BY id LIMIT 50 OFFSET " . max(0, $off)); $st->execute($p);
    $n = db()->prepare("SELECT COUNT(*) FROM kb_crawl_urls WHERE $w"); $n->execute($p);
    return ['items' => array_map(function ($r) { return ['id' => (int)$r['id'], 'url' => (string)$r['url'], 'title' => (string)$r['title'], 'chars' => (int)$r['chars']]; }, $st->fetchAll()), 'total' => (int)$n->fetchColumn()];
}
function kbc_page_text(array $c, int $pid): ?array
{
    $st = db()->prepare('SELECT url, title, txt FROM kb_crawl_urls WHERE id = ? AND crawl_id = ? AND st = 1'); $st->execute([$pid, (int)$c['id']]); $r = $st->fetch();
    if (!$r) return null;
    return ['url' => (string)$r['url'], 'title' => (string)$r['title'], 'text' => $r['txt'] !== null ? (string)@gzuncompress((string)$r['txt']) : ''];
}

/** نص صفحات الموقع المقروءة يتحفظ كملف من ملفات الشركة (يستخدمه الذكاء الاصطناعي في الردود). ملف واحد لكل موقع، بيتبدل لو اتقرا تاني */
function kbc_to_kb(array $c): int
{
    $wid = (int)$c['workspace_id']; $name = mb_substr(t('الموقع: %s', $c['host']), 0, 190);
    $old = db()->prepare("SELECT id FROM kb_files WHERE workspace_id = ? AND method = 'site' AND name = ?"); $old->execute([$wid, $name]);
    foreach ($old->fetchAll(PDO::FETCH_COLUMN) as $oid) { db()->prepare('DELETE FROM kb_chunks WHERE file_id = ?')->execute([(int)$oid]); db()->prepare('DELETE FROM kb_files WHERE id = ?')->execute([(int)$oid]); }
    db()->prepare("INSERT INTO kb_files (workspace_id, name, mime, size, method, content, read_state, chunked) VALUES (?, ?, 'text/html', 0, 'site', '', 'done', 1)")->execute([$wid, $name]);
    $fid = (int)db()->lastInsertId(); $seq = 0; $chars = 0; $preview = '';
    $ins = db()->prepare('INSERT INTO kb_chunks (workspace_id, file_id, seq, body) VALUES (?, ?, ?, ?)');
    $last = 0;
    while (true) {
        $st = db()->prepare('SELECT id, url, title, txt FROM kb_crawl_urls WHERE crawl_id = ? AND st = 1 AND id > ? ORDER BY id LIMIT 200'); $st->execute([(int)$c['id'], $last]); $rows = $st->fetchAll();
        if (!$rows) break;
        foreach ($rows as $r) {
            $last = (int)$r['id']; $txt = $r['txt'] !== null ? trim((string)@gzuncompress((string)$r['txt'])) : ''; if ($txt === '') continue;
            $head = trim((string)$r['title']) !== '' ? $r['title'] . ' — ' . $r['url'] : $r['url'];
            foreach (kb_chunks($txt, 1500) as $ch) { $ins->execute([$wid, $fid, ++$seq, '[' . $head . "]\n" . $ch]); }
            $chars += mb_strlen($txt);
            if (mb_strlen($preview) < 200000) $preview .= ($preview !== '' ? "\n\n" : '') . '[' . $head . "]\n" . mb_substr($txt, 0, 4000);
        }
    }
    db()->prepare('UPDATE kb_files SET content = ?, chars = ?, size = ? WHERE id = ?')->execute([mb_substr($preview, 0, 1000000), $chars, $chars, $fid]);
    return $fid;
}
