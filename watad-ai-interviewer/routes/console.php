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

// Reap abandoned in-progress interviews past the grace window.
Schedule::call(function () {
    $cutoff = now()->subSeconds((int) config('watad.interview.abandon_grace_sec'))->subMinutes(30);
    Interview::where('status', 'in_progress')
        ->where('updated_at', '<', $cutoff)
        ->update(['status' => 'abandoned']);
})->everyTenMinutes()->name('interviews:reap-abandoned');

// GDPR retention purge runs daily (PurgeExpiredCandidateData job in production).
Schedule::command('watad:gdpr-purge')->dailyAt('03:00')->name('gdpr:purge');
