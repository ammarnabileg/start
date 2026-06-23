<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\Interview;
use Illuminate\Contracts\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an .xlsx export of interviews (same columns as the sheet plus per-competency scores).
 * Streaming keeps memory flat for large datasets. See docs/11.
 */
class ExcelExportService
{
    private const BASE_HEADERS = [
        'Candidate ID', 'Date', 'Position', 'Name', 'Email', 'Phone', 'Country',
        'Experience', 'Interview Score', 'Recommendation', 'Status',
    ];

    public function download(Builder $query, string $filename = 'interviews.xlsx'): StreamedResponse
    {
        $competencies = \App\Enums\Competency::values();

        return new StreamedResponse(function () use ($query, $competencies) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([...self::BASE_HEADERS, ...array_map('ucfirst', $competencies)]));

            $query->with(['candidate', 'jobPosition', 'competencyScores'])
                ->chunk(500, function ($interviews) use ($writer, $competencies) {
                    foreach ($interviews as $interview) {
                        $writer->addRow(Row::fromValues($this->row($interview, $competencies)));
                    }
                });

            $writer->close();
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function row(Interview $interview, array $competencies): array
    {
        $scores = $interview->competencyScores->keyBy('competency');

        $base = [
            $interview->public_id,
            optional($interview->completed_at)->toDateString(),
            $interview->jobPosition?->title,
            $interview->candidate?->full_name,
            $interview->candidate?->email,
            $interview->candidate?->phone,
            $interview->candidate?->country,
            $interview->candidate?->years_experience,
            $interview->overall_score,
            $interview->recommendation?->label(),
            $interview->status->value,
        ];

        foreach ($competencies as $c) {
            $base[] = $scores[$c]->score ?? null;
        }

        return $base;
    }
}
