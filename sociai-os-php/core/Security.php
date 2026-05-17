<?php
/**
 * SociAI OS - Security Utilities
 * Encryption, sanitization, UUID, CSRF, rate limiting.
 */

declare(strict_types=1);

namespace SociAI\Core;

use RuntimeException;

class Security
{
    private const CIPHER    = 'aes-256-gcm';
    private const TAG_LEN   = 16;
    private const IV_LEN    = 12;  // GCM recommended IV length

    // --------------------------------------------------------
    // Encryption / Decryption  (AES-256-GCM)
    // --------------------------------------------------------
    public static function encrypt(string $plaintext): string
    {
        $key = self::deriveKey();
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException("Encryption failed: " . openssl_error_string());
        }

        // Pack: iv (12) + tag (16) + ciphertext → base64
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN + 1) {
            throw new RuntimeException("Invalid encrypted payload.");
        }

        $key        = self::deriveKey();
        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException("Decryption failed: authentication tag mismatch.");
        }

        return $plaintext;
    }

    private static function deriveKey(): string
    {
        $rawKey = ENCRYPTION_KEY;
        // Ensure exactly 32 bytes for AES-256
        return hash('sha256', $rawKey, true);
    }

    // --------------------------------------------------------
    // Sanitization / XSS Prevention
    // --------------------------------------------------------
    public static function sanitize(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        if (is_string($input)) {
            return htmlspecialchars(strip_tags($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return $input;
    }

    public static function sanitizeHtml(string $input, array $allowedTags = []): string
    {
        if (empty($allowedTags)) {
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $allowed = implode('', array_map(fn($t) => "<{$t}>", $allowedTags));
        return strip_tags($input, $allowed);
    }

    public static function escapeOutput(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // --------------------------------------------------------
    // Validation helpers
    // --------------------------------------------------------
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && strlen($email) <= 255
            && preg_match('/^[^@]+@[^@]+\.[^@]+$/', $email) === 1;
    }

    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    public static function validateUsername(string $username): bool
    {
        return preg_match('/^[a-zA-Z0-9_.-]{3,64}$/', $username) === 1;
    }

    public static function validatePassword(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        return $errors;
    }

    // --------------------------------------------------------
    // UUID v4
    // --------------------------------------------------------
    public static function generateUUID(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    // --------------------------------------------------------
    // Secure random token
    // --------------------------------------------------------
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function constantTimeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    // --------------------------------------------------------
    // Rate Limiting  (file-based, no Redis dependency)
    // --------------------------------------------------------
    /**
     * @throws \RuntimeException if rate limit exceeded
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public static function rateLimit(
        string $key,
        int $maxAttempts,
        int $window
    ): array {
        $dir  = RATE_LIMIT_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $file    = $dir . '/' . hash('sha256', $key) . '.json';
        $now     = time();
        $data    = ['count' => 0, 'window_start' => $now, 'reset_at' => $now + $window];

        if (file_exists($file)) {
            $stored = json_decode(file_get_contents($file), true);
            if ($stored && $stored['reset_at'] > $now) {
                $data = $stored;
            }
        }

        $data['count']++;
        file_put_contents($file, json_encode($data), LOCK_EX);

        $remaining = max(0, $maxAttempts - $data['count']);
        $allowed   = $data['count'] <= $maxAttempts;

        return [
            'allowed'   => $allowed,
            'remaining' => $remaining,
            'reset_at'  => $data['reset_at'],
            'count'     => $data['count'],
        ];
    }

    public static function clearRateLimit(string $key): void
    {
        $file = RATE_LIMIT_DIR . '/' . hash('sha256', $key) . '.json';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // --------------------------------------------------------
    // CSRF Tokens
    // --------------------------------------------------------
    public static function generateCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException("Session not started.");
        }
        $token = self::generateToken(32);
        $expires = time() + CSRF_TOKEN_LIFETIME;
        $_SESSION['csrf_tokens'][$token] = $expires;

        // Prune expired tokens
        foreach ($_SESSION['csrf_tokens'] ?? [] as $t => $exp) {
            if ($exp < time()) {
                unset($_SESSION['csrf_tokens'][$t]);
            }
        }
        return $token;
    }

    public static function validateCsrfToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $tokens = $_SESSION['csrf_tokens'] ?? [];
        if (!isset($tokens[$token])) {
            return false;
        }
        if ($tokens[$token] < time()) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        // One-time use
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }

    // --------------------------------------------------------
    // File Upload Security
    // --------------------------------------------------------
    public static function validateUpload(array $file, array $allowedTypes): array
    {
        $errors = [];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
                UPLOAD_ERR_PARTIAL   => 'File upload was interrupted.',
                UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
                default              => 'Upload error code: ' . $file['error'],
            };
            return $errors;
        }

        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'File size exceeds limit of ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . ' MB.';
        }

        // Verify MIME type via finfo, not just extension
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedTypes, true)) {
            $errors[] = "File type '{$mime}' is not allowed.";
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Security: file was not uploaded via HTTP POST.';
        }

        return $errors;
    }

    public static function safeFilename(string $filename): string
    {
        $info = pathinfo($filename);
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $info['filename'] ?? 'file');
        $ext  = strtolower($info['extension'] ?? '');
        return substr($name, 0, 100) . ($ext ? '.' . $ext : '');
    }

    // --------------------------------------------------------
    // IP Address
    // --------------------------------------------------------
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
