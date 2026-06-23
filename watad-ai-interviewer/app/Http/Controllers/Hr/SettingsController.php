<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Read-only integration & configuration status (secrets are set via env, never edited from the UI).
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        $status = [
            'AI provider'        => config('watad.ai.provider'),
            'Conversation model' => config('watad.ai.models.'.config('watad.ai.provider').'.conversation'),
            'Analysis model'     => config('watad.ai.models.'.config('watad.ai.provider').'.analysis'),
            'Anthropic API key'  => filled(config('watad.ai.anthropic_api_key')) ? 'configured' : 'missing',
            'OpenAI API key'     => filled(config('watad.ai.openai_api_key')) ? 'configured' : 'not set',
            'Google Sheets'      => config('watad.sheets.enabled') ? 'enabled' : 'disabled',
            'Video provider'     => config('watad.video.provider'),
            'GDPR retention'     => config('watad.gdpr.retention_days').' days',
        ];

        return view('hr.settings', compact('status'));
    }
}
