<?php
declare(strict_types=1);

class CareerController
{
    /**
     * GET /careers/{slug} — public career page for a company.
     */
    public static function index(Request $r, string $slug): void
    {
        $db = Database::getInstance();

        $tenant = $db->fetch(
            "SELECT * FROM tenants WHERE slug = ? AND status IN ('active','trial')",
            [$slug]
        );

        if (!$tenant) {
            http_response_code(404);
            renderView('errors/404', ['pageTitle' => 'Page Not Found'], 'public');
            return;
        }

        $careerPage = $db->fetch(
            "SELECT * FROM career_page_settings WHERE tenant_id = ?",
            [(int)$tenant['id']]
        );

        // If career page is explicitly hidden, return 404
        if ($careerPage && empty($careerPage['is_public'])) {
            http_response_code(404);
            renderView('errors/404', ['pageTitle' => 'Page Not Found'], 'public');
            return;
        }

        $jobs = $db->fetchAll(
            "SELECT j.id, j.title, j.slug, j.location, j.is_remote, j.employment_type,
                    j.seniority, j.salary_min, j.salary_max, j.currency,
                    j.description, j.published_at,
                    d.name AS department_name
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.tenant_id = ? AND j.status = 'active'
             ORDER BY j.published_at DESC",
            [(int)$tenant['id']]
        );

        renderView('hr/career-page', [
            'pageTitle'  => ($careerPage['title'] ?? $tenant['name']) . ' — Careers',
            'tenant'     => $tenant,
            'careerPage' => $careerPage ?: [],
            'jobs'       => $jobs,
        ], 'public');
    }

    /**
     * GET  /careers/{slug}/apply/{jobId} — show application form.
     * POST /careers/{slug}/apply/{jobId} — process application.
     */
    public static function apply(Request $r, string $slug, int $jobId): void
    {
        $db = Database::getInstance();

        $tenant = $db->fetch(
            "SELECT * FROM tenants WHERE slug = ? AND status IN ('active','trial')",
            [$slug]
        );

        if (!$tenant) {
            http_response_code(404);
            renderView('errors/404', ['pageTitle' => 'Page Not Found'], 'public');
            return;
        }

        $job = $db->fetch(
            "SELECT j.*, d.name AS department_name FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             WHERE j.id = ? AND j.tenant_id = ? AND j.status = 'active'",
            [$jobId, (int)$tenant['id']]
        );

        if (!$job) {
            http_response_code(404);
            renderView('errors/404', ['pageTitle' => 'Job Not Found'], 'public');
            return;
        }

        if ($r->isPost()) {
            $data = $r->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'expected_salary',
                'cover_letter',
            ]);

            $v = Validator::make($data, [
                'first_name'      => 'required|min:2|max:100',
                'last_name'       => 'required|min:2|max:100',
                'email'           => 'required|email|max:255',
                'phone'           => 'nullable|max:50',
                'expected_salary' => 'nullable|numeric',
            ]);

            // CV upload validation
            $cvFile = $r->file('cv');
            if (!$cvFile || empty($cvFile['tmp_name']) || $cvFile['error'] !== UPLOAD_ERR_OK) {
                $v = Validator::make([], ['cv' => 'required']); // force fail for cleaner error
                // Manually inject error since Validator doesn't handle file fields directly
                $cvError = 'A CV file is required.';
            }

            $allowedMime = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

            if (isset($cvFile['tmp_name']) && $cvFile['error'] === UPLOAD_ERR_OK) {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $cvFile['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedMime)) {
                    $cvError = 'CV must be a PDF or Word document.';
                }

                if ($cvFile['size'] > 5 * 1024 * 1024) {
                    $cvError = 'CV file size must not exceed 5 MB.';
                }
            }

            if ($v->fails() || isset($cvError)) {
                $errors = $v->errors();
                if (isset($cvError)) $errors['cv'] = [$cvError];

                if ($r->isAjax()) {
                    Response::error('Validation failed.', 422, $errors);
                }
                $_SESSION['errors'] = $errors;
                $_SESSION['old']    = $data;
                Response::redirect("/careers/{$slug}/apply/{$jobId}");
            }

