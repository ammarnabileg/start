<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Interview;
use App\Models\InterviewReport;
use App\Services\AI\LlmManager;
use App\Services\AI\Prompts\PromptLibrary;
use App\Services\Reports\PdfReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Synthesizes the narrative report sections from the structured analysis, then renders the PDF.
 * See docs/12-pdf-report-structure.md.
 */
class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    public function __construct(public int $interviewId) {}

    public function handle(LlmManager $llm, PromptLibrary $prompts, PdfReportService $pdf): void
    {
        $interview = Interview::with([
            'candidate.latestCvAnalysis', 'jobPosition', 'competencyScores',
            'behavioralAnalysis', 'redFlags', 'messages',
        ])->findOrFail($this->interviewId);

        $data = $llm->json('analysis', [
            'system'   => [['type' => 'text', 'text' => $prompts->reportSystem()]],
            'thinking' => $prompts->analysisThinking(),
            'messages' => [['role' => 'user', 'content' => $this->context($interview)]],
        ]);

        $report = InterviewReport::updateOrCreate(
            ['interview_id' => $interview->id],
            [
                'overall_score'         => $interview->overall_score,
                'recommendation'        => $interview->recommendation,
                'resume_summary'        => $data['resume_summary'] ?? $interview->candidate?->latestCvAnalysis?->summary,
                'interview_summary'     => $data['interview_summary'] ?? null,
                'strengths'             => $data['strengths'] ?? [],
                'weaknesses'            => $data['weaknesses'] ?? [],
                'technical_assessment'  => $data['technical_assessment'] ?? null,
                'behavioral_assessment' => $data['behavioral_assessment'] ?? null,
                'ai_analysis'           => $data['ai_analysis'] ?? null,
                'hiring_recommendation' => $data['hiring_recommendation'] ?? null,
                'model'                 => $llm->model('analysis'),
                'generated_at'          => now(),
            ],
        );

        $pdf->generate($interview->setRelation('report', $report));
    }

    private function context(Interview $interview): string
    {
        $scores = $interview->competencyScores
            ->map(fn ($s) => "- {$s->competency}: {$s->score}/100 (weight {$s->weight}) — {$s->rationale}")
            ->implode("\n");

        $flags = $interview->redFlags->isEmpty()
            ? 'None detected.'
            : $interview->redFlags->map(fn ($f) => "- [{$f->severity}] {$f->type}: {$f->description}")->implode("\n");

        $behavioral = $interview->behavioralAnalysis;
        $beh = $behavioral
            ? "Personality: {$behavioral->personality_type}. ".($behavioral->observations ?? '')
            : 'n/a';

        $cv = $interview->candidate?->latestCvAnalysis?->summary ?? 'n/a';

        $transcript = $interview->messages
            ->where('role', '!=', 'system')
            ->sortBy('seq')
            ->map(fn ($m) => "[{$m->seq}] ".strtoupper($m->role).': '.$m->content)
            ->implode("\n");

        return "POSITION: {$interview->jobPosition?->title}\n"
            ."OVERALL: {$interview->overall_score}/100  RECOMMENDATION: {$interview->recommendation?->value}\n\n"
            ."COMPETENCY SCORES:\n{$scores}\n\nBEHAVIORAL:\n{$beh}\n\nRED FLAGS:\n{$flags}\n\n"
            ."CV SUMMARY:\n{$cv}\n\nTRANSCRIPT:\n{$transcript}";
    }
}
