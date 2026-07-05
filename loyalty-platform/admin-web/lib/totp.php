<?php
// 2FA — TOTP (RFC 6238) بدون مكتبات خارجية.
function totp_secret(int $len = 16): string {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = '';
  for ($i = 0; $i < $len; $i++) $s .= $a[random_int(0, 31)];
  return $s;
}
function base32_decode(string $b32): string {
  $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b32 = strtoupper($b32); $bits = ''; $out = '';
  foreach (str_split($b32) as $c) { $v = strpos($a, $c); if ($v === false) continue; $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT); }
  foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
  return $out;
}
function totp_code(string $secret, ?int $t = null): string {
  $counter = intdiv($t ?? time(), 30);
  $bin = "\0\0\0\0" . pack('N', $counter);
  $h = hash_hmac('sha1', $bin, base32_decode($secret), true);
  $o = ord($h[19]) & 0xf;
  $n = ((ord($h[$o]) & 0x7f) << 24) | ((ord($h[$o+1]) & 0xff) << 16) | ((ord($h[$o+2]) & 0xff) << 8) | (ord($h[$o+3]) & 0xff);
  return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}
function totp_verify(string $secret, string $code): bool {
  $code = preg_replace('/\s+/', '', $code);
  for ($w = -1; $w <= 1; $w++) if (hash_equals(totp_code($secret, time() + $w * 30), $code)) return true;
  return false;
}
function totp_uri(string $secret, string $label, string $issuer = 'Hatchy Admin'): string {
  return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
}