            $now       = date('Y-m-d H:i:s');
            $email     = strtolower(trim((string)$data['email']));
            $firstName = trim((string)$data['first_name']);
            $lastName  = trim((string)$data['last_name']);

            $db->beginTransaction();
            try {
                // Find or create user
                $user = $db->fetch("SELECT id FROM users WHERE email = ?", [$email]);

                if (!$user) {
                    $tempPassword = bin2hex(random_bytes(8));
                    $userId = $db->insert('users', [
                        'first_name'           => $firstName,
                        'last_name'            => $lastName,
                        'email'                => $email,
                        'password_hash'        => password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                        'phone'                => trim((string)($data['phone'] ?? '')),
                        'status'               => 'active',
                        'tenant_id'            => null,
                        'is_super_admin'       => 0,
                        'onboarding_completed' => 0,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);

                    $role = $db->fetch("SELECT id FROM roles WHERE slug = 'candidate' LIMIT 1");
                    if ($role) {
                        $db->insertOrIgnore('user_roles', [
                            'user_id'    => $userId,
                            'role_id'    => (int)$role['id'],
                            'created_at' => $now,
                        ]);
                    }

                    // Minimal candidate profile
                    $db->insertOrIgnore('candidate_profiles', [
                        'user_id'             => $userId,
                        'years_experience'    => 0,
                        'salary_currency'     => 'USD',
                        'notice_period_days'  => 0,
                        'willing_to_relocate' => 0,
                        'willing_remote'      => 1,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);
                } else {
                    $userId = (int)$user['id'];
                }

                // Upload CV
                $uploadDir = UPLOAD_PATH . '/cvs/' . $userId;
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $ext          = strtolower(pathinfo($cvFile['name'], PATHINFO_EXTENSION));
                $safeFilename = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
                $destPath     = $uploadDir . '/' . $safeFilename;

                if (!move_uploaded_file($cvFile['tmp_name'], $destPath)) {
                    throw new \RuntimeException('Failed to save uploaded CV.');
                }

                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $destPath);
                finfo_close($finfo);

                $documentId = $db->insert('candidate_documents', [
                    'user_id'       => $userId,
                    'type'          => 'cv',
                    'filename'      => $safeFilename,
                    'original_name' => $cvFile['name'],
                    'file_path'     => 'cvs/' . $userId . '/' . $safeFilename,
                    'file_size'     => $cvFile['size'],
                    'mime_type'     => $mimeType,
                    'is_default'    => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                // Create application
                $applicationId = $db->insert('applications', [
                    'tenant_id'       => (int)$tenant['id'],
                    'job_id'          => $jobId,
                    'user_id'         => $userId,
                    'status'          => 'applied',
                    'source'          => 'career_page',
                    'cover_letter'    => trim((string)($data['cover_letter'] ?? '')),
                    'expected_salary' => isset($data['expected_salary']) && $data['expected_salary'] !== ''
                                            ? (float)$data['expected_salary'] : null,
                    'cv_document_id'  => $documentId,
                    'applied_at'      => $now,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);

                // Link document to application
                $db->insertOrIgnore('application_documents', [
                    'application_id' => $applicationId,
                    'document_id'    => $documentId,
                    'is_primary'     => 1,
                    'created_at'     => $now,
                ]);

                $db->commit();
            } catch (\Throwable $e) {
                $db->rollback();
                if ($r->isAjax()) {
                    Response::error('Application submission failed. Please try again.', 500);
                }
                $_SESSION['errors'] = ['general' => ['Application submission failed. Please try again.']];
                Response::redirect("/careers/{$slug}/apply/{$jobId}");
            }

            if ($r->isAjax()) {
                Response::json(['success' => true, 'message' => 'Application submitted successfully.']);
            }

            Response::redirect("/careers/{$slug}/apply/{$jobId}/confirmation");
        }

        // GET — show application form
        renderView('hr/career-apply', [
            'pageTitle'  => 'Apply — ' . htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8'),
            'tenant'     => $tenant,
            'job'        => $job,
            'errors'     => $_SESSION['errors'] ?? [],
            'old'        => $_SESSION['old'] ?? [],
            'authUser'   => Auth::user(),
        ], 'public');

        unset($_SESSION['errors'], $_SESSION['old']);
    }
}
