<?php
declare(strict_types=1);

class JobController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('jobs.view');
        $db = Database::getInstance();
        $tid = Auth::tenantId();

        $status = $r->get('status', 'all');
        $search = $r->get('q', '');

        $sql = "SELECT j.*, d.name AS dept_name, a.name AS avatar_name,
                (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS app_count,
                u.first_name, u.last_name
                FROM jobs j
                LEFT JOIN departments d ON d.id = j.department_id
                LEFT JOIN avatars a ON a.id = j.avatar_id
                LEFT JOIN users u ON u.id = j.created_by
                WHERE j.tenant_id = ?";
        $params = [$tid];

        if ($status !== 'all') { $sql .= " AND j.status = ?"; $params[] = $status; }
        if ($search)           { $sql .= " AND (j.title LIKE ? OR d.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $sql .= " ORDER BY j.created_at DESC";

        $jobs = $db->fetchAll($sql, $params);
        $stats = [
            'active'   => $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'active'", [$tid]),
            'draft'    => $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'draft'",  [$tid]),
            'archived' => $db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE tenant_id = ? AND status = 'archived'", [$tid]),
        ];

        renderView('hr/jobs/index', compact('jobs','stats','status','search'), 'app');
    }

    public static function create(Request $r): void
    {
        Auth::requirePermission('jobs.create');
        $db = Database::getInstance();
        $tid = Auth::tenantId();

        if ($r->isPost()) {
            $data = $r->all();
            $v = Validator::make($data, [
                'title'       => 'required|max:255',
                'seniority'   => 'required|in:intern,junior,mid,senior,lead,manager,director,executive',
                'description' => 'required',
            ]);
            if ($v->fails()) {
                setFlash('errors', $v->errors());
                setFlash('old', $data);
                Response::redirect('/jobs/create');
                return;
            }

            $slug = self::makeSlug($data['title']);
            $jobId = $db->insert('jobs', [
                'tenant_id'       => $tid,
                'title'           => $data['title'],
                'slug'            => $slug,
                'department_id'   => $data['department_id'] ?: null,
                'seniority'       => $data['seniority'],
                'employment_type' => $data['employment_type'] ?? 'full_time',
                'location'        => $data['location'] ?? null,
                'is_remote'       => isset($data['is_remote']) ? 1 : 0,
                'salary_min'      => $data['salary_min'] ?: null,
                'salary_max'      => $data['salary_max'] ?: null,
                'currency'        => $data['currency'] ?? 'USD',
                'description'     => $data['description'],
                'requirements'    => $data['requirements'] ?? null,
                'benefits'        => $data['benefits'] ?? null,
                'avatar_id'       => $data['avatar_id'] ?: null,
                'created_by'      => Auth::id(),
                'status'          => $data['status'] ?? 'draft',
                'published_at'    => ($data['status'] ?? 'draft') === 'active' ? date('Y-m-d H:i:s') : null,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);

            // Create default job settings
            $db->insert('job_settings', [
                'job_id'     => $jobId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            Audit::log('job.created', 'job', $jobId, null, ['title' => $data['title']]);
            setFlash('success', 'Job created successfully.');
            Response::redirect('/jobs/' . $jobId);
        } else {
            $departments = $db->fetchAll("SELECT * FROM departments WHERE tenant_id = ? ORDER BY name", [$tid]);
            $avatars     = $db->fetchAll("SELECT * FROM avatars WHERE tenant_id = ? AND status = 'active' ORDER BY name", [$tid]);
            $old         = flash('old') ?? [];
            $errors      = flash('errors') ?? [];
            renderView('hr/jobs/create', compact('departments','avatars','old','errors'), 'app');
        }
    }

    public static function show(Request $r, int $id): void
    {
        Auth::requirePermission('jobs.view');
        $db  = Database::getInstance();
        $tid = Auth::tenantId();

        $job = $db->fetch(
            "SELECT j.*, d.name AS dept_name, a.name AS avatar_name, a.photo_url AS avatar_photo
             FROM jobs j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN avatars a ON a.id = j.avatar_id
             WHERE j.id = ? AND j.tenant_id = ?",
            [$id, $tid]
        );
        if (!$job) { http_response_code(404); renderView('errors/404', [], 'app'); return; }

        $settings   = $db->fetch("SELECT * FROM job_settings WHERE job_id = ?", [$id]) ?: [];
        $criteria   = $db->fetchAll("SELECT * FROM job_criteria WHERE job_id = ? ORDER BY weight DESC", [$id]);
        $questions  = $db->fetchAll("SELECT * FROM job_questions WHERE job_id = ? ORDER BY created_at DESC", [$id]);
        $links      = $db->fetchAll("SELECT il.*, u.first_name, u.last_name FROM interview_links il LEFT JOIN users u ON u.id = il.created_by WHERE il.job_id = ? ORDER BY il.created_at DESC", [$id]);

        $applications = $db->fetchAll(
            "SELECT a.*, u.first_name, u.last_name, u.email,
                    ar.final_score, ar.recommendation
             FROM applications a
             JOIN users u ON u.id = a.user_id
             LEFT JOIN ai_recommendations ar ON ar.application_id = a.id
             WHERE a.job_id = ? AND a.tenant_id = ?
             ORDER BY a.created_at DESC",
            [$id, $tid]
        );

        $stats = [
            'total'        => count($applications),
            'ai_done'      => $db->fetchColumn("SELECT COUNT(*) FROM ai_interviews ai JOIN applications a ON a.id = ai.application_id WHERE a.job_id = ? AND ai.status = 'completed'", [$id]),
            'qualified'    => $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = 'qualified'", [$id]),
            'hired'        => $db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = 'hired'", [$id]),
        ];

        $tab = $r->get('tab', 'overview');
        renderView('hr/jobs/show', compact('job','settings','criteria','questions','links','applications','stats','tab'), 'app');
    }

    public static function update(Request $r, int $id): void
    {
        Auth::requirePermission('jobs.edit');
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
        if (!$job) { Response::error('Not found', 404); return; }

        $data = $r->all();
        $db->update('jobs', [
            'title'           => $data['title'] ?? $job['title'],
            'department_id'   => $data['department_id'] ?: null,
            'seniority'       => $data['seniority'] ?? $job['seniority'],
            'employment_type' => $data['employment_type'] ?? $job['employment_type'],
            'location'        => $data['location'] ?? null,
            'is_remote'       => isset($data['is_remote']) ? 1 : 0,
            'salary_min'      => $data['salary_min'] ?: null,
            'salary_max'      => $data['salary_max'] ?: null,
            'currency'        => $data['currency'] ?? $job['currency'],
            'description'     => $data['description'] ?? $job['description'],
            'requirements'    => $data['requirements'] ?? null,
            'benefits'        => $data['benefits'] ?? null,
            'avatar_id'       => $data['avatar_id'] ?: null,
            'status'          => $data['status'] ?? $job['status'],
            'published_at'    => ($data['status'] ?? '') === 'active' && !$job['published_at'] ? date('Y-m-d H:i:s') : $job['published_at'],
            'updated_at'      => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        Audit::log('job.updated', 'job', $id);
        setFlash('success', 'Job updated successfully.');
        Response::redirect('/jobs/' . $id);
    }

    public static function archive(Request $r, int $id): void
    {
        Auth::requirePermission('jobs.archive');
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $db->update('jobs', ['status' => 'archived', 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id, 'tenant_id' => $tid]);
        Audit::log('job.archived', 'job', $id);
        if ($r->isAjax()) { Response::success(null, 'Job archived.'); return; }
        setFlash('success', 'Job archived.');
        Response::redirect('/jobs');
    }

    public static function settings(Request $r, int $id): void
    {
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
        if (!$job) { http_response_code(404); return; }

        if ($r->isPost()) {
            $data = $r->all();
            $existing = $db->fetch("SELECT id FROM job_settings WHERE job_id = ?", [$id]);
            $payload = [
                'interview_mode'       => $data['interview_mode'] ?? 'text',
                'interview_language'   => $data['interview_language'] ?? 'auto',
                'max_questions'        => (int)($data['max_questions'] ?? 12),
                'time_limit_minutes'   => (int)($data['time_limit_minutes'] ?? 20),
                'passing_score'        => (float)($data['passing_score'] ?? 68),
                'auto_qualify_score'   => (float)($data['auto_qualify_score'] ?? 82),
                'auto_disqualify_score'=> (float)($data['auto_disqualify_score'] ?? 50),
                'cv_screening_enabled' => isset($data['cv_screening_enabled']) ? 1 : 0,
                'link_expiry_days'     => (int)($data['link_expiry_days'] ?? 14),
                'updated_at'           => date('Y-m-d H:i:s'),
            ];
            if ($existing) { $db->update('job_settings', $payload, ['job_id' => $id]); }
            else { $db->insert('job_settings', array_merge($payload, ['job_id'=>$id,'created_at'=>date('Y-m-d H:i:s')])); }
            if ($r->isAjax()) { Response::success(null, 'Settings saved.'); return; }
            setFlash('success', 'Settings saved.');
            Response::redirect('/jobs/' . $id . '?tab=settings');
        } else {
            Response::redirect('/jobs/' . $id . '?tab=settings');
        }
    }

    public static function criteria(Request $r, int $id): void
    {
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
        if (!$job) { Response::error('Not found', 404); return; }

        if ($r->isPost()) {
            $data = $r->post('criteria', []);
            $db->query("DELETE FROM job_criteria WHERE job_id = ?", [$id]);
            foreach ((array)$data as $c) {
                if (empty($c['name'])) continue;
                $db->insert('job_criteria', [
                    'job_id'     => $id,
                    'name'       => $c['name'],
                    'weight'     => (float)($c['weight'] ?? 1),
                    'max_score'  => (float)($c['max_score'] ?? 5),
                    'pass_score' => (float)($c['pass_score'] ?? 3),
                    'description'=> $c['description'] ?? null,
                    'created_by' => Auth::id(),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            Response::success(null, 'Criteria saved.');
        } else {
            $criteria = $db->fetchAll("SELECT * FROM job_criteria WHERE job_id = ? ORDER BY weight DESC", [$id]);
            Response::json(['criteria' => $criteria]);
        }
    }

    public static function questions(Request $r, int $id): void
    {
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
        if (!$job) { Response::error('Not found', 404); return; }

        if ($r->isPost()) {
            $data = $r->all();
            if ($r->post('action') === 'delete') {
                $qId = (int)$r->post('question_id');
                $db->delete('job_questions', ['id' => $qId, 'job_id' => $id]);
                Response::success(null, 'Question deleted.');
                return;
            }
            if ($r->post('action') === 'import') {
                $sourceJobId = (int)$r->post('source_job_id');
                $sourceJob = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$sourceJobId, $tid]);
                if ($sourceJob) {
                    $srcQs = $db->fetchAll("SELECT * FROM job_questions WHERE job_id = ? AND is_active = 1", [$sourceJobId]);
                    $count = 0;
                    foreach ($srcQs as $q) {
                        $db->insert('job_questions', [
                            'job_id' => $id, 'question' => $q['question'],
                            'category' => $q['category'], 'difficulty' => $q['difficulty'],
                            'language' => $q['language'], 'is_active' => 1,
                            'created_by' => Auth::id(), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $count++;
                    }
                    $db->insert('job_question_imports', ['job_id'=>$id,'source_job_id'=>$sourceJobId,'imported_count'=>$count,'imported_by'=>Auth::id(),'created_at'=>date('Y-m-d H:i:s')]);
                    Response::success(['count'=>$count], "Imported {$count} questions.");
                }
                return;
            }
            $v = Validator::make($data, ['question'=>'required']);
            if ($v->fails()) { Response::error($v->firstError()); return; }
            $qId = $db->insert('job_questions', [
                'job_id'     => $id,
                'question'   => $data['question'],
                'category'   => $data['category'] ?? null,
                'difficulty' => $data['difficulty'] ?? 'medium',
                'language'   => $data['language'] ?? 'en',
                'is_active'  => 1,
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $q = $db->fetch("SELECT * FROM job_questions WHERE id = ?", [$qId]);
            Response::success(['question' => $q], 'Question added.');
        } else {
            $questions = $db->fetchAll("SELECT * FROM job_questions WHERE job_id = ? ORDER BY created_at DESC", [$id]);
            $otherJobs = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? AND id != ? AND status != 'archived'", [$tid, $id]);
            Response::json(['questions' => $questions, 'other_jobs' => $otherJobs]);
        }
    }

    public static function generateLink(Request $r, int $id): void
    {
        Auth::requirePermission('jobs.generate_link');
        $db = Database::getInstance();
        $tid = Auth::tenantId();
        $job = $db->fetch("SELECT * FROM jobs WHERE id = ? AND tenant_id = ?", [$id, $tid]);
        if (!$job) { Response::error('Job not found', 404); return; }

        $settings  = $db->fetch("SELECT * FROM job_settings WHERE job_id = ?", [$id]) ?: [];
        $expiryDays = (int)($settings['link_expiry_days'] ?? 14);
        $token     = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        $linkId = $db->insert('interview_links', [
            'tenant_id'      => $tid,
            'job_id'         => $id,
            'token'          => $token,
            'expires_at'     => $expiresAt,
            'created_by'     => Auth::id(),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $appUrl = $_ENV['APP_URL'] ?? '';
        $link   = rtrim($appUrl, '/') . '/interview/' . $token;

        Response::success(['link' => $link, 'token' => $token, 'expires_at' => $expiresAt], 'Interview link generated.');
    }

    private static function makeSlug(string $title): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title)) . '-' . substr(uniqid(), -6);
    }
}
