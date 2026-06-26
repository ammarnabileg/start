<?php
declare(strict_types=1);

class CandidateController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('candidates.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $search    = $r->get('q', '');
        $jobId     = (int)$r->get('job_id', 0);
        $status    = $r->get('status', '');
        $page      = max(1, (int)$r->get('page', 1));
        $perPage   = 20;

        $sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                       j.title AS job_title,
                       ai.overall_score AS ai_score
                FROM applications a
                JOIN users u ON u.id = a.user_id
                JOIN jobs j ON j.id = a.job_id
                LEFT JOIN ai_interviews ai ON ai.application_id = a.id AND ai.status = 'completed'
                WHERE a.tenant_id = ?";
        $params = [$tenantId];

        if ($search) {
            $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $like = "%$search%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($jobId) { $sql .= " AND a.job_id = ?"; $params[] = $jobId; }
        if ($status) { $sql .= " AND a.status = ?"; $params[] = $status; }

        $sql .= " ORDER BY a.created_at DESC";

        $result = $db->paginate($sql, $params, $page, $perPage);

        $jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? ORDER BY title", [$tenantId]);

        renderView('hr/candidates/index', [
            'applications' => $result['data'],
            'pagination'   => $result,
            'search'       => $search,
            'jobId'        => $jobId,
            'status'       => $status,
            'jobs'         => $jobs,
        ], 'app');
    }

    public static function show(Request $r, int $id): void
    {
        Auth::requirePermission('candidates.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $application = $db->fetch(
            "SELECT a.*, u.first_name, u.last_name, u.email, u.phone,
                    j.title AS job_title, j.id AS job_id,
                    cp.years_experience, cp.expected_salary_min, cp.expected_salary_max, cp.salary_currency,
                    cp.linkedin_url, cp.portfolio_url, cp.current_job_title, cp.current_company,
                    cp.summary
             FROM applications a
             JOIN users u ON u.id = a.user_id
             JOIN jobs j ON j.id = a.job_id
             LEFT JOIN candidate_profiles cp ON cp.user_id = a.user_id
             WHERE a.id = ? AND a.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$application) {
            http_response_code(404);
            renderView('errors/404', [], 'app');
            return;
        }

        $aiInterview = $db->fetch(
            "SELECT * FROM ai_interviews WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
            [$id]
        );

        $skillScores = $aiInterview ? $db->fetchAll(
            "SELECT * FROM ai_skill_scores WHERE interview_id = ?",
            [(int)$aiInterview['id']]
        ) : [];

        $personality = $aiInterview ? $db->fetch(
            "SELECT * FROM ai_personality_analyses WHERE interview_id = ?",
            [(int)$aiInterview['id']]
        ) : null;

        $redFlags = $aiInterview ? $db->fetchAll(
            "SELECT * FROM ai_red_flags WHERE interview_id = ?",
            [(int)$aiInterview['id']]
        ) : [];

        $notes = $db->fetchAll(
            "SELECT n.*, u.first_name, u.last_name FROM application_notes n
             JOIN users u ON u.id = n.user_id
             WHERE n.application_id = ? ORDER BY n.created_at DESC",
            [$id]
        );

        $documents = $db->fetchAll(
            "SELECT * FROM candidate_documents WHERE user_id = ? ORDER BY created_at DESC",
            [(int)$application['user_id']]
        );

        $humanInterviews = $db->fetchAll(
            "SELECT hi.*, u.first_name AS interviewer_fname, u.last_name AS interviewer_lname
             FROM human_interviews hi
             LEFT JOIN users u ON u.id = hi.interviewer_id
             WHERE hi.application_id = ? ORDER BY hi.scheduled_at DESC",
            [$id]
        );

        $statusHistory = $db->fetchAll(
            "SELECT al.*, u.first_name, u.last_name FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.entity_type = 'application' AND al.entity_id = ?
             ORDER BY al.created_at DESC LIMIT 20",
            [$id]
        );

        renderView('hr/candidates/show', compact(
            'application', 'aiInterview', 'skillScores', 'personality',
            'redFlags', 'notes', 'documents', 'humanInterviews', 'statusHistory'
        ), 'app');
    }

    public static function move(Request $r, int $id): void
    {
        Auth::requirePermission('candidates.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $app = $db->fetch("SELECT id, status FROM applications WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$app) { Response::error('Not found', 404); return; }

        $newStatus = trim((string)$r->post('status', ''));
        $validStatuses = ['applied','screening','ai_interview','technical_test','human_interview',
                          'shortlisted','reference_check','offer_extended','offer_accepted',
                          'offer_declined','hired','rejected','withdrawn'];

        if (!in_array($newStatus, $validStatuses, true)) {
            Response::error('Invalid status', 422);
            return;
        }

        $db->update('applications', ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $id]);
        Audit::log('application.status_changed', 'application', $id, ['status' => $app['status']], ['status' => $newStatus]);
        Response::success(['status' => $newStatus], 'Status updated.');
    }

    public static function addNote(Request $r, int $id): void
    {
        Auth::requirePermission('candidates.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$app) { Response::error('Not found', 404); return; }

        $content = trim((string)$r->post('content', ''));
        $type    = (string)$r->post('type', 'general');

        if (!$content) { Response::error('Note content is required.', 422); return; }

        $noteId = $db->insert('application_notes', [
            'application_id' => $id,
            'user_id'        => Auth::id(),
            'content'        => $content,
            'type'           => $type,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $note = $db->fetch(
            "SELECT n.*, u.first_name, u.last_name FROM application_notes n
             JOIN users u ON u.id = n.user_id WHERE n.id = ?",
            [$noteId]
        );

        Response::success(['note' => $note], 'Note added.');
    }

    public static function scheduleInterview(Request $r, int $id): void
    {
        Auth::requirePermission('human_interviews.schedule');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$app) { Response::error('Not found', 404); return; }

        $data = $r->only(['scheduled_at', 'type', 'location', 'notes', 'interviewer_id']);
        $v = Validator::make($data, [
            'scheduled_at'   => 'required|date',
            'type'           => 'required|in:in_person,video,phone',
            'interviewer_id' => 'required|integer',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $db->insert('human_interviews', [
            'application_id' => $id,
            'tenant_id'      => $tenantId,
            'interviewer_id' => (int)$data['interviewer_id'],
            'type'           => $data['type'],
            'scheduled_at'   => $data['scheduled_at'],
            'location'       => $data['location'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'scheduled',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        Response::success(null, 'Interview scheduled.');
    }

    public static function sendInterview(Request $r, int $id): void
    {
        Auth::requirePermission('ai_interviews.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $app = $db->fetch(
            "SELECT a.*, j.id AS job_id, u.email, u.first_name FROM applications a
             JOIN jobs j ON j.id = a.job_id JOIN users u ON u.id = a.user_id
             WHERE a.id = ? AND a.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$app) { Response::error('Not found', 404); return; }

        if (!Tenant::hasOpenAI()) {
            Response::error('OpenAI API key not configured. Please add it in Settings → AI Settings.', 422);
            return;
        }

        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime('+14 days'));

        // Check if a link already exists for this application
        $existingLink = $db->fetch(
            "SELECT il.*, ai.id AS ai_interview_id FROM interview_links il
             LEFT JOIN ai_interviews ai ON ai.link_id = il.id
             WHERE il.application_id = ? AND il.tenant_id = ? ORDER BY il.created_at DESC LIMIT 1",
            [$id, $tenantId]
        );

        if ($existingLink) {
            $token   = $existingLink['token'];
            $linkId  = $existingLink['id'];
        } else {
            $token  = bin2hex(random_bytes(16));
            $linkId = $db->insert('interview_links', [
                'tenant_id'      => $tenantId,
                'job_id'         => (int)$app['job_id'],
                'application_id' => $id,
                'token'          => $token,
                'expires_at'     => $expiresAt,
                'created_by'     => Auth::id(),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            $db->insert('ai_interviews', [
                'tenant_id'      => $tenantId,
                'application_id' => $id,
                'link_id'        => $linkId,
                'status'         => 'pending',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        $interviewUrl = (Env::get('APP_URL', '') ?: '') . '/interview/' . $token;

        // Send email
        $subject = 'You have been invited to an AI Interview';
        $body = "Dear {$app['first_name']},\n\nYou have been invited to complete an AI-powered interview for the position you applied for.\n\nPlease click the link below to start your interview:\n\n{$interviewUrl}\n\nThis link will expire on " . date('M j, Y', strtotime($expiresAt)) . ".\n\nGood luck!";
        @mail($app['email'], $subject, $body, 'From: ' . Env::get('MAIL_FROM', 'noreply@example.com'));

        $db->update('applications', ['status' => 'ai_interview', 'updated_at' => $now], ['id' => $id]);

        Response::success(['interview_url' => $interviewUrl], 'AI Interview invitation sent.');
    }
}
