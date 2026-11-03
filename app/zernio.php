<?php
/**
 * الاتصال بـ Zernio API
 * https://docs.zernio.com
 */

class ZernioException extends Exception
{
    public $status;
    public function __construct(string $msg, int $status = 0)
    {
        parent::__construct($msg);
        $this->status = $status;
    }
}

class Zernio
{
    const BASE = 'https://zernio.com/api';

    private $key;

    public function __construct(?string $key = null)
    {
        $this->key = $key ?? connector_secret('zernio', 'api_key');
        if ($this->key === '') {
            throw new ZernioException(t('الشركة دي لسه مش مربوطة بخدمة النشر. صاحب الشركة يربطها من صفحة التكاملات.'));
        }
    }

    public static function configured(): bool
    {
        foreach (connections('zernio') as $c) {
            if (($c['credentials']['api_key'] ?? '') !== '') return true;
        }
        return false;
    }

    /** تقدر تقفل الكاش لطلب معين (مثلاً زرار "تحديث") */
    public static $noCache = false;

    /** مدة الحفظ المؤقت بالثواني لكل نوع بيانات (القراءة بس) */
    private static function ttl(string $path): int
    {
        $rules = [
            '#^/v1/inbox/conversations/[^/]+/messages#' => 8,
            '#^/v1/inbox/conversations#' => 15,
            '#^/v1/inbox/comments/#' => 20,
            '#^/v1/inbox/#' => 30,
            '#^/v1/analytics#' => 600,
            '#^/v1/accounts/follower-stats#' => 600,
            '#^/v1/accounts/[^/]+/(gmb-|linkedin-|facebook-|pinterest-boards)#' => 300,
            '#^/v1/accounts#' => 120,
            '#^/v1/profiles#' => 600,
            '#^/v1/posts#' => 30,
            '#^/v1/queue#' => 60,
            '#^/v1/ads#' => 120,
            '#^/v1/connect/#' => 0, // روابط الربط لازم تكون جديدة كل مرة
            '#^/v1/media#' => 0,
        ];
        foreach ($rules as $rx => $sec) {
            if (preg_match($rx, $path)) return $sec;
        }
        return 30;
    }

    private static function cacheDir(): ?string
    {
        $dir = ROOT_DIR . '/uploads/cache';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return null;
        return is_writable($dir) ? $dir : null;
    }

    /** "جيل" الكاش لكل مفتاح: أي تعديل بيزوّده، فكل البيانات القديمة بتتجاهل فوراً */
    private function cacheGen(): string
    {
        $dir = self::cacheDir();
        $f = $dir ? $dir . '/gen_' . sha1($this->key) : null;
        return ($f && is_file($f)) ? (string)@file_get_contents($f) : '0';
    }

    private function bumpCacheGen(): void
    {
        $dir = self::cacheDir();
        if ($dir) @file_put_contents($dir . '/gen_' . sha1($this->key), (string)microtime(true), LOCK_EX);
    }

