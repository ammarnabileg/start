<?php
declare(strict_types=1);

class InterviewRoomController
{
    public static function show(Request $r, string $token): void
    {
        $db = Database::getInstance();

        $link = $db->fetch(
            "SELECT il.*, j.title AS job_title, j.id AS job_id, j.description AS job_desc,
                    j.seniority, j.tenant_id,
                    js.max_questions, js.time_limit_minutes, js.interview_mode, js.interview_language,
                    a.id AS avatar_id, a.name AS avatar_name, a.gender, a.style, a.photo_url AS avatar_photo,
                    t.name AS company_name
             FROM interview_links il
             JOIN jobs j ON j.id = il.job_id
             LEFT JOIN job_settings js ON js.job_id = j.id
             LEFT JOIN avatars a ON a.id = j.avatar_id
             JOIN tenants t ON t.id = il.tenant_id
             WHERE il.token = ?",
            [$token]
        );

        if (!$link) {
            renderView('interview/invalid', ['reason' => 'not_found'], 'auth');
            return;
        }

        // Check expiry
        if (strtotime($link['expires_at']) < time()) {
            renderView('interview/invalid', ['reason' => 'expired'], 'auth');
            return;
        }

        // Check if already completed (link used + interview completed)
        if ($link['used_at']) {
            $interview = $db->fetch(
                "SELECT * FROM ai_interviews WHERE link_id = ? ORDER BY created_at DESC LIMIT 1",
                [$link['id']]
            );
            if ($interview && $interview['status'] === 'completed') {
                renderView('interview/completed', ['interview' => $interview], 'auth');
                return;
            }
        }

        // Check if an in-progress interview exists (candidate left and came back)
        $existingInterview = null;
        if ($link['application_id']) {
            $existingInterview = $db->fetch(
                "SELECT * FROM ai_interviews WHERE application_id = ? AND link_id = ? AND status = 'in_progress' ORDER BY created_at DESC LIMIT 1",
                [$link['application_id'], $link['id']]
            );
        }

        $mode = $link['interview_mode'] ?? 'text';

        // Check if candidate info needed (guest link)
        $needsGuestInfo = !$link['application_id'];
        $guestInfo = null;
        if ($needsGuestInfo) {
            $guestInfo = $db->fetch("SELECT * FROM interview_link_guest_info WHERE link_id = ?", [$link['id']]);
            if (!$guestInfo && $r->isGet()) {
                // Show guest info form first
                renderView('interview/guest-info', ['link' => $link, 'token' => $token], 'auth');
                return;
            }
        }

        // Set tenant context
        Tenant::set((int)$link['tenant_id']);

        // Check AI settings
        if (!Tenant::hasOpenAI()) {
            renderView('interview/invalid', ['reason' => 'no_ai'], 'auth');
            return;
        }

        $messages = [];
        if ($existingInterview) {
            $messages = $db->fetchAll(
                "SELECT * FROM ai_interview_messages WHERE interview_id = ? ORDER BY sent_at ASC",
                [$existingInterview['id']]
            );
        }

        renderView('interview/room', [
            'link'              => $link,
            'token'             => $token,
            'existingInterview' => $existingInterview,
            'messages'          => $messages,
            'mode'              => $mode,
            'guestInfo'         => $guestInfo,
            'pageTitle'         => 'AI Interview — ' . ($link['job_title'] ?? 'Position'),
        ], 'auth');
    }

