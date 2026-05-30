<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!verify_csrf($data['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']); exit;
}
if (!is_logged_in()) {
    echo json_encode(['error' => 'Authentication required']); exit;
}

$current_user = get_auth_user();
$action = $data['action'] ?? '';

if ($action === 'create') {
    $community_id = (int)($data['community_id'] ?? 0);
    $content = trim($data['content'] ?? '');
    $topic_id = !empty($data['topic_id']) ? (int)$data['topic_id'] : null;

    if (!$content || !$community_id) {
        echo json_encode(['error' => 'Content and community_id required']); exit;
    }

    // Check membership
    $mem = db_fetch('SELECT * FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
    if (!$mem) { echo json_encode(['error' => 'Not a member']); exit; }

    $post_id = db_insert(
        'INSERT INTO posts (community_id, user_id, topic_id, content) VALUES (?,?,?,?)',
        [$community_id, $current_user['id'], $topic_id, $content]
    );

    // Award XP
    award_points($current_user['id'], $community_id, 5, 'Created a post');

    // Update member count (posts)
    echo json_encode(['success' => true, 'post_id' => $post_id]);
    exit;
}

if ($action === 'comment') {
    $post_id = (int)($data['post_id'] ?? 0);
    $content = trim($data['content'] ?? '');
    $community_id = (int)($data['community_id'] ?? 0);

    if (!$post_id || !$content) { echo json_encode(['error' => 'Missing data']); exit; }

    $post = db_fetch('SELECT * FROM posts WHERE id = ?', [$post_id]);
    if (!$post) { echo json_encode(['error' => 'Post not found']); exit; }

    db_insert('INSERT INTO comments (post_id, user_id, content) VALUES (?,?,?)', [$post_id, $current_user['id'], $content]);
    db_execute('UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?', [$post_id]);

    if ($community_id) award_points($current_user['id'], $community_id, 3, 'Posted a comment');

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'like') {
    $post_id = (int)($data['post_id'] ?? 0);
    if (!$post_id) { echo json_encode(['error' => 'post_id required']); exit; }

    $post = db_fetch('SELECT * FROM posts WHERE id = ?', [$post_id]);
    if (!$post) { echo json_encode(['error' => 'Post not found']); exit; }

    $existing = db_fetch('SELECT 1 FROM post_likes WHERE user_id=? AND post_id=?', [$current_user['id'], $post_id]);
    if ($existing) {
        db_execute('DELETE FROM post_likes WHERE user_id=? AND post_id=?', [$current_user['id'], $post_id]);
        db_execute('UPDATE posts SET like_count = GREATEST(0, like_count - 1) WHERE id = ?', [$post_id]);
        $liked = false;
    } else {
        db_insert('INSERT INTO post_likes (user_id, post_id) VALUES (?,?)', [$current_user['id'], $post_id]);
        db_execute('UPDATE posts SET like_count = like_count + 1 WHERE id = ?', [$post_id]);
        $liked = true;
        // Award XP to post author
        if ($post['user_id'] !== $current_user['id']) {
            award_points($post['user_id'], $post['community_id'], 2, 'Post received a like');
            // Notify post author
            $ns = db_fetch('SELECT post_likes FROM notification_settings WHERE user_id = ?', [$post['user_id']]);
            if (!$ns || $ns['post_likes']) {
                $community_row = db_fetch('SELECT slug FROM communities WHERE id=?', [$post['community_id']]);
                create_notification($post['user_id'], 'post_like', 'Post Liked',
                    ($current_user['first_name'] ?: $current_user['username']) . ' liked your post',
                    '/community.php?slug=' . urlencode($community_row['slug'] ?? '')
                );
            }
        }
    }

    $updated = db_fetch('SELECT like_count FROM posts WHERE id = ?', [$post_id]);
    echo json_encode(['success' => true, 'liked' => $liked, 'like_count' => (int)($updated['like_count'] ?? 0)]);
    exit;
}

if ($action === 'pin') {
    $post_id = (int)($data['post_id'] ?? 0);
    $pin = (bool)($data['pin'] ?? true);

    $post = db_fetch('SELECT * FROM posts WHERE id = ?', [$post_id]);
    if (!$post) { echo json_encode(['error' => 'Post not found']); exit; }

    // Check owner
    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$post['community_id']]);
    if (!$community || (int)$community['owner_id'] !== (int)$current_user['id']) {
        echo json_encode(['error' => 'Permission denied']); exit;
    }

    if ($pin) {
        // Check max 3 pinned
        $pinned_count = db_fetch('SELECT COUNT(*) as cnt FROM posts WHERE community_id=? AND is_pinned=1', [$post['community_id']]);
        if ((int)($pinned_count['cnt'] ?? 0) >= 3) {
            echo json_encode(['error' => 'Maximum 3 posts can be pinned']); exit;
        }
        $max_order = db_fetch('SELECT COALESCE(MAX(pin_order), 0) as max_o FROM posts WHERE community_id=? AND is_pinned=1', [$post['community_id']]);
        db_execute('UPDATE posts SET is_pinned=1, pin_order=? WHERE id=?', [(int)($max_order['max_o'] ?? 0) + 1, $post_id]);
    } else {
        db_execute('UPDATE posts SET is_pinned=0, pin_order=0 WHERE id=?', [$post_id]);
    }

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $post_id = (int)($data['post_id'] ?? 0);
    $post = db_fetch('SELECT * FROM posts WHERE id = ?', [$post_id]);
    if (!$post) { echo json_encode(['error' => 'Post not found']); exit; }

    $is_author = (int)$post['user_id'] === (int)$current_user['id'];
    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$post['community_id']]);
    $is_owner = $community && (int)$community['owner_id'] === (int)$current_user['id'];

    $mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $post['community_id']]);
    $is_admin = $mem && in_array($mem['role'], ['admin', 'owner']);

    if (!$is_author && !$is_owner && !$is_admin) {
        echo json_encode(['error' => 'Permission denied']); exit;
    }

    db_execute('DELETE FROM post_likes WHERE post_id = ?', [$post_id]);
    db_execute('DELETE FROM comments WHERE post_id = ?', [$post_id]);
    db_execute('DELETE FROM posts WHERE id = ?', [$post_id]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'add_topic') {
    $community_id = (int)($data['community_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    if (!$name || !$community_id) { echo json_encode(['error' => 'Missing data']); exit; }

    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$community_id]);
    $mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
    if (!$community || !$mem || !in_array($mem['role'], ['admin', 'owner'])) {
        echo json_encode(['error' => 'Permission denied']); exit;
    }

    $max = db_fetch('SELECT COALESCE(MAX(sort_order), 0) as m FROM topics WHERE community_id=?', [$community_id]);
    $topic_id = db_insert('INSERT INTO topics (community_id, name, sort_order) VALUES (?,?,?)', [$community_id, $name, (int)($max['m'] ?? 0) + 1]);
    echo json_encode(['success' => true, 'topic_id' => $topic_id]);
    exit;
}

if ($action === 'delete_topic') {
    $topic_id = (int)($data['topic_id'] ?? 0);
    $community_id = (int)($data['community_id'] ?? 0);
    if (!$topic_id || !$community_id) { echo json_encode(['error' => 'Missing data']); exit; }

    $community = db_fetch('SELECT * FROM communities WHERE id = ?', [$community_id]);
    $mem = db_fetch('SELECT role FROM memberships WHERE user_id=? AND community_id=? AND status="approved"', [$current_user['id'], $community_id]);
    if (!$community || !$mem || !in_array($mem['role'], ['admin', 'owner'])) {
        echo json_encode(['error' => 'Permission denied']); exit;
    }

    db_execute('DELETE FROM topics WHERE id = ? AND community_id = ?', [$topic_id, $community_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
