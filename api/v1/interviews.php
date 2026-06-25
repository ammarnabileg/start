<?php
declare(strict_types=1);
/**
 * api/v1/interview.php — Public token-based interview endpoints
 * POST /api/v1/interview/{token}/start
 * POST /api/v1/interview/{token}/message
 */

$db = Database::getInstance();
// $id = token, $sub = action (start | message)
$token  = $id ?? '';
$action = $sub ?? '';

if ($req->method !== 'POST') {
    echo Response::error('Method not allowed', 405);
    exit;
}

if ($token === '') {
    echo Response::error('Token required', 400);
    exit;
}

// Load and validate the interview link
$link = $db->fetch(
    "SELECT * FROM interview_links WHERE token = ?",
    [$token]
);

if (!$link) {
    echo Response::error('Invalid interview link', 404);
    exit;
}
if (!$link['is_active']) {
    echo Response::error('This interview link is no longer active', 410);
    exit;
}
if (strtotime($link['expires_at']) < time()) {
    echo Response::error('This interview link has expired', 410);
    exit;
}

switch ($action) {
    case 'start':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $job = $db->fetch("SELECT * FROM jobs WHERE id = ?", [(int)$link['job_id']]);
        if (!$job) { echo Response::error('Job not found', 404); exit; }

        $job['questions'] = $db->fetchAll(
            "SELECT * FROM job_questions WHERE job_id = ? ORDER BY `order` ASC",
            [(int)$link['job_id']]
        ) ?: [];

        $avatar = null;
        if (!empty($link['avatar_id'])) {
            $avatar = $db->fetch("SELECT * FROM avatars WHERE id = ?", [(int)$link['avatar_id']]);
        }

        // Find or create ai_interview record
        $interviewId = $_SESSION['interview_id'] ?? null;
        $interview = null;
        if ($interviewId) {
            $interview = $db->fetch("SELECT * FROM ai_interviews WHERE id = ?", [$interviewId]);
        }

        if (!$interview) {
            // We need an application; create a temporary/anonymous one or look up candidate
            $candidateId = null;
            $user = Auth::user();
            if ($user && isset($user['id'])) {
                $candidate = $db->fetch("SELECT id FROM candidates WHERE user_id = ?", [$user['id']]);
                if ($candidate) {
                    $candidateId = $candidate['id'];
                }
            }

            if ($candidateId) {
                // Check for existing application
                $app = $db->fetch(
                    "SELECT id FROM applications WHERE job_id = ? AND candidate_id = ? AND tenant_id = ?",
                    [(int)$link['job_id'], $candidateId, $link['tenant_id']]
                );
                if (!$app) {
                    $appId = $db->insert('applications', [
                        'tenant_id'     => $link['tenant_id'],
                        'job_id'        => (int)$link['job_id'],
                        'candidate_id'  => $candidateId,
                        'current_stage' => 'ai_screening',
                        'applied_at'    => date('Y-m-d H:i:s'),
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $appId = $app['id'];
                }
            } else {
                $appId = null;
            }

            if ($appId) {
                $interviewId = $db->insert('ai_interviews', [
                    'application_id' => $appId,
                    'status'         => 'in_progress',
                    'transcript'     => json_encode([]),
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
                $_SESSION['interview_id'] = $interviewId;
                $interview = $db->fetch("SELECT * FROM ai_interviews WHERE id = ?", [$interviewId]);
            }
        }

        // Increment used count
        $db->query(
            "UPDATE interview_links SET used_count = used_count + 1 WHERE id = ?",
            [$link['id']]
        );

        $openingMessage = 'Hello! Welcome to the interview. I am your AI interviewer today. Shall we begin?';
        if (class_exists('InterviewConductor') && $interview) {
            try {
                $apiKey = $db->fetchColumn(
                    "SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'openai_api_key'",
                    [$link['tenant_id']]
                );
                $conductor = new InterviewConductor($job, $avatar, $apiKey ?: '');
                $openingMessage = $conductor->getOpeningMessage();
            } catch (\Throwable $e) {
                // Fall back to default message
            }
        }

        echo Response::success([
            'message'      => $openingMessage,
            'interview_id' => $interviewId,
            'avatar'       => $avatar,
            'job'          => [
                'id'        => $job['id'],
                'title'     => $job['title'],
                'questions' => $job['questions'],
            ],
        ]);
        break;

    case 'message':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $interviewId = $_SESSION['interview_id'] ?? null;
        if (!$interviewId) {
            echo Response::error('Interview session not found. Please start the interview first.', 400);
            exit;
        }

        $interview = $db->fetch("SELECT * FROM ai_interviews WHERE id = ?", [$interviewId]);
        if (!$interview) {
            echo Response::error('Interview not found', 404);
            exit;
        }

        $userMessage = trim($req->input('message') ?? '');
        if ($userMessage === '') {
            echo Response::error('Message is required', 422);
            exit;
        }

        $transcript = json_decode($interview['transcript'] ?? '[]', true) ?: [];
        $transcript[] = ['role' => 'candidate', 'message' => $userMessage, 'time' => date('Y-m-d H:i:s')];

        $responseMessage = 'Thank you for your response. Could you tell me more?';
        $isComplete = false;
        $score = null;

        if (class_exists('InterviewConductor')) {
            try {
                $job    = $db->fetch("SELECT * FROM jobs WHERE id = ?", [(int)$link['job_id']]);
                $avatar = null;
                if (!empty($link['avatar_id'])) {
                    $avatar = $db->fetch("SELECT * FROM avatars WHERE id = ?", [(int)$link['avatar_id']]);
                }
                $apiKey = $db->fetchColumn(
                    "SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'openai_api_key'",
                    [$link['tenant_id']]
                );
                $conductor = new InterviewConductor($job, $avatar, $apiKey ?: '');
                $result = $conductor->processMessage($userMessage, $transcript);
                $responseMessage = $result['message'] ?? $responseMessage;
                $isComplete      = (bool)($result['is_complete'] ?? false);
                $score           = $result['score'] ?? null;
            } catch (\Throwable $e) {
                // Use defaults
            }
        }

        $transcript[] = ['role' => 'ai', 'message' => $responseMessage, 'time' => date('Y-m-d H:i:s')];

        $updateData = [
            'transcript'  => json_encode($transcript),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($isComplete) {
            $updateData['status']       = 'completed';
            $updateData['completed_at'] = date('Y-m-d H:i:s');
            if ($score !== null) {
                $updateData['score'] = $score;
            }

            // Run evaluator if available
            if (class_exists('InterviewEvaluator') && $interview['application_id']) {
                try {
                    $apiKey = $db->fetchColumn(
                        "SELECT setting_value FROM system_settings WHERE tenant_id = ? AND setting_key = 'openai_api_key'",
                        [$link['tenant_id']]
                    );
                    $evaluator = new InterviewEvaluator($apiKey ?: '');
                    $evaluation = $evaluator->evaluate($transcript);
                    if (isset($evaluation['score'])) {
                        $updateData['score'] = $evaluation['score'];
                        $score = $evaluation['score'];
                    }
                    $db->update('applications', [
                        'score'         => $updateData['score'] ?? $score,
                        'current_stage' => 'qualified',
                        'updated_at'    => date('Y-m-d H:i:s'),
                    ], ['id' => $interview['application_id']]);
                } catch (\Throwable $e) {
                    // Ignore evaluation errors
                }
            }

            unset($_SESSION['interview_id']);
        }

        $db->update('ai_interviews', $updateData, ['id' => $interviewId]);

        echo Response::success([
            'message'     => $responseMessage,
            'is_complete' => $isComplete,
            'score'       => $score,
        ]);
        break;

    default:
        echo Response::error('Unknown action', 404);
        break;
}
