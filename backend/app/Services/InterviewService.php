<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AiEvaluation;
use App\Models\BehavioralAnalysis;
use App\Models\FeedbackResponse;
use App\Models\InterviewMessage;
use App\Models\InterviewSession;
use App\Models\InvitationLink;
use App\Models\RiskFlag;
use App\Models\SkillScore;
use Illuminate\Support\Str;

class InterviewService
{
    public function __construct(private AIService $aiService) {}

    public function generateInvitationLink(Application $application, string $type = 'text', int $expiryDays = 14): InvitationLink
    {
        $application->invitationLinks()->where('is_active', true)->update(['is_active' => false]);

        return $application->invitationLinks()->create([
            'token' => Str::random(64),
            'interview_type' => $type,
            'expires_at' => now()->addDays($expiryDays),
        ]);
    }

    public function startInterview(InvitationLink $link): InterviewSession
    {
        $application = $link->application->load(['job', 'candidate', 'candidate.primaryCv']);

        $link->update(['used_at' => now()]);
        $application->update(['pipeline_stage' => 'ai_screening', 'status' => 'ai_screening']);

        $session = $application->interviewSessions()->create([
            'invitation_link_id' => $link->id,
            'type' => $link->interview_type,
            'status' => 'in_progress',
            'max_questions' => $application->job->max_questions ?? 12,
            'started_at' => now(),
        ]);

        $this->sendWelcomeMessage($session, $application);

        FeedbackResponse::create([
            'interview_session_id' => $session->id,
            'candidate_id' => $application->candidate_id,
            'expires_at' => now()->addHours(24),
        ]);

        return $session;
    }

    public function processMessage(InterviewSession $session, string $candidateMessage): string
    {
        $application = $session->application->load(['job.criteria', 'job.questionBank', 'candidate']);

        $session->messages()->create([
            'role' => 'user',
            'content' => $candidateMessage,
            'message_type' => 'text',
        ]);

        $messages = $session->messages()->orderBy('created_at')->get()->map(fn($m) => [
            'role' => $m->role,
            'content' => $m->content,
        ])->toArray();

        $jobData = array_merge($application->job->toArray(), [
            'company_name' => $application->job->tenant->name,
            'avatar_name' => $application->job->avatar?->name ?? 'Sarah',
        ]);

        $isQuestion = $session->questions_asked < $session->max_questions;

        $aiResponse = $this->aiService->generateInterviewResponse(
            $messages,
            $jobData,
            $application->job->criteria->toArray(),
            $application->job->questionBank->toArray(),
            $session->questions_asked,
            $session->max_questions
        );

        if ($isQuestion && !str_starts_with(strtolower($candidateMessage), 'hi') && strlen($candidateMessage) > 10) {
            $session->increment('questions_asked');
        }

        $session->messages()->create([
            'role' => 'assistant',
            'content' => $aiResponse,
            'question_number' => $session->questions_asked,
        ]);

        if ($session->questions_asked >= $session->max_questions) {
            $this->completeInterview($session);
        }

        return $aiResponse;
    }

