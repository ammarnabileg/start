<?php
// أدوات مساعدة: جلسة، هروب HTML، CSRF، فلاش، تحويل، تقسيم صفحات.

function boot_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(cfg()['session']);
    session_start();
  }
}

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

function redirect(string $to): void { header('Location: ' . $to); exit; }

function post(string $k, $d = null) { return $_POST[$k] ?? $d; }
function get(string $k, $d = null)  { return $_GET[$k]  ?? $d; }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

// ---- CSRF ----
function csrf_token(): string {
  boot_session();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_field(): string {
  return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
  boot_session();
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)post('_csrf'))) {
    http_response_code(419);
    exit('انتهت صلاحية الجلسة (CSRF). أعد المحاولة.');
  }
}

// ---- Flash ----
function flash(string $msg, string $type = 'success'): void {
  boot_session();
  $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
}
function take_flash(): array {
  boot_session();
  $f = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $f;
}

// ---- عرض ----
function dt($v): string { return $v ? date('Y-m-d H:i', strtotime($v)) : '—'; }
function d($v): string  { return $v ? date('Y-m-d', strtotime($v)) : '—'; }
function n($v): string  { return number_format((int)$v); }

// شارة حالة ملوّنة
function badge(string $text, string $color): string {
  $map = [
    'green'  => 'bg-green-100 text-green-700',
    'amber'  => 'bg-amber-100 text-amber-700',
    'red'    => 'bg-red-100 text-red-700',
    'gray'   => 'bg-gray-100 text-gray-600',
    'blue'   => 'bg-blue-100 text-blue-700',
  ];
  return '<span class="px-2 py-0.5 rounded-full text-xs font-bold ' . ($map[$color] ?? $map['gray']) . '">' . e($text) . '</span>';
}
function status_badge(string $s): string {
  $c = ['approved'=>'green','active'=>'green','open'=>'amber','pending'=>'amber',
        'reviewing'=>'blue','trial'=>'blue','suspended'=>'red','rejected'=>'red',
        'expired'=>'red','resolved'=>'gray','canceled'=>'gray','past_due'=>'red'];
  return badge($s, $c[$s] ?? 'gray');
}

// ---- تقسيم الصفحات ----
function page_num(): int { return max(1, (int)get('page', 1)); }
function per_page(): int { return 25; }
function pager(int $total, int $page, string $baseQuery): string {
  $pages = (int)ceil($total / per_page());
  if ($pages <= 1) return '';
  $out = '<div class="flex gap-1 justify-center mt-4">';
  for ($i = 1; $i <= min($pages, 12); $i++) {
    $cls = $i === $page ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-amber-50';
    $out .= '<a href="?' . e($baseQuery) . '&page=' . $i . '" class="px-3 py-1 rounded border ' . $cls . '">' . $i . '</a>';
  }
  return $out . '</div>';
}
