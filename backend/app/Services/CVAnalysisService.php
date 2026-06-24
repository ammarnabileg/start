<?php

namespace App\Services;

use App\Models\CandidateCv;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class CVAnalysisService
{
    public function __construct(private AIService $aiService) {}

    public function parse(CandidateCv $cv): array
    {
        $text = $this->extractText($cv);
        return ['text' => $text, 'word_count' => str_word_count($text)];
    }

    public function analyzeForJob(CandidateCv $cv, array $jobData): array
    {
        $text = $this->extractText($cv);
        if (empty($text)) return ['match_score' => 0, 'error' => 'Could not read CV'];

        $analysis = $this->aiService->analyzeCv($text, $jobData);

        $cv->update([
            'parsed_data' => $analysis,
            'parsing_confidence' => ($analysis['match_score'] ?? 0) / 100,
            'parsed_at' => now(),
        ]);

        return $analysis;
    }

    private function extractText(CandidateCv $cv): string
    {
        try {
            $path = Storage::path($cv->file_path);
            if ($cv->file_type === 'pdf') {
                $parser = new Parser();
                $pdf = $parser->parseFile($path);
                return $pdf->getText();
            }
            return file_get_contents($path);
        } catch (\Exception $e) {
            return '';
        }
    }
}
