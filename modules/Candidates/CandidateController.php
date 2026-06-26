<?php
declare(strict_types=1);

class CandidateController
{
    public static function dashboard(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $stats = [
            'total_applications' => (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE user_id = ?", [$userId]),
            'active_applications'=> (int)$db->fetchColumn("SELECT COUNT(*) FROM applications WHERE user_id = ? AND status NOT IN ('rejected','withdrawn','hired')", [$userId]),
            'interviews'         => (int)$db->fetchColumn("SELECT COUNT(*) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.user_id = ?", [$userId]),
            'offers'             => (int)$db->fetchColumn("SELECT COUNT(*) FROM offers o JOIN applications a ON a.id = o.application_id WHERE a.user_id = ? AND o.status = 'sent'", [$userId]),
        ];

        $recentApplications = $db->fetchAll(
            "SELECT a.*, j.title AS job_title, t.name AS company_name
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC LIMIT 5",
            [$userId]
        );

        $pendingInterviews = $db->fetchAll(
            "SELECT ai.*, j.title AS job_title, t.name AS company_name
             FROM ai_interviews ai
             JOIN applications a ON a.id = ai.application_id
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.user_id = ? AND ai.status = 'pending'
             ORDER BY ai.created_at DESC",
            [$userId]
        );

        renderView('candidate/dashboard', compact('stats', 'recentApplications', 'pendingInterviews'), 'candidate');
    }

    public static function jobs(Request $r): void
    {
        $db     = Database::getInstance();
        $search = $r->get('q', '');
        $type   = $r->get('type', '');
        $page   = max(1, (int)$r->get('page', 1));

        $sql = "SELECT j.*, t.name AS company_name, d.name AS department_name
                FROM jobs j
                JOIN tenants t ON t.id = j.tenant_id AND t.status = 'active'
                LEFT JOIN departments d ON d.id = j.department_id
                WHERE j.status = 'active'";
        $params = [];

        if ($search) {
            $sql .= " AND (j.title LIKE ? OR j.location LIKE ? OR t.name LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($type) { $sql .= " AND j.employment_type = ?"; $params[] = $type; }
        $sql .= " ORDER BY j.published_at DESC";

        $result = $db->paginate($sql, $params, $page, 15);

        renderView('candidate/jobs', [
            'jobs'       => $result['data'],
            'pagination' => $result,
            'search'     => $search,
            'type'       => $type,
        ], 'candidate');
    }

    public static function apply(Request $r, int $jobId): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $job = $db->fetch(
            "SELECT j.*, t.name AS company_name FROM jobs j
             JOIN tenants t ON t.id = j.tenant_id
             WHERE j.id = ? AND j.status = 'active'",
            [$jobId]
        );
        if (!$job) { Response::error('Job not found.', 404); return; }

        $existing = $db->fetchColumn(
            "SELECT COUNT(*) FROM applications WHERE job_id = ? AND user_id = ?",
            [$jobId, $userId]
        );
        if ($existing) { Response::error('You have already applied for this position.', 422); return; }

        $now = date('Y-m-d H:i:s');
        $id  = $db->insert('applications', [
            'tenant_id'       => (int)$job['tenant_id'],
            'job_id'          => $jobId,
            'user_id'         => $userId,
            'status'          => 'applied',
            'source'          => 'candidate_portal',
            'cover_letter'    => trim((string)$r->post('cover_letter', '')),
            'expected_salary' => $r->post('expected_salary') ? (float)$r->post('expected_salary') : null,
            'applied_at'      => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        Response::success(['application_id' => $id], 'Application submitted successfully.');
    }

    public static function applications(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $applications = $db->fetchAll(
            "SELECT a.*, j.title AS job_title, t.name AS company_name,
                    ai.status AS interview_status, ai.overall_score,
                    o.status AS offer_status, o.salary, o.currency
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = a.tenant_id
             LEFT JOIN ai_interviews ai ON ai.application_id = a.id
             LEFT JOIN offers o ON o.application_id = a.id
             WHERE a.user_id = ?
             ORDER BY a.created_at DESC",
            [$userId]
        );

        renderView('candidate/applications', compact('applications'), 'candidate');
    }

    public static function applicationDetail(Request $r, int $id): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $application = $db->fetch(
            "SELECT a.*, j.title AS job_title, j.location, j.employment_type,
                    t.name AS company_name
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.id = ? AND a.user_id = ?",
            [$id, $userId]
        );

        if (!$application) {
            http_response_code(404);
            renderView('errors/404', [], 'candidate');
            return;
        }

        $aiInterview = $db->fetch(
            "SELECT * FROM ai_interviews WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
            [$id]
        );

        $offer = $db->fetch(
            "SELECT * FROM offers WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
            [$id]
        );

        renderView('candidate/application-detail', compact('application', 'aiInterview', 'offer'), 'candidate');
    }

    public static function profile(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();
        $user   = Auth::user();

        $profile = $db->fetch("SELECT * FROM candidate_profiles WHERE user_id = ?", [$userId]) ?: [];
        $skills  = $db->fetchAll("SELECT * FROM candidate_skills WHERE user_id = ? ORDER BY proficiency_level DESC", [$userId]);
        $docs    = $db->fetchAll("SELECT * FROM candidate_documents WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

        renderView('candidate/profile', compact('user', 'profile', 'skills', 'docs'), 'candidate');
    }

    public static function updateProfile(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $data = $r->only([
            'first_name', 'last_name', 'phone',
            'current_job_title', 'current_company', 'years_experience',
            'expected_salary_min', 'expected_salary_max', 'salary_currency',
            'notice_period_days', 'willing_to_relocate', 'willing_remote',
            'linkedin_url', 'portfolio_url', 'summary',
        ]);

        $v = Validator::make($data, [
            'first_name' => 'required|max:100',
            'last_name'  => 'required|max:100',
            'email'      => 'nullable|email',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('users', [
            'first_name' => trim($data['first_name']),
            'last_name'  => trim($data['last_name']),
            'phone'      => trim($data['phone'] ?? ''),
            'updated_at' => $now,
        ], ['id' => $userId]);

        $profileData = [
            'current_job_title'    => $data['current_job_title'] ?? null,
            'current_company'      => $data['current_company'] ?? null,
            'years_experience'     => isset($data['years_experience']) ? (float)$data['years_experience'] : 0,
            'expected_salary_min'  => isset($data['expected_salary_min']) && $data['expected_salary_min'] !== '' ? (float)$data['expected_salary_min'] : null,
            'expected_salary_max'  => isset($data['expected_salary_max']) && $data['expected_salary_max'] !== '' ? (float)$data['expected_salary_max'] : null,
            'salary_currency'      => $data['salary_currency'] ?? 'USD',
            'notice_period_days'   => isset($data['notice_period_days']) ? (int)$data['notice_period_days'] : 0,
            'willing_to_relocate'  => !empty($data['willing_to_relocate']) ? 1 : 0,
            'willing_remote'       => !empty($data['willing_remote']) ? 1 : 0,
            'linkedin_url'         => $data['linkedin_url'] ?? null,
            'portfolio_url'        => $data['portfolio_url'] ?? null,
            'summary'              => $data['summary'] ?? null,
            'updated_at'           => $now,
        ];

        $existing = $db->fetch("SELECT id FROM candidate_profiles WHERE user_id = ?", [$userId]);
        if ($existing) {
            $db->update('candidate_profiles', $profileData, ['user_id' => $userId]);
        } else {
            $db->insert('candidate_profiles', array_merge($profileData, ['user_id' => $userId, 'created_at' => $now]));
        }

        Auth::refreshUser();
        Response::success(null, 'Profile updated.');
    }

    public static function offers(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $offers = $db->fetchAll(
            "SELECT o.*, j.title AS job_title, t.name AS company_name
             FROM offers o
             JOIN applications a ON a.id = o.application_id
             JOIN jobs j ON j.id = a.job_id
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.user_id = ? AND o.status IN ('sent','accepted','declined')
             ORDER BY o.created_at DESC",
            [$userId]
        );

        renderView('candidate/offers', compact('offers'), 'candidate');
    }

    public static function acceptOffer(Request $r, int $id): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $offer = $db->fetch(
            "SELECT o.* FROM offers o JOIN applications a ON a.id = o.application_id
             WHERE o.id = ? AND a.user_id = ? AND o.status = 'sent'",
            [$id, $userId]
        );

        if (!$offer) { Response::error('Offer not found.', 404); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('offers', ['status' => 'accepted', 'responded_at' => $now, 'updated_at' => $now], ['id' => $id]);
        $db->update('applications', ['status' => 'hired', 'updated_at' => $now], ['id' => (int)$offer['application_id']]);

        Response::success(null, 'Offer accepted. Congratulations!');
    }

    public static function rejectOffer(Request $r, int $id): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $offer = $db->fetch(
            "SELECT o.* FROM offers o JOIN applications a ON a.id = o.application_id
             WHERE o.id = ? AND a.user_id = ? AND o.status = 'sent'",
            [$id, $userId]
        );

        if (!$offer) { Response::error('Offer not found.', 404); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('offers', ['status' => 'declined', 'responded_at' => $now, 'updated_at' => $now], ['id' => $id]);
        $db->update('applications', ['status' => 'offer_declined', 'updated_at' => $now], ['id' => (int)$offer['application_id']]);

        Response::success(null, 'Offer declined.');
    }

    public static function notifications(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $notifications = $db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
            [$userId]
        );

        if ($r->isAjax()) {
            Response::success(['notifications' => $notifications]);
            return;
        }

        renderView('candidate/notifications', compact('notifications'), 'candidate');
    }

    public static function uploadDocument(Request $r): void
    {
        $db     = Database::getInstance();
        $userId = Auth::id();

        $file = $r->file('document');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::error('No file uploaded.', 422);
            return;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('File size must not exceed 5 MB.', 422);
            return;
        }

        $allowed = ['application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) { Response::error('Only PDF and Word documents allowed.', 422); return; }

        $type = (string)$r->post('type', 'cv');
        $dir  = UPLOAD_PATH . '/cvs/' . $userId;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::error('Upload failed.', 500);
            return;
        }

        $now = date('Y-m-d H:i:s');
        $docId = $db->insert('candidate_documents', [
            'user_id'       => $userId,
            'type'          => $type,
            'filename'      => $name,
            'original_name' => $file['name'],
            'file_path'     => 'cvs/' . $userId . '/' . $name,
            'file_size'     => $file['size'],
            'mime_type'     => $mime,
            'is_default'    => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        Response::success(['id' => $docId, 'name' => $file['name']], 'Document uploaded.');
    }
}
