<?php
function format_member_count(int $n): string {
 if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
 if ($n >= 1000) return round($n / 1000, 1) . 'k';
 return (string)$n;
}

function time_ago(string $timestamp): string {
 $ts = strtotime($timestamp);
 if ($ts === false) return 'unknown';
 $diff = time() - $ts;
 if ($diff < 60) return 'just now';
 if ($diff < 3600) return floor($diff / 60) . 'm ago';
 if ($diff < 86400) return floor($diff / 3600) . 'h ago';
 if ($diff < 604800) return floor($diff / 86400) . 'd ago';
 if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
 if ($diff < 31536000) return floor($diff / 2592000) . 'mo ago';
 return floor($diff / 31536000) . 'y ago';
}

function get_video_embed(string $input): string {
 $input = trim($input);
 // If already an iframe embed code
 if (stripos($input, '<iframe') !== false) {
 return $input;
 }
 // YouTube patterns
 $yt_patterns = [
 '/youtu\.be\/([a-zA-Z0-9_-]{11})(?:[?&]|$)/',
 '/youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:&|$)/',
 '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:[?\/]|$)/',
 '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:[?\/]|$)/',
 '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})(?:[?\/]|$)/',
 ];
 foreach ($yt_patterns as $pattern) {
 if (preg_match($pattern, $input, $m)) {
 $id = $m[1];
 return '<iframe class="w-full aspect-video rounded-xl" src="https://www.youtube.com/embed/' . htmlspecialchars($id) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
 }
 }
 // Vimeo
 if (preg_match('/vimeo\.com\/(\d+)/', $input, $m)) {
 return '<iframe class="w-full aspect-video rounded-xl" src="https://player.vimeo.com/video/' . $m[1] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
 }
 // Generic URL - just embed in iframe
 if (filter_var($input, FILTER_VALIDATE_URL)) {
 return '<iframe class="w-full aspect-video rounded-xl" src="' . htmlspecialchars($input) . '" frameborder="0" allowfullscreen></iframe>';
 }
 return '';
}

function get_user_points_in_community(int $user_id, int $community_id): int {
 $row = db_fetch(
 'SELECT SUM(points) as total FROM user_points WHERE user_id = ? AND community_id = ?',
 [$user_id, $community_id]
 );
 return (int)($row['total'] ?? 0);
}

function get_community_leaderboard(int $community_id, int $limit = 20): array {
    return db_fetch_all(
        'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar,
         COALESCE(mp.total_points, 0) as total_points,
         (SELECT COUNT(*) FROM user_badges ub WHERE ub.user_id = u.id AND ub.community_id = ?) as badge_count,
         (SELECT COUNT(DISTINCT lp.lesson_id) FROM lesson_progress lp
          JOIN lessons l ON l.id = lp.lesson_id
          JOIN course_sections cs ON cs.id = l.section_id
          JOIN courses c ON c.id = cs.course_id
          WHERE lp.user_id = u.id AND c.community_id = ?) as lessons_completed
         FROM users u
         JOIN memberships m ON m.user_id = u.id AND m.community_id = ? AND m.status = "approved"
         LEFT JOIN member_points mp ON mp.user_id = u.id AND mp.community_id = ?
         ORDER BY total_points DESC
         LIMIT ?',
        [$community_id, $community_id, $community_id, $community_id, $limit]
    );
}

function get_unread_notification_count(int $user_id): int {
 $row = db_fetch('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0', [$user_id]);
 return (int)($row['cnt'] ?? 0);
}

function create_notification(int $user_id, string $type, string $title, string $message, string $link = ''): void {
 db_insert(
 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)',
 [$user_id, $type, $title, $message, $link]
 );
}

function slugify(string $text): string {
 $text = strtolower(trim($text));
 $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
 $text = preg_replace('/[\s-]+/', '-', $text);
 return trim($text, '-');
}

function unique_slug(string $base): string {
 $slug = slugify($base);
 if ($slug === '') $slug = 'community';
 $original = $slug;
 $i = 2;
 while (db_fetch('SELECT id FROM communities WHERE slug = ?', [$slug])) {
 $slug = $original . '-' . $i++;
 if ($i > 9999) break; // safety limit
 }
 return $slug;
}

function get_avatar_url(?string $avatar, string $name = 'U', int $size = 40): string {
 if ($avatar && trim($avatar) !== '') return htmlspecialchars(trim($avatar));
 $initials = urlencode(strtoupper(substr($name, 0, 2)));
 return "https://ui-avatars.com/api/?name={$initials}&size={$size}&background=0d9488&color=fff&bold=true";
}

function get_user_community_role(int $user_id, int $community_id): ?string {
 $row = db_fetch(
 'SELECT role, status FROM memberships WHERE user_id = ? AND community_id = ?',
 [$user_id, $community_id]
 );
 if (!$row || $row['status'] !== 'approved') return null;
 return $row['role'];
}

function is_community_admin(int $user_id, int $community_id): bool {
 $role = get_user_community_role($user_id, $community_id);
 return in_array($role, ['admin', 'owner']);
}

function get_course_progress(int $user_id, int $course_id): array {
 $total = db_fetch(
 'SELECT COUNT(*) as cnt FROM lessons l JOIN course_sections cs ON cs.id = l.section_id WHERE cs.course_id = ?',
 [$course_id]
 );
 $completed = db_fetch(
 'SELECT COUNT(*) as cnt FROM lesson_progress lp
 JOIN lessons l ON l.id = lp.lesson_id
 JOIN course_sections cs ON cs.id = l.section_id
 WHERE cs.course_id = ? AND lp.user_id = ?',
 [$course_id, $user_id]
 );
 $t = (int)($total['cnt'] ?? 0);
 $c = (int)($completed['cnt'] ?? 0);
 return ['total' => $t, 'completed' => $c, 'percent' => $t > 0 ? round(($c / $t) * 100) : 0];
}

function format_price($price, string $interval = ''): string {
 $price = (float)$price;
 if ($price <= 0) return 'Free';
 $formatted = '$' . number_format($price, 2);
 if ($interval === 'monthly') return $formatted . '/mo';
 if ($interval === 'yearly') return $formatted . '/yr';
 return $formatted;
}

function e(string $str): string {
 return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token(): string {
 return csrf_token();
}
