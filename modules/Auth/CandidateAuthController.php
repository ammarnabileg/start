<?php
declare(strict_types=1);

class CandidateAuthController
{
    /**
     * GET  /register — show candidate registration form.
     * POST /register — validate, create user + candidate_profile, login, redirect.
     */
    public static function register(Request $r): void
    {
        // Already logged-in candidates go straight to their dashboard
        if (Auth::check() && Auth::isCandidate()) {
            Response::redirect('/c/dashboard');
        }

        if ($r->isPost()) {
            $data = $r->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'password',
                'password_confirmation',
                'years_experience',
                'expected_salary',
            ]);

            $v = Validator::make($data, [
                'first_name'      => 'required|min:2|max:100',
                'last_name'       => 'required|min:2|max:100',
                'email'           => 'required|email|unique:users,email',
                'phone'           => 'required|min:7|max:50',
                'password'        => 'required|min:8|confirmed',
                'years_experience' => 'required|numeric',
                'expected_salary' => 'nullable|numeric',
            ]);

            if ($v->fails()) {
                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $v->errors());
                }
                $_SESSION['errors'] = $v->errors();
                $_SESSION['old']    = array_diff_key($data, array_flip(['password', 'password_confirmation']));
                Response::redirect('/register');
            }

            $db  = Database::getInstance();
            $now = date('Y-m-d H:i:s');

            $db->beginTransaction();
            try {
                // Create user
                $userId = $db->insert('users', [
                    'first_name'          => trim((string)$data['first_name']),
                    'last_name'           => trim((string)$data['last_name']),
                    'email'               => strtolower(trim((string)$data['email'])),
                    'password_hash'       => password_hash((string)$data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                    'phone'               => trim((string)($data['phone'] ?? '')),
                    'status'              => 'active',
                    'tenant_id'           => null,
                    'is_super_admin'      => 0,
                    'onboarding_completed'=> 0,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                ]);

                // Assign candidate role
                $role = $db->fetch("SELECT id FROM roles WHERE slug = 'candidate' LIMIT 1");
                if ($role) {
                    $db->insertOrIgnore('user_roles', [
                        'user_id'    => $userId,
                        'role_id'    => (int)$role['id'],
                        'created_at' => $now,
                    ]);
                }

                // Create candidate profile
                $db->insert('candidate_profiles', [
                    'user_id'               => $userId,
                    'years_experience'      => (float)($data['years_experience'] ?? 0),
                    'expected_salary_min'   => isset($data['expected_salary']) && $data['expected_salary'] !== ''
                                                ? (float)$data['expected_salary'] : null,
                    'expected_salary_max'   => null,
                    'salary_currency'       => 'USD',
                    'notice_period_days'    => 0,
                    'willing_to_relocate'   => 0,
                    'willing_remote'        => 1,
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                if ($r->isAjax()) {
                    Response::error('Registration failed. Please try again.', 500);
                }
                $_SESSION['errors'] = ['general' => ['Registration failed. Please try again.']];
                Response::redirect('/register');
            }

            // Log in immediately
            Auth::login(strtolower(trim((string)$data['email'])), (string)$data['password']);

            if ($r->isAjax()) {
                Response::json(['success' => true, 'redirect' => '/c/dashboard']);
            }

            Response::redirect('/c/dashboard');
        }

        // GET
        renderView('auth/register-candidate', [
            'pageTitle' => 'Create Candidate Account',
            'errors'    => $_SESSION['errors'] ?? [],
            'old'       => $_SESSION['old'] ?? [],
        ], 'auth');

        unset($_SESSION['errors'], $_SESSION['old']);
    }
}
