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
            throw new ZernioException('الشركة دي لسه مش مربوطة بـ Zernio. صاحب الشركة يربطها من صفحة Connectors.');
        }
    }

    public static function configured(): bool
    {
        return connector_secret('zernio', 'api_key') !== '';
    }

    public function request(string $method, string $path, array $query = [], $body = null, bool $multipart = false): array
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
            throw new ZernioException('تعذر الاتصال بـ Zernio: ' . $err);
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
            return 'مفتاح Zernio API غير صحيح أو منتهي، أو الحساب محتاج إعادة ربط. ' . $msg;
        }
        if ($status === 429) {
            return 'Zernio بيقول إن الطلبات كتير. استنى دقيقة وجرب تاني.';
        }
        return 'خطأ من Zernio (' . $status . '): ' . ($msg ?: 'غير معروف');
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
            throw new ZernioException('Zernio لم يرجع رابط رفع للملف.');
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
            throw new ZernioException('فشل رفع الملف (' . $status . ') ' . $err);
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
        return new Zernio();
    } catch (ZernioException $e) {
        $error = $e->getMessage();
        return null;
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
        throw new ZernioException('مفيش أي Profile في حساب Zernio. اعمل واحد من لوحة Zernio.');
    }
    return $profiles[0]['_id'];
}

/** الـ Profile المختار في Zernio للشركة الحالية (أو null = كلهم) */
function zernio_profile(): ?string
{
    $p = connector_setting('zernio', 'profile_id');
    return $p ? (string)$p : null;
}
