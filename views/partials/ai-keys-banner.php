<?php
/**
 * ai-keys-banner.php — Renders a prominent warning when the current tenant
 * has not configured their AI API keys.
 *
 * Safe to include even if ApiKeyManager isn't loaded yet.
 */
if (!class_exists('ApiKeyManager')) return; // bootstrap not yet run
/**
 *
 * Usage:
 *   <?php $aiMissingOpenAI = !ApiKeyManager::hasTenantOpenAIKey(); ?>
 *   <?php require VIEWS_PATH . '/partials/ai-keys-banner.php'; ?>
 *
 * Variables (set before including):
 *   $aiMissingOpenAI  bool — true if OpenAI key not configured
 *   $aiMissingHeyGen  bool — true if HeyGen key not configured (optional)
 *   $aiFeatureLabel   string — e.g. "AI Screening", "Video Interviews" (optional)
 */
require_once __DIR__ . '/helpers.php';

$aiMissingOpenAI = $aiMissingOpenAI ?? false;
$aiMissingHeyGen = $aiMissingHeyGen ?? false;
$aiFeatureLabel  = $aiFeatureLabel  ?? 'AI features';

if (!$aiMissingOpenAI && !$aiMissingHeyGen) return; // nothing to show
?>

<div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 flex gap-3 items-start" role="alert">
    <div class="shrink-0 mt-0.5">
        <svg class="w-5 h-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-amber-900">
            <?php if ($aiMissingOpenAI && $aiMissingHeyGen): ?>
                AI keys not configured — <?= e($aiFeatureLabel) ?> is unavailable
            <?php elseif ($aiMissingOpenAI): ?>
                OpenAI key not configured — <?= e($aiFeatureLabel) ?> is unavailable
            <?php else: ?>
                HeyGen key not configured — Video interviews are unavailable
            <?php endif; ?>
        </p>
        <p class="text-xs text-amber-700 mt-0.5">
            Your company must add its own API keys. Go to
            <a href="/settings?tab=integrations" class="font-semibold underline hover:text-amber-900 transition-colors">
                Settings → Integrations
            </a>
            to add your
            <?= $aiMissingOpenAI ? 'OpenAI' : '' ?>
            <?= ($aiMissingOpenAI && $aiMissingHeyGen) ? ' and ' : '' ?>
            <?= $aiMissingHeyGen ? 'HeyGen' : '' ?>
            API key<?= ($aiMissingOpenAI && $aiMissingHeyGen) ? 's' : '' ?>.
            Each company is billed directly to its own account.
        </p>
    </div>
    <a href="/settings?tab=integrations"
       class="shrink-0 bg-amber-500 hover:bg-amber-600 text-white rounded-full px-4 py-1.5 text-xs font-semibold transition-colors whitespace-nowrap">
        Configure Keys
    </a>
</div>
