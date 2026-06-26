<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <h1 style="font-size:1.375rem;font-weight:800;color:#f1f5f9;">AI Avatars</h1>
        <p style="color:#64748b;font-size:.875rem;margin-top:2px;">Configure AI interviewer personas for your interviews.</p>
    </div>
    <button onclick="openAvatarModal(null)" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:.875rem;font-weight:600;cursor:pointer;">+ New Avatar</button>
</div>

<?= flash() ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
    <?php foreach ($avatars ?? [] as $av): ?>
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.15);border-radius:12px;padding:20px;text-align:center;">
        <div style="font-size:3.5rem;margin-bottom:12px;"><?= htmlspecialchars($av['emoji'] ?? '🤖') ?></div>
        <div style="font-size:1rem;font-weight:700;color:#e2e8f0;margin-bottom:4px;"><?= htmlspecialchars($av['name']) ?></div>
        <div style="font-size:.8rem;color:#64748b;margin-bottom:4px;text-transform:capitalize;"><?= htmlspecialchars(str_replace('_',' ',$av['personality'] ?? 'professional')) ?></div>
        <div style="font-size:.75rem;color:#475569;margin-bottom:12px;"><?= htmlspecialchars($av['language'] ?? 'English') ?></div>
        <?php if ($av['is_default']): ?>
        <span style="background:rgba(16,185,129,.1);color:#4ade80;padding:3px 10px;border-radius:4px;font-size:.72rem;font-weight:600;display:inline-block;margin-bottom:12px;">Default</span>
        <?php endif; ?>
        <div style="display:flex;gap:6px;justify-content:center;">
            <button onclick="openAvatarModal(<?= htmlspecialchars(json_encode($av)) ?>)" style="padding:6px 14px;background:rgba(79,70,229,.1);border:1px solid rgba(79,70,229,.2);border-radius:6px;color:#e2e8f0;font-size:.78rem;cursor:pointer;">Edit</button>
            <?php if (!$av['is_default']): ?>
            <button onclick="deleteAvatar(<?= $av['id'] ?>)" style="padding:6px 14px;background:transparent;border:1px solid rgba(239,68,68,.3);border-radius:6px;color:#f87171;font-size:.78rem;cursor:pointer;">Delete</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($avatars)): ?>
    <div style="grid-column:1/-1;text-align:center;padding:3rem;color:#475569;">
        <div style="font-size:2.5rem;margin-bottom:.75rem;">🤖</div>
        <div style="font-size:.9rem;">No avatars yet. Create your first AI interviewer.</div>
    </div>
    <?php endif; ?>
</div>

<!-- Avatar Modal -->
<div id="avatarModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center;">
    <div style="background:#1a1a2e;border:1px solid rgba(79,70,229,.2);border-radius:16px;padding:28px;width:100%;max-width:480px;margin:1rem;max-height:90vh;overflow-y:auto;">
        <h3 id="avatarModalTitle" style="font-size:1rem;font-weight:700;color:#f1f5f9;margin-bottom:1.25rem;">New Avatar</h3>
        <form id="avatarForm" method="POST" action="/avatars">
            <input type="hidden" name="_id" id="avatarId">
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input class="form-input" type="text" name="name" id="avatarName" required placeholder="e.g. Alex">
            </div>
            <div class="form-group">
                <label class="form-label">Emoji</label>
                <input class="form-input" type="text" name="emoji" id="avatarEmoji" placeholder="🤖" style="font-size:1.5rem;text-align:center;width:80px;">
            </div>
            <div class="form-group">
                <label class="form-label">Personality Style</label>
                <select class="form-input" name="personality" id="avatarPersonality">
                    <option value="professional">Professional</option>
                    <option value="friendly">Friendly</option>
                    <option value="strict">Strict</option>
                    <option value="conversational">Conversational</option>
                    <option value="technical">Technical</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Language</label>
                <select class="form-input" name="language" id="avatarLanguage">
                    <option value="English">English</option>
                    <option value="Arabic">Arabic</option>
                    <option value="French">French</option>
                    <option value="Spanish">Spanish</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Custom System Prompt (optional)</label>
                <textarea class="form-input" name="custom_prompt" id="avatarPrompt" rows="4" style="resize:none;" placeholder="Additional instructions for the AI interviewer…"></textarea>
            </div>
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:1rem;">
                <input type="checkbox" name="is_default" id="avatarDefault" value="1" style="width:16px;height:16px;accent-color:#4f46e5;">
                <span style="font-size:.875rem;color:#e2e8f0;">Set as default avatar</span>
            </label>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-primary" style="flex:1;">Save Avatar</button>
                <button type="button" onclick="closeAvatarModal()" style="flex:1;padding:11px;background:transparent;border:1px solid rgba(79,70,229,.2);border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAvatarModal(av) {
    document.getElementById('avatarModal').style.display = 'flex';
    if (av) {
        document.getElementById('avatarModalTitle').textContent = 'Edit Avatar';
        document.getElementById('avatarId').value = av.id;
        document.getElementById('avatarName').value = av.name || '';
        document.getElementById('avatarEmoji').value = av.emoji || '🤖';
        document.getElementById('avatarPersonality').value = av.personality || 'professional';
        document.getElementById('avatarLanguage').value = av.language || 'English';
        document.getElementById('avatarPrompt').value = av.custom_prompt || '';
        document.getElementById('avatarDefault').checked = !!av.is_default;
        document.getElementById('avatarForm').action = '/avatars/' + av.id;
    } else {
        document.getElementById('avatarModalTitle').textContent = 'New Avatar';
        document.getElementById('avatarId').value = '';
        document.getElementById('avatarForm').reset();
        document.getElementById('avatarEmoji').value = '🤖';
        document.getElementById('avatarForm').action = '/avatars';
    }
}
function closeAvatarModal() { document.getElementById('avatarModal').style.display = 'none'; }
function deleteAvatar(id) {
    if (!confirm('Delete this avatar?')) return;
    api('/avatars/' + id, 'DELETE', {}).then(function(res) {
        if (res.ok) location.reload();
        else showToast('Error', 'error');
    });
}
</script>