    public function request(string $method, string $path, array $query = [], $body = null, bool $multipart = false): array
    {
        $ttl = $method === 'GET' ? self::ttl($path) : 0;
        $cacheFile = null;
        if ($ttl > 0 && ($dir = self::cacheDir())) {
            $q = array_filter($query, function ($v) { return $v !== null && $v !== ''; });
            ksort($q);
            $h = sha1($this->key . '|' . $this->cacheGen() . '|' . $path . '?' . http_build_query($q));
            $cacheFile = $dir . '/' . substr($h, 0, 2) . '/' . $h . '.json';
            if (!self::$noCache && is_file($cacheFile) && filemtime($cacheFile) > time() - $ttl) {
                $hit = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($hit)) return $hit;
            }
        }
        try {
            $data = $this->send($method, $path, $query, $body, $multipart);
        } catch (ZernioException $ex) {
            // كتر الطلبات أو مشكلة مؤقتة: نرجّع آخر نسخة محفوظة بدل الخطأ
            if ($cacheFile && in_array($ex->status, [429, 500, 502, 503, 504], true) && is_file($cacheFile)) {
                $stale = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($stale)) return $stale;
            }
            throw $ex;
        }
        if ($method !== 'GET') {
            $this->bumpCacheGen(); // اتعمل تغيير: الكاش القديم مبقاش صالح
        } elseif ($cacheFile) {
            @mkdir(dirname($cacheFile), 0750, true);
            $tmp = $cacheFile . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $cacheFile);
            if (mt_rand(1, 200) === 1) self::cacheGc();
        }
        return $data;
    }

    /** تنظيف ملفات الكاش القديمة (أقدم من ساعة) */
    private static function cacheGc(): void
    {
        $dir = self::cacheDir();
        if (!$dir) return;
        foreach (glob($dir . '/*/*.json') ?: [] as $f) {
            if (@filemtime($f) < time() - 3600) @unlink($f);
        }
    }

    private function send(string $method, string $path, array $query = [], $body = null, bool $multipart = false): array
    {
        $url = self::BASE . $path;
        $query = array_filter($query, function ($v) { return $v !== null && $v !== ''; });
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $headers = ['Authorization: Bearer ' . $this->key, 'Accept: application/json'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        if ($body !== null) {
            if ($multipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            } else {
                $headers[] = 'Content-Type: application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new ZernioException(t('تعذر الاتصال بـ Zernio: ') . $err);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        if ($status >= 400) {
            throw new ZernioException(self::errorMessage($status, $data), $status);
        }
        return $data;
    }

    private static function errorMessage(int $status, array $data): string
    {
        $msg = $data['error']['message'] ?? $data['message'] ?? $data['error'] ?? '';
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
        if ($status === 401) {
            return t('مفتاح الـ API غير صحيح أو منتهي، أو الحساب محتاج إعادة ربط. ') . $msg;
        }
        if ($status === 429) {
            return t('Zernio بيقول إن الطلبات كتير. استنى دقيقة وجرب تاني.');
        }
        return t('خطأ من Zernio (%s): %s', $status, $msg ?: t('غير معروف'));
    }

    /* ---------- البروفايلات والحسابات ---------- */

    public function profiles(): array
    {
        return $this->request('GET', '/v1/profiles')['profiles'] ?? [];
    }

    public function accounts(?string $profileId = null): array
    {
        return $this->request('GET', '/v1/accounts', ['profileId' => $profileId, 'category' => 'social'])['accounts'] ?? [];
    }

    public function allAccounts(?string $profileId = null): array
    {
        return $this->request('GET', '/v1/accounts', ['profileId' => $profileId])['accounts'] ?? [];
    }

    public function connectUrl(string $platform, string $profileId, string $redirect): string
    {
        $r = $this->request('GET', '/v1/connect/' . rawurlencode($platform), [
            'profileId' => $profileId,
            'redirect_url' => $redirect,
        ]);
        return $r['authUrl'] ?? '';
    }

    public function disconnect(string $accountId): void
    {
        $this->request('DELETE', '/v1/accounts/' . rawurlencode($accountId));
    }

    /* ---------- البوستات ---------- */

    public function posts(array $filters = []): array
    {
        return $this->request('GET', '/v1/posts', $filters);
    }

    public function createPost(array $payload): array
    {
        return $this->request('POST', '/v1/posts', [], $payload);
    }

    public function deletePost(string $id): void
    {
        $this->request('DELETE', '/v1/posts/' . rawurlencode($id));
    }

    /* ---------- الميديا ---------- */

    /** يرفع ملف من السيرفر لـ Zernio ويرجع الرابط العام */
    public function uploadFile(string $path, string $filename, string $mime): string
    {
        $r = $this->request('POST', '/v1/media/presign', [], [
            'filename' => $filename,
            'contentType' => $mime,
            'size' => filesize($path),
        ]);
        if (empty($r['uploadUrl']) || empty($r['publicUrl'])) {
            throw new ZernioException(t('خدمة النشر لم ترجع رابط رفع للملف.'));
        }
        $fh = fopen($path, 'rb');
        $ch = curl_init($r['uploadUrl']);
        curl_setopt_array($ch, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => filesize($path),
            CURLOPT_HTTPHEADER => ['Content-Type: ' . $mime],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
        ]);
        curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($status < 200 || $status >= 300) {
            throw new ZernioException(t('فشل رفع الملف (%s) %s', $status, $err));
        }
        return $r['publicUrl'];
    }

    /* ---------- الرسايل ---------- */

    public function conversations(array $filters = []): array
    {
        return $this->request('GET', '/v1/inbox/conversations', $filters);
    }

    public function messages(string $conversationId, string $accountId): array
    {
        return $this->request('GET', '/v1/inbox/conversations/' . rawurlencode($conversationId) . '/messages', [
            'accountId' => $accountId,
            'limit' => 100,
        ]);
    }

    public function sendMessage(string $conversationId, string $accountId, string $text, ?string $attachmentUrl = null, ?string $attachmentType = null): array
    {
        $body = ['accountId' => $accountId, 'message' => $text];
        if ($attachmentUrl) {
            $body['attachmentUrl'] = $attachmentUrl;
            $body['attachmentType'] = $attachmentType ?: 'file';
        }
        return $this->request('POST', '/v1/inbox/conversations/' . rawurlencode($conversationId) . '/messages', [], $body);
    }

    public function markRead(string $conversationId, string $accountId): void
    {
        try {
            $this->request('POST', '/v1/inbox/conversations/' . rawurlencode($conversationId) . '/read', [], ['accountId' => $accountId]);
        } catch (Throwable $t) {
            // مش ضروري
        }
    }

    /* ---------- التعليقات ---------- */

    public function commentedPosts(array $filters = []): array
    {
        return $this->request('GET', '/v1/inbox/comments', $filters);
    }

    public function comments(string $postId, string $accountId): array
    {
        return $this->request('GET', '/v1/inbox/comments/' . rawurlencode($postId), [
            'accountId' => $accountId,
            'limit' => 100,
        ]);
    }

    public function replyComment(string $postId, string $accountId, string $text, ?string $commentId = null): array
    {
        $body = ['accountId' => $accountId, 'message' => $text];
        if ($commentId) {
            $body['commentId'] = $commentId;
        }
        return $this->request('POST', '/v1/inbox/comments/' . rawurlencode($postId), [], $body);
    }

    public function deleteComment(string $postId, string $commentId, string $accountId): void
    {
        $this->request('DELETE', '/v1/inbox/comments/' . rawurlencode($postId), ['accountId' => $accountId, 'commentId' => $commentId]);
    }

    public function hideComment(string $postId, string $commentId, string $accountId): void
    {
        $this->request('POST', '/v1/inbox/comments/' . rawurlencode($postId) . '/' . rawurlencode($commentId) . '/hide', [], ['accountId' => $accountId]);
    }
}

