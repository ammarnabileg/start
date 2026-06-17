<?php
// مصادقة المسؤولين (جلسة + bcrypt).

function attempt_login(string $email, string $pass): bool {
  $u = one("select * from admin.users where lower(email)=lower(:e) and is_active", ['e' => trim($email)]);
  if (!$u || !password_verify($pass, $u['password_hash'])) return false;
  boot_session();
  session_regenerate_id(true);
  $_SESSION['admin_id'] = $u['id'];
  q("update admin.users set last_login_at=now() where id=:id", ['id' => $u['id']]);
  return true;
}

function logout(): void {
  boot_session();
  $_SESSION = [];
  session_destroy();
}

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
