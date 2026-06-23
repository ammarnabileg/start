<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Candidate;
use App\Models\CvAnalysis;
use App\Models\Interview;
use App\Models\JobPosition;
use App\Services\AI\Prompts\PromptLibrary;
use Illuminate\Support\Facades\Storage;

/**
 * Analyzes a candidate's CV against the job before the interview. Uses native PDF (vision) input
 * when available, falling back to extracted text. Output feeds interview.topics_to_probe.
 */
final class CvAnalyzer
{
    public function __construct(
        private readonly LlmManager $llm,
        private readonly PromptLibrary $prompts,
    ) {}

    public function analyze(Candidate $candidate, JobPosition $job, ?Interview $interview = null): CvAnalysis
    {
        $userContent = [[
            'type' => 'text',
            'text' => "JOB: {$job->title} ({$job->seniority})\n"
                .'Requirements: '.json_encode($job->requirements)."\n\n"
                .'Analyze the attached/!below CV against this job.',
        ]];

        $pdf = $this->pdfBlock($candidate);
        if ($pdf) {
            $userContent[] = $pdf;
        } elseif ($candidate->cv_text) {
            $userContent[] = ['type' => 'text', 'text' => "CV TEXT:\n".$candidate->cv_text];
        }

        $data = $this->llm->json('cv', [
            'system'   => [['type' => 'text', 'text' => $this->prompts->cvAnalystSystem()]],
            'thinking' => $this->prompts->analysisThinking(),
            'messages' => [['role' => 'user', 'content' => $userContent]],
        ]);

        return CvAnalysis::updateOrCreate(
            ['candidate_id' => $candidate->id, 'interview_id' => $interview?->id],
            [
                'summary'         => $data['summary'] ?? null,
                'extracted'       => $data['extracted'] ?? null,
                'highlights'      => $data['highlights'] ?? null,
                'gaps'            => $data['gaps'] ?? null,
                'jd_match_score'  => isset($data['jd_match_score']) ? (float) $data['jd_match_score'] : null,
                'topics_to_probe' => $data['topics_to_probe'] ?? null,
                'model'           => $this->llm->model('cv'),
            ],
        );
    }

    /** Build an Anthropic PDF document content block from the stored CV, if it is a PDF. */
    private function pdfBlock(Candidate $candidate): ?array
    {
        if (! $candidate->cv_path || ! str_ends_with(strtolower($candidate->cv_path), '.pdf')) {
            return null;
        }

        try {
            $bytes = Storage::get($candidate->cv_path);
        } catch (\Throwable) {
            return null;
        }
        if (! $bytes) {
            return null;
        }

        return [
            'type'   => 'document',
            'source' => [
                'type'       => 'base64',
                'media_type' => 'application/pdf',
                'data'       => base64_encode($bytes),
            ],
        ];
    }
}
