<?php
namespace App\Modules\Interviews;

use App\Core\Database;
use App\Modules\AI\InterviewConductor;
use App\Modules\AI\InterviewEvaluator;

/**
 * Interview orchestration: lifecycle (create/start/converse/complete), AI
 * question generation, evaluation persistence and reporting.
 */
class InterviewService
{
    private InterviewRepository $repository;
    private Database $db;

    public function __construct(?InterviewRepository $repository = null, ?Database $db = null)
    {
        $this->repository = $repository ?? new InterviewRepository();
        $this->db = $db ?? Database::instance();
    }

    public function getInterviews(int $tenantId, array $filters = []): array
    {
        return $this->repository->findAll($tenantId, $filters);
    }

    /**
     * Create a pending interview for an application with a unique access token.
     *
     * @param array<string,mixed> $config currently supports nothing required,
     *        reserved for future per-interview settings.
     * @return array<string,mixed> the created interview row (includes token)
     */
    public function createInterview(int $applicationId, string $type = 'ai_text', array $config = []): array
    {
        $type = in_array($type, ['ai_text', 'ai_voice', 'ai_video'], true) ? $type : 'ai_text';
        $token = $this->generateToken();

        $id = $this->repository->create([
            'application_id' => $applicationId,
            'type'           => $type,
            'status'         => 'pending',
            'token'          => $token,
        ]);

        $interview = $this->repository->findById($id);
        return $interview ?? [
            'id'             => $id,
            'application_id' => $applicationId,
            'type'           => $type,
            'status'         => 'pending',
            'token'          => $token,
        ];
    }

