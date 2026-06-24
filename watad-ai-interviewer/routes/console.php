<?php

use App\Jobs\PushToSheet;
use App\Models\Interview;
use App\Models\InterviewInvitation;
use App\Models\SheetSync;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks (see docs/18-deployment.md)
|--------------------------------------------------------------------------
*/

// Expire stale invitations.
Schedule::call(function () {
    InterviewInvitation::whereNotIn('status', ['completed', 'cancelled', 'expired'])
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['status' => 'expired']);
})->hourly()->name('invitations:expire');

// Retry failed Google Sheets syncs with backoff.
Schedule::call(function () {
    SheetSync::where('status', 'failed')->limit(100)->get()
        ->each(fn (SheetSync $sync) => PushToSheet::dispatch($sync->interview_id));
})->everyFifteenMinutes()->name('sheets:retry');

// Reap abandoned in-progress interviews past the grace window. Scenarios D/E/F (closed tab, lost
// connection, inactivity): the AI still closes the interview gracefully (thank-you + next steps)
// and analysis/scoring runs, so a candidate who drops off still produces a complete record. An
// interview with no candidate answers at all is just marked abandoned (nothing to score).
Schedule::call(function () {
    $cutoff = now()->subSeconds((int) config('watad.interview.abandon_grace_sec'));
    Interview::where('status', 'in_progress')
        ->where('updated_at', '<', $cutoff)
        ->get()
        ->each(function (Interview $interview) {
            $hasProgress = $interview->messages()->where('role', 'candidate')->exists();
            if ($hasProgress) {
                app(\App\Services\AI\InterviewEngine::class)->complete($interview);
            } else {
                $interview->update(['status' => 'abandoned']);
            }
        });
})->everyFiveMinutes()->name('interviews:reap-abandoned')->withoutOverlapping();

// Drain the database queue (scoring, behavioral, red flags, report, notifications, sheet sync).
// Shared hosting has no long-running worker, so the scheduler processes pending jobs each minute.
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()->withoutOverlapping()->name('queue:drain');

// GDPR retention purge runs daily (PurgeExpiredCandidateData job in production).
Schedule::command('watad:gdpr-purge')->dailyAt('03:00')->name('gdpr:purge');
