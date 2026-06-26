<?php
declare(strict_types=1);

class JWT
{
    public static function encode(array $payload, string $secret, int $expiry = 86400): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiry;
        $header  = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::b64url(json_encode($payload));
        $sig     = self::b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
        return "{$header}.{$payload}.{$sig}";
    }

    public static function decode(string $token, string $secret): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$header, $payload, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', "{$header}.{$payload}", $secret, true));
        if (!hash_equals($expected, $sig)) return false;
        $data = json_decode(self::b64urlDecode($payload), true);
        if (!is_array($data)) return false;
        if (isset($data['exp']) && $data['exp'] < time()) return false;
        return $data;
    }

    public static function verify(string $token, string $secret): bool
    {
        return self::decode($token, $secret) !== false;
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
