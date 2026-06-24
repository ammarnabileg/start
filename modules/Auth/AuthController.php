<?php
namespace App\Modules\Auth;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/**
 * HTTP entry points for authentication: login/logout, password reset request
 * and reset completion. Supports both API (JSON) and server-rendered (web)
 * clients — JSON for AJAX/bearer callers, redirects for browser forms.
 */
class AuthController
{
    private Auth $auth;
    private AuthService $service;
    private Request $request;

    public function __construct(?Auth $auth = null, ?AuthService $service = null, ?Request $request = null)
    {
        $this->auth = $auth ?? new Auth();
        $this->service = $service ?? new AuthService();
        $this->request = $request ?? new Request();
    }

    /**
     * Render the login form.
     */
    public function showLogin(array $params = []): void
    {
        Response::view('auth.login', [
            'csrf_token' => Request::csrfToken(),
        ]);
    }

    /**
     * Authenticate a user. On success returns a JWT (JSON) or redirects to the
     * dashboard (browser form). On failure returns 401.
     */
    public function login(array $params = []): void
    {
        try {
            $email = trim((string) $this->request->input('email', ''));
            $password = (string) $this->request->input('password', '');
            $tenantId = $this->request->input('tenant_id');
            $tenantId = ($tenantId !== null && $tenantId !== '') ? (int) $tenantId : null;

            [$valid, $errors] = (new Validator())->validate(
                ['email' => $email, 'password' => $password],
                ['email' => 'required|email', 'password' => 'required']
            );
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $token = $this->auth->login($email, $password, $tenantId);
            if ($token === false) {
                Response::error('Invalid credentials', 401);
                return;
            }

            if ($this->wantsJson()) {
                Response::success([
                    'token' => $token,
                    'user'  => $this->publicUser($this->auth->user()),
                ], 'Logged in successfully');
                return;
            }

            Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            logger('Login failed: ' . $e->getMessage(), 'error');
            Response::error('Login failed', 500);
        }
    }

    /**
     * Destroy the current session/token and return to the login page.
     */
    public function logout(array $params = []): void
    {
        $this->auth->logout();

        if ($this->wantsJson()) {
            Response::success(null, 'Logged out');
            return;
        }
        Response::redirect('/login');
    }

    /**
     * Begin a password reset. Always responds with a generic success message
     * regardless of whether the email exists, to avoid account enumeration.
     */
    public function forgotPassword(array $params = []): void
    {
        try {
            $email = trim((string) $this->request->input('email', ''));

            [$valid, $errors] = (new Validator())->validate(
                ['email' => $email],
                ['email' => 'required|email']
            );
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $token = $this->service->createPasswordReset($email);
            if ($token !== null) {
                // In production an email would be dispatched here; we log so the
                // link is recoverable in development without exposing it in the
                // response body.
                logger('Password reset requested for ' . $email, 'info');
            }

            Response::success(null, 'If an account exists for that email, a reset link has been sent.');
        } catch (\Throwable $e) {
            logger('forgotPassword failed: ' . $e->getMessage(), 'error');
            // Still respond generically to avoid leaking internal state.
            Response::success(null, 'If an account exists for that email, a reset link has been sent.');
        }
    }

    /**
     * Complete a password reset given a valid token and a new password.
     */
    public function resetPassword(array $params = []): void
    {
        try {
            $token = (string) $this->request->input('token', '');
            $password = (string) $this->request->input('password', '');

            [$valid, $errors] = (new Validator())->validate(
                [
                    'token'                 => $token,
                    'password'              => $password,
                    'password_confirmation' => $this->request->input('password_confirmation'),
                ],
                [
                    'token'    => 'required',
                    'password' => 'required|min:8|confirmed',
                ]
            );
            if (!$valid) {
                Response::error('Validation failed', 422, $errors);
                return;
            }

            $ok = $this->service->resetPassword($token, $password);
            if (!$ok) {
                Response::error('Invalid or expired reset token', 400);
                return;
            }

            if ($this->wantsJson()) {
                Response::success(null, 'Password updated successfully');
                return;
            }
            Response::redirect('/login');
        } catch (\Throwable $e) {
            logger('resetPassword failed: ' . $e->getMessage(), 'error');
            Response::error('Could not reset password', 500);
        }
    }

    /**
     * Whether the caller expects a JSON response (AJAX or bearer/API client).
     */
    private function wantsJson(): bool
    {
        if ($this->request->isAjax() || $this->request->bearerToken() !== null) {
            return true;
        }
        $accept = $this->request->header('Accept') ?? '';
        $contentType = $this->request->header('Content-Type') ?? '';
        return stripos($accept, 'application/json') !== false
            || stripos($contentType, 'application/json') !== false;
    }

    /**
     * Strip sensitive fields before returning a user in an API response.
     */
    private function publicUser(?array $user): ?array
    {
        if ($user === null) {
            return null;
        }
        unset($user['password_hash']);
        return $user;
    }
}