/** يرجع كائن Zernio أو null مع رسالة للمستخدم */
function zernio_or_null(?string &$error = null): ?Zernio
{
    try {
        return ZernioMulti::forWorkspace();
    } catch (ZernioException $e) {
        $error = $e->getMessage();
        return null;
    }
}

/**
 * عميل بيوزّع الطلبات على كل اتصالات الشركة.
 * - طلب فيه حساب (accountId) بيروح للاتصال اللي الحساب ده تبعه.
 * - منشور لحسابات من أكتر من اتصال بيتقسم لمنشور لكل اتصال.
 * - طلب قراءة عام (زي قائمة الحسابات أو الرسائل) بيتبعت لكل الاتصالات والنتايج بتتجمع.
 * - أي حاجة تانية بتروح للاتصال الأساسي (الأول).
 */
class ZernioMulti extends Zernio
{
    /** @var Zernio[] */
    private $clients = [];
    private $labels = [];
    private $profiles = [];
    private $accMap = null;

    public static function forWorkspace(): self
    {
        $conns = array_values(array_filter(connections('zernio'), function ($c) { return ($c['credentials']['api_key'] ?? '') !== ''; }));
        if (!$conns) {
            throw new ZernioException(t('الشركة دي لسه مش مربوطة بخدمة النشر. صاحب الشركة يربطها من صفحة التكاملات.'));
        }
        $m = new self($conns[0]['credentials']['api_key']);
        foreach ($conns as $c) {
            $m->clients[(int)$c['id']] = new Zernio($c['credentials']['api_key']);
            $m->labels[(int)$c['id']] = $c['label'];
            $m->profiles[(int)$c['id']] = $c['settings']['profile_id'] ?? null;
        }
        return $m;
    }

