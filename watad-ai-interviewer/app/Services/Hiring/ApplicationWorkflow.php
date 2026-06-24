<?php

declare(strict_types=1);

namespace App\Services\Hiring;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionType;
use App\Enums\Recommendation;
use App\Models\CandidateActivity;
use App\Models\HiringDecision;
use App\Models\JobApplication;

/**
 * Owns application status transitions, hiring decisions (incl. AI override) and the candidate
 * activity timeline. The AI never advances an application — only a human decision does.
 * See docs/21-hiring-workflow.md.
 */
class ApplicationWorkflow
{
    /** Ordered board statuses (Kanban columns). */
    public const BOARD = [
        ApplicationStatus::Applied,
        ApplicationStatus::AiScreening,
        ApplicationStatus::Qualified,
        ApplicationStatus::TechInterview,
        ApplicationStatus::ManagerInterview,
        ApplicationStatus::FinalReview,
        ApplicationStatus::Offer,
        ApplicationStatus::Hired,
        ApplicationStatus::Rejected,
    ];

    public function logActivity(JobApplication $app, string $type, string $summary, ?int $actorId = null, array $payload = []): void
    {
        CandidateActivity::create([
            'candidate_id'   => $app->candidate_id,
            'application_id' => $app->id,
            'type'           => $type,
            'actor_type'     => $actorId ? 'user' : 'system',
            'actor_id'       => $actorId,
            'summary'        => $summary,
            'payload'        => $payload,
            'occurred_at'    => now(),
        ]);
        $app->forceFill(['last_activity_at' => now()])->save();
    }

    /**
     * Apply the AI screening outcome to the application. The AI auto-advances strong candidates to
     * Qualified ("approved") and holds everyone else for human review — it NEVER rejects. Final
     * rejection always requires a human decision (see decide()).
     */
    public function aiScreeningOutcome(JobApplication $app, Recommendation $rec, float $overall, bool $criticalFlags = false): void
    {
        if ($app->status->isTerminal()) {
            return; // a human already closed this out — don't override
        }

        $approved = ! $criticalFlags && in_array($rec, [Recommendation::StrongHire, Recommendation::Hire], true);
        $score    = round($overall, 1);

        if ($approved) {
            $from = $app->status;
            if ($from !== ApplicationStatus::Qualified) {
                $app->status = ApplicationStatus::Qualified;
                $app->save();
            }
            $this->logActivity($app, 'ai_advanced',
                "AI screening passed — {$score}/100 ({$rec->label()}). Auto-advanced to Qualified.",
                null, ['overall_score' => $overall, 'recommendation' => $rec->value, 'outcome' => 'approved']);
            return;
        }

        // Held for human review. Stays in Ai Screening; flag for attention when critical.
        $reason = $criticalFlags
            ? 'critical red flags — HR attention required'
            : 'score below auto-advance threshold';
        $this->logActivity($app, $criticalFlags ? 'ai_attention_required' : 'ai_pending_review',
            "AI screening complete — {$score}/100 ({$rec->label()}). Held for human review ({$reason}).",
            null, ['overall_score' => $overall, 'recommendation' => $rec->value,
                   'outcome' => 'pending_review', 'critical_flags' => $criticalFlags]);
    }

    /** Record a hiring decision and apply the resulting status transition. */
    public function decide(JobApplication $app, DecisionType $decision, ?int $userId, ?string $reason = null, bool $overrideAi = false): HiringDecision
    {
        $from = $app->status;
        $to   = $this->resolveStatus($app, $decision);

        $record = HiringDecision::create([
            'application_id' => $app->id,
            'user_id'        => $userId,
            'stage'          => $from->value,
            'decision'       => $decision,
            'ai_overridden'  => $overrideAi,
            'reason'         => $reason,
            'from_status'    => $from->value,
            'to_status'      => $to?->value,
        ]);

        if ($to && $to !== $from) {
            $app->status = $to;
            $app->save();
        }

        $this->logActivity(
            $app,
            $overrideAi ? 'ai_overridden' : 'decision_made',
            ($overrideAi ? 'AI overridden — ' : '').ucfirst(str_replace('_', ' ', $decision->value)).($to ? " → {$to->label()}" : ''),
            $userId,
            ['decision' => $decision->value, 'reason' => $reason],
        );

        return $record;
    }

    /** Directly set a status (used by the Kanban drag-and-drop). */
    public function moveToStatus(JobApplication $app, ApplicationStatus $to, ?int $userId): void
    {
        $from = $app->status;
        if ($from === $to) {
            return;
        }
        $app->status = $to;
        $app->save();
        $this->logActivity($app, 'stage_changed', "{$from->label()} → {$to->label()}", $userId);
    }

    private function resolveStatus(JobApplication $app, DecisionType $decision): ?ApplicationStatus
    {
        return match ($decision) {
            DecisionType::Advance   => $this->advanceFrom($app->status),
            DecisionType::Reject    => ApplicationStatus::Rejected,
            DecisionType::Approve, DecisionType::MakeOffer => ApplicationStatus::Offer,
            DecisionType::Hold      => null,
        };
    }

    private function advanceFrom(ApplicationStatus $status): ApplicationStatus
    {
        return match ($status) {
            ApplicationStatus::Applied,
            ApplicationStatus::AiScreening      => ApplicationStatus::Qualified,
            ApplicationStatus::Qualified        => ApplicationStatus::TechInterview,
            ApplicationStatus::TechInterview    => ApplicationStatus::ManagerInterview,
            ApplicationStatus::ManagerInterview => ApplicationStatus::FinalReview,
            ApplicationStatus::FinalReview      => ApplicationStatus::Offer,
            default                             => $status,
        };
    }
}