    /**
     * Start an interview by token: mark in_progress, generate and persist the
     * AI's opening message.
     *
     * @return array{interview:array<string,mixed>,opening:string,messages:array<int,array<string,mixed>>}
     */
    public function startInterview(string $token): array
    {
        $interview = $this->repository->findByToken($token);
        if ($interview === null) {
            throw new \RuntimeException('Interview not found');
        }
        if ($interview['status'] === 'completed') {
            throw new \RuntimeException('This interview has already been completed');
        }
        if ($interview['status'] === 'expired') {
            throw new \RuntimeException('This interview link has expired');
        }

        $context = $this->loadContext((int) $interview['application_id']);
        $job = $context['job'];
        $candidate = $context['candidate'];

        // If already in progress, just return the existing transcript.
        if ($interview['status'] === 'in_progress') {
            $messages = $this->repository->getMessages((int) $interview['id']);
            $opening = '';
            foreach ($messages as $m) {
                if ($m['role'] === 'ai') {
                    $opening = (string) $m['content'];
                    break;
                }
            }
            return ['interview' => $interview, 'opening' => $opening, 'messages' => $messages];
        }

        $this->repository->update((int) $interview['id'], [
            'status'     => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $conductor = new InterviewConductor();
        $opening = (string) $conductor->startInterview($job, $candidate, (string) $interview['type']);
        if ($opening === '') {
            $opening = sprintf(
                'Hello%s, thank you for joining this interview for the %s role. When you are ready, let us begin.',
                !empty($candidate['first_name']) ? ' ' . $candidate['first_name'] : '',
                (string) ($job['title'] ?? 'open')
            );
        }

        $this->repository->addMessage((int) $interview['id'], 'ai', $opening);

        $interview = $this->repository->findById((int) $interview['id']);
        $messages = $this->repository->getMessages((int) $interview['id']);

        return ['interview' => $interview, 'opening' => $opening, 'messages' => $messages];
    }

    /**
     * Process a candidate's message: persist it, then either close the interview
     * or ask the next AI question.
     *
     * @return array{reply:string,is_complete:bool,is_closing:bool,questions_asked:int}
     */
    public function processMessage(string $token, string $message): array
    {
        $interview = $this->repository->findByToken($token);
        if ($interview === null) {
            throw new \RuntimeException('Interview not found');
        }
        if ($interview['status'] !== 'in_progress') {
            throw new \RuntimeException('Interview is not active');
        }

        $interviewId = (int) $interview['id'];
        $message = trim($message);
        if ($message === '') {
            throw new \RuntimeException('Message cannot be empty');
        }

        // Record the candidate's reply.
        $this->repository->addMessage($interviewId, 'candidate', $message);

        $messages = $this->repository->getMessages($interviewId);
        $questionsAsked = $this->countAiMessages($messages);
        $startEpoch = $this->startEpoch($interview);

        $conductor = new InterviewConductor();

        if ($conductor->shouldEnd($questionsAsked, $startEpoch, $messages)) {
            $closing = (string) $conductor->generateClosingMessage($interview);
            if ($closing === '') {
                $closing = 'Thank you for your time and thoughtful answers. That concludes the interview '
                    . 'questions. Our team will review your responses and be in touch with next steps shortly.';
            }
            $this->repository->addMessage($interviewId, 'ai', $closing);

            return [
                'reply'           => $closing,
                'is_complete'     => true,
                'is_closing'      => true,
                'questions_asked' => $questionsAsked,
            ];
        }

        $next = $conductor->getNextQuestion($interview, $messages, $questionsAsked);
        $question = is_array($next) ? (string) ($next['question'] ?? '') : (string) $next;
        $isClosing = is_array($next) ? (bool) ($next['is_closing'] ?? false) : false;
        if ($question === '') {
            $question = 'Could you tell me more about a recent challenge you faced and how you handled it?';
        }

        $this->repository->addMessage($interviewId, 'ai', $question);

        return [
            'reply'           => $question,
            'is_complete'     => $isClosing,
            'is_closing'      => $isClosing,
            'questions_asked' => $questionsAsked + 1,
        ];
    }

    /**
     * Convenience: ask for the next AI question without recording a candidate
     * message first (used by the GET next-question endpoint).
     *
     * @return array{question:string,is_closing:bool,questions_asked:int}
     */
    public function getNextQuestion(string $token): array
    {
        $interview = $this->repository->findByToken($token);
        if ($interview === null) {
            throw new \RuntimeException('Interview not found');
        }
        $messages = $this->repository->getMessages((int) $interview['id']);
        $questionsAsked = $this->countAiMessages($messages);

        $conductor = new InterviewConductor();
        $next = $conductor->getNextQuestion($interview, $messages, $questionsAsked);
        $question = is_array($next) ? (string) ($next['question'] ?? '') : (string) $next;
        $isClosing = is_array($next) ? (bool) ($next['is_closing'] ?? false) : false;

        return [
            'question'        => $question,
            'is_closing'      => $isClosing,
            'questions_asked' => $questionsAsked,
        ];
    }

    /**
     * Complete an interview: stamp completion + duration, run the AI evaluator,
     * persist the evaluation and advance the pipeline when recommended.
     *
     * @return array<string,mixed> the evaluation result plus persistence metadata
     */
    public function completeInterview(string $token): array
    {
        $interview = $this->repository->findByToken($token);
        if ($interview === null) {
            throw new \RuntimeException('Interview not found');
        }
        $interviewId = (int) $interview['id'];

        // Idempotency: if already completed, return the stored evaluation.
        if ($interview['status'] === 'completed') {
            $existing = $this->getReport($interviewId);
            if (!empty($existing['evaluation'])) {
                return array_merge((array) $existing['evaluation'], [
                    'already_completed' => true,
                    'skill_scores'      => $existing['skill_scores'] ?? [],
                    'personality'       => $existing['personality_analysis'] ?? null,
                    'red_flags'         => $existing['red_flags'] ?? [],
                ]);
            }
        }

        $startedAt = $interview['started_at'] ?? null;
        $now = date('Y-m-d H:i:s');
        $duration = null;
        if ($startedAt) {
            $duration = max(0, strtotime($now) - strtotime((string) $startedAt));
        }

        $this->repository->update($interviewId, [
            'status'           => 'completed',
            'completed_at'     => $now,
            'duration_seconds' => $duration,
        ]);

        $messages = $this->repository->getMessages($interviewId);

        $context = $this->loadContext((int) $interview['application_id']);
        $job = $context['job'];
        $jobCriteria = $this->decodeJson($job['ai_criteria'] ?? null);
        if ($jobCriteria === []) {
            $jobCriteria = [
                'title'        => $job['title'] ?? '',
                'description'  => $job['description'] ?? '',
                'requirements' => $job['requirements'] ?? '',
            ];
        }

        $evaluator = new InterviewEvaluator();
        $evaluation = $evaluator->evaluate($messages, $jobCriteria);
        if (!is_array($evaluation)) {
            $evaluation = [];
        }

        $evaluationId = $this->repository->saveEvaluation($interviewId, $evaluation);
        $evaluation['evaluation_id'] = $evaluationId;
        $evaluation['interview_id'] = $interviewId;

        // Advance the pipeline when the candidate is recommended.
        $recommendation = strtolower((string) ($evaluation['recommendation'] ?? ''));
        if (in_array($recommendation, ['hire', 'maybe'], true)) {
            $this->db->update(
                'applications',
                ['pipeline_stage' => 'human_interview'],
                ['id' => (int) $interview['application_id']]
            );
            $evaluation['pipeline_stage'] = 'human_interview';
        }

        return $evaluation;
    }

    /**
     * Assemble a full interview report.
     *
     * @return array<string,mixed>
     */
    public function getReport(int $interviewId): array
    {
        $interview = $this->repository->findById($interviewId);
        if ($interview === null) {
            return [];
        }

        $context = $this->loadContext((int) $interview['application_id']);
        $messages = $this->repository->getMessages($interviewId);

        $evaluation = $this->db->fetch(
            'SELECT * FROM interview_evaluations WHERE interview_id = :id ORDER BY created_at DESC LIMIT 1',
            [':id' => $interviewId]
        );

        $skillScores = [];
        $personality = null;
        $redFlags = [];
        if ($evaluation !== null) {
            $evalId = (int) $evaluation['id'];
            $skillScores = $this->db->fetchAll(
                'SELECT * FROM skill_scores WHERE evaluation_id = :eid ORDER BY score DESC',
                [':eid' => $evalId]
            );
            $personality = $this->db->fetch(
                'SELECT * FROM personality_analysis WHERE evaluation_id = :eid LIMIT 1',
                [':eid' => $evalId]
            );
            $redFlags = $this->db->fetchAll(
                "SELECT * FROM red_flags WHERE evaluation_id = :eid
                    ORDER BY FIELD(severity,'high','medium','low'), id ASC",
                [':eid' => $evalId]
            );
        }

        return [
            'interview'            => $interview,
            'candidate'            => $context['candidate'],
            'job'                  => $context['job'],
            'application'          => $context['application'],
            'messages'             => $messages,
            'evaluation'           => $evaluation,
            'skill_scores'         => $skillScores,
            'personality_analysis' => $personality,
            'red_flags'            => $redFlags,
        ];
    }

    /**
     * Build an invite email (subject + HTML body) containing the interview room
     * link.
     *
     * @return array{subject:string,body:string,room_url:string,token:string}
     */
    public function generateInviteEmail(int $interviewId): array
    {
        $interview = $this->repository->findById($interviewId);
        if ($interview === null) {
            throw new \RuntimeException('Interview not found');
        }
        $context = $this->loadContext((int) $interview['application_id']);
        $job = $context['job'];
        $candidate = $context['candidate'];

        $config = function_exists('config') ? config('app') : require dirname(__DIR__, 2) . '/config/app.php';
        $baseUrl = rtrim((string) ($config['url'] ?? ''), '/');
        $token = (string) $interview['token'];
        $roomUrl = $baseUrl . '/interview/room/' . $token;

        $candidateName = trim((string) ($candidate['first_name'] ?? '') . ' ' . (string) ($candidate['last_name'] ?? ''));
        $candidateName = $candidateName !== '' ? $candidateName : 'there';
        $jobTitle = (string) ($job['title'] ?? 'the role');
        $company = (string) ($context['company'] ?? 'our company');
        $typeLabel = $this->typeLabel((string) $interview['type']);

        $safeName = htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8');
        $safeJob = htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8');
        $safeCompany = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($roomUrl, ENT_QUOTES, 'UTF-8');

        $subject = sprintf('Your AI interview for %s at %s', $jobTitle, $company);

        $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <div style="max-width:560px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border-radius:12px;padding:32px;">
      <h1 style="margin:0 0 16px;font-size:22px;color:#7C3AED;">You're invited to an interview</h1>
      <p style="font-size:15px;line-height:1.6;">Hi {$safeName},</p>
      <p style="font-size:15px;line-height:1.6;">
        Thank you for applying for the <strong>{$safeJob}</strong> position at <strong>{$safeCompany}</strong>.
        The next step is a {$typeLabel} interview that you can complete at a time that suits you.
      </p>
      <p style="font-size:15px;line-height:1.6;">
        The interview takes around 15&ndash;20 minutes. Find a quiet place, then click the button below to begin.
      </p>
      <p style="text-align:center;margin:28px 0;">
        <a href="{$safeUrl}" style="background:#7C3AED;color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:8px;font-size:15px;display:inline-block;">Start your interview</a>
      </p>
      <p style="font-size:13px;line-height:1.6;color:#6b7280;">
        If the button does not work, copy and paste this link into your browser:<br>
        <a href="{$safeUrl}" style="color:#7C3AED;">{$safeUrl}</a>
      </p>
      <hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
      <p style="font-size:12px;color:#9ca3af;">This link is unique to you. Please do not share it.</p>
    </div>
  </div>
</body>
</html>
HTML;

        return [
            'subject'  => $subject,
            'body'     => $body,
            'room_url' => $roomUrl,
            'token'    => $token,
        ];
    }

    public function getRepository(): InterviewRepository
    {
        return $this->repository;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Load application, candidate, job and company (tenant name) for context.
     *
     * @return array{application:?array<string,mixed>,candidate:array<string,mixed>,job:array<string,mixed>,company:string,avatar:?array<string,mixed>}
     */
    private function loadContext(int $applicationId): array
    {
        $row = $this->db->fetch(
            'SELECT a.*, j.id AS job_id, j.title AS job_title, j.description AS job_description,
                    j.requirements AS job_requirements, j.ai_criteria, j.question_bank, j.avatar_id,
                    j.tenant_id AS job_tenant_id,
                    c.id AS candidate_id, c.first_name, c.last_name, c.email, c.cv_text,
                    t.name AS company_name
                FROM applications a
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                LEFT JOIN tenants t ON t.id = j.tenant_id
                WHERE a.id = :id LIMIT 1',
            [':id' => $applicationId]
        );

        if ($row === null) {
            return [
                'application' => null,
                'candidate'   => [],
                'job'         => [],
                'company'     => '',
                'avatar'      => null,
            ];
        }

        $job = [
            'id'           => (int) $row['job_id'],
            'title'        => $row['job_title'],
            'description'  => $row['job_description'],
            'requirements' => $row['job_requirements'],
            'ai_criteria'  => $row['ai_criteria'],
            'question_bank' => $row['question_bank'],
            'avatar_id'    => $row['avatar_id'],
            'tenant_id'    => (int) $row['job_tenant_id'],
        ];
        $candidate = [
            'id'         => (int) $row['candidate_id'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'email'      => $row['email'],
            'cv_text'    => $row['cv_text'],
        ];

        $avatar = null;
        if (!empty($row['avatar_id'])) {
            $avatar = $this->db->fetch('SELECT * FROM avatars WHERE id = :id LIMIT 1', [':id' => (int) $row['avatar_id']]);
        }

        return [
            'application' => $row,
            'candidate'   => $candidate,
            'job'         => $job,
            'company'     => (string) ($row['company_name'] ?? ''),
            'avatar'      => $avatar,
        ];
    }

    private function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24));
        } while ($this->repository->findByToken($token) !== null);
        return $token;
    }

    /**
     * @param array<int,array<string,mixed>> $messages
     */
    private function countAiMessages(array $messages): int
    {
        $count = 0;
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'ai') {
                $count++;
            }
        }
        return $count;
    }

    private function startEpoch(array $interview): int
    {
        $startedAt = $interview['started_at'] ?? null;
        if ($startedAt) {
            $ts = strtotime((string) $startedAt);
            if ($ts !== false) {
                return $ts;
            }
        }
        return time();
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'ai_voice' => 'voice',
            'ai_video' => 'video',
            default    => 'text-based',
        };
    }

    /**
     * @return array<int|string,mixed>
     */
    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