    public function connectionIds(): array { return array_keys($this->clients); }
    public function label(int $id): string { return $this->labels[$id] ?? ''; }
    public function isMulti(): bool { return count($this->clients) > 1; }

    /** عميل اتصال واحد بعينه (مثلاً لربط حساب جديد على اتصال معين) */
    public function client(int $id): Zernio
    {
        if (!isset($this->clients[$id])) throw new ZernioException(t('الاتصال ده مش موجود.'));
        return $this->clients[$id];
    }
    public function profileOf(int $id): ?string { return $this->profiles[$id] ?? null; }

    /** الاتصال اللي الحساب ده تبعه */
    public function connectionOf(string $accountId): ?int
    {
        if ($this->accMap === null || !isset($this->accMap[$accountId])) {
            $this->accMap = [];
            foreach ($this->clients as $id => $c) {
                try {
                    foreach ($c->request('GET', '/v1/accounts', array_filter(['profileId' => $this->profiles[$id], 'includeSandbox' => 'true'])) ['accounts'] ?? [] as $a) {
                        $this->accMap[(string)$a['_id']] = $id;
                    }
                } catch (ZernioException $e) {
                }
            }
        }
        return $this->accMap[$accountId] ?? null;
    }

    private function first(): Zernio { return reset($this->clients); }
    private function firstId(): int { return (int)array_key_first($this->clients); }

    /** profileId: لو الصفحة سابتاه فاضي، كل اتصال بياخد البروفايل المختار بتاعه */
    private function q(int $id, array $query): array
    {
        if (array_key_exists('profileId', $query) && ($query['profileId'] === null || $query['profileId'] === '')) {
            $query['profileId'] = $this->profiles[$id];
        }
        return $query;
    }

    private static function accountIn(string $path, array $query, $body): ?string
    {
        if (!empty($query['accountId']) && is_string($query['accountId'])) return $query['accountId'];
        if (is_array($body) && !empty($body['accountId']) && is_string($body['accountId'])) return $body['accountId'];
        if (preg_match('#^/v1/accounts/([a-f0-9]{24})(/|$)#', $path, $m)) return $m[1];
        return null;
    }

