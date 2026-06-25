<?php
class JWT {
    public static function encode(array $payload, string $secret, int $expiry = 86400): string {
        $header  = self::b64(['alg'=>'HS256','typ'=>'JWT']);
        $payload['exp'] = time() + $expiry;
        $payload['iat'] = time();
        $body    = self::b64($payload);
        $sig     = self::b64url(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
        return "{$header}.{$body}.{$sig}";
    }

    public static function verify(string $token, string $secret): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
        if (!hash_equals($expected, $sig)) return null;
        $payload = json_decode(self::b64decode($body), true);
        if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;
        return $payload;
    }

    private static function b64(array $data): string {
        return self::b64url(json_encode($data));
    }
    private static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function b64decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
