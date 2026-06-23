<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Candidate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hard-deletes candidate data (and S3 objects) past the configured retention window.
 * See docs/13-security-architecture.md → GDPR.
 */
class GdprPurge extends Command
{
    protected $signature = 'watad:gdpr-purge {--dry-run}';

    protected $description = 'Purge candidate PII and S3 objects older than the retention window';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('watad.gdpr.retention_days'));

        $candidates = Candidate::withTrashed()
            ->where('created_at', '<', $cutoff)
            ->with('interviews.recordings', 'interviews.report')
            ->get();

        $this->info("Found {$candidates->count()} candidate(s) past retention ({$cutoff->toDateString()}).");

        foreach ($candidates as $candidate) {
            if ($this->option('dry-run')) {
                $this->line("  would purge candidate #{$candidate->id} ({$candidate->email})");
                continue;
            }

            // Remove S3 objects (CV, recordings, report PDFs).
            if ($candidate->cv_path) {
                Storage::delete($candidate->cv_path);
            }
            foreach ($candidate->interviews as $interview) {
                foreach ($interview->recordings as $rec) {
                    $rec->url && Storage::delete($rec->url);
                }
                $interview->report?->pdf_path && Storage::delete($interview->report->pdf_path);
            }

            $candidate->forceDelete(); // cascades to interviews/messages/analyses via FKs
        }

        return self::SUCCESS;
    }
}