    // ─── AJAX: save guest info and create application ──────────
    public static function saveGuestInfo(Request $r, string $token): void
    {
        $db = Database::getInstance();
        $link = $db->fetch("SELECT * FROM interview_links WHERE token = ?", [$token]);
        if (!$link || strtotime($link['expires_at']) < time()) {
            Response::error('Invalid or expired link.', 404); return;
        }

        $data = $r->all();
        $v = Validator::make($data, [
            'first_name' => 'required|max:100',
            'email'      => 'required|email',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422); return; }

        Tenant::set((int)$link['tenant_id']);

        // Create or find user
        $user = $db->fetch("SELECT * FROM users WHERE email = ?", [strtolower($data['email'])]);
        if (!$user) {
            $userId = $db->insert('users', [
                'tenant_id'  => null,
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'] ?? '',
                'email'      => strtolower($data['email']),
                'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'phone'      => $data['phone'] ?? null,
                'status'     => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $candidateRole = $db->fetch("SELECT id FROM roles WHERE slug = 'candidate'");
            if ($candidateRole) {
                $db->insert('user_roles', ['user_id'=>$userId,'role_id'=>$candidateRole['id'],'created_at'=>date('Y-m-d H:i:s')]);
            }
            $db->insert('candidate_profiles', [
                'user_id'         => $userId,
                'years_experience'=> (float)($data['years_experience'] ?? 0),
                'expected_salary_min' => $data['expected_salary'] ?? null,
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ]);
        } else {
            $userId = $user['id'];
        }

        // Handle CV upload
        $cvDocId = null;
        if (!empty($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $cvDocId = self::saveCVUpload($_FILES['cv'], $userId, $link['tenant_id']);
        }

        // Create application
        $appId = $db->insert('applications', [
            'tenant_id'  => $link['tenant_id'],
            'job_id'     => $link['job_id'],
            'user_id'    => $userId,
            'status'     => 'ai_screening',
            'source'     => 'invite',
            'cv_document_id' => $cvDocId,
            'applied_at' => date('Y-m-d H:i:s'),
            'last_stage_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Save guest info
        $db->insert('interview_link_guest_info', [
            'link_id'          => $link['id'],
            'first_name'       => $data['first_name'],
            'last_name'        => $data['last_name'] ?? '',
            'email'            => strtolower($data['email']),
            'phone'            => $data['phone'] ?? null,
            'years_experience' => (float)($data['years_experience'] ?? 0),
            'expected_salary'  => $data['expected_salary'] ?? null,
            'cv_path'          => $cvDocId ? ($db->fetch("SELECT file_path FROM candidate_documents WHERE id = ?", [$cvDocId])['file_path'] ?? null) : null,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        // Update link with application_id
        $db->update('interview_links', ['application_id' => $appId, 'updated_at' => date('Y-m-d H:i:s')], ['id' => $link['id']]);

        // Run CV analysis if doc uploaded
        if ($cvDocId) {
            try {
                require_once MODULES_PATH . '/AI/OpenAIService.php';
                require_once MODULES_PATH . '/AI/CVAnalyzer.php';
                $analyzer = new CVAnalyzer((int)$link['tenant_id']);
                $job = $db->fetch("SELECT * FROM jobs WHERE id = ?", [$link['job_id']]);
                $doc = $db->fetch("SELECT * FROM candidate_documents WHERE id = ?", [$cvDocId]);
                if ($job && $doc) {
                    $cvText = $analyzer->extractTextFromPDF(STORAGE_PATH . '/uploads/' . $doc['filename']);
                    if ($cvText) $analyzer->analyze($appId, $cvDocId, $cvText, $job['title'], $job['seniority'], $job['description'] ?? '', $job['requirements'] ?? '');
                }
            } catch (Throwable) {}
        }

        Response::success(['application_id' => $appId], 'Ready to start interview.');
    }

    // ─── AJAX: Start or resume interview ──────────────────────
    public static function start(Request $r, string $token): void
    {
        $db = Database::getInstance();
        $link = $db->fetch("SELECT il.*, j.*, js.max_questions, js.time_limit_minutes, js.interview_language, a.name AS avatar_name, a.style, a.photo_url AS avatar_photo, a.personality_prompt, t.name AS company_name FROM interview_links il JOIN jobs j ON j.id = il.job_id LEFT JOIN job_settings js ON js.job_id = j.id LEFT JOIN avatars a ON a.id = j.avatar_id JOIN tenants t ON t.id = il.tenant_id WHERE il.token = ? AND il.application_id IS NOT NULL", [$token]);
        if (!$link || strtotime($link['expires_at']) < time()) { Response::error('Invalid link.', 404); return; }

        Tenant::set((int)$link['tenant_id']);

        // Find or create interview
        $interview = $db->fetch("SELECT * FROM ai_interviews WHERE link_id = ? AND application_id = ? ORDER BY created_at DESC LIMIT 1", [$link['id'], $link['application_id']]);
        if (!$interview) {
            $iid = $db->insert('ai_interviews', [
                'tenant_id'      => $link['tenant_id'],
                'application_id' => $link['application_id'],
                'link_id'        => $link['id'],
                'avatar_id'      => $link['avatar_id'] ?? null,
                'status'         => 'in_progress',
                'mode'           => $link['interview_mode'] ?? 'text',
                'language'       => $link['interview_language'] ?? 'auto',
                'started_at'     => date('Y-m-d H:i:s'),
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $db->update('interview_links', ['used_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], ['id' => $link['id']]);
            $db->update('applications', ['status' => 'ai_screening', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $link['application_id']]);

            $db->insert('ai_interview_timeline', ['interview_id'=>$iid,'event_type'=>'started','description'=>'Interview started','occurred_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]);
            $interview = $db->fetch("SELECT * FROM ai_interviews WHERE id = ?", [$iid]);

            // Get opening message from AI
            require_once MODULES_PATH . '/AI/OpenAIService.php';
            require_once MODULES_PATH . '/AI/InterviewConductor.php';
            $conductor = new InterviewConductor((int)$link['tenant_id'], (int)$iid);
            $opening = $conductor->openInterview($link, $link, $link['interview_language'] ?? 'auto');

            Response::success(['interview_id' => $iid, 'opening_message' => $opening, 'max_questions' => $link['max_questions'] ?? 12, 'time_limit' => $link['time_limit_minutes'] ?? 20]);
        } else {
            // Resume: get last messages
            $messages = $db->fetchAll("SELECT * FROM ai_interview_messages WHERE interview_id = ? ORDER BY sent_at ASC", [$interview['id']]);
            Response::success(['interview_id' => $interview['id'], 'messages' => $messages, 'questions_asked' => $interview['questions_asked'], 'max_questions' => $link['max_questions'] ?? 12, 'time_limit' => $link['time_limit_minutes'] ?? 20]);
        }
    }

    // ─── AJAX: Send message ───────────────────────────────────
    public static function sendMessage(Request $r, string $token): void
    {
        $db = Database::getInstance();
        $data = $r->json() ?: $r->all();
        $interviewId = (int)($data['interview_id'] ?? 0);
        $message     = trim($data['message'] ?? '');

        if (!$interviewId || !$message) { Response::error('Missing params.', 422); return; }

        $interview = $db->fetch("SELECT ai.*, il.tenant_id, il.job_id, il.application_id, j.title AS job_title, j.seniority, j.description AS job_desc, js.max_questions, js.time_limit_minutes, a.name AS avatar_name, a.style, a.personality_prompt, t.name AS company_name FROM ai_interviews ai JOIN interview_links il ON il.id = ai.link_id JOIN jobs j ON j.id = il.job_id LEFT JOIN job_settings js ON js.job_id = j.id LEFT JOIN avatars a ON a.id = ai.avatar_id JOIN tenants t ON t.id = il.tenant_id WHERE ai.id = ? AND il.token = ?", [$interviewId, $token]);
        if (!$interview || $interview['status'] === 'completed') { Response::error('Interview not found or completed.', 404); return; }

        Tenant::set((int)$interview['tenant_id']);

        // Save candidate message
        $db->insert('ai_interview_messages', [
            'interview_id'     => $interviewId,
            'role'             => 'candidate',
            'content'          => $message,
            'question_number'  => $interview['questions_asked'],
            'sent_at'          => date('Y-m-d H:i:s'),
            'created_at'       => date('Y-m-d H:i:s'),
        ]);

        // Get conversation history
        $history = $db->fetchAll("SELECT role, content FROM ai_interview_messages WHERE interview_id = ? ORDER BY sent_at ASC", [$interviewId]);

        require_once MODULES_PATH . '/AI/OpenAIService.php';
        require_once MODULES_PATH . '/AI/InterviewConductor.php';
        $conductor = new InterviewConductor((int)$interview['tenant_id'], $interviewId);
        $result = $conductor->respond($message, $history, $interview, $interview, $interview['questions_asked'], $interview['max_questions'] ?? 12);

        if ($result['isLastQuestion'] || ($interview['questions_asked'] ?? 0) >= ($interview['max_questions'] ?? 12)) {
            $conductor->closeInterview($interviewId);
            // Trigger evaluation async (flag it)
            $db->insert('ai_interview_timeline', ['interview_id'=>$interviewId,'event_type'=>'needs_evaluation','description'=>'Interview completed, evaluation queued','occurred_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]);
            // Run evaluation now (synchronous for simplicity)
            try {
                require_once MODULES_PATH . '/AI/InterviewEvaluator.php';
                $evaluator = new InterviewEvaluator((int)$interview['tenant_id']);
                $evaluator->runFullEvaluation($interviewId, (int)$interview['application_id']);
            } catch (Throwable $e) {}
        }

        Response::success([
            'message'        => $result['message'],
            'isLastQuestion' => $result['isLastQuestion'],
            'questionNumber' => $result['questionNumber'],
        ]);
    }

    // ─── AJAX: Submit feedback ────────────────────────────────
    public static function submitFeedback(Request $r, string $token): void
    {
        $db = Database::getInstance();
        $data = $r->json() ?: $r->all();
        $interviewId = (int)($data['interview_id'] ?? 0);
        if (!$interviewId) { Response::error('Missing interview id.', 422); return; }

        $feedback = $db->fetch("SELECT * FROM interview_feedback WHERE interview_id = ?", [$interviewId]);
        if (!$feedback) { Response::error('Feedback period expired.', 404); return; }

        if (strtotime($feedback['expires_at']) < time()) { Response::error('Feedback period expired.', 404); return; }

        $db->update('interview_feedback', [
            'rating'           => (int)($data['rating'] ?? 0) ?: null,
            'experience_rating'=> (int)($data['experience_rating'] ?? 0) ?: null,
            'clarity_rating'   => (int)($data['clarity_rating'] ?? 0) ?: null,
            'feedback_text'    => $data['feedback_text'] ?? null,
            'submitted_at'     => date('Y-m-d H:i:s'),
        ], ['id' => $feedback['id']]);

        Response::success(null, 'Thank you for your feedback!');
    }

    private static function saveCVUpload(array $file, int $userId, int $tenantId): ?int
    {
        $allowed = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowed)) return null;
        if ($file['size'] > 10 * 1024 * 1024) return null;

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('cv_', true) . '.' . $ext;
        $dir      = STORAGE_PATH . '/uploads/' . date('Y/m');
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) return null;

        $db = Database::getInstance();

        // Unset previous defaults
        $db->update('candidate_documents', ['is_default' => 0], ['user_id' => $userId, 'type' => 'cv']);

        return $db->insert('candidate_documents', [
            'user_id'       => $userId,
            'type'          => 'cv',
            'filename'      => date('Y/m') . '/' . $filename,
            'original_name' => $file['name'],
            'file_path'     => 'storage/uploads/' . date('Y/m') . '/' . $filename,
            'file_size'     => $file['size'],
            'mime_type'     => $file['type'],
            'is_default'    => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
