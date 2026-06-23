<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Interview;
use App\Models\SheetSync;
use App\Services\Sheets\GoogleSheetsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Appends (or updates) the candidate's row in the configured Google Sheet. Idempotent via the
 * sheet_syncs record. See docs/11-google-sheets-integration.md.
 */
class PushToSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public int $interviewId) {}

    public function handle(GoogleSheetsService $sheets): void
    {
        if (! config('watad.sheets.enabled')) {
            return;
        }

        $interview = Interview::with(['candidate', 'jobPosition', 'competencyScores', 'report'])
            ->findOrFail($this->interviewId);

        $sync = SheetSync::firstOrCreate(
            ['interview_id' => $interview->id],
            [
                'spreadsheet_id' => (string) config('watad.sheets.spreadsheet_id'),
                'sheet_tab'      => (string) config('watad.sheets.tab'),
                'status'         => 'pending',
            ],
        );

        try {
            $row = $sheets->upsertCandidateRow($interview, $sync);
            $sync->update(['status' => 'synced', 'row_number' => $row, 'synced_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $sync->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
            report($e);
            throw $e;
        }
    }
}
