<div style="margin-bottom:1.5rem;">
    <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">AI Settings</h1>
    <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Configure your AI API keys and interview settings. Keys are stored securely and never shared.</p>
</div>

<?= flash() ?>

<div style="max-width:720px;display:flex;flex-direction:column;gap:20px;">
    <!-- OpenAI -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;">OpenAI API Key</h3>
                <p style="font-size:.82rem;color:#64748b;margin-top:2px;">Required for AI interview conversations and candidate evaluation.</p>
            </div>
            <?php if ($aiSettings['openai_key'] ?? null): ?>
            <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:5px 14px;border-radius:6px;font-size:.8rem;font-weight:600;">🟢 Connected</span>
            <?php else: ?>
            <span style="background:rgba(239,68,68,.1);color:#f87171;padding:5px 14px;border-radius:6px;font-size:.8rem;font-weight:600;">🔴 Not set</span>
            <?php endif; ?>
        </div>
        <form method="POST" action="/settings/ai">
            <input type="hidden" name="section" value="openai">
            <div class="form-group">
                <label class="form-label">API Key</label>
                <input class="form-input" type="password" name="openai_key" placeholder="sk-..." autocomplete="off"
                    value="<?= $aiSettings['openai_key'] ? str_repeat('•', 20) . substr($aiSettings['openai_key'], -4) : '' ?>">
                <div style="font-size:.75rem;color:#475569;margin-top:4px;">Leave blank to keep the current key.</div>
            </div>
            <div class="form-group">
                <label class="form-label">Model</label>
                <select class="form-input" name="openai_model">
                    <?php foreach (['gpt-4o','gpt-4o-mini','gpt-4-turbo','gpt-3.5-turbo'] as $m): ?>
                    <option value="<?= $m ?>" <?= ($aiSettings['openai_model'] ?? 'gpt-4o') === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($aiSettings['openai_key'] ?? null): ?>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Update Key</button>
                <button type="button" onclick="removeKey('openai')" style="padding:10px 20px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;cursor:pointer;font-size:.875rem;">Remove Key</button>
            </div>
            <?php else: ?>
            <button type="submit" class="btn-primary" style="padding:10px 24px;">Save OpenAI Key</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- HeyGen -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div>
                <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;">HeyGen API Key</h3>
                <p style="font-size:.82rem;color:#64748b;margin-top:2px;">Optional. Required for video avatar AI interviews.</p>
            </div>
            <?php if ($aiSettings['heygen_key'] ?? null): ?>
            <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:5px 14px;border-radius:6px;font-size:.8rem;font-weight:600;">🟢 Connected</span>
            <?php else: ?>
            <span style="background:rgba(100,116,139,.1);color:#94a3b8;padding:5px 14px;border-radius:6px;font-size:.8rem;font-weight:600;">Not configured</span>
            <?php endif; ?>
        </div>
        <form method="POST" action="/settings/ai">
            <input type="hidden" name="section" value="heygen">
            <div class="form-group">
                <label class="form-label">API Key</label>
                <input class="form-input" type="password" name="heygen_key" placeholder="Your HeyGen API key" autocomplete="off"
                    value="<?= $aiSettings['heygen_key'] ? str_repeat('•', 20) . substr($aiSettings['heygen_key'], -4) : '' ?>">
            </div>
            <?php if ($aiSettings['heygen_key'] ?? null): ?>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary" style="padding:10px 24px;">Update Key</button>
                <button type="button" onclick="removeKey('heygen')" style="padding:10px 20px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;cursor:pointer;font-size:.875rem;">Remove Key</button>
            </div>
            <?php else: ?>
            <button type="submit" class="btn-primary" style="padding:10px 24px;">Save HeyGen Key</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Interview Defaults -->
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:24px;">
        <h3 style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">Interview Defaults</h3>
        <form method="POST" action="/settings/ai">
            <input type="hidden" name="section" value="defaults">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Default Questions per Interview</label>
                    <input class="form-input" type="number" name="default_question_count" min="3" max="20" value="<?= (int)($aiSettings['default_question_count'] ?? 10) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Interview Language</label>
                    <select class="form-input" name="default_language">
                        <option value="English" <?= ($aiSettings['default_language'] ?? 'English') === 'English' ? 'selected' : '' ?>>English</option>
                        <option value="Arabic" <?= ($aiSettings['default_language'] ?? '') === 'Arabic' ? 'selected' : '' ?>>Arabic</option>
                        <option value="French" <?= ($aiSettings['default_language'] ?? '') === 'French' ? 'selected' : '' ?>>French</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="padding:10px 24px;">Save Defaults</button>
        </form>
    </div>
</div>

<script>
function removeKey(provider) {
    if (!confirm('Remove this API key? AI features using this key will stop working.')) return;
    api('/settings/ai/remove-key', 'POST', {provider: provider}).then(function(res) {
        if (res.ok) { showToast('Key removed', 'success'); location.reload(); }
        else showToast('Error removing key', 'error');
    });
}
</script>
