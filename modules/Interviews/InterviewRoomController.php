<?php

class InterviewRoomController
{
    public static function show(string $token, Request $req): void
    {
        $db = Database::getInstance();

        $link = $db->fetch(
            'SELECT * FROM interview_links WHERE token = ? LIMIT 1',
            [$token]
        );

        if (!$link) {
            self::showError(
                'Invalid Interview Link',
                'This interview link does not exist. Please check your invitation email.'
            );
            return;
        }

        if (!$link['is_active']) {
            self::showError(
                'Interview Link Inactive',
                'This interview link has been deactivated. Please contact the hiring team.'
            );
            return;
        }

        if ($link['expires_at'] !== null && strtotime($link['expires_at']) < time()) {
            self::showError(
                'Interview Link Expired',
                'This interview link has expired. Please contact the hiring team for a new link.'
            );
            return;
        }

        $job = $db->fetch(
            'SELECT j.*, t.name AS company_name
             FROM jobs j
             JOIN tenants t ON t.id = j.tenant_id
             WHERE j.id = ? LIMIT 1',
            [$link['job_id']]
        );

        if (!$job) {
            self::showError(
                'Job Not Found',
                'The job associated with this interview link could not be found.'
            );
            return;
        }

        $tenant = $db->fetch(
            'SELECT * FROM tenants WHERE id = ? LIMIT 1',
            [$link['tenant_id']]
        );

        $avatar = null;
        if (!empty($job['avatar_id'])) {
            $avatar = $db->fetch(
                'SELECT * FROM avatars WHERE id = ? LIMIT 1',
                [$job['avatar_id']]
            );
        }

        if (!defined('VIEWS_PATH')) {
            define('VIEWS_PATH', dirname(__DIR__, 2) . '/views');
        }

        $viewFile = VIEWS_PATH . '/interview/room.php';

        if (!file_exists($viewFile)) {
            self::showError(
                'Configuration Error',
                'The interview room could not be loaded. Please contact support.'
            );
            return;
        }

        extract([
            'token'  => $token,
            'link'   => $link,
            'job'    => $job,
            'avatar' => $avatar,
            'tenant' => $tenant,
        ]);

        require $viewFile;
    }

    private static function showError(string $title, string $message): void
    {
        http_response_code(400);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' — AI Recruitment</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 3rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #f1f5f9;
        }
        p { color: #94a3b8; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>' . htmlspecialchars($message) . '</p>
    </div>
</body>
</html>';
    }
}
