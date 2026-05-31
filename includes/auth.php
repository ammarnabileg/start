<?php
if (session_status() === PHP_SESSION_NONE) {
 session_start();
}

require_once __DIR__ . '/db.php';

function get_auth_user(): ?array {
 if (!isset($_SESSION['user_id'])) return null;
 $user = db_fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
 return $user ?: null;
}

function require_login(): void {
 if (!isset($_SESSION['user_id'])) {
 $uri = $_SERVER['REQUEST_URI'] ?? '/';
 // Only pass redirect if it's a simple path (no scheme, no double slashes)
 if (strpos($uri, '/') === 0 && strpos($uri, '//') !== 0) {
 header('Location: /login.php?redirect=' . urlencode($uri));
 } else {
 header('Location: /login.php');
 }
 exit;
 }
}

function login_user(int $user_id): void {
 session_regenerate_id(true);
 $_SESSION['user_id'] = $user_id;
 // Record session in DB
 $token = bin2hex(random_bytes(32));
 $device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
 $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
 db_insert(
 'INSERT INTO user_sessions (user_id, session_token, device_info, ip_address) VALUES (?, ?, ?, ?)',
 [$user_id, $token, $device, $ip]
 );
 $_SESSION['session_token'] = $token;
 // Update streak
 update_daily_streak($user_id);
}

function logout_user(): void {
 if (isset($_SESSION['session_token'])) {
 db_execute('DELETE FROM user_sessions WHERE session_token = ?', [$_SESSION['session_token']]);
 }
 session_destroy();
 session_start();
 session_regenerate_id(true);
}

function logout_all_devices(int $user_id): void {
 db_execute('DELETE FROM user_sessions WHERE user_id = ?', [$user_id]);
 session_destroy();
 session_start();
 session_regenerate_id(true);
}

function is_logged_in(): bool {
 return isset($_SESSION['user_id']);
}

function update_daily_streak(int $user_id): void {
 // Just update last_active for communities the user is in
 $memberships = db_fetch_all(
 'SELECT community_id FROM memberships WHERE user_id = ? AND status = "approved"',
 [$user_id]
 );
 foreach ($memberships as $m) {
 $streak = db_fetch(
 'SELECT * FROM user_streaks WHERE user_id = ? AND community_id = ?',
 [$user_id, $m['community_id']]
 );
 $today = date('Y-m-d');
 if (!$streak) {
 db_insert(
 'INSERT INTO user_streaks (user_id, community_id, current_streak, longest_streak, last_active) VALUES (?,?,1,1,?)',
 [$user_id, $m['community_id'], $today]
 );
 award_points($user_id, $m['community_id'], 5, 'Daily login streak');
 } else {
 $last = $streak['last_active'];
 $yesterday = date('Y-m-d', strtotime('-1 day'));
 if ($last === $today) continue;
 if ($last === $yesterday) {
 $newStreak = $streak['current_streak'] + 1;
 $longest = max($newStreak, $streak['longest_streak']);
 db_execute(
 'UPDATE user_streaks SET current_streak=?, longest_streak=?, last_active=? WHERE user_id=? AND community_id=?',
 [$newStreak, $longest, $today, $user_id, $m['community_id']]
 );
 award_points($user_id, $m['community_id'], $newStreak * 5, 'Daily login streak day ' . $newStreak);
 } else {
 db_execute(
 'UPDATE user_streaks SET current_streak=1, last_active=? WHERE user_id=? AND community_id=?',
 [$today, $user_id, $m['community_id']]
 );
 award_points($user_id, $m['community_id'], 5, 'Daily login streak');
 }
 }
 }
}

function award_points(int $user_id, int $community_id, int $points, string $reason = ''): void {
    db_insert(
        'INSERT INTO community_points (user_id, community_id, points, reason) VALUES (?,?,?,?)',
        [$user_id, $community_id, $points, $reason]
    );
    db_execute(
        'INSERT INTO member_points (user_id, community_id, total_points) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE total_points = total_points + VALUES(total_points)',
        [$user_id, $community_id, $points]
    );
}

function csrf_token(): string {
 if (empty($_SESSION['csrf_token'])) {
 $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
 }
 return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
 return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
