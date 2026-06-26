<?php
declare(strict_types=1);

class AuthController
{
    /**
     * POST /login — authenticate user and redirect by type, or return JSON for AJAX.
     */
    public static function login(Request $r): void
    {
        $data = $r->only(['email', 'password']);

        $v = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required|min:1',
        ]);

        if ($v->fails()) {
            if ($r->isAjax()) {
                Response::error('Validation failed.', 422, $v->errors());
            }
            $_SESSION['errors']   = $v->errors();
            $_SESSION['old']      = ['email' => $data['email'] ?? ''];
            Response::redirect('/login');
        }

        $result = Auth::login((string)($data['email'] ?? ''), (string)($data['password'] ?? ''));

        if (!$result) {
            if ($r->isAjax()) {
                Response::error('Invalid credentials or account is inactive.', 401);
            }
            $_SESSION['errors']   = ['email' => ['Invalid email or password.']];
            $_SESSION['old']      = ['email' => $data['email'] ?? ''];
            Response::redirect('/login');
        }

        $user = $result['user'];

        if ($r->isAjax()) {
            $redirect = match ($user['type']) {
                'super_admin' => '/super/dashboard',
                'candidate'   => '/c/dashboard',
                default       => '/dashboard',
            };
            Response::json(['success' => true, 'redirect' => $redirect, 'token' => $result['token']]);
        }

        match ($user['type']) {
            'super_admin' => Response::redirect('/super/dashboard'),
            'candidate'   => Response::redirect('/c/dashboard'),
            default       => Response::redirect('/dashboard'),
        };
    }

    /**
     * GET  /forgot-password — show form.
     * POST /forgot-password — send reset email.
     */
    public static function forgotPassword(Request $r): void
    {
        if ($r->isPost()) {
            $email = trim((string)$r->post('email', ''));

            $v = Validator::make(['email' => $email], ['email' => 'required|email']);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Invalid email address.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                $_SESSION['old']    = ['email' => $email];
                Response::redirect('/forgot-password');
            }

            // Always show success message to prevent email enumeration
            AuthService::sendPasswordReset($email);

            if ($r->isAjax()) {
                Response::json([
                    'success' => true,
                    'message' => 'If that email exists, a reset link has been sent.',
                ]);
            }

            $_SESSION['flash_success'] = 'If that email exists, a reset link has been sent.';
            Response::redirect('/forgot-password');
        }

        // GET
        renderView('auth/forgot-password', [
            'pageTitle' => 'Forgot Password',
            'errors'    => $_SESSION['errors'] ?? [],
            'old'       => $_SESSION['old'] ?? [],
        ], 'auth');

        unset($_SESSION['errors'], $_SESSION['old']);
    }

    /**
     * GET  /reset-password/{token} — show form.
     * POST /reset-password/{token} — process reset.
     */
    public static function resetPassword(Request $r, string $token): void
    {
        if ($r->isPost()) {
            $data = $r->only(['password', 'password_confirmation']);
            $data['token'] = $token;

            $v = Validator::make($data, [
                'password' => 'required|min:8|confirmed',
            ]);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                Response::redirect('/reset-password/' . $token);
            }

            $ok = AuthService::processReset($token, (string)($data['password'] ?? ''));

            if (!$ok) {
                if ($r->isAjax()) {
                    Response::error('This reset link is invalid or has expired.', 400);
                }
                $_SESSION['errors'] = ['token' => ['This reset link is invalid or has expired.']];
                Response::redirect('/reset-password/' . $token);
            }

            if ($r->isAjax()) {
                Response::json(['success' => true, 'message' => 'Password reset successfully.', 'redirect' => '/login']);
            }

            $_SESSION['flash_success'] = 'Password reset successfully. Please log in.';
            Response::redirect('/login');
        }

        // GET — validate token exists before showing form
        $db    = Database::getInstance();
        $reset = $db->fetch(
            "SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > NOW()",
            [$token]
        );

        if (!$reset) {
            renderView('auth/reset-password-invalid', ['pageTitle' => 'Invalid Link'], 'auth');
            return;
        }

        renderView('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'token'     => $token,
            'errors'    => $_SESSION['errors'] ?? [],
        ], 'auth');

        unset($_SESSION['errors']);
    }

    /**
     * GET  /onboarding — show onboarding wizard.
     * POST /onboarding (action=complete_step) — mark step done.
     * POST /onboarding (action=skip)          — mark step skipped.
     */
    public static function onboarding(Request $r): void
    {
        Auth::requireAuth();
        $user   = Auth::user();
        $userId = (int)$user['id'];

        if ($r->isPost()) {
            $action = (string)$r->post('action', '');
            $step   = trim((string)$r->post('step', ''));

            if (!$step) {
                Response::error('Step is required.', 422);
            }

            switch ($action) {
                case 'complete_step':
                    AuthService::completeOnboardingStep($userId, $step);
                    $complete = AuthService::isOnboardingComplete($userId);
                    if ($complete) {
                        $db = Database::getInstance();
                        $db->update('users', ['onboarding_completed' => 1], ['id' => $userId]);
                        Auth::refreshUser();
                    }
                    Response::json([
                        'success'          => true,
                        'message'          => 'Step completed.',
                        'onboarding_done'  => $complete,
                    ]);
                    break;

                case 'skip':
                    AuthService::skipOnboardingStep($userId, $step);
                    $complete = AuthService::isOnboardingComplete($userId);
                    Response::json([
                        'success'         => true,
                        'message'         => 'Step skipped.',
                        'onboarding_done' => $complete,
                    ]);
                    break;

                default:
                    Response::error('Unknown action.', 400);
            }
        }

        // GET — redirect if onboarding already complete
        if (!empty($user['onboarding_completed']) || AuthService::isOnboardingComplete($userId)) {
            $redirect = match ($user['type']) {
                'super_admin' => '/super/dashboard',
                'candidate'   => '/c/dashboard',
                default       => '/dashboard',
            };
            Response::redirect($redirect);
        }

        $steps = AuthService::getCurrentUserOnboarding($userId);

        renderView('auth/onboarding', [
            'pageTitle' => 'Welcome — Setup Your Account',
            'steps'     => $steps,
            'user'      => $user,
        ], 'app');
    }
}
