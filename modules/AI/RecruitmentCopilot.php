<?php

namespace App\Modules\AI;

use App\Core\Database;
use Throwable;

/**
 * Conversational copilot for HR professionals using this platform, plus the
 * deterministic context + proactive suggestions that ground its answers.
 */
class RecruitmentCopilot
{
    private Database $db;
    private OpenAIService $ai;

    public function __construct(?Database $db = null, ?OpenAIService $ai = null)
    {
        $this->db = $db ?? Database::instance();
        $this->ai = $ai ?? new OpenAIService();
    }

    /**
     * Answer an HR question, grounded in optional tenant context and prior turns.
     *
     * @param array<string,mixed> $context Counts/summary from getContext().
     * @param array<int,array{role:string,content:string}> $history Prior turns.
     * @return array{reply:string}
     */
    public function chat(string $message, array $context = [], array $history = []): array
    {
        $message = trim($message);

        if (!$this->ai->isConfigured()) {
            return ['reply' => $this->fallbackReply($message, $context)];
        }

        $system = 'You are an expert AI recruitment copilot embedded in an AI hiring platform used by HR and talent '
            . 'acquisition professionals. You help with sourcing, screening, interviewing, evaluating candidates, '
            . 'writing job posts and outreach, and interpreting hiring data inside this platform. Be concise, '
            . 'practical, and action-oriented. When you reference the user\'s data, use the live context provided. '
            . 'If you are unsure, say so and suggest the next best step.';

        if ($context !== []) {
            $system .= "\n\nLIVE WORKSPACE CONTEXT (current tenant):\n" . $this->renderContext($context);
        }

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $h) {
            $role = strtolower((string) ($h['role'] ?? ''));
            $role = in_array($role, ['user', 'assistant'], true) ? $role : ($role === 'ai' ? 'assistant' : 'user');
            $content = trim((string) ($h['content'] ?? ''));
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message !== '' ? $message : 'Hello'];

        $res = $this->ai->chat($messages, ['feature' => 'copilot', 'temperature' => 0.5, 'max_tokens' => 900]);
        $reply = trim($res['content']);
        if ($reply === '' || str_contains($reply, 'AI is not configured')) {
            $reply = $this->fallbackReply($message, $context);
        }
        return ['reply' => $reply];
    }

    /**
     * Gather live recruitment counts for a tenant.
     *
     * @return array<string,int>
     */
    public function getContext(int $tenantId): array
    {
        return [
            'open_jobs'          => $this->countOpenJobs($tenantId),
            'total_candidates'   => $this->countCandidates($tenantId),
            'pending_interviews' => $this->countPendingInterviews($tenantId),
            'offers_sent'        => $this->countOffersSent($tenantId),
        ];
    }

    /**
     * Deterministic proactive suggestions derived from the tenant context.
     *
     * @return array<int,string>
     */
    public function suggestActions(int $tenantId): array
    {
        $ctx = $this->getContext($tenantId);
        $suggestions = [];

        if (($ctx['open_jobs'] ?? 0) === 0) {
            $suggestions[] = 'You have no published jobs. Create and publish a role to start attracting candidates.';
        } else {
            $suggestions[] = 'You have ' . $ctx['open_jobs'] . ' open job'
                . ($ctx['open_jobs'] === 1 ? '' : 's') . ' live. Review their applicant pipelines for new activity.';
        }

        if (($ctx['pending_interviews'] ?? 0) > 0) {
            $suggestions[] = 'You have ' . $ctx['pending_interviews'] . ' candidate'
                . ($ctx['pending_interviews'] === 1 ? '' : 's')
                . ' awaiting or mid-interview. Follow up so they complete their AI interview.';
        }

        if (($ctx['total_candidates'] ?? 0) > 0 && ($ctx['open_jobs'] ?? 0) > 0) {
            $suggestions[] = 'Use the candidate matcher to rank your ' . $ctx['total_candidates']
                . ' candidate' . ($ctx['total_candidates'] === 1 ? '' : 's') . ' against your open roles.';
        } elseif (($ctx['total_candidates'] ?? 0) === 0) {
            $suggestions[] = 'No candidates yet. Share your career page link or import candidates to build your pipeline.';
        }

        if (($ctx['offers_sent'] ?? 0) > 0) {
            $suggestions[] = 'You have ' . $ctx['offers_sent'] . ' offer'
                . ($ctx['offers_sent'] === 1 ? '' : 's') . ' sent. Check for responses and nudge any that are pending.';
        }

        $suggestions[] = 'Ask me to draft a job description, generate interview questions, or summarize a candidate.';

        return $suggestions;
    }

    // ----------------------------------------------------------------------
    // Context queries (each is resilient: failures yield 0, never an exception)
    // ----------------------------------------------------------------------

    private function countOpenJobs(int $tenantId): int
    {
        return $this->scalarCount(
            "SELECT COUNT(*) AS c FROM jobs WHERE tenant_id = :tenant AND status = 'published'",
            [':tenant' => $tenantId]
        );
    }

    private function countCandidates(int $tenantId): int
    {
        return $this->scalarCount(
            'SELECT COUNT(*) AS c FROM candidates WHERE tenant_id = :tenant',
            [':tenant' => $tenantId]
        );
    }

    private function countPendingInterviews(int $tenantId): int
    {
        return $this->scalarCount(
            "SELECT COUNT(*) AS c
             FROM interviews i
             INNER JOIN applications a ON a.id = i.application_id
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE j.tenant_id = :tenant AND i.status IN ('pending','in_progress')",
            [':tenant' => $tenantId]
        );
    }

    private function countOffersSent(int $tenantId): int
    {
        return $this->scalarCount(
            "SELECT COUNT(*) AS c
             FROM offers o
             INNER JOIN applications a ON a.id = o.application_id
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE j.tenant_id = :tenant AND o.status = 'sent'",
            [':tenant' => $tenantId]
        );
    }

    /**
     * @param array<string,mixed> $params
     */
    private function scalarCount(string $sql, array $params): int
    {
        try {
            $row = $this->db->fetch($sql, $params);
            return (int) ($row['c'] ?? 0);
        } catch (Throwable $e) {
            if (function_exists('logger')) {
                logger('Copilot context query failed: ' . $e->getMessage(), 'warning');
            }
            return 0;
        }
    }

    // ----------------------------------------------------------------------
    // Rendering / fallback
    // ----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $context
     */
    private function renderContext(array $context): string
    {
        $lines = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $label = ucwords(str_replace('_', ' ', (string) $key));
                $lines[] = '- ' . $label . ': ' . $value;
            }
        }
        return $lines === [] ? '(no context available)' : implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function fallbackReply(string $message, array $context): string
    {
        $reply = "I'm your recruitment copilot. AI responses are currently unavailable (the OpenAI API key is not "
            . "configured), but I can still point you in the right direction.";

        if ($context !== []) {
            $reply .= "\n\nHere's a snapshot of your workspace:\n" . $this->renderContext($context);
        }

        $reply .= "\n\nThings you can do right now:\n"
            . "- Create or publish a job posting.\n"
            . "- Review candidate pipelines and AI match scores.\n"
            . "- Launch or follow up on AI interviews.\n"
            . "- Compare shortlisted candidates side by side.\n"
            . "\nOnce an OpenAI API key is configured, I can answer your questions in full and draft content for you.";

        return $reply;
    }
}
