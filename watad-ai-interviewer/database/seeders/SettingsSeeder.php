<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('branding', 'name', 'Watad AI Interviewer');
        Setting::put('localization', 'default_language', 'en');
        Setting::put('localization', 'rtl', true);
        Setting::put('ai', 'conversation_model', config('watad.ai.models.claude.conversation'));
        Setting::put('ai', 'analysis_model', config('watad.ai.models.claude.analysis'));
        Setting::put('ai', 'auto_advance_strong_hire', false);
        Setting::put('workflow', 'require_human_before_reject', true);
    }
}