    public function request(string $method, string $path, array $query = [], $body = null, bool $multipart = false): array
    {
        if (count($this->clients) === 1) {
            $id = $this->firstId();
            return $this->clients[$id]->request($method, $path, $this->q($id, $query), $body, $multipart);
        }

        // 1) طلب خاص بحساب معين
        $acc = self::accountIn($path, $query, $body);
        if ($acc !== null && ($id = $this->connectionOf($acc)) !== null) {
            return $this->clients[$id]->request($method, $path, $this->q($id, $query), $body, $multipart);
        }

        // 2) منشور لحسابات من أكتر من اتصال: نقسمه
        if ($method === 'POST' && is_array($body) && !empty($body['platforms']) && is_array($body['platforms'])) {
            $groups = [];
            foreach ($body['platforms'] as $pl) {
                $aid = is_array($pl['accountId'] ?? null) ? ($pl['accountId']['_id'] ?? '') : (string)($pl['accountId'] ?? '');
                $groups[$this->connectionOf($aid) ?? $this->firstId()][] = $pl;
            }
            $results = [];
            foreach ($groups as $id => $pls) {
                $results[] = $this->clients[$id]->request($method, $path, $this->q($id, $query), ['platforms' => $pls] + $body, $multipart);
            }
            $out = $results[0];
            if (count($results) > 1) $out['posts'] = $results;
            return $out;
        }

        // 3) profileId محدد: يروح للاتصال صاحب البروفايل
        if (!empty($query['profileId'])) {
            foreach ($this->profiles as $id => $p) {
                if ($p === $query['profileId']) return $this->clients[$id]->request($method, $path, $query, $body, $multipart);
            }
        }

        // 4) قراءة عامة: نجمع من كل الاتصالات
        if ($method === 'GET' && empty($query['cursor'])) {
            return $this->fanOut($path, $query);
        }

        // 5) عنصر برقمه (منشور، محادثة...) مش عارفين تبع مين: نجرب كل اتصال لحد ما نلاقيه
        if (preg_match('#^/v1/[a-z\-/]+/[A-Za-z0-9_:\-.]{6,}#', $path)) {
            $last = null;
            foreach ($this->clients as $id => $c) {
                try {
                    return $c->request($method, $path, $this->q($id, $query), $body, $multipart);
                } catch (ZernioException $e) {
                    if ($e->status !== 404) throw $e;
                    $last = $e;
                }
            }
            if ($last) throw $last;
        }

        $id = $this->firstId();
        return $this->clients[$id]->request($method, $path, $this->q($id, $query), $body, $multipart);
    }

    private function fanOut(string $path, array $query): array
    {
        $results = [];
        $error = null;
        foreach ($this->clients as $id => $c) {
            try {
                $r = $c->request('GET', $path, $this->q($id, $query));
                // نعلّم كل حساب باسم الاتصال بتاعه
                if (isset($r['accounts']) && is_array($r['accounts'])) {
                    foreach ($r['accounts'] as &$a) { $a['_connection'] = $this->labels[$id]; $a['_connectionId'] = $id; $this->accMap[(string)($a['_id'] ?? '')] = $id; }
                    unset($a);
                }
                $results[] = $r;
            } catch (ZernioException $e) {
                $error = $error ?: $e;
            }
        }
        if (!$results) throw $error ?: new ZernioException(t('غير معروف'));
        $out = $results[0];
        foreach (array_slice($results, 1) as $r) {
            foreach ($r as $k => $v) {
                if (is_array($v) && isset($out[$k]) && is_array($out[$k]) && self::isList($v) && self::isList($out[$k])) {
                    $out[$k] = array_merge($out[$k], $v);
                } elseif (is_numeric($v) && isset($out[$k]) && is_numeric($out[$k]) && in_array($k, ['total', 'count', 'unread'], true)) {
                    $out[$k] += $v;
                }
            }
            if (isset($r['pagination']['total'], $out['pagination']['total'])) $out['pagination']['total'] += $r['pagination']['total'];
        }
        return $out;
    }

    private static function isList(array $a): bool
    {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }
}

/** البروفايل المستخدم: اللي اختاره المدير، وإلا البروفايل الافتراضي في Zernio */
function current_profile_id(Zernio $z): string
{
    $id = (string)zernio_profile();
    if ($id !== '') {
        return $id;
    }
    $profiles = $z->profiles();
    foreach ($profiles as $p) {
        if (!empty($p['isDefault'])) {
            return $p['_id'];
        }
    }
    if (!$profiles) {
        throw new ZernioException(t('مفيش أي Profile في حساب التكامل. اعمل واحد من لوحة التكامل.'));
    }
    return $profiles[0]['_id'];
}

/** الـ Profile المختار في Zernio للشركة الحالية (أو null = كلهم) */
function zernio_profile(): ?string
{
    // مع أكتر من اتصال، كل اتصال بيستخدم البروفايل بتاعه تلقائياً (ZernioMulti::q)
    if (count(connections('zernio')) !== 1) return null;
    $p = connector_setting('zernio', 'profile_id');
    return $p ? (string)$p : null;
}
