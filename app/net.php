<?php
/**
 * جلب رابط كتبه المستخدم بأمان (ضد SSRF):
 * - http/https بس، وبورتات الويب المعروفة بس.
 * - الدومين بيتحل لعنوان IP مرة واحدة، والعنوان لازم يكون عام (مش شبكة داخلية ولا محجوز)،
 *   وبعدين الاتصال بيتثبت على نفس العنوان (CURLOPT_RESOLVE) عشان مايتغيرش بين الفحص والاتصال (DNS rebinding).
 * - التحويلات (redirect) بتتتبع يدوي، وكل تحويل بيتفحص من الأول.
 * - حد أقصى للحجم والوقت.
 * في الاختبارات بس (UI_FIXTURES) مسموح بـ 127.0.0.1.
 *
 * $o: max (بايت)، accept (regex لنوع المحتوى)، file (true = يحفظ في ملف مؤقت)، ua، headers
 * بيرجع ['url', 'body' | 'file', 'type', 'code'] أو ['err' => رسالة]
 */
function safe_fetch(string $url, array $o = []): array
{
    $max = (int)($o['max'] ?? 600000);
    $fx = (bool)getenv('UI_FIXTURES');
    for ($hop = 0; $hop < 5; $hop++) {
        if (!preg_match('~^https?://~i', $url)) return ['err' => t('اكتب رابط يبدأ بـ https://')];
        $p = parse_url($url);
        $host = strtolower((string)($p['host'] ?? ''));
        if ($host === '' || isset($p['user']) || isset($p['pass'])) return ['err' => t('الرابط مش صحيح.')];
        $scheme = strtolower((string)$p['scheme']);
        $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true) && !($fx && $host === '127.0.0.1')) return ['err' => t('الرابط ده مش متاح.')];
        $ips = $fx && in_array($host, ['127.0.0.1', 'localhost'], true) ? ['127.0.0.1'] : (@gethostbynamel($host) ?: []);
        if (!$ips) return ['err' => t('مش قادرين نوصل للموقع ده.')];
        foreach ($ips as $ip) {
            if (!safe_fetch_public_ip($ip) && !($fx && $ip === '127.0.0.1')) return ['err' => t('الرابط ده مش متاح.')];
        }
        $ch = curl_init($url);
        $size = 0; $body = ''; $fh = null; $tmp = null;
        if (!empty($o['file'])) { $tmp = tempnam(sys_get_temp_dir(), 'sf'); $fh = fopen($tmp, 'wb'); }
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => (int)($o['timeout'] ?? 12), CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ips[0]],
            CURLOPT_USERAGENT => (string)($o['ua'] ?? 'Mozilla/5.0 (compatible; AIPush/1.0)'),
            CURLOPT_HTTPHEADER => (array)($o['headers'] ?? ['Accept-Language: ar,en;q=0.8']),
            CURLOPT_HEADERFUNCTION => function ($c, $h) { return strlen($h); },
            CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$size, &$body, $fh, $max) {
                $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
                if ($code >= 300 && $code < 400) return strlen($d); // جسم التحويل مش مهم
                $size += strlen($d);
                if ($size > $max) { if ($fh) return 0; $body .= substr($d, 0, max(0, $max - strlen($body))); return 0; }
                if ($fh) fwrite($fh, $d); else $body .= $d;
                return strlen($d);
            },
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        if ($fh) fclose($fh);
        if ($code >= 300 && $code < 400 && $loc !== '') { if ($tmp) @unlink($tmp); $url = $loc; continue; }
        $tooBig = $size > $max;
        if ($code === 0 || $code >= 400 || ($fh && $tooBig) || (!$fh && $body === '' && !$tmp)) { if ($tmp) @unlink($tmp); return ['err' => $code >= 400 ? t('الموقع رد بخطأ (%s).', $code) : t('مش قادرين نوصل للموقع ده.')]; }
        if (!empty($o['accept']) && $type !== '' && !preg_match($o['accept'], $type)) { if ($tmp) @unlink($tmp); return ['err' => t('الرابط ده مش صفحة ويب.')]; }
        return ['url' => $url, 'code' => $code, 'type' => $type] + ($tmp ? ['file' => $tmp] : ['body' => $body]);
    }
    return ['err' => t('تحويلات كتير.')];
}

/** عنوان IPv4 عام (مش داخلي ولا محجوز ولا loopback ولا link-local زي 169.254.x.x) */
function safe_fetch_public_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    $n = ip2long($ip);
    foreach (['0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '192.0.0.0/24', '198.18.0.0/15', '224.0.0.0/3'] as $cidr) {
        [$net, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int)$bits);
        if (($n & $mask) === (ip2long($net) & $mask)) return false;
    }
    return true;
}
