<?php
class InterviewRoomController {
    public static function show(string $token, Request $request): void {
        $db = Database::getInstance();
        $application = $db->fetch("SELECT a.*, j.title as job_title, j.interview_type, j.interview_duration, j.max_questions,
            CONCAT(c.first_name,' ',c.last_name) as candidate_name, c.email as candidate_email, t.name as tenant_name, t.id as tenant_id
            FROM applications a
            JOIN jobs j ON j.id = a.job_id
            JOIN candidates c ON c.id = a.candidate_id
            JOIN tenants t ON t.id = a.tenant_id
            WHERE a.interview_link_token = ?", [$token]);

        if (!$application) {
            http_response_code(404);
            echo self::errorPage('Invalid Interview Link', 'This interview link is invalid or does not exist.');
            return;
        }
        if ($application['interview_link_expires_at'] && strtotime($application['interview_link_expires_at']) < time()) {
            echo self::errorPage('Link Expired', 'This interview link has expired. Please contact the company for a new link.');
            return;
        }
        // Load the interview record
        $interview = $db->fetch(
            "SELECT * FROM interviews WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
            [$application['id']]
        );

        if ($interview && $interview['status'] === 'completed') {
            echo self::errorPage('Interview Completed', 'You have already completed this interview. Thank you!');
            return;
        }

        // If no interview exists yet, create one via InterviewService
        if (!$interview) {
            require_once MODULES_PATH . '/Interviews/InterviewService.php';
            try {
                $service = new InterviewService();
                $result  = $service->createInterview((int)$application['id']);
                $interview = $db->fetch(
                    "SELECT * FROM interviews WHERE application_id = ? ORDER BY created_at DESC LIMIT 1",
                    [$application['id']]
                );
            } catch (\Throwable $e) {
                $interview = null;
            }
        }

        // Fetch the first AI question
        $firstQuestion = 'Hello! Thank you for joining this interview. Tell me about yourself and your background.';
        if ($interview) {
            $msg = $db->fetch(
                "SELECT content FROM interview_messages WHERE interview_id = ? AND role = 'ai' ORDER BY id ASC LIMIT 1",
                [$interview['id']]
            );
            if ($msg) $firstQuestion = $msg['content'];
        }

        $candidate = [
            'full_name' => $application['candidate_name'] ?? 'Candidate',
            'email'     => $application['candidate_email'] ?? '',
        ];
        $job = [
            'title'        => $application['job_title'] ?? 'Position',
            'company_name' => $application['tenant_name'] ?? '',
            'interview_type' => $application['interview_type'] ?? 'text',
        ];

        extract([
            'application'   => $application,
            'interview'     => $interview,
            'candidate'     => $candidate,
            'job'           => $job,
            'firstQuestion' => $firstQuestion,
            'token'         => $token,
            'pageTitle'     => 'Interview Room',
        ]);
        require VIEWS_PATH . '/interview/room.php';
    }

    private static function errorPage(string $title, string $message): string {
        $platformName = $_ENV['APP_NAME'] ?? 'HireAI';
        return <<<HTML
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title><script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Inter',sans-serif}</style></head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
<div class="text-center max-w-md">
  <div class="w-20 h-20 bg-violet-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
    <svg class="w-10 h-10 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  </div>
  <h1 class="text-2xl font-bold text-gray-900 mb-3">{$title}</h1>
  <p class="text-gray-500 text-sm leading-relaxed">{$message}</p>
  <p class="mt-6 text-xs text-gray-400">{$platformName} · AI Recruitment Platform</p>
</div></body></html>
HTML;
    }
}
