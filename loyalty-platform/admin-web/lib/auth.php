<?php
// مصادقة المسؤولين: bcrypt + قفل بعد محاولات + سجلّ دخول + 2FA (TOTP).

function log_login(?string $aid, string $email, bool $ok): void {
  q("insert into admin.login_log (admin_id, email, ip, ok) values (:a,:e,:ip,:o)",
    ['a' => $aid, 'e' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'o' => $ok ? 'true' : 'false']);
}

function complete_login(string $id): void {
  boot_session();
  session_regenerate_id(true);
  $_SESSION['admin_id'] = $id;
  q("update admin.users set last_login_at=now(), failed_attempts=0, locked_until=null where id=:id", ['id' => $id]);
}

// يرجّع: ok | bad | locked | need_2fa
function attempt_login(string $email, string $pass): string {
  $u = one("select * from admin.users where lower(email)=lower(:e)", ['e' => trim($email)]);
  if (!$u || !$u['is_active']) { log_login($u['id'] ?? null, $email, false); return 'bad'; }
  if ($u['locked_until'] && strtotime($u['locked_until']) > time()) { log_login($u['id'], $email, false); return 'locked'; }

  if (!password_verify($pass, $u['password_hash'])) {
    $fa = (int)$u['failed_attempts'] + 1;
    $lock = $fa >= 5 ? ", locked_until = now() + interval '15 minutes'" : '';
    q("update admin.users set failed_attempts=:fa $lock where id=:id", ['fa' => $fa, 'id' => $u['id']]);
    log_login($u['id'], $email, false);
    return $fa >= 5 ? 'locked' : 'bad';
  }

  if (!empty($u['totp_secret'])) {
    boot_session();
    $_SESSION['2fa_pending'] = $u['id'];
    return 'need_2fa';
  }
  complete_login($u['id']);
  log_login($u['id'], $email, true);
  return 'ok';
}

function complete_2fa(string $code): bool {
  boot_session();
  $id = $_SESSION['2fa_pending'] ?? null;
  if (!$id) return false;
  $u = one("select * from admin.users where id=:id", ['id' => $id]);
  if (!$u || empty($u['totp_secret']) || !totp_verify($u['totp_secret'], $code)) {
    log_login($id, $u['email'] ?? '', false);
    return false;
  }
  unset($_SESSION['2fa_pending']);
  complete_login($id);
  log_login($id, $u['email'], true);
  return true;
}

function logout(): void { boot_session(); $_SESSION = []; session_destroy(); }

function current_admin(): ?array {
  static $cached = false, $admin = null;
  if ($cached) return $admin;
  $cached = true;
  boot_session();
  if (empty($_SESSION['admin_id'])) return $admin = null;
  $admin = one(
    "select u.*, r.name as role_name, r.permissions, r.is_super
       from admin.users u left join admin.roles r on r.id=u.role_id
      where u.id=:id and u.is_active",
    ['id' => $_SESSION['admin_id']]
  );
  return $admin;
}

function require_login(): array {
  $a = current_admin();
  if (!$a) redirect('login.php');
  return $a;
}
