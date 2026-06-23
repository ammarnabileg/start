<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Watad AI Interviewer — central configuration
|--------------------------------------------------------------------------
| Models, scoring weights, thresholds, avatars, integrations and interview
| budgets all live here so business logic never hard-codes them.
| See docs/06-ai-prompt-engineering.md and docs/08-scoring-and-analysis.md.
*/

return [

    'ai' => [
        // Which provider implements LlmProvider for each role.
        'provider' => env('WATAD_AI_PROVIDER', 'claude'), // claude | openai

        // Model per workload role. Business logic references roles, not strings.
        'models' => [
            'claude' => [
                'conversation' => env('WATAD_AI_CONVERSATION_MODEL', 'claude-sonnet-4-6'),
                'analysis'     => env('WATAD_AI_ANALYSIS_MODEL', 'claude-opus-4-8'),
                'cv'           => env('WATAD_AI_CV_MODEL', 'claude-opus-4-8'),
            ],
            'openai' => [
                'conversation' => env('WATAD_OPENAI_CONVERSATION_MODEL', 'gpt-4o'),
                'analysis'     => env('WATAD_OPENAI_ANALYSIS_MODEL', 'gpt-4o'),
                'cv'           => env('WATAD_OPENAI_ANALYSIS_MODEL', 'gpt-4o'),
            ],
        ],

        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'openai_api_key'    => env('OPENAI_API_KEY'),

        // Max output tokens per call by role.
        'max_tokens' => [
            'conversation' => 1024,
            'analysis'     => 8000,
            'cv'           => 4000,
        ],
    ],

    'interview' => [
        'max_tokens_per_interview' => (int) env('WATAD_INTERVIEW_MAX_TOKENS_PER_INTERVIEW', 400000),
        'abandon_grace_sec'        => (int) env('WATAD_INTERVIEW_ABANDON_GRACE_SEC', 180),
        'coverage_target'          => (float) env('WATAD_INTERVIEW_COVERAGE_TARGET', 0.7),
        'default_min_questions'    => 6,
        'default_max_questions'    => 14,
        'default_max_duration_min' => 25,
        'default_follow_up_depth'  => 2,
    ],

    // The 11 competencies and their default weights (overridable per template).
    'competencies' => [
        'technical'         => 18,
        'communication'     => 12,
        'problem_solving'   => 12,
        'critical_thinking' => 10,
        'confidence'        => 8,
        'leadership'        => 8,
        'culture_fit'       => 8,
        'professionalism'   => 8,
        'ai_knowledge'      => 6,
        'english_fluency'   => 6,
        'learning_ability'  => 4,
    ],

    'scoring' => [
        // overall score → recommendation bands
        'bands' => [
            'strong_hire' => 82,
            'hire'        => 68,
            'maybe'       => 50,
            // below 'maybe' → reject
        ],
        // override rules enforced in code (not left to the model)
        'overrides' => [
            'high_flag_downgrades_strong_hire' => true,
            'two_medium_flags_downgrade_hire'  => true,
            'fatal_flag_types'                 => ['fake_experience', 'inconsistent_answer'],
        ],
        // salary mismatch tolerance around the posted band
        'salary_tolerance' => 0.25,
        // weight given to video confidence signal when blending (video mode only)
        'video_confidence_weight' => 0.20,
    ],

    'sheets' => [
        'enabled'        => (bool) env('WATAD_SHEETS_ENABLED', false),
        'spreadsheet_id' => env('WATAD_SHEETS_SPREADSHEET_ID'),
        'tab'            => env('WATAD_SHEETS_TAB', 'Candidates'),
        'credentials'    => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],

    'video' => [
        'provider' => env('WATAD_VIDEO_PROVIDER', 'none'), // none | tavus | heygen
        'tavus'    => ['api_key' => env('TAVUS_API_KEY')],
        'heygen'   => ['api_key' => env('HEYGEN_API_KEY')],
        'livekit'  => [
            'url'    => env('LIVEKIT_URL'),
            'key'    => env('LIVEKIT_API_KEY'),
            'secret' => env('LIVEKIT_API_SECRET'),
        ],
    ],

    'uploads' => [
        'cv_max_kb'    => 8192,
        'cv_mimes'     => ['pdf', 'doc', 'docx'],
        'av_scan'      => env('WATAD_UPLOAD_AV_SCAN', false),
    ],

    'gdpr' => [
        'retention_days' => (int) env('WATAD_GDPR_RETENTION_DAYS', 365),
    ],

    'notifications' => [
        'high_potential_threshold' => 82, // notify HR when overall ≥ this
        'whatsapp' => [
            'token'    => env('WHATSAPP_TOKEN'),
            'phone_id' => env('WHATSAPP_PHONE_ID'),
        ],
    ],
];
