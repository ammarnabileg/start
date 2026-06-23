<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Interview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the interview report Blade template to PDF and stores it (S3). See docs/12.
 */
class PdfReportService
{
    public function generate(Interview $interview): string
    {
        $interview->loadMissing([
            'candidate.latestCvAnalysis', 'jobPosition', 'avatar',
            'competencyScores', 'behavioralAnalysis', 'redFlags', 'report', 'events',
        ]);

        $pdf = Pdf::loadView('reports.interview', ['interview' => $interview])
            ->setPaper('a4');

        $key = "reports/{$interview->public_id}.pdf";
        Storage::put($key, $pdf->output());

        $interview->report?->update(['pdf_path' => $key]);

        return $key;
    }
}
