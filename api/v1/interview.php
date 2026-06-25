<?php
// Public interview API — token auth, no session login required
$db = Database::getInstance();

// POST /api/v1/interview/{token}/start
if ($method === 'POST' && $id && $sub === 'start') {
    $token = $id;
    $link  = $db->fetch(
        "SELECT il.*, j.title as job_title, j.description as job_description, j.id as job_real_id
         FROM interview_links il
         JOIN jobs j ON j.id=il.job_id
         WHERE il.token=? AND il.is_active=1",
        [$token]
    );

    if (!$link || ($link['expires_at'] && strtotime($link['expires_at']) < time())) {
        Response::error('Invalid or expired interview link', 400);
    }

    $questions = $db->fetchAll("SELECT * FROM job_questions WHERE job_id=? ORDER BY sort_order ASC", [$link['job_real_id']]);

    $avatar = null;
    if (!empty($link['avatar_id'])) {
        $avatar = $db->fetch("SELECT * FROM avatars WHERE id=?", [$link['avatar_id']]);
    }

    $job = ['id' => $link['job_real_id'], 'title' => $link['job_title'], 'description' => $link['job_description']];

    // Create or resume interview record
    $interviewId = $_SESSION['interview_id_' . $token] ?? null;
    $interview = $interviewId ? $db->fetch("SELECT * FROM ai_interviews WHERE id=?", [$interviewId]) : null;

    if (!$interview || $interview['status'] === 'completed') {
        $aiSvc = OpenAIService::forTenant($link['tenant_id']);
        $conductor = new InterviewConductor($aiSvc);
        $opening = $conductor->startInterview($job, $questions, $avatar);

        $interviewId = $db->insert('ai_interviews', [
            'application_id' => null,
            'tenant_id'      => $link['tenant_id'],
            'link_id'        => $link['id'],
            'token'          => $token,
            'status'         => 'in_progress',
            'transcript'     => json_encode([['role' => 'assistant', 'content' => $opening['message']]]),
        ]);

        $_SESSION['interview_id_' . $token] = $interviewId;
        $_SESSION['interview_q_' . $token]  = 0;

        // Increment used count
        $db->query("UPDATE interview_links SET used_count=used_count+1 WHERE id=?", [$link['id']]);

        Response::success(['message' => $opening['message'], 'interview_id' => $interviewId, 'total_questions' => count($questions)]);
    }

    $transcript = json_decode($interview['transcript'] ?? '[]', true);
    $lastMsg = end($transcript);
    Response::success(['message' => $lastMsg['content'] ?? 'Welcome back!', 'interview_id' => $interviewId, 'total_questions' => count($questions)]);
}

// POST /api/v1/interview/{token}/message
if ($method === 'POST' && $id && $sub === 'message') {
    $token       = $id;
    $userMessage = trim($req->input('message', ''));

    if (!$userMessage) Response::error('Message required');

    $link = $db->fetch(
        "SELECT il.*, j.title as job_title, j.description as job_description, j.id as job_real_id
         FROM interview_links il JOIN jobs j ON j.id=il.job_id
         WHERE il.token=? AND il.is_active=1",
        [$token]
    );
    if (!$link) Response::error('Invalid interview link', 400);

    $interviewId = $_SESSION['interview_id_' . $token] ?? null;
    if (!$interviewId) Response::error('Interview not started', 400);

    $interview = $db->fetch("SELECT * FROM ai_interviews WHERE id=?", [$interviewId]);
    if (!$interview || $interview['status'] === 'completed') Response::error('Interview already completed', 400);

    $transcript = json_decode($interview['transcript'] ?? '[]', true);
    $qIndex     = $_SESSION['interview_q_' . $token] ?? 0;
    $questions  = $db->fetchAll("SELECT * FROM job_questions WHERE job_id=? ORDER BY sort_order ASC", [$link['job_real_id']]);

    $avatar = null;
    if (!empty($link['avatar_id'])) $avatar = $db->fetch("SELECT * FROM avatars WHERE id=?", [$link['avatar_id']]);

    $job = ['id' => $link['job_real_id'], 'title' => $link['job_title'], 'description' => $link['job_description']];

    $transcript[] = ['role' => 'user', 'content' => $userMessage];

    $aiSvc     = OpenAIService::forTenant($link['tenant_id']);
    $conductor = new InterviewConductor($aiSvc);
    $result    = $conductor->processMessage($userMessage, $transcript, $job, $questions, $qIndex);

    $transcript[] = ['role' => 'assistant', 'content' => $result['message']];
    $_SESSION['interview_q_' . $token] = $result['question_index'];

    // Save transcript
    $db->query("UPDATE ai_interviews SET transcript=? WHERE id=?", [json_encode($transcript), $interviewId]);

    if ($result['is_complete']) {
        // Evaluate the interview
        $criteria = $db->fetchAll("SELECT * FROM job_criteria WHERE job_id=?", [$link['job_real_id']]);
        $evaluator = new InterviewEvaluator($aiSvc);
        $evaluation = $evaluator->evaluate($transcript, $job, $criteria);

        $db->query(
            "UPDATE ai_interviews SET status='completed', overall_score=?, recommendation=?,
             skills_scores=?, behavioral_analysis=?, red_flags=?, completed_at=NOW()
             WHERE id=?",
            [
                $evaluation['overall_score'],
                $evaluation['recommendation'],
                json_encode($evaluation['skills_scores']),
                json_encode($evaluation['behavioral_analysis']),
                json_encode($evaluation['red_flags']),
                $interviewId,
            ]
        );

        // Log usage
        if (!empty($evaluation['_usage'])) {
            $aiSvc->logUsage($link['tenant_id'], 0, 'interview_evaluation', $evaluation['_usage'], $link['job_real_id']);
        }
    }

    Response::success([
        'message'     => $result['message'],
        'is_complete' => $result['is_complete'],
    ]);
}

Response::notFound();
