<?php
declare(strict_types=1);
namespace SociAI\Controllers;
use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\User;

class ProfileController
{
    private Request $req;
    private User $userModel;

    public function __construct()
    {
        $this->req       = new Request();
        $this->userModel = new User();
    }

    public function show(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        Response::view('profile.show', [
            'user'      => $this->userModel->sanitize($u),
            'pageTitle' => 'Profile',
            'layout'    => 'app',
            'csrf'      => Auth::csrfToken(),
        ]);
    }

    public function update(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        $this->userModel->update($u['id'], [
            'full_name' => $this->req->post('full_name', $u['full_name'] ?? ''),
        ]);
        if ($this->req->isAjax()) {
            Response::success([], 'Profile updated.');
            return;
        }
        Response::flash('success', 'Profile updated.');
        Response::redirect('/profile');
    }

    public function changePassword(array $p): void
    {
        Auth::requireAuth();
        $u       = Auth::getCurrentUser();
        $current = $this->req->post('current_password', '');
        $new     = $this->req->post('new_password', '');
        if (!Auth::verifyPassword($current, $u['password_hash'])) {
            Response::error('Current password is incorrect.');
            return;
        }
        $errs = \SociAI\Core\Security::validatePassword($new);
        if (!empty($errs)) {
            Response::error(implode(' ', $errs));
            return;
        }
        $this->userModel->update($u['id'], ['password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12])]);
        Response::success([], 'Password changed.');
    }

    public function enable2FA(array $p): void
    {
        Auth::requireAuth();
        $u      = Auth::getCurrentUser();
        $code   = $this->req->post('code', '');
        $secret = $this->req->post('secret', '');
        if (!Auth::verifyTOTP($secret, $code)) {
            Response::error('Invalid code.');
            return;
        }
        $this->userModel->enable2FA($u['id'], $secret);
        Response::success([], '2FA enabled.');
    }

    public function disable2FA(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        $this->userModel->disable2FA($u['id']);
        Response::success([], '2FA disabled.');
    }

    public function sessions(array $p): void
    {
        Auth::requireAuth();
        $u = Auth::getCurrentUser();
        Response::success($this->userModel->getSessions($u['id']));
    }
}
