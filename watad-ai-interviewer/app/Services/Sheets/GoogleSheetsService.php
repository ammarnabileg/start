<?php

declare(strict_types=1);

namespace App\Services\Sheets;

use App\Models\Interview;
use App\Models\SheetSync;
use Google\Client as GoogleClient;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

/**
 * Appends/updates the candidate row in a Google Sheet. Column layout is fixed (docs/11).
 * Auth via a service-account JSON (GOOGLE_APPLICATION_CREDENTIALS) shared with the spreadsheet.
 */
class GoogleSheetsService
{
    private const HEADERS = [
        'Candidate ID', 'Date', 'Position', 'Name', 'Email', 'Phone', 'Country',
        'Experience (yrs)', 'Interview Score', 'Technical Score', 'Soft Skills Score',
        'Recommendation', 'Status', 'Report',
    ];

    /** @return int the 1-based sheet row written. */
    public function upsertCandidateRow(Interview $interview, SheetSync $sync): int
    {
        $service = $this->service();
        $tab     = $sync->sheet_tab;
        $row     = $this->row($interview);

        $this->ensureHeader($service, $sync->spreadsheet_id, $tab);

        if ($sync->row_number) {
            $service->spreadsheets_values->update(
                $sync->spreadsheet_id,
                "{$tab}!A{$sync->row_number}",
                new ValueRange(['values' => [$row]]),
                ['valueInputOption' => 'USER_ENTERED'],
            );
            return $sync->row_number;
        }

        $result = $service->spreadsheets_values->append(
            $sync->spreadsheet_id,
            "{$tab}!A1",
            new ValueRange(['values' => [$row]]),
            ['valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS'],
        );

        return $this->parseRowNumber($result->getUpdates()->getUpdatedRange());
    }

    private function service(): Sheets
    {
        $client = new GoogleClient();
        $client->setApplicationName('Watad AI Interviewer');
        $client->setScopes([Sheets::SPREADSHEETS]);

        if ($credentials = config('watad.sheets.credentials')) {
            $client->setAuthConfig($credentials);
        } else {
            $client->useApplicationDefaultCredentials();
        }

        return new Sheets($client);
    }

    /** @return list<string|float|null> the 14 ordered columns (docs/11). */
    private function row(Interview $interview): array
    {
        $candidate = $interview->candidate;
        $scores    = $interview->competencyScores->keyBy('competency');

        $soft = collect(['communication', 'confidence', 'culture_fit', 'professionalism'])
            ->map(fn ($c) => $scores[$c]->score ?? null)
            ->filter()
            ->avg();

        return [
            $interview->public_id,
            optional($interview->completed_at)->toIso8601String(),
            $interview->jobPosition?->title,
            $candidate?->full_name,
            $candidate?->email,
            $candidate?->phone,
            $candidate?->country,
            $candidate?->years_experience,
            $interview->overall_score,
            $scores['technical']->score ?? null,
            $soft ? round($soft, 1) : null,
            $interview->recommendation?->label(),
            $interview->status->value,
            $interview->report?->pdf_path ? route('hr.interviews.report.pdf', $interview->public_id) : null,
        ];
    }

    private function ensureHeader(Sheets $service, string $spreadsheetId, string $tab): void
    {
        $existing = $service->spreadsheets_values->get($spreadsheetId, "{$tab}!A1:N1");
        if (empty($existing->getValues())) {
            $service->spreadsheets_values->update(
                $spreadsheetId,
                "{$tab}!A1",
                new ValueRange(['values' => [self::HEADERS]]),
                ['valueInputOption' => 'USER_ENTERED'],
            );
        }
    }

    private function parseRowNumber(string $updatedRange): int
    {
        // e.g. "Candidates!A42:N42" → 42
        if (preg_match('/![A-Z]+(\d+)/', $updatedRange, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
