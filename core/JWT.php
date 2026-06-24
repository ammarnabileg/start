<?php
namespace App\Core;

/**
 * Minimal, dependency-free JWT implementation supporting HS256.
 */
class JWT
{
    public static function sign(array $payload, string $secret, int $expiry = 86400): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now = time();
        $payload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expiry,
        ], $payload);

        $segments = [
            self::b64UrlEncode(json_encode($header)),
            self::b64UrlEncode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $signature = self::b64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
        $segments[] = $signature;
        return implode('.', $segments);
    }

    /**
     * Verify token signature and expiry. Returns payload array or false.
     */
    public static function verify(string $token, string $secret)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        [$header64, $payload64, $sig64] = $parts;
        $signingInput = $header64 . '.' . $payload64;
        $expected = self::b64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
        if (!hash_equals($expected, $sig64)) {
            return false;
        }
        $payload = json_decode(self::b64UrlDecode($payload64), true);
        if (!is_array($payload)) {
            return false;
        }
        $now = time();
        if (isset($payload['nbf']) && $now < (int) $payload['nbf']) {
            return false;
        }
        if (isset($payload['exp']) && $now >= (int) $payload['exp']) {
            return false;
        }
        return $payload;
    }

    /**
     * Decode without verifying. Returns payload array or false.
     */
    public static function decode(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return false;
        }
        $payload = json_decode(self::b64UrlDecode($parts[1]), true);
        return is_array($payload) ? $payload : false;
    }

    /**
     * Re-issue a token with a fresh expiry if the current one is valid.
     */
    public static function refresh(string $token, string $secret, int $expiry = 86400)
    {
        $payload = self::verify($token, $secret);
        if ($payload === false) {
            return false;
        }
        unset($payload['iat'], $payload['nbf'], $payload['exp']);
        return self::sign($payload, $secret, $expiry);
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
