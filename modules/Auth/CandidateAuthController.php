<?php
declare(strict_types=1);

class CandidateAuthController {
    public static function register(Request $request): void {
        if ($request->method() !== 'POST') {
            renderView('candidate/register', ['pageTitle' => 'Create Account'], 'auth');
            return;
        }

        $db   = Database::getInstance();
        $name = trim($request->input('full_name', ''));
        $email= strtolower(trim($request->input('email', '')));
        $pass = $request->input('password', '');
        $slug = trim($request->input('tenant_slug', ''));

        // Validate
        $errors = [];
        if (!$name)  $errors[] = 'Full name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';

        // Find tenant
        $tenant = null;
        if ($slug) {
            $tenant = $db->fetch("SELECT * FROM tenants WHERE slug = ? AND status = 'active'", [$slug]);
        }
        if (!$tenant) {
            // Try to find from the domain or use default
            $tenant = $db->fetch("SELECT * FROM tenants WHERE status = 'active' LIMIT 1");
        }
        if (!$tenant) $errors[] = 'Company not found.';

        if (!empty($errors)) {
            http_response_code(422);
            renderView('candidate/register', [
                'pageTitle' => 'Create Account',
                'errors'    => $errors,
                'old'       => compact('name', 'email')
            ], 'auth');
            return;
        }

        // Check email uniqueness
        $exists = $db->fetchColumn("SELECT id FROM users WHERE email = ? AND tenant_id = ?", [$email, $tenant['id']]);
        if ($exists) {
            renderView('candidate/register', [
                'pageTitle' => 'Create Account',
                'errors'    => ['An account with this email already exists.'],
                'old'       => compact('name', 'email')
            ], 'auth');
            return;
        }

        // Create candidate user
        $userId = $db->insert('users', [
            'tenant_id'  => $tenant['id'],
            'full_name'  => $name,
            'email'      => $email,
            'password'   => password_hash($pass, PASSWORD_DEFAULT),
            'role'       => 'candidate',
            'type'       => 'candidate',
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Create candidate record
        $db->insert('candidates', [
            'user_id'    => $userId,
            'tenant_id'  => $tenant['id'],
            'full_name'  => $name,
            'email'      => $email,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Auto-login
        $_SESSION['user'] = [
            'id'           => $userId,
            'tenant_id'    => $tenant['id'],
            'tenant_slug'  => $tenant['slug'],
            'full_name'    => $name,
            'email'        => $email,
            'role'         => 'candidate',
            'type'         => 'candidate',
        ];

        header('Location: /c/dashboard');
        exit;
    }
}