    public function completeInterview(InterviewSession $session): void
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_seconds' => now()->diffInSeconds($session->started_at),
        ]);

        dispatch(function () use ($session) {
            $this->evaluateInterview($session);
        })->afterResponse();
    }

    public function evaluateInterview(InterviewSession $session): AiEvaluation
    {
        $application = $session->application->load(['job.criteria', 'candidate.primaryCv']);
        $transcript = $session->getTranscript();

        $jobData = array_merge($application->job->toArray(), [
            'company_name' => $application->job->tenant->name,
        ]);

        $result = $this->aiService->evaluateInterview(
            $transcript,
            $jobData,
            $application->job->criteria->toArray(),
            $application->cv_analysis ?? []
        );

        $evaluation = $application->aiEvaluation()->updateOrCreate(
            ['application_id' => $application->id],
            [
                'interview_session_id' => $session->id,
                'overall_score' => $result['overall_score'] ?? 0,
                'recommendation' => $result['recommendation'] ?? 'not_recommended',
                'executive_summary' => $result['executive_summary'] ?? '',
                'strengths' => json_encode($result['strengths'] ?? []),
                'weaknesses' => json_encode($result['weaknesses'] ?? []),
                'missing_skills' => json_encode($result['missing_skills'] ?? []),
                'criteria_scores' => $result['criteria_scores'] ?? [],
                'raw_response' => $result,
                'evaluated_at' => now(),
            ]
        );

        if (!empty($result['skill_scores'])) {
            foreach ($result['skill_scores'] as $skill) {
                $skillDef = collect(SkillScore::DEFAULT_SKILLS)->firstWhere('key', $skill['skill_key']);
                SkillScore::updateOrCreate(
                    ['ai_evaluation_id' => $evaluation->id, 'skill_key' => $skill['skill_key']],
                    [
                        'candidate_id' => $application->candidate_id,
                        'skill_name' => $skillDef['name'] ?? $skill['skill_key'],
                        'skill_name_ar' => $skillDef['name_ar'] ?? null,
                        'score' => $skill['score'] ?? 0,
                        'weight' => $skillDef['weight'] ?? 0,
                        'confidence' => $skill['confidence'] ?? 0,
                        'evidence' => $skill['evidence'] ?? null,
                    ]
                );
            }
        }

        BehavioralAnalysis::updateOrCreate(
            ['ai_evaluation_id' => $evaluation->id],
            [
                'candidate_id' => $application->candidate_id,
                'disc_profile' => $result['disc_profile'] ?? null,
                'big_five' => $result['big_five'] ?? null,
                'growth_score' => $result['growth_score'] ?? 0,
                'stress_score' => $result['stress_score'] ?? 0,
                'leadership_style' => $result['leadership_style'] ?? null,
                'learning_ability' => $result['learning_ability'] ?? null,
            ]
        );

        if (!empty($result['risk_flags'])) {
            $evaluation->riskFlags()->delete();
            foreach ($result['risk_flags'] as $flag) {
                $evaluation->riskFlags()->create([
                    'flag_type' => $flag['flag_type'],
                    'severity' => $flag['severity'] ?? 'low',
                    'description' => $flag['description'],
                    'evidence' => $flag['evidence'] ?? null,
                ]);
            }
        }

        $application->update([
            'overall_score' => $result['overall_score'] ?? 0,
            'ai_recommendation' => $result['recommendation'] ?? 'not_recommended',
            'pipeline_stage' => $this->determineStage($result['overall_score'] ?? 0),
        ]);

        return $evaluation;
    }

    private function sendWelcomeMessage(InterviewSession $session, Application $application): void
    {
        $avatarName = $application->job->avatar?->name ?? 'Sarah';
        $jobTitle = $application->job->title;
        $companyName = $application->job->tenant->name;
        $candidateName = $application->candidate->name;

        $welcomeAr = "أهلاً {$candidateName}! أنا {$avatarName}، مساعد التوظيف الذكي في {$companyName}. سأجري معك اليوم مقابلة أولية لوظيفة {$jobTitle}. هذه المقابلة ستستغرق حوالي 20 دقيقة وستتضمن {$session->max_questions} أسئلة. هل أنت مستعد للبدء؟";

        $welcomeEn = "Hello {$candidateName}! I'm {$avatarName}, the AI interview assistant at {$companyName}. I'll be conducting your first-round interview for the {$jobTitle} position today. This will take about 20 minutes with {$session->max_questions} questions. Are you ready to begin?";

        $lang = $application->candidate->preferred_language ?? 'ar';

        $session->messages()->create([
            'role' => 'assistant',
            'content' => $lang === 'ar' ? $welcomeAr : $welcomeEn,
            'message_type' => 'welcome',
        ]);
    }

    private function determineStage(float $score): string
    {
        return match(true) {
            $score >= 68 => 'qualified',
            $score >= 50 => 'qualified',
            default => 'disqualified',
        };
    }
}
