<?php

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (stateless, token-authenticated where applicable)
|--------------------------------------------------------------------------
| The live candidate interview endpoints are session-bound and live in routes/web.php.
*/

// HR token API (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/export/interviews.xlsx', [ExportController::class, 'interviews'])
        ->middleware('can:report.export')
        ->name('api.export.interviews');
});

// Inbound provider webhooks (HMAC-verified in production via VerifyWebhookSignature middleware).
Route::post('/webhooks/avatar/{provider}', [WebhookController::class, 'avatar'])->name('api.webhooks.avatar');
Route::post('/webhooks/video-analysis', [WebhookController::class, 'videoAnalysis'])->name('api.webhooks.video');
