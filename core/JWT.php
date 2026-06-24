<?php
class JWT {
    public static function encode(array $payload, string $secret, int $expiry = 86400): string {
        $header = self::base64UrlEncode(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $body = self::base64UrlEncode(json_encode($payload));
        $sig = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));
        return "{$header}.{$body}.{$sig}";
    }

    public static function decode(string $token, string $secret): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$header, $payload, $sig] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
        if (!hash_equals($expected, $sig)) return false;
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data) return false;
        if (isset($data['exp']) && $data['exp'] < time()) return false;
        return $data;
    }

    public static function verify(string $token, string $secret): bool {
        return self::decode($token, $secret) !== false;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
