<?php
class AuthController {
    public static function login(Request $request): never {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Email and password are required.';
            Response::redirect('/login');
        }

        $result = Auth::login($email, $password);

        if (!$result) {
            // Rate limiting attempt
            $attempts = ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['login_attempts'] = $attempts;
            $_SESSION['login_error'] = 'Invalid email or password. Please try again.';
            Response::redirect('/login');
        }

        unset($_SESSION['login_attempts'], $_SESSION['login_error']);
        $user = $result['user'];

        // Redirect based on role
        if ($user['type'] === 'super_admin') {
            Response::redirect('/super/dashboard');
        }
        if ($user['type'] === 'candidate') {
            Response::redirect('/c/dashboard');
        }
        Response::redirect('/dashboard');
    }
}
