<?php

class AuthController
{
    public static function login(Request $req): void
    {
        $req->verifyCsrf();

        $email    = trim((string) $req->input('email', ''));
        $password = (string) $req->input('password', '');

        if ($email === '' || $password === '') {
            $_SESSION['flash']['error'] = 'Email and password are required.';
            Response::redirect('/login');
            return;
        }

        $db   = Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM users WHERE email = ? AND status = ? LIMIT 1',
            [$email, 'active']
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['flash']['error'] = 'Invalid email or password.';
            Response::redirect('/login');
            return;
        }

        Auth::login($user);

        if ($user['is_super_admin']) {
            Response::redirect('/super/dashboard');
            return;
        }

        if ($user['type'] === 'candidate') {
            Response::redirect('/c/dashboard');
            return;
        }

        // HR / tenant users
        Response::redirect('/dashboard');
    }

    public static function logout(Request $req): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    public static function register(Request $req): void
    {
        $req->verifyCsrf();

        $firstName = trim((string) $req->input('first_name', ''));
        $lastName  = trim((string) $req->input('last_name', ''));
        $email     = trim((string) $req->input('email', ''));
        $password  = (string) $req->input('password', '');
        $confirm   = (string) $req->input('password_confirm', '');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            $_SESSION['flash']['error'] = 'All fields are required.';
            Response::redirect('/register');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash']['error'] = 'Please enter a valid email address.';
            Response::redirect('/register');
            return;
        }

        if (strlen($password) < 8) {
            $_SESSION['flash']['error'] = 'Password must be at least 8 characters.';
            Response::redirect('/register');
            return;
        }

        if ($password !== $confirm) {
            $_SESSION['flash']['error'] = 'Passwords do not match.';
            Response::redirect('/register');
            return;
        }

        $db = Database::getInstance();

        $existing = $db->fetchColumn(
            'SELECT COUNT(*) FROM users WHERE email = ?',
            [$email]
        );

        if ($existing > 0) {
            $_SESSION['flash']['error'] = 'An account with this email already exists.';
            Response::redirect('/register');
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userId = $db->insert('users', [
            'tenant_id'     => null,
            'email'         => $email,
            'password_hash' => $passwordHash,
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'is_super_admin'=> 0,
            'status'        => 'active',
            'type'          => 'candidate',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $user = $db->fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);

        Auth::login($user);

        Response::redirect('/c/dashboard');
    }

    public static function careers(string $path, Request $req): void
    {
        $db = Database::getInstance();

        // /careers
        if ($path === '/careers' || $path === '/careers/') {
            $companies = $db->fetchAll(
                'SELECT t.id, t.name, t.slug, t.logo_url,
                        COUNT(j.id) AS open_jobs
                 FROM tenants t
                 LEFT JOIN jobs j ON j.tenant_id = t.id AND j.status = ?
                 WHERE t.status = ?
                 GROUP BY t.id
                 ORDER BY t.name ASC',
                ['published', 'active']
            );

            view('careers/companies', ['companies' => $companies], 'public');
            return;
        }

        // /careers/{slug}/apply/{job_id}
        if (preg_match('#^/careers/([^/]+)/apply/(\d+)$#', $path, $m)) {
            $slug  = $m[1];
            $jobId = (int) $m[2];

            $tenant = $db->fetch(
                'SELECT * FROM tenants WHERE slug = ? AND status = ? LIMIT 1',
                [$slug, 'active']
            );

            if (!$tenant) {
                http_response_code(404);
                view('errors/404', [], 'public');
                return;
            }

            $job = $db->fetch(
                'SELECT * FROM jobs WHERE id = ? AND tenant_id = ? AND status = ? LIMIT 1',
                [$jobId, $tenant['id'], 'published']
            );

            if (!$job) {
                http_response_code(404);
                view('errors/404', [], 'public');
                return;
            }

            view('careers/apply', [
                'tenant' => $tenant,
                'job'    => $job,
            ], 'public');
            return;
        }

        // /careers/{slug}
        if (preg_match('#^/careers/([^/]+)$#', $path, $m)) {
            $slug = $m[1];

            $tenant = $db->fetch(
                'SELECT * FROM tenants WHERE slug = ? AND status = ? LIMIT 1',
                [$slug, 'active']
            );

            if (!$tenant) {
                http_response_code(404);
                view('errors/404', [], 'public');
                return;
            }

            $jobs = $db->fetchAll(
                'SELECT * FROM jobs WHERE tenant_id = ? AND status = ? ORDER BY created_at DESC',
                [$tenant['id'], 'published']
            );

            view('careers/index', [
                'tenant' => $tenant,
                'jobs'   => $jobs,
            ], 'public');
            return;
        }

        http_response_code(404);
        view('errors/404', [], 'public');
    }
}
